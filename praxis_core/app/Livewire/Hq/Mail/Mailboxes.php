<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAccountMember;
use App\Models\Mail\MailFolder;
use App\Models\User;
use App\Support\Mail\Autodiscover;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailConnector;
use App\Support\Mail\MailMembers;
use App\Support\Mail\MailNav;
use App\Support\Mail\MailSettings;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * HQ → Mail → Mailboxes (docs/MAIL.md, "Mailboxes"): connect your own mailbox; add shared mailboxes (with the
 * 'Shared mailboxes: Create' permission) and give people Read, Read and send or Manager access; new passwords;
 * rename and remove. Owner accounts also open Mail to everyone here (owner-only until then).
 *
 * Every connect and password change signs in to IMAP and SMTP before saving (MailConnector). Passwords are
 * cleared from the component as soon as they are used, whether it worked or not.
 */
#[Layout('components.layouts.hq')]
#[Title('Mailboxes')]
class Mailboxes extends Component
{
    /** Highlight this mailbox (links from notifications) */
    #[Url(as: 'box')]
    public ?string $box = null;

    // Connect your own mailbox
    public string $connectEmail = '';
    public string $connectPassword = '';
    public array $connectServer = [];
    public bool $connectShowServer = false;

    // Add a shared mailbox
    public string $sharedEmail = '';
    public string $sharedName = '';
    public string $sharedPassword = '';
    public array $sharedServer = [];
    public bool $sharedShowServer = false;

    /** Which dialog is open: add-shared, password, rename, people, remove, open-mail, close-mail */
    #[Locked]
    public ?string $modal = null;
    #[Locked]
    public ?string $modalAccountId = null;

