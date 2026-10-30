<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Support\Mail\Compose\Signatures;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * HQ → Mail → Signature: each person's signature as it sends (one company design built from HR records, owner
 * decision 5), their own numbers, and the company settings for those who may change them (Shared mailboxes:
 * Create, i.e. Owner and Partner). Name, job title and department come from HR and are changed there.
 */
#[Layout('components.layouts.hq')]
#[Title('Signature')]
class Signature extends Component
{
    public string $phone = '';
    public string $mobile = '';

    public string $previewAccountId = '';
    public string $previewStyle = 'full';

    public array $company = [];

    #[Locked]
    public ?string $flash = null;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(MailAccess::canUse($user), 403);
        $numbers = Signatures::numbers($user);
        $this->phone = (string) $numbers['phone'];
        $this->mobile = (string) $numbers['mobile'];
        $this->previewAccountId = (string) (MailAccess::accounts($user, 'send')->first()->id ?? '');
        if ($this->canEditCompany()) {
            $this->company = array_map(fn ($v) => is_bool($v) ? $v : (string) $v, Signatures::company());
        }
    }

    public function saveNumbers(): void
    {
        $this->resetErrorBag();
        Signatures::setNumbers(Auth::user(), $this->phone, $this->mobile);
        $this->flash = 'Your numbers are saved. Your next messages carry them.';
    }

    public function saveCompany(): void
    {
        abort_unless($this->canEditCompany(), 403);
        $this->resetErrorBag();
        try {
            $saved = Signatures::setCompany($this->company, Auth::user());
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['company.' . $k => $m])->all());
        }
        $this->company = array_map(fn ($v) => is_bool($v) ? $v : (string) $v, $saved);
        $this->flash = 'The company details are saved. Everyone’s next messages carry them.';
    }

    public function render()
    {
        $user = Auth::user();
        $accounts = MailAccess::accounts($user, 'send');
        $account = $accounts->firstWhere('id', $this->previewAccountId) ?? $accounts->first()
            ?? new MailAccount(['email' => strtolower((string) $user->email), 'display_name' => $user->name, 'kind' => 'personal']);
        $style = array_key_exists($this->previewStyle, Signatures::STYLES) && $this->previewStyle !== 'none' ? $this->previewStyle : 'full';

        return view('livewire.hq.mail.signature', [
            'person' => Signatures::person($user),
            'hrPhone' => $user->phone,
            'accounts' => $accounts,
            'previewHtml' => Signatures::html($user, $account, $style),
            'previewText' => Signatures::text($user, $account, $style),
            'previewShared' => $account->isShared(),
            'canEditCompany' => $this->canEditCompany(),
        ]);
    }

    private function canEditCompany(): bool
    {
        return MailAccess::canAddShared(Auth::user());
    }
}
