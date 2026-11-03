<?php

namespace App\Support\Campaigns;

use App\Models\Campaigns\Broadcast;
use App\Models\Campaigns\Segment;
use App\Models\User;
use App\Support\Access;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * What happens to a broadcast (docs/CAMPAIGNS.md 4.6). A draft is edited freely; Send now or Schedule freezes it.
 * When sending starts (at once, or when broadcast:process reaches the scheduled time) the segment is worked out
 * afresh, so people who unsubscribed in between are left out and new leads are in: one broadcast_logs row per
 * person, with their address, language and link token, and one broadcast_links row per counted address. The
 * sender then goes through the rows at the server's pace (PER_RUN every 5 minutes).
 */
final class Broadcasts
{
    /** Emails per run of broadcast:process (every 5 minutes): the cPanel SMTP limit the server has always kept */
    public const PER_RUN = 30;

    public const RUN_MINUTES = 5;

    public static function create(User $by, string $layout = Layouts::DEFAULT): Broadcast
    {
        $layout = Layouts::has($layout) ? $layout : Layouts::DEFAULT;

        return Broadcast::create([
            'layout' => $layout,
            'language' => 'both',
            'greeting' => $layout !== 'insight',
            'content' => ['en' => Layouts::starter($layout, 'en'), 'fr' => Layouts::starter($layout, 'fr')],
            'status' => 'Draft',
            'created_by' => $by->id,
            'updated_by' => $by->id,
        ]);
    }

