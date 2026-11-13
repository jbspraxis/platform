<?php

namespace App\Support\Chat;

use App\Support\Access;
use App\Support\People;
use App\Support\ProjectAccess;
use App\Support\Pwa\ClientNotify;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The conversation between a client and its delivery team, one per project (docs/CHAT.md). The Client Portal
 * (App\Livewire\ClientOs\DeliveryChat) and HQ (App\Livewire\Hq\ClientMessages\*) both read and write it here.
 *
 * A party is 'client' (client_users) or 'staff' (users). A client never sees internal notes. Messages are shown in
 * (created_at, id) order; "new since" and "read up to" use the id, which only grows.
 */
class ProjectChat
{
    // Owner, 13 Nov 2026: new messages within 3 seconds, Seen ticks and "is typing"
    public const POLL_MS = 3000;
    public const IDLE_POLL_MS = 30000;      // the chat closed, or the browser tab in the background: the badge only
    public const TYPING_SECONDS = 6;
    public const PRESENT_SECONDS = 15;      // checked in within this: looking at the chat now, so no bell or push
    public const COLLAPSE_MINUTES = 10;     // a bell not yet read is updated, not repeated, for this long
    public const EMAIL_AFTER_MINUTES = 60;  // owner: an email when a message isn't seen within an hour
    public const PAGE = 40;
    public const BODY_MAX = 5000;
    public const MAX_FILES = 5;
    public const MAX_FILE_KB = 10240;
    public const MAX_VOICE_SECONDS = 300;
    public const FILE_MIMES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip';
    // What browsers record: Chrome and Firefox webm/ogg (opus), Safari mp4 (aac); fileinfo may call webm audio video/webm
    public const VOICE_TYPES = 'audio/webm,audio/ogg,audio/mp4,audio/mpeg,audio/aac,audio/x-m4a,audio/wav,audio/x-wav,video/webm,video/mp4,application/ogg';
    public const EVENTS = ['investigating', 'resolved', 'fix_approved', 'task_approved'];
    // The portal's bells for the chat (App\Support\Pwa\ClientNotify::KINDS): a message with words, or a file alone
    public const CLIENT_KINDS = ['chat_message', 'chat_file'];

    // ---- Reading ---------------------------------------------------------------------------------------------

    /** The project's messages this party may read: not deleted, not on a deleted ticket; never internal notes for a client */
    public static function visible(string $projectId, string $party)
    {
        return DB::table('chat_messages as m')
            ->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->where('m.project_id', $projectId)
            ->whereNull('m.deleted_at')->whereNull('t.deleted_at')
            ->when($party === 'client', fn ($q) => $q->where('m.is_internal', 0));
    }

