<?php

namespace App\Support\Mail\Read;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Senders whose remote pictures a person always loads ("Always for this sender"). Per person. */
final class ImageTrust
{
    /** @param  list<string>  $senders  @return list<string> those trusted, lower case */
    public static function trusted(User $user, array $senders): array
    {
        $senders = array_values(array_unique(array_filter(array_map(fn ($s) => strtolower(trim((string) $s)), $senders))));

        return $senders ? DB::table('mail_image_trust')->where('user_id', $user->id)->whereIn('sender', $senders)->pluck('sender')->all() : [];
    }

    public static function trust(User $user, string $sender): void
    {
        $sender = strtolower(trim($sender));
        if ($sender !== '' && filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            DB::table('mail_image_trust')->insertOrIgnore(['user_id' => $user->id, 'sender' => $sender, 'created_at' => now()]);
        }
    }
}
