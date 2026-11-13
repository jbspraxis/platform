<?php

namespace App\Livewire\Concerns;

use App\Support\Chat\ProjectChat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

/**
 * What a project's conversation does on either side (docs/CHAT.md): check for news, typing, earlier messages,
 * replying, files and voice notes. The Client Portal's DeliveryChat and HQ's Thread use it; each says who is
 * writing (chatParty, chatPerson) and may add its own (issues for the client, internal notes for staff).
 *
 * The browser asks heartbeat() every 3 seconds while the chat is open (30 seconds otherwise, for the badge). The
 * conversation is drawn again only when it changed (ProjectChat::signature); read receipts and "is typing" travel
 * as a browser event (chat-state), so most checks send no HTML at all.
 */
trait ProjectChatRoom
{
    #[Locked]
    public string $projectId = '';
    #[Locked]
    public string $signature = '';
    #[Locked]
    public int $lastReadAtOpen = 0;  // where "Unread messages" goes: what this person had read when the chat opened
    #[Locked]
    public ?int $replyTo = null;

    #[Locked]
    public int $limit = ProjectChat::PAGE;  // grows with "Show earlier messages" (server only, up to 2,000)
    public string $draft = '';
    public array $files = [];
    public array $incoming = [];     // files being added (picked, pasted or dropped): appended to $files
    public $voice;

    /** 'client' or 'staff' */
    abstract protected function chatParty(): string;

    /** The person writing, allowed to read this chat (and to write in it when $write); stops with 403 otherwise */
    abstract protected function chatPerson(bool $write = false): object;

    protected function messages(): array
    {
        $v = (array) __('chat.validation');

        return [
            'draft.max' => $v['body_max'], 'files.max' => $v['files_max'],
            'files.*.mimes' => $v['file_type'], 'files.*.max' => $v['file_size'], 'files.*.file' => $v['file_type'],
            'voice.required' => $v['voice'], 'voice.file' => $v['voice'], 'voice.mimetypes' => $v['voice'], 'voice.max' => $v['file_size'],
            'issueTitle.required' => $v['subject'], 'issueTitle.max' => $v['subject_max'], 'draft.required' => $v['details'],
            'issueSeverity.in' => $v['priority'], 'issueSeverity.required' => $v['priority'],
        ];
    }

    protected function lastRead(): int
    {
        return (int) DB::table('chat_readers')->where(['project_id' => $this->projectId, 'party' => $this->chatParty(), 'person_id' => $this->chatPerson()->id])->value('last_read_id');
    }

    // ---- Live --------------------------------------------------------------------------------------------------

    /** $open: the chat is on screen; $reading: and scrolled to the newest message with the page in front (read) */
    public function heartbeat(bool $open = false, bool $reading = false): void
    {
        $person = $this->chatPerson();
        $party = $this->chatParty();
        if ($open) {
            ProjectChat::touch($this->projectId, $party, $person->id);
        }
        $latest = ProjectChat::latestId($this->projectId, $party);
        if ($open && $reading) {
            ProjectChat::markRead($this->projectId, $party, $person->id, $latest);
        }
        $this->sendState($person);

        $signature = ProjectChat::signature($this->projectId, $party);
        if ($signature === $this->signature) {
            $this->skipRender();

            return;
        }
        $this->signature = $signature;
        // Watching the newest messages: what arrives now is read as it comes, so no "Unread messages" line for it
        if ($open && $reading) {
            $this->lastReadAtOpen = $latest;
        }
        $this->dispatch('chat-changed', id: $this->getId(), latest: $latest);
    }

    /** The chat was opened: where the unread messages start, and present from now on */
    public function opened(): void
    {
        $this->lastReadAtOpen = $this->lastRead();
        $this->heartbeat(true, false);
    }

    /** Typing in the composer (the browser asks at most every 3 seconds) */
    public function typing(): void
    {
        $person = $this->chatPerson(true);
        ProjectChat::touch($this->projectId, $this->chatParty(), $person->id, true);
        $this->skipRender();
    }

    protected function sendState(object $person): void
    {
        [$seen, $typing] = ProjectChat::state($this->projectId, $this->chatParty(), $person->id);
        $this->dispatch('chat-state', id: $this->getId(), seen: $seen, typing: $typing,
            unread: ProjectChat::unread($this->projectId, $this->chatParty(), $person->id));
    }

