<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailThread;
use App\Support\Mail\Ai\Gemini;
use App\Support\Mail\Ai\ThreadSummariser;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Summarise with Praxis AI: a button and the summary card above a conversation. Mounted in the reader with :thread-id.
 * Only on the person's press, by Gemini, never in Spam; the summary is kept on the conversation for everyone
 * who reads it until new mail arrives (docs/MAIL.md 7.6, ThreadSummariser).
 */
class ThreadSummary extends Component
{
    public ?int $threadId = null;

    public ?string $error = null;

    public bool $collapsed = false;

    public function summarise(): void
    {
        $thread = $this->thread();
        abort_unless($thread, 403);
        $this->error = null;

        $result = ThreadSummariser::summarise(Auth::user(), $thread);
        if (! $result['ok']) {
            $this->error = ThreadSummariser::ERRORS[$result['error']] ?? ThreadSummariser::ERRORS['unavailable'];
        }
        $this->collapsed = false;
    }

    public function toggle(): void
    {
        $this->collapsed = ! $this->collapsed;
    }

    public function render()
    {
        $thread = $this->thread();
        $show = $thread && Gemini::configured() && ! ThreadSummariser::isSpam($thread);

        return view('livewire.hq.mail.thread-summary', [
            'thread' => $show ? $thread : null,
            'summary' => $show ? $thread->ai_summary : null,
        ]);
    }

    private function thread(): ?MailThread
    {
        if (! $this->threadId) {
            return null;
        }
        $thread = MailThread::with('account')->find($this->threadId);

        return $thread && MailAccess::canThread(Auth::user(), $thread, 'read') ? $thread : null;
    }
}
