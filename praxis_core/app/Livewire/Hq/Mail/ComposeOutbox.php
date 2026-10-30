<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailOutbox;
use App\Support\Mail\Compose\Outbox;
use App\Support\Mail\Compose\OutboxSender;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * HQ → Mail → Scheduled and unsent (/mail/outbox, PR 3): the person's own scheduled messages, messages that
 * didn't go (the "Mail not sent" notification links here) and unfinished drafts. Each can be opened in the
 * composer, sent now, have its schedule cancelled, or be discarded. Nobody sees anyone else's.
 */
#[Layout('components.layouts.hq')]
#[Title('Scheduled and unsent')]
class ComposeOutbox extends Component
{
    #[Locked]
    public ?string $flash = null;
    #[Locked]
    public ?string $confirmDiscard = null;

    public function mount(): void
    {
        abort_unless(MailAccess::canUse(Auth::user()), 403);
    }

    #[On('mail-sent')]
    public function refreshList(): void
    {
        // re-render
    }

    public function edit(string $id): void
    {
        $this->dispatch('mail-compose', mode: 'draft', outboxId: $this->row($id)->id);
    }

    /** A scheduled or failed message: send it now (Undo is not offered here; it was written already) */
    public function sendNow(string $id): void
    {
        $row = $this->row($id);
        try {
            $outbox = Outbox::make();
            $outbox->open(Auth::user(), $row);
            $outbox->queue(Auth::user(), $row->refresh());
            $row->forceFill(['send_at' => now()])->save();
            $row = OutboxSender::make()->send($row);
        } catch (ValidationException $e) {
            $this->flash = collect($e->errors())->flatten()->first();

            return;
        }
        $this->flash = match ($row->status) {
            'sent' => 'Sent.',
            'failed' => 'Not sent: ' . $row->last_error,
            default => 'Not sent yet: ' . ($row->last_error ?: 'the mail server is busy.') . ' HQ will try again by itself.',
        };
        $this->dispatch('mail-sent', accountId: $row->account_id, threadId: $row->thread_id, outboxId: $row->id);
    }

    public function cancelSchedule(string $id): void
    {
        $row = $this->row($id);
        $this->flash = Outbox::make()->undo(Auth::user(), $row) ? 'The schedule is cancelled. The message is back in your drafts.' : 'Too late: the message has gone.';
    }

    public function askDiscard(?string $id): void
    {
        $this->confirmDiscard = $id;
    }

    public function discard(string $id): void
    {
        $row = $this->row($id);
        try {
            Outbox::make()->discard(Auth::user(), $row);
            $this->flash = 'Discarded.';
        } catch (ValidationException $e) {
            $this->flash = collect($e->errors())->flatten()->first();
        }
        $this->confirmDiscard = null;
    }

    public function render()
    {
        $user = Auth::user();
        $accountIds = MailAccess::accountIds($user, 'send');
        $rows = MailOutbox::with('account')->where('user_id', $user->id)->whereIn('account_id', $accountIds)
            ->whereIn('status', ['queued', 'failed', 'draft'])->orderByRaw("FIELD(status, 'failed', 'queued', 'draft')")
            ->orderByDesc('updated_at')->limit(200)->get()
            ->filter(fn ($r) => $r->status !== 'draft' || (! empty($r->payload['touched']) && Outbox::make()->hasContent($r->payload ?? [])));

        return view('livewire.hq.mail.compose.outbox', [
            'failed' => $rows->where('status', 'failed'),
            'scheduled' => $rows->where('status', 'queued'),
            'drafts' => $rows->where('status', 'draft'),
            'tz' => (string) config('mail_compose.timezone', 'Africa/Lagos'),
        ]);
    }

    private function row(string $id): MailOutbox
    {
        $row = MailOutbox::with('account')->findOrFail($id);
        abort_unless($row->user_id === Auth::id(), 403);

        return $row;
    }
}