    public function loadEarlier(): void
    {
        $this->chatPerson();
        $this->limit = min($this->limit + ProjectChat::PAGE, 2000);
    }

    /** Show at least down to this message (an issue opened from a list), then the browser scrolls to it */
    public function reveal(int $messageId): void
    {
        $party = $this->chatParty();
        $this->chatPerson();
        $target = ProjectChat::visible($this->projectId, $party)->where('m.id', $messageId)->first(['m.created_at', 'm.id']);
        if (! $target) {
            return;
        }
        $newer = ProjectChat::visible($this->projectId, $party)->where(fn ($q) => $q->where('m.created_at', '>', $target->created_at)
            ->orWhere(fn ($q) => $q->where('m.created_at', $target->created_at)->where('m.id', '>', $target->id)))->count();
        $this->limit = max($this->limit, min($newer + 10, 2000));
        $this->dispatch('chat-reveal', id: $this->getId(), message: $messageId);
    }

    // ---- Writing -----------------------------------------------------------------------------------------------

    /** A message with words and/or files ($extra: internal for staff) */
    protected function sendMessage(array $extra = []): void
    {
        $person = $this->chatPerson(true);
        $this->validate([
            'draft' => 'nullable|string|max:' . ProjectChat::BODY_MAX,
            'files' => 'array|max:' . ProjectChat::MAX_FILES,
            'files.*' => 'file|max:' . ProjectChat::MAX_FILE_KB . '|mimes:' . ProjectChat::FILE_MIMES,
        ]);
        if (trim($this->draft) === '' && ! $this->files) {
            $this->addError('draft', __('chat.validation.empty'));

            return;
        }
        $stored = array_map(fn ($file) => ProjectChat::store($file, $this->projectId), $this->files);
        ProjectChat::post($this->projectId, $this->chatParty(), $person->id, ['body' => $this->draft, 'files' => $stored, 'reply_to' => $this->validReply()] + $extra);
        $this->afterSend();
    }

    /** A recorded voice note, with its length in milliseconds (measured by the browser) */
    public function sendVoice(int $durationMs = 0): void
    {
        $person = $this->chatPerson(true);
        $this->validate(['voice' => 'required|file|max:' . ProjectChat::MAX_FILE_KB . '|mimetypes:' . ProjectChat::VOICE_TYPES]);
        $stored = ProjectChat::store($this->voice, $this->projectId, 'voice', $durationMs);
        ProjectChat::post($this->projectId, $this->chatParty(), $person->id, ['files' => [$stored], 'reply_to' => $this->validReply()] + $this->voiceExtra());
        $this->voice = null;
        $this->afterSend();
    }

    /** What else a voice note carries (staff: an internal note) */
    protected function voiceExtra(): array
    {
        return [];
    }

    protected function afterSend(): void
    {
        $this->reset(['draft', 'files', 'replyTo']);
        $this->resetErrorBag();
        $this->signature = ProjectChat::signature($this->projectId, $this->chatParty());
        $this->lastReadAtOpen = $this->lastRead();
        $this->dispatch('chat-sent', id: $this->getId());
    }

    /** The quoted message, if it is still one this reader can see */
    protected function validReply(): ?int
    {
        return $this->replyTo && ProjectChat::visible($this->projectId, $this->chatParty())->where('m.id', $this->replyTo)->exists() ? $this->replyTo : null;
    }

    public function reply(int $messageId): void
    {
        $this->chatPerson(true);
        $this->replyTo = ProjectChat::visible($this->projectId, $this->chatParty())->where('m.id', $messageId)->where('m.kind', '!=', 'event')->exists() ? $messageId : null;
        $this->dispatch('chat-focus', id: $this->getId());
    }

    public function cancelReply(): void
    {
        $this->replyTo = null;
    }

    public function updatedIncoming(): void
    {
        $this->files = array_slice(array_merge($this->files, array_values(array_filter($this->incoming))), 0, ProjectChat::MAX_FILES + 1);
        $this->incoming = [];
        $this->validateOnly('files.*', ['files.*' => 'file|max:' . ProjectChat::MAX_FILE_KB . '|mimes:' . ProjectChat::FILE_MIMES]);
    }

    public function removeFile(int $index): void
    {
        $files = $this->files;
        unset($files[$index]);
        $this->files = array_values($files);
        $this->resetErrorBag('files.*');
    }
}
