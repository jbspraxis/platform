<?php

namespace App\Console\Commands;

use App\Mail\ChatUnreadMail;
use App\Support\Chat\ProjectChat;
use App\Support\MailLocale;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Email about messages in a project's conversation (docs/CHAT.md) that nobody on the other side has read within an
 * hour (owner, 13 Nov 2026). "Read" is per side, as the Seen ticks: once anyone at the client (or at JBS Praxis)
 * has read a message, nobody on that side is emailed about it. Each person gets at most one email an hour per
 * conversation, and never one about a message older than a day, an internal note, an issue's status line or a
 * message copied from the old tickets. Every five minutes (routes/console.php).
 */
class ChatEmailUnread extends Command
{
    protected $signature = 'chat:email-unread {--dry-run : list who would be emailed, send nothing}';

    protected $description = 'Email people about conversation messages not read within an hour (docs/CHAT.md)';

    public function handle(): int
    {
        if (! Schema::hasTable('chat_messages')) {
            return self::SUCCESS;
        }
        $until = now()->subMinutes(ProjectChat::EMAIL_AFTER_MINUTES);
        $since = now()->subDay();
        $projects = DB::table('chat_messages')->where('imported', 0)->where('is_internal', 0)->where('kind', '!=', 'event')->whereNull('deleted_at')
            ->whereBetween('created_at', [$since, $until])->distinct()->pluck('project_id');

        $sent = 0;
        foreach ($projects as $projectId) {
            $sent += $this->remind($projectId, 'staff', 'client', $since, $until);
            $sent += $this->remind($projectId, 'client', 'staff', $since, $until);
        }
        $this->info($sent . ' ' . Str::plural('email', $sent) . ($this->option('dry-run') ? ' would be sent.' : ' sent.'));

        return self::SUCCESS;
    }

    /** Tell the $reader side about $author's messages it hasn't read for an hour; how many emails went */
    private function remind(string $projectId, string $author, string $reader, Carbon $since, Carbon $until): int
    {
        $seen = (int) DB::table('chat_readers')->where('project_id', $projectId)->where('party', $reader)->max('last_read_id');
        $waiting = ProjectChat::visible($projectId, $reader)->where('m.author_type', $author)->where('m.imported', 0)->where('m.is_internal', 0)
            ->where('m.kind', '!=', 'event')->where('m.id', '>', $seen)->whereBetween('m.created_at', [$since, $until])
            ->orderBy('m.id')->get(['m.id', 'm.author_id', 'm.kind', 'm.body', 'm.qa_ticket_id', 'm.created_at']);
        if ($waiting->isEmpty()) {
            return 0;
        }
        $project = DB::table('projects')->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('projects.id', $projectId)->first(['projects.title', 'organizations.name as client', 'organizations.preferred_language']);
        if (! $project) {
            return 0;
        }

        $sent = 0;
        $people = $reader === 'client' ? ProjectChat::clientAudience($projectId) : ProjectChat::staffAudience($projectId);
        foreach ($people as $personId) {
            $row = DB::table('chat_readers')->where(['project_id' => $projectId, 'party' => $reader, 'person_id' => $personId])->first();
            if ($row && $row->emailed_at && Carbon::parse($row->emailed_at)->gt(now()->subMinutes(ProjectChat::EMAIL_AFTER_MINUTES))) {
                continue; // one email an hour per conversation
            }
            $after = max((int) ($row->emailed_up_to ?? 0), (int) ($row->last_read_id ?? 0));
            $news = $waiting->filter(fn ($m) => $m->id > $after && ! ($m->author_id === $personId))->values();
            if ($news->isEmpty()) {
                continue;
            }
            $person = DB::table($reader === 'client' ? 'client_users' : 'users')->where('id', $personId)->first(['email', 'first_name', 'last_name']);
            if (! $person || ! filter_var($person->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $locale = $reader === 'client' ? MailLocale::forRecipient($project->preferred_language ?? null) : 'en';
            $url = $reader === 'client'
                ? rtrim(route('client.dashboard'), '/') . '/' . ltrim(ProjectChat::clientLink($projectId), '/')
                : ProjectChat::staffLink($projectId);

            if ($this->option('dry-run')) {
                $this->line("{$person->email}: {$news->count()} unread on {$project->title}");
                $sent++;
                continue;
            }
            try {
                Mail::to($person->email)->locale($locale)->send(new ChatUnreadMail(
                    $person->first_name, $reader === 'client', $project->client, $project->title,
                    $this->lines($news->slice(-3)->values(), $author, $reader, $locale), $news->count(), $url,
                ));
            } catch (\Throwable $e) {
                report($e);
                continue;
            }
            $key = ['project_id' => $projectId, 'party' => $reader, 'person_id' => $personId];
            DB::table('chat_readers')->insertOrIgnore($key + ['last_read_id' => 0]);
            DB::table('chat_readers')->where($key)->update(['emailed_at' => now(), 'emailed_up_to' => (int) $news->max('id')]);
            $sent++;
        }

        return $sent;
    }

    /** The messages as the email shows them: who, when (WAT), what */
    private function lines($messages, string $author, string $reader, string $locale): array
    {
        $names = DB::table($author === 'client' ? 'client_users' : 'users')->whereIn('id', $messages->pluck('author_id')->filter())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $today = now()->setTimezone('Africa/Lagos')->startOfDay();

        return $messages->map(function ($m) use ($names, $author, $reader, $locale, $today) {
            $who = $names[$m->author_id] ?? null;
            $at = Carbon::parse($m->created_at)->setTimezone('Africa/Lagos')->locale($locale);
            $text = trim((string) $m->body) !== '' ? Str::limit(trim((string) $m->body), 600) : ProjectChat::summary($m, $locale);
            if ($m->kind === 'issue') {
                $text = ProjectChat::summary($m, $locale) . (trim((string) $m->body) !== '' ? "\n" . Str::limit(trim((string) $m->body), 400) : '');
            }

            return [
                'name' => ProjectChat::name($who->first_name ?? '', $who->last_name ?? '', $author, $reader),
                'time' => ($at->copy()->startOfDay()->equalTo($today) ? $at->format('H:i') : $at->isoFormat('D MMM, HH:mm')) . ' WAT',
                'text' => $text,
            ];
        })->all();
    }
}