    public string $newPassword = '';
    public bool $passwordShowServer = false;
    public array $passwordServer = [];
    public string $renameName = '';
    public string $inviteUser = '';
    public string $inviteLevel = 'send';
    public array $memberLevels = [];

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        abort_unless(MailAccess::canUse(Auth::user()), 403);
        $this->connectEmail = (string) Auth::user()->email;
        $this->connectServer = Autodiscover::for($this->connectEmail);
        $this->sharedServer = Autodiscover::for('x@jbspraxis.com');
    }

    /** Fill in the usual servers when the address changes */
    public function updatedConnectEmail(): void
    {
        $this->connectServer = Autodiscover::for($this->connectEmail);
    }

    public function updatedSharedEmail(): void
    {
        $this->sharedServer = Autodiscover::for($this->sharedEmail);
        if (trim($this->sharedName) === '' && str_contains($this->sharedEmail, '@')) {
            $local = ucwords(str_replace(['.', '_', '-'], ' ', strstr($this->sharedEmail, '@', true)));
            $this->sharedName = 'JBS Praxis ' . $local;
        }
    }

    public function connect(MailConnector $connector): void
    {
        $this->flash = null;
        $password = $this->connectPassword;
        $this->connectPassword = '';
        $this->validateFields(['connectEmail' => 'required|email|max:190'], ['connectEmail' => 'address']);
        if ($password === '') {
            $this->addError('connectPassword', 'Enter the mailbox password.');

            return;
        }

        try {
            $account = $connector->connectPersonal(Auth::user(), $this->connectEmail, $password, $this->connectServer);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['email' => 'connectEmail', 'password' => 'connectPassword', 'imap_host' => 'connectServer.imap_host', 'smtp_host' => 'connectServer.smtp_host']);

            return;
        }

        $this->syncFirst($account);
        $this->flash = "Connected {$account->email}. HQ is copying your mail now; the newest arrives first.";
    }

    public function addShared(MailConnector $connector): void
    {
        abort_unless(MailAccess::canAddShared(Auth::user()), 403);
        $this->flash = null;
        $password = $this->sharedPassword;
        $this->sharedPassword = '';
        $this->validateFields(
            ['sharedEmail' => 'required|email|max:190', 'sharedName' => 'required|string|max:120'],
            ['sharedEmail' => 'address', 'sharedName' => 'name'],
        );
        if ($password === '') {
            $this->addError('sharedPassword', 'Enter the mailbox password.');

            return;
        }

        try {
            $account = $connector->addShared(Auth::user(), $this->sharedEmail, $password, $this->sharedServer, $this->sharedName);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['email' => 'sharedEmail', 'password' => 'sharedPassword', 'imap_host' => 'sharedServer.imap_host', 'smtp_host' => 'sharedServer.smtp_host', 'display_name' => 'sharedName']);

            return;
        }

        $this->reset('sharedEmail', 'sharedName', 'sharedShowServer');
        $this->sharedServer = Autodiscover::for('x@jbspraxis.com');
        $this->syncFirst($account);
        $this->box = $account->id;
        $this->openModal('people', $account->id);
        $this->flash = "Added {$account->email}. You are its manager: choose who can read and send from it.";
    }

    public function openModal(string $modal, ?string $accountId = null): void
    {
        $this->resetErrorBag();
        $this->newPassword = '';
        $this->passwordShowServer = false;

        if (in_array($modal, ['open-mail', 'close-mail'], true)) {
            abort_unless(MailAccess::isOwner(Auth::user()), 403);
            $this->modal = $modal;
            $this->modalAccountId = null;

            return;
        }
        if ($modal === 'add-shared') {
            abort_unless(MailAccess::canAddShared(Auth::user()), 403);
            $this->modal = $modal;
            $this->modalAccountId = null;

            return;
        }

        $account = $this->account($accountId, 'manage');
        $this->modal = $modal;
        $this->modalAccountId = $account->id;
        $this->renameName = (string) $account->display_name;
        $this->passwordServer = \App\Support\Mail\MailServer::settingsOf($account);
        $this->inviteUser = '';
        $this->inviteLevel = 'send';
        $this->memberLevels = MailAccountMember::where('account_id', $account->id)->pluck('level', 'id')->mapWithKeys(fn ($l, $id) => [(string) $id => $l])->all();
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->modalAccountId = null;
        $this->newPassword = '';
        $this->resetErrorBag();
    }

    public function savePassword(MailConnector $connector): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        $password = $this->newPassword;
        $this->newPassword = '';
        if ($password === '') {
            $this->addError('newPassword', 'Enter the new password.');

            return;
        }

        try {
            $connector->updatePassword(Auth::user(), $account, $password, $this->passwordShowServer ? $this->passwordServer : null);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['password' => 'newPassword', 'imap_host' => 'passwordServer.imap_host', 'smtp_host' => 'passwordServer.smtp_host']);

            return;
        }

        $this->closeModal();
        $this->syncFirst($account->fresh());
        $this->flash = "Password updated for {$account->email}. Mail is updating again.";
    }

    public function saveName(MailConnector $connector): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        try {
            $connector->rename(Auth::user(), $account, $this->renameName);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['display_name' => 'renameName']);

            return;
        }
        $this->closeModal();
        $this->flash = "Renamed: mail from {$account->email} now shows \"{$account->fresh()->display_name}\".";
    }

    public function invite(): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        $person = User::find($this->inviteUser);
        if (! $person) {
            $this->addError('inviteUser', 'Choose a person.');

            return;
        }
        try {
            MailMembers::add(Auth::user(), $account, $person, $this->inviteLevel);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['invite_user' => 'inviteUser', 'invite_level' => 'inviteLevel']);

            return;
        }
        MailNav::forget($person->id);
        $this->inviteUser = '';
        $this->memberLevels = MailAccountMember::where('account_id', $account->id)->pluck('level', 'id')->mapWithKeys(fn ($l, $id) => [(string) $id => $l])->all();
        $this->flash = "{$person->name} can now use {$account->email}. They have been told in HQ.";
    }

    public function updatedMemberLevels(string $level, string $memberId): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        $member = MailAccountMember::where('account_id', $account->id)->findOrFail((int) $memberId);
        try {
            MailMembers::change(Auth::user(), $account, $member, $level);
            $this->flash = ($member->user?->name ?? 'Their') . ' access is now ' . MailAccountMember::LEVEL_LABELS[$level] . '.';
        } catch (ValidationException $e) {
            $this->memberLevels[$memberId] = $member->level;
            $this->showErrors($e, ['members' => 'members', 'invite_level' => 'members']);
        }
    }

    public function removeMember(int $memberId): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        $member = MailAccountMember::where('account_id', $account->id)->findOrFail($memberId);
        try {
            MailMembers::remove(Auth::user(), $account, $member);
        } catch (ValidationException $e) {
            $this->showErrors($e, ['members' => 'members']);

            return;
        }
        MailNav::forget($member->user_id);
        unset($this->memberLevels[(string) $memberId]);
        $this->flash = ($member->user?->name ?? 'That person') . " no longer has access to {$account->email}.";
    }

    public function removeMailbox(MailConnector $connector): void
    {
        $account = $this->account($this->modalAccountId, 'manage');
        $email = $account->email;
        $shared = $account->isShared();
        $connector->remove(Auth::user(), $account);
        $this->closeModal();
        MailNav::forget(Auth::id());
        $this->flash = $shared ? "Removed {$email} from HQ. Its mail is still on the mail server." : "Disconnected {$email}. Its mail is still on the mail server.";
    }

    public function refreshNow(string $accountId): void
    {
        $account = $this->account($accountId, 'read');
        $result = MailSync::make()->account($account, 20, true);
        MailNav::forget(Auth::id());
        $this->flash = match ($result['status']) {
            'busy' => "{$account->email} is updating already.",
            'error' => "{$account->email} could not be updated: " . ($result['error'] ?? 'the mail server did not answer.'),
            'skipped' => "{$account->email} needs its password before it can update.",
            default => "{$account->email} is up to date" . ($result['added'] ? " ({$result['added']} new)." : '.'),
        };
    }

    public function setOpenTo(string $who): void
    {
        abort_unless(MailAccess::isOwner(Auth::user()) && in_array($who, ['owner', 'everyone'], true), 403);
        MailSettings::set('open_to', $who, Auth::id());
        \Illuminate\Support\Facades\DB::table('system_audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $who === 'everyone' ? 'MAIL_OPENED_TO_EVERYONE' : 'MAIL_LIMITED_TO_OWNERS',
            'description' => $who === 'everyone' ? 'Opened HQ Mail to everyone with the Mail permission.' : 'Limited HQ Mail to Owner accounts.',
            'target_table' => 'mail_settings',
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
        MailAccess::flush();
        $this->closeModal();
        $this->flash = $who === 'everyone'
            ? 'Mail is open: everyone whose role has the Mail permission now sees Mail in the menu.'
            : 'Mail is limited to Owner accounts again.';
    }

    public function render()
    {
        $user = Auth::user();
        // This screen changes mailboxes (rename, password, people, remove): always the list as it is now
        MailAccess::flush();
        $accounts = MailAccess::accounts($user);
        $personal = $accounts->first(fn (MailAccount $a) => ! $a->isShared());
        $shared = $accounts->filter(fn (MailAccount $a) => $a->isShared())->values();

        $modalAccount = $this->modalAccountId ? $accounts->firstWhere('id', $this->modalAccountId) : null;
        $members = $modalAccount && $modalAccount->isShared()
            ? MailAccountMember::with('user:id,first_name,last_name,email,avatar_path')->where('account_id', $modalAccount->id)->get()
                ->sortBy(fn ($m) => (3 - MailAccess::RANK[$m->level]) . ($m->user?->name ?? ''))->values()
            : collect();

        return view('livewire.hq.mail.mailboxes', [
            'personal' => $personal,
            'shared' => $shared,
            'states' => $accounts->mapWithKeys(fn (MailAccount $a) => [$a->id => self::state($a)])->all(),
            'canAddShared' => MailAccess::canAddShared($user),
            'isOwner' => MailAccess::isOwner($user),
            'openToEveryone' => MailAccess::isOpenToEveryone(),
            'modalAccount' => $modalAccount,
            'members' => $members,
            'invitable' => $modalAccount && $this->modal === 'people' ? MailMembers::invitable($modalAccount) : collect(),
            'levels' => MailAccountMember::LEVEL_LABELS,
        ]);
    }

    /**
     * How a mailbox is doing, for its status pill: tone (success, info, warning, danger), label and a line of detail.
     */
    public static function state(MailAccount $account): array
    {
        if ($account->status === 'needs_reconnect') {
            return ['tone' => 'danger', 'label' => 'Needs password', 'detail' => 'The mail server refused the saved password. Enter the current one to start updating again.'];
        }
        if ($account->status === 'paused') {
            return ['tone' => 'neutral', 'label' => 'Paused', 'detail' => 'Not updating.'];
        }

        $inbox = MailFolder::where('account_id', $account->id)->where('role', 'inbox')->first();
        $updated = $account->last_synced_at ? 'Updated ' . $account->last_synced_at->diffForHumans() . '.' : 'Not updated yet.';

        if ($account->last_error && (! $account->last_synced_at || $account->updated_at?->gt($account->last_synced_at))) {
            return ['tone' => 'warning', 'label' => 'Can’t reach the server', 'detail' => $updated . ' The last try failed; HQ tries again every minute.'];
        }
        if (! $inbox || ! $inbox->initial_synced_at) {
            $copied = $inbox ? $inbox->total_count : 0;

            return ['tone' => 'info', 'label' => 'Copying mail', 'detail' => $copied > 0 ? number_format($copied) . ' Inbox messages copied so far.' : 'The first copy has started; the newest mail arrives first.'];
        }

        $total = (int) MailFolder::where('account_id', $account->id)->sum('total_count');

        return ['tone' => 'success', 'label' => 'Up to date', 'detail' => $updated . ' ' . number_format($total) . ' messages from the last 12 months in HQ.'];
    }

    private function syncFirst(MailAccount $account): void
    {
        // A short first run so the person sees mail straight away; the scheduler carries on
        rescue(fn () => MailSync::make()->account($account, 12), null, false);
        MailNav::forget(Auth::id());
    }

    private function account(?string $id, string $ability): MailAccount
    {
        $account = $id ? MailAccount::find($id) : null;
        abort_unless($account && MailAccess::can(Auth::user(), $account, $ability), 403);

        return $account;
    }

    private function validateFields(array $rules, array $attributes): void
    {
        $this->validate($rules, [], $attributes);
    }

    private function showErrors(ValidationException $e, array $map): void
    {
        foreach ($e->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError($map[$key] ?? $key, $message);
            }
        }
    }
}
