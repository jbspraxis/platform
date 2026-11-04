<?php

namespace App\Mail;

use DateTimeInterface;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * The key vault's two emails to the owner (docs/KEY_VAULT.md section 3), internal and English only (send with
 * ->locale('en')): the one-time code that opens an item, and the notice that an item was revealed. Neither ever
 * carries any part of a secret; the code is in the body only, never in the subject or the preheader.
 */
class KeyVaultMail extends BrandedMail
{
    public $kind;

    public $data;

    public function __construct(string $kind, array $data)
    {
        $this->kind = $kind === 'revealed' ? 'revealed' : 'code';
        $this->data = $data;
    }

    public static function code(string $code, string $item, ?string $firstName, int $minutes = 10): static
    {
        return new static('code', ['code' => $code, 'item' => $item, 'first_name' => $firstName, 'minutes' => $minutes]);
    }

    public static function revealed(string $item, ?string $firstName, DateTimeInterface $at, ?string $ip, ?string $device): static
    {
        return new static('revealed', ['item' => $item, 'first_name' => $firstName, 'at' => Carbon::instance($at)->utc()->toIso8601String(), 'ip' => $ip, 'device' => $device]);
    }

    protected function subjectLine(): string
    {
        return __('emails.key_vault.' . $this->kind . '_subject', ['minutes' => $this->data['minutes'] ?? 10, 'item' => $this->data['item']]);
    }

    public function content(): Content
    {
        $when = Carbon::parse($this->data['at'] ?? now())->setTimezone('Africa/Lagos')->locale('en')->isoFormat('dddd D MMMM YYYY, HH:mm') . ' (Lagos time)';

        return new Content(view: 'emails.key-vault', with: [
            'kind' => $this->kind,
            'item' => $this->data['item'],
            'firstName' => $this->data['first_name'] ?? null,
            'code' => $this->data['code'] ?? null,
            'minutes' => $this->data['minutes'] ?? 10,
            'facts' => $this->kind === 'revealed' ? [
                __('emails.key_vault.fact_item') => $this->data['item'],
                __('emails.key_vault.fact_when') => $when,
                __('emails.key_vault.fact_device') => $this->data['device'] ?? null,
                __('emails.key_vault.fact_ip') => $this->data['ip'] ?? null,
            ] : [],
            'vaultUrl' => route('hq.system.key-vault'),
        ]);
    }

    /** Only the owner's own messages: replies go nowhere useful, so they come back to the team inbox as usual */
    public static function locales(): array
    {
        return ['en'];
    }

    public static function samples(): array
    {
        return [
            'code' => static::code('590312', 'Server backup private key (demo)', 'Ada'),
            'revealed' => static::revealed('Server backup private key (demo)', 'Ada', Carbon::parse('2026-11-05 09:41:00', 'UTC'), '102.89.33.10', 'Windows · Edge'),
        ];
    }
}