    /**
     * The last $limit messages, oldest first, ready to draw: author, issue card, quoted message, files.
     *
     * @return array{0: array<int, array>, 1: bool} [messages, whether there are earlier ones]
     */
    public static function messages(string $projectId, string $party, int $limit = self::PAGE): array
    {
        $query = self::visible($projectId, $party)
            ->select('m.id', 'm.author_type', 'm.author_id', 'm.kind', 'm.body', 'm.event', 'm.qa_ticket_id', 'm.reply_to_id', 'm.is_internal', 'm.imported', 'm.created_at',
                't.title as ticket_title', 't.status as ticket_status', 't.severity as ticket_severity', 't.task_id as ticket_task_id')
            ->orderByDesc('m.created_at')->orderByDesc('m.id')->limit($limit + 1);
        $rows = People::joinAuthor($query, 'm.author_id', 'm.author_type')->get();
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->reverse()->values();
        if ($rows->isEmpty()) {
            return [[], false];
        }

        $ids = $rows->pluck('id')->all();
        $files = self::files($ids);
        $quotes = self::quotes($projectId, $party, $rows->pluck('reply_to_id')->filter()->unique()->values()->all());
        // A ticket's task, only when the reader may see it (a client: tasks shown to the client)
        $taskIds = $rows->pluck('ticket_task_id')->filter()->unique()->values()->all();
        $tasks = $taskIds ? DB::table('tasks')->whereIn('id', $taskIds)->whereNull('deleted_at')
            ->when($party === 'client', fn ($q) => $q->where('is_client_visible', 1))->pluck('title', 'id') : collect();
        $cards = $rows->where('kind', 'issue')->pluck('id')->all();
        $replies = $cards ? self::visible($projectId, $party)->whereIn('m.reply_to_id', $cards)->where('m.kind', 'text')->groupBy('m.reply_to_id')
            ->selectRaw('m.reply_to_id, COUNT(*) as n')->pluck('n', 'reply_to_id') : collect();

        return [$rows->map(fn ($m) => [
            'id' => (int) $m->id,
            'party' => $m->author_type,
            'author_id' => $m->author_id,
            'name' => self::name($m->first_name, $m->last_name, $m->author_type, $party),
            'full_name' => trim($m->first_name . ' ' . $m->last_name),
            'initials' => self::initials($m->first_name, $m->last_name),
            'kind' => $m->kind,
            'body' => (string) $m->body,
            'event' => $m->event,
            'internal' => (bool) $m->is_internal,
            'imported' => (bool) $m->imported,
            'at' => Carbon::parse($m->created_at)->setTimezone('Africa/Lagos'),
            'ticket' => $m->qa_ticket_id && $m->ticket_title !== null ? [
                'id' => $m->qa_ticket_id, 'title' => self::ticketTitle($m->ticket_title), 'status' => $m->ticket_status, 'severity' => $m->ticket_severity,
                'task_id' => $m->ticket_task_id, 'task' => $tasks[$m->ticket_task_id] ?? null, 'replies' => (int) ($replies[$m->id] ?? 0),
            ] : null,
            'quote' => $m->reply_to_id ? ($quotes[$m->reply_to_id] ?? ['missing' => true]) : null,
            'files' => $files[$m->id] ?? [],
        ])->all(), $more];
    }

