<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailThread;
use App\Models\Mail\MailThreadNote;
use App\Models\User;
use App\Support\Mail\MailAccess;
use App\Support\Mail\Team\MailPresence;
use App\Support\Mail\Team\MailTeam;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Teamwork on a conversation in a shared mailbox: assignment, Open / Done, internal notes, who is viewing or
 * replying, and the activity timeline (docs/MAIL.md, owner decision 7). Mounted in the reader with :thread-id;
 * renders nothing for personal mailboxes or people who can't read the mailbox.
 *
 * Levels: read sees all of it and adds notes; send also assigns and marks Open / Done (MailTeam).
 * Presence comes from the browser events mail-thread-opened (PR 2) and mail-composing (PR 3) and a 20 s poll.
 */
class ThreadTeam extends Component
{
    public ?int $threadId = null;

    public string $note = '';

    /** '' (closed), 'notes' or 'activity' */
    public string $panel = '';

    public ?string $error = null;

    public function mount(?int $threadId = null): void
    {
        $this->threadId = $threadId;
        if ($thread = $this->thread()) {
            MailPresence::touch(Auth::user(), $thread);
        }
    }

    /** The poll: keeps "viewing" alive and picks up others' changes */
    public function heartbeat(): void
    {
        if ($thread = $this->thread()) {
            MailPresence::touch(Auth::user(), $thread);
        }
    }

    #[On('mail-thread-opened')]
    public function threadOpened($threadId = null, $accountId = null): void
    {
        if ((int) $threadId === $this->threadId && ($thread = $this->thread())) {
            MailPresence::touch(Auth::user(), $thread);
        }
    }

    #[On('mail-composing')]
    public function composing($threadId = null, $state = null): void
    {
        if ((int) $threadId !== $this->threadId || ! ($thread = $this->thread())) {
            return;
        }
        $state === 'replying' ? MailPresence::touch(Auth::user(), $thread, 'replying') : MailPresence::stopReplying(Auth::user(), $thread);
    }

    public function assign(?string $userId = null): void
    {
        $this->act(fn (MailThread $t) => MailTeam::assign(Auth::user(), $t, $userId ?: null));
    }

    public function assignToMe(): void
    {
        $this->assign(Auth::id());
    }

    public function markDone(): void
    {
        $this->act(fn (MailThread $t) => MailTeam::setStatus(Auth::user(), $t, 'done'));
    }

    public function reopen(): void
    {
        $this->act(fn (MailThread $t) => MailTeam::setStatus(Auth::user(), $t, 'open'));
    }

    public function addNote(): void
    {
        $this->validate(['note' => 'required|string|max:' . MailTeam::NOTE_MAX], [
            'note.required' => 'Write the note first.',
            'note.max' => 'Keep the note under ' . number_format(MailTeam::NOTE_MAX) . ' characters.',
        ]);
        $this->act(fn (MailThread $t) => MailTeam::addNote(Auth::user(), $t, $this->note), false);
        $this->note = '';
        $this->panel = 'notes';
    }

    public function deleteNote(int $noteId): void
    {
        $note = MailThreadNote::where('thread_id', $this->threadId)->findOrFail($noteId);
        MailTeam::deleteNote(Auth::user(), $note);
    }

    public function togglePanel(string $panel): void
    {
        $this->panel = $this->panel === $panel || ! in_array($panel, ['notes', 'activity'], true) ? '' : $panel;
    }

    public function render()
    {
        $user = Auth::user();
        $thread = $this->thread();
        if (! $thread) {
            return view('livewire.hq.mail.thread-team', ['thread' => null]);
        }
        $account = $thread->account;
        $notes = MailTeam::notes($user, $thread);

        return view('livewire.hq.mail.thread-team', [
            'thread' => $thread,
            'account' => $account,
            'me' => $user,
            'canSend' => MailAccess::can($user, $account, 'send'),
            'canManage' => MailAccess::can($user, $account, 'manage'),
            'assignees' => MailTeam::assignees($account),
            'assignee' => $thread->assignee,
            'doneBy' => $thread->done_by ? User::find($thread->done_by) : null,
            'others' => MailPresence::others($user, $thread),
            'notes' => $notes,
            'activity' => $this->panel === 'activity' ? MailTeam::activity($user, $thread) : [],
        ]);
    }

    /** The conversation when it is in a shared mailbox this person can read, else null */
    private function thread(): ?MailThread
    {
        if (! $this->threadId || ! MailAccess::canUse(Auth::user())) {
            return null;
        }
        $thread = MailThread::with('account')->find($this->threadId);
        if (! $thread || ! $thread->account instanceof MailAccount || ! $thread->account->isShared()) {
            return null;
        }

        return MailAccess::can(Auth::user(), $thread->account, 'read') ? $thread : null;
    }

    private function act(callable $fn, bool $changed = true): void
    {
        $thread = $this->thread();
        abort_unless($thread, 403);
        $this->error = null;
        try {
            $fn($thread);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }
            $this->error = $e->getMessage() ?: 'That didn’t work. Refresh the page and try again.';

            return;
        }
        if ($changed) {
            $this->dispatch('mail-thread-changed', threadId: $thread->id);
        }
    }
}
