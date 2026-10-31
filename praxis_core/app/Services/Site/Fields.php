<?php

namespace App\Services\Site;

/**
 * Field helpers shared by the site services. The services name fields as the site API does (snake_case JSON).
 * (The old Livewire pages, which used their own property names, were removed from HQ with their renaming helpers.)
 */
final class Fields
{
    /**
     * A typed full name as client_users' first_name and last_name (50 characters each): the first word, then
     * the rest ("Unknown" when there is none). A long name is shortened instead of failing the form (a 500).
     *
     * @return array{0: string, 1: string}
     */
    public static function nameParts(?string $fullName): array
    {
        $parts = explode(' ', trim((string) $fullName), 2);
        $last = trim($parts[1] ?? '');

        return [mb_substr($parts[0], 0, 50), $last === '' ? 'Unknown' : mb_substr($last, 0, 50)];
    }
}