    /** The messages being quoted: who wrote them and their first words (or what they hold) */
    private static function quotes(string $projectId, string $party, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $query = self::visible($projectId, $party)->whereIn('m.id', $ids)
            ->select('m.id', 'm.author_type', 'm.author_id', 'm.kind', 'm.body', 'm.event', 't.title as ticket_title');
        $files = self::files($ids);

        return People::joinAuthor($query, 'm.author_id', 'm.author_type')->get()->mapWithKeys(fn ($m) => [$m->id => [
            'id' => (int) $m->id,
            'name' => self::name($m->first_name, $m->last_name, $m->author_type, $party),
            'party' => $m->author_type,
            'author_id' => $m->author_id,
            'kind' => $m->kind,
            'title' => $m->kind === 'issue' ? self::ticketTitle((string) $m->ticket_title) : null,
            'text' => Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $m->body)), 140),
            'file' => ($files[$m->id][0]['kind'] ?? null),
        ]])->all();
    }

    /** One quoted message (the composer's "Replying to"), or null */
    public static function quote(string $projectId, string $party, ?int $id): ?array
    {
        return $id ? (self::quotes($projectId, $party, [$id])[$id] ?? null) : null;
    }

    /** A message's words as HTML: escaped, links made clickable (opened in a new tab), line breaks kept */
    public static function html(string $text): string
    {
        $safe = e($text);
        // On the escaped text: a link stops at a space or an escaped quote or bracket
        $safe = preg_replace_callback('~\bhttps?://(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<>])+~iu', function ($m) {
            $url = rtrim($m[0], '.,;:!?)');
            $tail = substr($m[0], strlen($url));

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $url . '</a>' . $tail;
        }, $safe);

        return nl2br($safe, false);
    }

    /** Each message's files, as [message id => [[id, kind, name, size, mime, duration], …]] */
    private static function files(array $messageIds): array
    {
        if (! $messageIds) {
            return [];
        }

        return DB::table('chat_attachments')->whereIn('message_id', $messageIds)->orderBy('id')
            ->get(['id', 'message_id', 'kind', 'file_name', 'mime_type', 'size_bytes', 'duration_ms'])
            ->groupBy('message_id')
            ->map(fn ($group) => $group->map(fn ($f) => [
                'id' => (int) $f->id, 'kind' => $f->kind, 'name' => $f->file_name, 'mime' => $f->mime_type,
                'size' => (int) $f->size_bytes, 'duration' => $f->duration_ms !== null ? (int) $f->duration_ms : null,
            ])->values()->all())->all();
    }

    /** Older tickets carried a "[DIRECT COMM] " or "[BUG REPORT] " prefix */
    public static function ticketTitle(string $title): string
    {
        return preg_replace('/^\[(DIRECT COMM|BUG REPORT)\]\s*/', '', $title);
    }

    /** "Tom-Blake F." for a client reading a staff member; full names otherwise */
    public static function name(?string $first, ?string $last, ?string $authorParty, string $reader): string
    {
        $first = trim((string) $first);
        $last = trim((string) $last);
        if ($first === '' && $last === '') {
            return $authorParty === 'staff' ? 'JBS Praxis' : '—';
        }
        if ($reader === 'client' && $authorParty === 'staff' && $last !== '') {
            return trim($first . ' ' . mb_substr($last, 0, 1) . '.');
        }

        return trim($first . ' ' . $last);
    }

    public static function initials(?string $first, ?string $last): string
    {
        return mb_strtoupper(mb_substr(trim((string) $first), 0, 1) . mb_substr(trim((string) $last), 0, 1)) ?: '·';
    }

    /** The latest message id this party may read (0 when there is none) */
    public static function latestId(string $projectId, string $party): int
    {
        return (int) self::visible($projectId, $party)->max('m.id');
    }

    /**
     * What changes the drawn conversation: the latest message, and the project's tickets (a card's status). Polling
     * draws the conversation again only when this changes; read receipts and typing travel apart (state()).
     */
    public static function signature(string $projectId, string $party): string
    {
        $tickets = DB::table('qa_tickets')->where('project_id', $projectId)->selectRaw('COUNT(*) as n, MAX(updated_at) as at, SUM(deleted_at IS NOT NULL) as gone')->first();

        return self::latestId($projectId, $party) . '|' . ($tickets->n ?? 0) . '|' . ($tickets->at ?? '') . '|' . ($tickets->gone ?? 0);
    }

    /** Messages for this person they haven't read yet: from the other side (a client: anyone else), never imported ones */
    public static function unread(string $projectId, string $party, string $personId): int
    {
        $last = (int) DB::table('chat_readers')->where(['project_id' => $projectId, 'party' => $party, 'person_id' => $personId])->value('last_read_id');

        return self::visible($projectId, $party)->where('m.id', '>', $last)->where('m.imported', 0)
            ->when($party === 'staff', fn ($q) => $q->where('m.author_type', 'client'))
            ->when($party === 'client', fn ($q) => $q->where(fn ($q) => $q->where('m.author_type', 'staff')->orWhere('m.author_id', '!=', $personId)))
            ->count();
    }

    /** Read receipts and who is typing, for the person looking: [seen up to (the other side's furthest read), names typing] */
    public static function state(string $projectId, string $party, string $personId): array
    {
        $other = $party === 'client' ? 'staff' : 'client';
        $readers = DB::table('chat_readers')->where('project_id', $projectId)
            ->where(fn ($q) => $q->where('party', $other)->orWhere('typing_at', '>=', now()->subSeconds(self::TYPING_SECONDS)))
            ->get(['party', 'person_id', 'last_read_id', 'typing_at']);
        $seen = (int) $readers->where('party', $other)->max('last_read_id');

        $typing = $readers->filter(fn ($r) => $r->typing_at && Carbon::parse($r->typing_at)->gte(now()->subSeconds(self::TYPING_SECONDS))
            && ! ($r->party === $party && $r->person_id === $personId));
        $names = [];
        foreach ($typing as $r) {
            $person = DB::table($r->party === 'staff' ? 'users' : 'client_users')->where('id', $r->person_id)->first(['first_name', 'last_name']);
            if ($person) {
                $names[] = $r->party === 'staff' && $party === 'client'
                    ? self::name($person->first_name, $person->last_name, 'staff', 'client')
                    : trim($person->first_name . ' ' . $person->last_name);
            }
        }

        return [$seen, $names];
    }

    // ---- Presence and reading ------------------------------------------------------------------------------

    /** This person's row (made on first use) */
    private static function reader(string $projectId, string $party, string $personId): object
    {
        $key = ['project_id' => $projectId, 'party' => $party, 'person_id' => $personId];
        $row = DB::table('chat_readers')->where($key)->first();
        if (! $row) {
            DB::table('chat_readers')->insertOrIgnore($key + ['last_read_id' => 0]);
            $row = DB::table('chat_readers')->where($key)->first();
        }

        return $row;
    }

    /** Checked in with the chat open (and maybe typing). Written at most every 5 seconds unless typing changes. */
    public static function touch(string $projectId, string $party, string $personId, bool $typing = false): void
    {
        $row = self::reader($projectId, $party, $personId);
        $update = [];
        if (! $row->seen_at || Carbon::parse($row->seen_at)->lt(now()->subSeconds(5))) {
            $update['seen_at'] = now();
        }
        if ($typing) {
            $update['typing_at'] = now();
        }
        if ($update) {
            DB::table('chat_readers')->where('id', $row->id)->update($update);
        }
    }

    /** Read up to this message: moves forward only; the bells about this chat are read too */
    public static function markRead(string $projectId, string $party, string $personId, int $upTo): void
    {
        $row = self::reader($projectId, $party, $personId);
        if ($upTo <= (int) $row->last_read_id) {
            return;
        }
        DB::table('chat_readers')->where('id', $row->id)->update(['last_read_id' => $upTo, 'read_at' => now()]);

        if ($party === 'staff') {
            DB::table('notifications')->where('user_id', $personId)->where('is_read', 0)->where('link_url', self::staffLink($projectId))->update(['is_read' => 1]);
        } elseif (ClientNotify::ready()) {
            DB::table('client_notifications')->where('client_user_id', $personId)->whereIn('kind', self::CLIENT_KINDS)->where('is_read', 0)
                ->where('link_url', self::clientLink($projectId))->update(['is_read' => 1]);
        }
    }

    /** Stopped typing (a message was sent) */
    public static function stopTyping(string $projectId, string $party, string $personId): void
    {
        DB::table('chat_readers')->where(['project_id' => $projectId, 'party' => $party, 'person_id' => $personId])->update(['typing_at' => null]);
    }

    // ---- Writing ---------------------------------------------------------------------------------------------

    /**
     * Post a message. $files: [['path', 'name', 'mime', 'size', 'kind', 'duration'], …] already stored (store()).
     * The author has read up to it; the other side is told (bell, push) unless it is an internal note or an event.
     */
    public static function post(string $projectId, string $party, string $personId, array $data): int
    {
        $kind = $data['kind'] ?? 'text';
        $internal = $party === 'staff' && ! empty($data['internal']);
        $id = DB::transaction(function () use ($projectId, $party, $personId, $data, $kind, $internal) {
            $id = DB::table('chat_messages')->insertGetId([
                'project_id' => $projectId, 'author_type' => $party, 'author_id' => $personId, 'kind' => $kind,
                'body' => isset($data['body']) && trim((string) $data['body']) !== '' ? rtrim((string) $data['body']) : null,
                'event' => $data['event'] ?? null, 'qa_ticket_id' => $data['ticket'] ?? null, 'reply_to_id' => $data['reply_to'] ?? null,
                'is_internal' => $internal ? 1 : 0, 'created_at' => now(),
            ]);
            foreach ($data['files'] ?? [] as $file) {
                DB::table('chat_attachments')->insert([
                    'message_id' => $id, 'project_id' => $projectId, 'kind' => $file['kind'], 'file_name' => Str::limit($file['name'], 250, ''),
                    'file_path' => $file['path'], 'mime_type' => Str::limit((string) $file['mime'], 100, ''), 'size_bytes' => (int) $file['size'],
                    'duration_ms' => $file['duration'] ?? null, 'created_at' => now(),
                ]);
            }

            return $id;
        });

        self::markRead($projectId, $party, $personId, $id);
        self::stopTyping($projectId, $party, $personId);
        if (! $internal && $kind !== 'event') {
            try {
                $party === 'client' ? self::tellStaff($projectId, $id) : self::tellClients($projectId, $id);
            } catch (\Throwable $e) {
                report($e); // a bell must never stop the message itself
            }
        }

        return $id;
    }

    /** A status change of an issue, as a line in the conversation ("Issue resolved by …") */
    public static function event(string $ticketId, string $event, string $party, string $personId): ?int
    {
        $ticket = DB::table('qa_tickets')->where('id', $ticketId)->first(['id', 'project_id']);
        if (! $ticket || ! in_array($event, self::EVENTS, true)) {
            return null;
        }
        $card = self::cardFor($ticketId);

        return self::post($ticket->project_id, $party, $personId, ['kind' => 'event', 'event' => $event, 'ticket' => $ticketId, 'reply_to' => $card]);
    }

    /** The issue card of a ticket, made now if the ticket has none (tickets made outside the chat: handovers, imports) */
    public static function cardFor(string $ticketId): ?int
    {
        $card = DB::table('chat_messages')->where('qa_ticket_id', $ticketId)->where('kind', 'issue')->value('id');
        if ($card) {
            return (int) $card;
        }
        $ticket = DB::table('qa_tickets')->where('id', $ticketId)->first();
        if (! $ticket) {
            return null;
        }

        return (int) DB::table('chat_messages')->insertGetId([
            'project_id' => $ticket->project_id, 'author_type' => self::ticketAuthorType($ticket), 'author_id' => $ticket->submitted_by,
            'kind' => 'issue', 'body' => $ticket->description, 'qa_ticket_id' => $ticket->id, 'created_at' => $ticket->created_at ?? now(),
        ]);
    }

    /**
     * Who raised a ticket: its submitted_by_type, unless that says client while the id is a staff member's (tickets
     * saved without the type took the column's default, 'client': opportunities:import before 13 Nov 2026)
     */
    public static function ticketAuthorType(object $ticket): string
    {
        $type = ($ticket->submitted_by_type ?? null) === 'client' ? 'client' : 'staff';
        if ($type === 'client' && $ticket->submitted_by
            && ! DB::table('client_users')->where('id', $ticket->submitted_by)->exists()
            && DB::table('users')->where('id', $ticket->submitted_by)->exists()) {
            return 'staff';
        }

        return $type;
    }

    /** A client reports an issue in the chat: the QA ticket (as before) and its card */
    public static function reportIssue(string $projectId, object $client, ?string $taskId, string $title, string $severity, string $details, array $files = []): int
    {
        return DB::transaction(function () use ($projectId, $client, $taskId, $title, $severity, $details, $files) {
            $ticketId = (string) Str::uuid();
            DB::table('qa_tickets')->insert([
                'id' => $ticketId, 'project_id' => $projectId, 'task_id' => $taskId ?: null, 'title' => $title, 'description' => $details,
                'status' => 'Open', 'severity' => $severity, 'submitted_by' => $client->id, 'submitted_by_type' => 'client',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return self::post($projectId, 'client', $client->id, ['kind' => 'issue', 'ticket' => $ticketId, 'body' => $details, 'files' => $files]);
        });
    }

    /** Keep an uploaded file on the private disk under chat/<project>/ and describe it for post() */
    public static function store(UploadedFile $file, string $projectId, ?string $kind = null, ?int $durationMs = null): array
    {
        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $kind ??= str_starts_with($mime, 'image/') && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'image' : 'file';
        $path = $file->store('chat/' . $projectId, 'local');
        $name = $file->getClientOriginalName() ?: basename($path);
        if ($kind === 'voice') {
            $name = 'voice-note-' . now()->setTimezone('Africa/Lagos')->format('Ymd-His') . '.' . (pathinfo($path, PATHINFO_EXTENSION) ?: 'webm');
        }

        return ['path' => $path, 'name' => $name, 'mime' => $mime, 'size' => (int) $file->getSize(), 'kind' => $kind,
            'duration' => $durationMs !== null ? max(0, min($durationMs, self::MAX_VOICE_SECONDS * 1000 + 999)) : null];
    }

    // ---- Who hears about it ----------------------------------------------------------------------------------

    /**
     * The delivery team told about a client's message: the project's lead and the people on its tasks (leading,
     * sharing or watching one) who may open client chats; nobody there, everyone who works on issues (qa.edit).
     */
    public static function staffAudience(string $projectId): array
    {
        $lead = DB::table('projects')->where('id', $projectId)->value('lead_user_id');
        $team = DB::table('tasks')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
            ->where('project_phases.project_id', $projectId)->whereNull('tasks.deleted_at')->whereNotNull('tasks.assigned_to')->pluck('tasks.assigned_to')
            ->merge(DB::table('task_people')->join('tasks', 'tasks.id', '=', 'task_people.task_id')->join('project_phases', 'project_phases.id', '=', 'tasks.phase_id')
                ->where('project_phases.project_id', $projectId)->whereNull('tasks.deleted_at')->pluck('task_people.user_id'))
            ->prepend($lead)->filter()->unique();
        $ids = $team->intersect(Access::staffWith('qa.view'))->values()->all();

        return $ids ?: Access::staffWith('qa.edit');
    }

    /** The client's accounts that read this chat (portal Quality and issues, limited to the project when set) */
    public static function clientAudience(string $projectId): array
    {
        $org = DB::table('projects')->where('id', $projectId)->value('organization_id');

        return $org ? ClientNotify::recipients($org, 'portal.qa.view', $projectId) : [];
    }

    /** People of this party looking at the chat right now */
    private static function present(string $projectId, string $party): array
    {
        return DB::table('chat_readers')->where('project_id', $projectId)->where('party', $party)
            ->where('seen_at', '>=', now()->subSeconds(self::PRESENT_SECONDS))->pluck('person_id')->all();
    }

    public static function staffLink(string $projectId): string
    {
        return route('hq.ops.messages', ['project' => $projectId]);
    }

    public static function clientLink(string $projectId): string
    {
        return '/?chat=' . $projectId;
    }

    /** What a message holds, in a few words, for a bell or an email ('en' or 'fr') */
    public static function summary(object|array $message, ?string $locale = 'en'): string
    {
        $message = (object) $message;
        $text = trim(preg_replace('/\s+/u', ' ', (string) ($message->body ?? '')));
        if (($message->kind ?? 'text') === 'issue') {
            $title = (string) DB::table('qa_tickets')->where('id', $message->qa_ticket_id)->value('title');

            return __('chat.summary.issue', ['title' => self::ticketTitle($title)], $locale);
        }
        if ($text !== '') {
            return Str::limit($text, 140);
        }
        $kind = DB::table('chat_attachments')->where('message_id', $message->id)->value('kind');

        return __('chat.summary.' . ($kind ?: 'file'), [], $locale);
    }

    /** A bell (and its push) for the delivery team; one unread bell per person per chat, updated for 10 minutes */
    private static function tellStaff(string $projectId, int $messageId): void
    {
        $message = DB::table('chat_messages')->where('id', $messageId)->first();
        $project = DB::table('projects')->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->where('projects.id', $projectId)->first(['projects.title', 'organizations.name as client']);
        $author = DB::table('client_users')->where('id', $message->author_id)->first(['first_name', 'last_name']);
        if (! $project) {
            return;
        }
        $who = trim(($author->first_name ?? '') . ' ' . ($author->last_name ?? '')) ?: $project->client;
        $issue = $message->kind === 'issue';
        $title = $issue ? 'Issue reported by ' . $project->client : 'Message from ' . $project->client;
        $text = "{$who} on {$project->title}: “" . self::summary($message) . '”. Reply in Client messages.';

        $people = array_diff(self::staffAudience($projectId), self::present($projectId, 'staff'));
        $link = self::staffLink($projectId);
        foreach ($people as $userId) {
            $open = DB::table('notifications')->where('user_id', $userId)->where('is_read', 0)->where('link_url', $link)
                ->where('created_at', '>=', now()->subMinutes(self::COLLAPSE_MINUTES))->orderByDesc('created_at')->value('id');
            if ($open && ! $issue) {
                DB::table('notifications')->where('id', $open)->update(['title' => $title, 'message' => $text]);
                continue;
            }
            DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'user_id' => $userId, 'title' => $title, 'message' => $text,
                'link_url' => $link, 'type' => 'Project', 'created_at' => now()]);
        }
    }

    /** The client's accounts hear of a reply (bell, push in their language); one unread bell per chat for 10 minutes */
    private static function tellClients(string $projectId, int $messageId): void
    {
        if (! ClientNotify::ready()) {
            return;
        }
        $message = DB::table('chat_messages')->where('id', $messageId)->first();
        $project = DB::table('projects')->where('id', $projectId)->first(['title', 'organization_id']);
        $author = DB::table('users')->where('id', $message->author_id)->first(['first_name', 'last_name']);
        if (! $project) {
            return;
        }
        // The words travel as written; a file alone is said in the reader's language (lang/*/portal.php, inbox)
        $text = trim(preg_replace('/\s+/u', ' ', (string) $message->body));
        $kind = $text === '' ? 'chat_file' : 'chat_message';
        $params = ['name' => self::name($author->first_name ?? '', $author->last_name ?? '', 'staff', 'client'), 'project' => $project->title,
            'text' => Str::limit($text, 140)];
        $people = array_values(array_diff(self::clientAudience($projectId), self::present($projectId, 'client')));
        $link = self::clientLink($projectId);
        $open = $people ? DB::table('client_notifications')->whereIn('client_user_id', $people)->whereIn('kind', self::CLIENT_KINDS)->where('is_read', 0)
            ->where('link_url', $link)->where('created_at', '>=', now()->subMinutes(self::COLLAPSE_MINUTES))->pluck('id', 'client_user_id') : collect();
        if ($open->isNotEmpty()) {
            DB::table('client_notifications')->whereIn('id', $open->values())->update(['kind' => $kind, 'params' => json_encode($params, JSON_UNESCAPED_UNICODE)]);
        }
        $new = array_values(array_diff($people, $open->keys()->all()));
        if ($new) {
            ClientNotify::clients($new, $kind, $params, $link, $project->organization_id);
        }
    }

    // ---- Who may open which chat (staff) ---------------------------------------------------------------------

    /** Staff who may read client chats (qa.view), on projects they can see: QA approvers and managers see every one */
    public static function staffMaySee(?string $projectId): bool
    {
        $user = Auth::user();
        if (! $projectId || ! $user || ! $user->can('qa.view')) {
            return false;
        }

        return $user->can('qa.approve') || ProjectAccess::maySee($projectId);
    }

    /** The project ids this staff member may open, or null for every project */
    public static function staffProjects(): ?array
    {
        $user = Auth::user();
        if (! $user || ! $user->can('qa.view')) {
            return [];
        }

        return $user->can('qa.approve') || ProjectAccess::seesEveryProject() ? null : ProjectAccess::assignedProjectIds();
    }

    /**
     * The chats a staff member may open, unread first, then the latest conversation; projects still running without
     * a message yet come last (to start one). Each: project, client, last message, unread count.
     */
    public static function inbox(string $userId, string $search = '', int $limit = 60): array
    {
        $allowed = self::staffProjects();
        if ($allowed === []) {
            return [];
        }
        $projects = DB::table('projects')->join('organizations', 'organizations.id', '=', 'projects.organization_id')
            ->whereNull('projects.deleted_at')
            ->where('projects.organization_id', '!=', \Database\Seeders\CoreSeeder::INTERNAL_ORG_ID)
            ->when($allowed !== null, fn ($q) => $q->whereIn('projects.id', $allowed))
            ->when(trim($search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('projects.title', 'like', '%' . trim($search) . '%')
                ->orWhere('organizations.name', 'like', '%' . trim($search) . '%')))
            ->get(['projects.id', 'projects.title', 'projects.status', 'organizations.name as client'])->keyBy('id');
        if ($projects->isEmpty()) {
            return [];
        }
        $ids = $projects->keys()->all();

        $last = DB::table('chat_messages as m')->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->whereIn('m.project_id', $ids)->whereNull('m.deleted_at')->whereNull('t.deleted_at')
            ->groupBy('m.project_id')->selectRaw('m.project_id, MAX(m.id) as id')->pluck('id', 'project_id');
        $lastRows = $last->isEmpty() ? collect() : People::joinAuthor(DB::table('chat_messages as m')->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->whereIn('m.id', $last->values())->select('m.id', 'm.project_id', 'm.kind', 'm.body', 'm.event', 'm.author_type', 'm.is_internal', 'm.created_at', 't.title as ticket_title'),
            'm.author_id', 'm.author_type')->get()->keyBy('project_id');
        $unread = DB::table('chat_messages as m')->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->leftJoin('chat_readers as r', fn ($j) => $j->on('r.project_id', '=', 'm.project_id')->where('r.party', '=', 'staff')->where('r.person_id', '=', $userId))
            ->whereIn('m.project_id', $ids)->whereNull('m.deleted_at')->whereNull('t.deleted_at')->where('m.imported', 0)->where('m.author_type', 'client')
            ->whereRaw('m.id > COALESCE(r.last_read_id, 0)')->groupBy('m.project_id')->selectRaw('m.project_id, COUNT(*) as n')->pluck('n', 'project_id');

        $rows = $projects->map(function ($p) use ($lastRows, $unread) {
            $m = $lastRows[$p->id] ?? null;

            return [
                'id' => $p->id, 'title' => $p->title, 'client' => $p->client, 'status' => $p->status,
                'unread' => (int) ($unread[$p->id] ?? 0),
                'last' => $m ? [
                    'who' => $m->author_type === 'staff' ? trim($m->first_name) : trim($m->first_name . ' ' . $m->last_name),
                    'party' => $m->author_type, 'internal' => (bool) $m->is_internal,
                    'text' => match ($m->kind) {
                        'issue' => 'Issue: ' . self::ticketTitle((string) $m->ticket_title),
                        'event' => self::eventText($m->event, 'en'),
                        default => self::summary($m),
                    },
                    'at' => Carbon::parse($m->created_at)->setTimezone('Africa/Lagos'),
                ] : null,
            ];
        })->filter(fn ($row) => $row['last'] || in_array($row['status'], ['Active', 'Scoping'], true));

        return $rows->sort(function ($a, $b) {
            return [$b['unread'] > 0, $b['last'] ? $b['last']['at']->timestamp : 0, $a['title']]
                <=> [$a['unread'] > 0, $a['last'] ? $a['last']['at']->timestamp : 0, $b['title']];
        })->take($limit)->values()->all();
    }

    /** Unread client messages across every chat this staff member may open (the floating button's badge) */
    public static function staffUnreadTotal(string $userId): int
    {
        $allowed = self::staffProjects();
        if ($allowed === []) {
            return 0;
        }

        return DB::table('chat_messages as m')->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->leftJoin('chat_readers as r', fn ($j) => $j->on('r.project_id', '=', 'm.project_id')->where('r.party', '=', 'staff')->where('r.person_id', '=', $userId))
            ->when($allowed !== null, fn ($q) => $q->whereIn('m.project_id', $allowed))
            ->whereNull('m.deleted_at')->whereNull('t.deleted_at')->where('m.imported', 0)->where('m.author_type', 'client')
            ->whereRaw('m.id > COALESCE(r.last_read_id, 0)')->count();
    }

    /** An event line in a language: "Issue resolved" */
    public static function eventText(?string $event, ?string $locale = null): string
    {
        return __('chat.events.' . ($event ?: 'resolved') . '.short', [], $locale);
    }
}
