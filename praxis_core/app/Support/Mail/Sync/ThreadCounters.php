<?php

namespace App\Support\Mail\Sync;

use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a thread's list fields in step with its messages: counts, dates, snippet, people, attachments.
 * Messages in Spam and Trash don't count towards unread or the date unless the thread is only there.
 * A copy of the same message in two folders counts once. A thread left without messages is deleted unless
 * the team worked on it (notes, assignment, done), so that work survives a message being moved elsewhere.
 * A new message clears the AI summary (PR 4 writes a new one on request).
 */
final class ThreadCounters
{
    public static function refresh(array $threadIds): void
    {
        $threadIds = array_values(array_unique(array_filter(array_map('intval', $threadIds))));
        if (! $threadIds) {
            return;
        }

        foreach (array_chunk($threadIds, 200) as $chunk) {
            $threads = MailThread::whereIn('id', $chunk)->get()->keyBy('id');
            $messages = MailMessage::whereIn('thread_id', $chunk)
                ->join('mail_folders', 'mail_folders.id', '=', 'mail_messages.folder_id')
                ->orderBy('mail_messages.received_at')
                ->get(['mail_messages.id', 'mail_messages.thread_id', 'mail_messages.message_id_hash', 'mail_messages.subject',
                    'mail_messages.from_name', 'mail_messages.from_email', 'mail_messages.to', 'mail_messages.cc',
                    'mail_messages.received_at', 'mail_messages.is_seen', 'mail_messages.has_attachments',
                    'mail_messages.is_outbound', 'mail_messages.snippet', 'mail_folders.role as folder_role'])
                ->groupBy('thread_id');

            foreach ($threads as $id => $thread) {
                self::one($thread, $messages->get($id, collect()));
            }
        }
    }

    private static function one(MailThread $thread, $all): void
    {
        if ($all->isEmpty()) {
            if (self::hasTeamwork($thread)) {
                $thread->forceFill(['message_count' => 0, 'unread_count' => 0])->save();
            } else {
                $thread->delete();
            }

            return;
        }

        $live = $all->filter(fn ($m) => ! in_array($m->folder_role, ['spam', 'trash'], true));
        $use = $live->isNotEmpty() ? $live : $all;

        // One per message: copies in two folders share message_id_hash
        $unique = $use->unique(fn ($m) => $m->message_id_hash ?? 'row:' . $m->id);

        $people = [];
        foreach ($all as $m) {
            foreach (array_merge([['name' => $m->from_name, 'email' => $m->from_email]], $m->to ?? [], $m->cc ?? []) as $p) {
                $email = strtolower(trim((string) ($p['email'] ?? '')));
                if ($email !== '' && ! isset($people[$email])) {
                    $people[$email] = ['name' => $p['name'] ?? null, 'email' => $email];
                } elseif ($email !== '' && empty($people[$email]['name']) && ! empty($p['name'])) {
                    $people[$email]['name'] = $p['name'];
                }
            }
        }

        $latest = $use->last();
        $lastAt = $use->max('received_at');
        $inbound = $all->filter(fn ($m) => ! $m->is_outbound)->max('received_at');

        $fill = [
            'subject' => $all->first()->subject,
            'subject_key' => Threader::subjectKey($all->first()->subject),
            'snippet' => $latest->snippet,
            'participants' => array_slice(array_values($people), 0, 12),
            'message_count' => $unique->count(),
            'unread_count' => $unique->where('is_seen', false)->count(),
            'has_attachments' => $all->contains(fn ($m) => (bool) $m->has_attachments),
            'first_message_at' => $all->min('received_at'),
            'last_message_at' => $lastAt,
            'last_inbound_at' => $inbound,
        ];

        // Kept until new mail (owner decision, as in MyCity)
        if ($thread->ai_summary_at && $lastAt && $thread->ai_summary_at->lt($lastAt)) {
            $fill['ai_summary'] = null;
            $fill['ai_summary_at'] = null;
        }

        $thread->forceFill($fill)->save();
    }

    private static function hasTeamwork(MailThread $thread): bool
    {
        return $thread->assigned_to !== null
            || $thread->status === 'done'
            || DB::table('mail_thread_notes')->where('thread_id', $thread->id)->whereNull('deleted_at')->exists();
    }

    /** A folder's own counts in HQ's copy */
    public static function folder(MailFolder $folder): void
    {
        $counts = MailMessage::where('folder_id', $folder->id)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_seen = 0 THEN 1 ELSE 0 END) as unread')
            ->first();

        $folder->forceFill([
            'total_count' => (int) ($counts->total ?? 0),
            'unread_count' => (int) ($counts->unread ?? 0),
        ])->save();
    }
}
