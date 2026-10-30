<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailThread;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ → Mail → Inbox. PR 2 owns this file and its view and replaces this first version (a plain list that
 * shows the copy is working). Keep the URL parameters (box, folder, thread: notifications link to them) and
 * the mount points in the view for PR 3 and PR 4 (docs/MAIL.md, "Mount points").
 */
#[Layout('components.layouts.hq')]
#[Title('Mail')]
class Inbox extends Component
{
    #[Url(as: 'box')]
    public ?string $box = null;

    #[Url(as: 'folder')]
    public ?string $folder = null;

    #[Url(as: 'thread')]
    public ?int $thread = null;

    public function mount(): void
    {
        abort_unless(MailAccess::canUse(Auth::user()), 403);
    }

    public function render()
    {
        $accounts = MailAccess::accounts(Auth::user());
        $account = $accounts->firstWhere('id', $this->box) ?? $accounts->first();
        $folder = null;
        $threads = collect();

        if ($account) {
            $this->box = $account->id;
            $role = $this->folder && ! ctype_digit((string) $this->folder) ? $this->folder : 'inbox';
            $folder = ctype_digit((string) $this->folder)
                ? MailFolder::where('account_id', $account->id)->find((int) $this->folder)
                : $account->folder($role);

            if ($folder) {
                $threads = MailThread::where('account_id', $account->id)
                    ->whereIn('id', $folder->messages()->select('thread_id'))
                    ->orderByDesc('last_message_at')
                    ->limit(50)->get();
            }
        }

        return view('livewire.hq.mail.inbox', [
            'accounts' => $accounts,
            'account' => $account,
            'currentFolder' => $folder,
            'threads' => $threads,
        ]);
    }
}
