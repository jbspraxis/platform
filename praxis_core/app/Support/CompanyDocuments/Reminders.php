<?php

namespace App\Support\CompanyDocuments;

use App\Models\CompanyDeadline;
use App\Models\CompanyDocument;
use App\Support\Access;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The compliance bell (owner's decision 3, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md). mdm:compliance-bell runs on
 * working days at 07:30 Lagos time, as leads:morning-bell does: each document whose expiry is within its reminder
 * days (or has passed), and each open deadline within its reminder days (or overdue), rings the owner and everyone
 * with mdm.edit once. "Once" is per date: the item remembers the date it rang for (reminded_for), so a renewed
 * document or next year's deadline rings again, and a reminder that fell on a weekend rings on Monday.
 */
final class Reminders
{
    /**
     * @param  bool  $dryRun  list what would ring, ring nobody
     * @return list<string> one line per item
     */
    public static function ring(bool $dryRun = false, ?string $today = null): array
    {
        $today ??= Documents::today();
        $people = Access::staffWith('mdm.edit');
        $lines = [];

        $documents = CompanyDocument::whereNull('archived_at')->whereNotNull('expires_on')->whereNotNull('remind_days')
            ->where(fn ($q) => $q->whereNull('reminded_for')->orWhereColumn('reminded_for', '!=', 'expires_on'))
            ->orderBy('expires_on')->get();
        foreach ($documents as $doc) {
            $date = $doc->expires_on->format('Y-m-d');
            if (CarbonImmutable::parse($date)->subDays($doc->remind_days)->format('Y-m-d') > $today) {
                continue;
            }
            $days = Documents::daysUntil($date, $today);
            $company = Companies::name($doc->legal_entity_id);
            $title = $doc->title . ' ' . match (true) {
                $days < 0 => 'has expired',
                $days === 0 => 'expires today',
                default => 'expires in ' . $days . ($days === 1 ? ' day' : ' days'),
            };
            $message = "{$company}: {$doc->title} " . ($days < 0 ? 'expired on ' : 'expires on ') . Documents::longDay($date)
                . '. Upload the renewed document in Master data › Company documents.';
            $lines[] = ($dryRun ? '[dry run] ' : '') . $title . '. ' . $message;
            if ($dryRun) {
                continue;
            }
            // Claimed before it rings, so a second run (or a second server) rings nobody
            if (! CompanyDocument::whereKey($doc->id)->where(fn ($q) => $q->whereNull('reminded_for')->orWhere('reminded_for', '!=', $date))->update(['reminded_for' => $date])) {
                continue;
            }
            self::bell($people, $title, $message, route('hq.mdm.documents.show', ['document' => $doc->id], false));
        }

        $deadlines = CompanyDeadline::whereNull('archived_at')->whereNull('done_at')->whereNotNull('remind_days')
            ->where(fn ($q) => $q->whereNull('reminded_for')->orWhereColumn('reminded_for', '!=', 'due_on'))
            ->orderBy('due_on')->get();
        foreach ($deadlines as $deadline) {
            $date = $deadline->due_on->format('Y-m-d');
            if (CarbonImmutable::parse($date)->subDays($deadline->remind_days)->format('Y-m-d') > $today) {
                continue;
            }
            $days = Documents::daysUntil($date, $today);
            $company = Companies::name($deadline->legal_entity_id);
            $title = $deadline->title . ' ' . match (true) {
                $days < 0 => 'is overdue',
                $days === 0 => 'is due today',
                default => 'is due in ' . $days . ($days === 1 ? ' day' : ' days'),
            };
            $message = "{$company}: {$deadline->title} " . ($days < 0 ? 'was due on ' : 'is due on ') . Documents::longDay($date)
                . '. Mark it done in Master data › Company documents once it is filed.';
            $lines[] = ($dryRun ? '[dry run] ' : '') . $title . '. ' . $message;
            if ($dryRun) {
                continue;
            }
            if (! CompanyDeadline::whereKey($deadline->id)->where(fn ($q) => $q->whereNull('reminded_for')->orWhere('reminded_for', '!=', $date))->update(['reminded_for' => $date])) {
                continue;
            }
            self::bell($people, $title, $message, route('hq.mdm.documents', ['company' => Companies::keyFor($deadline->legal_entity_id), 'tab' => 'calendar'], false));
        }

        return $lines;
    }

    /** One bell per person (a phone push too, through pwa:push-pending) */
    private static function bell(array $userIds, string $title, string $message, string $link): void
    {
        $rows = array_map(fn ($id) => [
            'id' => (string) Str::uuid(), 'user_id' => $id, 'type' => 'System',
            'title' => Str::limit($title, 150, '…'), 'message' => Str::limit($message, 500, '…'),
            'link_url' => $link, 'is_read' => 0, 'created_at' => now(),
        ], array_values(array_unique($userIds)));
        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