    /** A version in its stored form */
    public static function cleanVersion(array $version): array
    {
        $line = fn ($v, $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', is_string($v) ? $v : '')), 0, $max);

        return [
            'subject' => $line($version['subject'] ?? '', 150),
            'preheader' => $line($version['preheader'] ?? '', 160),
            'headline' => $line($version['headline'] ?? '', 150),
            'blocks' => Blocks::clean((array) ($version['blocks'] ?? [])),
        ];
    }

    /** Everything that stops it from going out, per version and overall */
    public static function problems(Broadcast $broadcast): array
    {
        $problems = [];
        foreach ($broadcast->locales() as $locale) {
            foreach (Blocks::problems(self::cleanVersion($broadcast->version($locale))) as $p) {
                $problems[] = (count($broadcast->locales()) > 1 ? ($locale === 'fr' ? 'French: ' : 'English: ') : '') . $p;
            }
        }
        if (! $broadcast->segment_id || ! Segment::whereKey($broadcast->segment_id)->exists()) {
            $problems[] = 'Choose who gets it: pick a segment.';
        }

        return $problems;
    }

    /** Send now: freezes it and queues everyone; returns how many people it goes to */
    public static function sendNow(Broadcast $broadcast, User $by): int
    {
        $broadcast->forceFill(['sent_by' => $by->id, 'scheduled_at' => null])->save();

        return self::start($broadcast);
    }

    public static function schedule(Broadcast $broadcast, CarbonInterface $at, User $by): void
    {
        $broadcast->forceFill(['status' => 'Scheduled', 'scheduled_at' => $at->copy()->utc(), 'sent_by' => $by->id, 'updated_by' => $by->id])->save();
        self::bellApprovers($broadcast, 'Broadcast scheduled', "'{$broadcast->title()}' is scheduled for " . self::wat($at) . '. Follow it in Campaigns › Broadcasts.', $by);
    }

    /** A scheduled broadcast back to a draft, before it starts */
    public static function unschedule(Broadcast $broadcast, User $by): bool
    {
        return Broadcast::whereKey($broadcast->id)->where('status', 'Scheduled')
            ->update(['status' => 'Draft', 'scheduled_at' => null, 'updated_by' => $by->id, 'updated_at' => now()]) > 0;
    }

    /** Scheduled broadcasts whose time has come start sending (broadcast:process) */
    public static function startDue(): int
    {
        $started = 0;
        foreach (Broadcast::where('status', 'Scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get() as $broadcast) {
            self::start($broadcast);
            $started++;
        }

        return $started;
    }

    /**
     * Works out the audience and queues one row per person. A broadcast nobody can receive any more is completed at
     * once, with a bell to whoever sent it. Returns how many people it goes to.
     */
    public static function start(Broadcast $broadcast): int
    {
        // Claimed once: a second caller (two runs, a double click) finds it no longer Draft or Scheduled
        $claimed = Broadcast::whereKey($broadcast->id)->whereIn('status', ['Draft', 'Scheduled'])
            ->update(['status' => 'Sending', 'started_at' => now(), 'updated_at' => now()]);
        if (! $claimed) {
            return 0;
        }
        $broadcast->refresh();

        $rules = Audience::rules($broadcast->segment?->rules ?? []);
        $people = Audience::recipients($rules);

        DB::transaction(function () use ($broadcast, $people, $rules) {
            // The versions as they go out, and their counted links
            $content = [];
            foreach ($broadcast->locales() as $locale) {
                $content[$locale] = self::cleanVersion($broadcast->version($locale));
                foreach (Blocks::links($content[$locale]['blocks']) as $url) {
                    DB::table('broadcast_links')->insert(['campaign_id' => $broadcast->id, 'locale' => $locale, 'url' => $url, 'created_at' => now()]);
                }
            }

            $now = now();
            foreach ($people->chunk(500) as $chunk) {
                DB::table('broadcast_logs')->insertOrIgnore($chunk->map(fn ($p) => [
                    'campaign_id' => $broadcast->id,
                    'user_id' => $p['contact_id'],
                    'subscriber_id' => $p['subscriber_id'],
                    'kind' => $p['kind'],
                    'email' => $p['email'],
                    'first_name' => mb_substr($p['first_name'], 0, 60),
                    'last_name' => mb_substr($p['last_name'], 0, 60),
                    'company' => mb_substr($p['company'], 0, 150),
                    'locale' => $broadcast->localeFor($p['locale']),
                    'token' => Tracking::newToken(),
                    'status' => 'Queued',
                    'signed_links' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all());
            }

            $count = $people->count();
            $broadcast->forceFill([
                'content' => $content + (array) $broadcast->content,
                'subject' => $content[$broadcast->locales()[0]]['subject'],
                'audience' => $rules,
                'total_recipients' => $count,
                'estimated_completion_time' => self::eta(),
            ])->save();
        });

        $count = (int) $broadcast->total_recipients;
        if ($count === 0) {
            $broadcast->forceFill(['status' => 'Completed', 'completed_at' => now()])->save();
            self::bell([$broadcast->sent_by ?: $broadcast->created_by], 'Broadcast not sent', "Nobody in the segment could receive '{$broadcast->title()}' when it was due to start, so it was not sent. Check the segment in Campaigns › Audiences.", $broadcast);

            return 0;
        }

        $minutes = (int) ceil($count / self::PER_RUN) * self::RUN_MINUTES;
        self::bellApprovers($broadcast, 'Broadcast sending', "'{$broadcast->title()}' is going to {$count} " . ($count === 1 ? 'person' : 'people') . ". Sending should finish in about {$minutes} minutes. Follow it in Campaigns › Broadcasts.");

        return $count;
    }

    /** Broadcasts with nothing left to send are completed, with a bell to whoever sent them (broadcast:process) */
    public static function finishDone(): void
    {
        $sending = Broadcast::where('status', 'Sending')->get();
        foreach ($sending as $broadcast) {
            if (DB::table('broadcast_logs')->where('campaign_id', $broadcast->id)->whereIn('status', ['Queued', 'Sending'])->exists()) {
                continue;
            }
            if (! Broadcast::whereKey($broadcast->id)->where('status', 'Sending')->update(['status' => 'Completed', 'completed_at' => now(), 'updated_at' => now()])) {
                continue;
            }
            $s = self::stats($broadcast);
            $problems = $s['failed'] + $s['bounced'] + $s['skipped'];
            self::bell([$broadcast->sent_by ?: $broadcast->created_by], 'Broadcast sent',
                "'{$broadcast->title()}' went to {$s['sent']} " . ($s['sent'] === 1 ? 'person' : 'people') . ($problems ? "; {$problems} could not be sent" : '') . '. See opens and clicks in its report.', $broadcast);
        }
    }

    /** The numbers of one broadcast (opens are approximate: Tracking) */
    public static function stats(Broadcast $broadcast): array
    {
        $row = DB::table('broadcast_logs')->where('campaign_id', $broadcast->id)->selectRaw("
            COUNT(*) AS total,
            SUM(status = 'Sent') AS sent,
            SUM(status IN ('Queued', 'Sending')) AS queued,
            SUM(status = 'Failed') AS failed,
            SUM(status = 'Bounced') AS bounced,
            SUM(status = 'Skipped') AS skipped,
            SUM(status = 'Sent' AND (open_count > 0 OR click_count > 0)) AS opened,
            SUM(click_count > 0) AS clicked,
            SUM(click_count) AS clicks,
            SUM(unsubscribed_at IS NOT NULL) AS unsubscribed,
            SUM(locale = 'en') AS en,
            SUM(locale = 'fr') AS fr")->first();
        $n = fn ($v) => (int) ($v ?? 0);
        $sent = $n($row->sent);
        $rate = fn ($v) => $sent > 0 ? (int) round(100 * $n($v) / $sent) : 0;

        return [
            'total' => $n($row->total), 'sent' => $sent, 'queued' => $n($row->queued), 'failed' => $n($row->failed),
            'bounced' => $n($row->bounced), 'skipped' => $n($row->skipped), 'opened' => $n($row->opened), 'clicked' => $n($row->clicked),
            'clicks' => $n($row->clicks), 'unsubscribed' => $n($row->unsubscribed), 'en' => $n($row->en), 'fr' => $n($row->fr),
            'open_rate' => $rate($row->opened), 'click_rate' => $rate($row->clicked), 'unsubscribe_rate' => $rate($row->unsubscribed),
        ];
    }

    /** Sends the versions to the person testing, with plain links (nothing is counted) */
    public static function sendTest(Broadcast $broadcast, User $to): array
    {
        $sent = [];
        foreach ($broadcast->locales() as $locale) {
            $person = ['first_name' => $to->first_name, 'last_name' => $to->last_name, 'company' => 'Acme Ltd (demo)', 'kind' => 'lead'];
            Mail::to($to->email)->send(Renderer::mail($broadcast, $locale, $person, unsubscribeUrl: route('email.unsubscribe', ['token' => 'preview']), test: true));
            $sent[] = $locale;
        }

        return $sent;
    }

    /** When the last queued email (and $more after it) should go, at the server's pace */
    public static function eta(int $more = 0): CarbonInterface
    {
        $queuedBefore = DB::table('broadcast_logs')->whereIn('status', ['Queued', 'Sending'])->count();

        return now()->addMinutes((int) ceil(max(1, $queuedBefore + $more) / self::PER_RUN) * self::RUN_MINUTES);
    }

    public static function wat(?CarbonInterface $at): string
    {
        return $at ? $at->copy()->timezone('Africa/Lagos')->format('j M Y, H:i') . ' WAT' : '';
    }

    private static function bellApprovers(Broadcast $broadcast, string $title, string $message, ?User $except = null): void
    {
        $ids = collect(Access::staffWith('broadcasts.approve'))->push($broadcast->sent_by)->filter()->unique()
            ->reject(fn ($id) => $except && $id === $except->id)->values()->all();
        self::bell($ids, $title, $message, $broadcast);
    }

    private static function bell(array $userIds, string $title, string $message, Broadcast $broadcast): void
    {
        $ids = DB::table('users')->whereIn('id', array_filter($userIds))->where('status', 'Active')->whereNull('deleted_at')->pluck('id');
        $link = route($broadcast->status === 'Draft' || $broadcast->status === 'Scheduled' ? 'hq.campaigns.broadcast' : 'hq.campaigns.report', ['broadcast' => $broadcast->id], false);
        $rows = $ids->map(fn ($id) => [
            'id' => (string) Str::uuid(), 'user_id' => $id, 'title' => Str::limit($title, 150, '…'), 'message' => Str::limit($message, 500, '…'),
            'link_url' => $link, 'type' => 'System', 'is_read' => 0, 'created_at' => now(),
        ])->all();
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
