<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A browser or installed app that receives push notifications (docs/PWA.md): for one staff member
 * (user_id, HQ) or one client portal account (client_user_id, Client Portal, in its locale).
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'client_user_id',
        'locale',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'content_encoding',
        'device',
        'standalone',
        'user_agent',
        'last_seen_at',
        'last_sent_at',
        'failures',
    ];

    // The address and keys let anyone push to the device: never in JSON or Livewire state
    protected $hidden = ['endpoint', 'public_key', 'auth_token'];

    protected function casts(): array
    {
        return [
            'standalone' => 'boolean',
            'last_seen_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'failures' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}
