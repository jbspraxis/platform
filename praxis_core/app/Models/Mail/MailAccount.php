<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A mailbox connected to HQ (docs/MAIL.md): a person's own (kind personal, owner_user_id = that person) or a
 * shared one like intelligence@ (kind shared, people listed in mail_account_members). The password is in
 * `secret`, encrypted; read it only through App\Support\Mail\MailCredentials.
 */
class MailAccount extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'folders_synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function isShared(): bool
    {
        return $this->kind === 'shared';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MailAccountMember::class, 'account_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(MailFolder::class, 'account_id');
    }

    public function threads(): HasMany
    {
        return $this->hasMany(MailThread::class, 'account_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'account_id');
    }

    /** The folder with this role (inbox, sent, drafts, archive, spam, trash), if the mailbox has one */
    public function folder(string $role): ?MailFolder
    {
        return $this->folders()->where('role', $role)->where('selectable', true)->orderBy('id')->first();
    }

    /** "JBS Praxis Intelligence" <intelligence@jbspraxis.com> */
    public function fromLine(): string
    {
        $name = trim(str_replace(['"', '\\', "\r", "\n"], '', (string) $this->display_name));

        return $name !== '' ? "\"{$name}\" <{$this->email}>" : $this->email;
    }
}
