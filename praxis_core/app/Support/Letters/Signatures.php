<?php

namespace App\Support\Letters;

use App\Models\Letters\LetterSignature;
use App\Models\User;
use RuntimeException;

/**
 * A person's stored signature (docs/DOCUMENTS.md section 5). Drawn with a finger or a mouse, or a photograph of a
 * signature on paper; either way the browser hands over a JPEG on white, because dompdf can embed a JPEG without
 * the gd extension, which the live PHP has not got, and cannot embed a PNG at all.
 *
 * The image is kept in letter_signatures.image with Laravel's encrypted cast, so it is never readable in the
 * database and never written to a public path. Only its owner can save it, and only its owner can apply it.
 */
final class Signatures
{
    /** 2 MB of base64 is a generous photograph of a signature */
    public const MAX_BYTES = 2_000_000;

    public static function forUser(User $user): ?LetterSignature
    {
        return LetterSignature::where('user_id', $user->id)->first();
    }

    /**
     * Save (or replace) this person's signature.
     *
     * @param  string  $dataUri  data:image/jpeg;base64,… exactly as the browser made it
     */
    public static function save(User $user, string $dataUri, string $name, string $title, string $kind, ?int $width = null, ?int $height = null): LetterSignature
    {
        $base64 = self::readJpeg($dataUri);

        $signature = LetterSignature::updateOrCreate(['user_id' => $user->id], [
            'signatory_name' => trim($name) !== '' ? trim($name) : trim($user->first_name . ' ' . $user->last_name),
            'signatory_title' => trim($title) !== '' ? trim($title) : (string) $user->job_title,
            'kind' => in_array($kind, ['drawn', 'uploaded'], true) ? $kind : 'drawn',
            'image' => $base64,
            'width' => $width,
            'height' => $height,
        ]);

        Letters::audit('SIGNATURE_SAVED', 'Saved a signature for ' . $signature->signatory_name);

        return $signature;
    }

    public static function forget(User $user): void
    {
        $signature = self::forUser($user);
        if ($signature) {
            $signature->delete();
            Letters::audit('SIGNATURE_REMOVED', 'Removed the stored signature of ' . $signature->signatory_name);
        }
    }

    /** The base64 of a JPEG, or an error saying why the image was refused */
    public static function readJpeg(string $dataUri): string
    {
        if (! preg_match('~^data:image/jpe?g;base64,([A-Za-z0-9+/=\s]+)$~', trim($dataUri), $match)) {
            throw new RuntimeException('The signature must be a JPEG image.');
        }
        $base64 = preg_replace('/\s+/', '', $match[1]);
        $bytes = base64_decode($base64, true);
        if ($bytes === false || strlen($bytes) < 100) {
            throw new RuntimeException('The signature image could not be read.');
        }
        if (strlen($base64) > self::MAX_BYTES) {
            throw new RuntimeException('The signature image is too large.');
        }
        // A JPEG always starts with the SOI marker, whatever the browser called it
        if (substr($bytes, 0, 2) !== "\xFF\xD8") {
            throw new RuntimeException('The signature must be a JPEG image.');
        }

        return $base64;
    }
}
