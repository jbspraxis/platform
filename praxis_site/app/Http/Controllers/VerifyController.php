<?php

namespace App\Http\Controllers;

use App\Support\Format;
use App\Exceptions\Hq\HqNotFound;
use App\Support\HqApi;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * /verify/{token} (and /fr/verifier/{token}): the page a QR code on a formal document opens
 * (docs/DOCUMENTS.md section 7). It says whether the document is one JBS Praxis issued, what it is, who
 * signed it and whether it still stands.
 *
 * The tamper check is done in the visitor's own browser: the file they drop on the page is hashed there
 * with SHA-256 and compared with the fingerprint of the file that was issued, so their copy never leaves
 * their device and never reaches us.
 */
class VerifyController extends Controller
{
    public function show(string $token, HqApi $api, Request $request)
    {
        try {
            $document = $api->document($token);
        } catch (HqNotFound) {
            // A token nobody was given answers in the same words as a document that was withdrawn for good
            return response()->view('pages.verify', ['token' => $token, 'document' => null], 404);
        }

        return view('pages.verify', [
            'token' => $token,
            'document' => $document,
            'issuedAt' => $this->day($document['issued_at'] ?? null),
            'documentDate' => $this->day($document['document_date'] ?? null),
            'revokedAt' => $this->day($document['revoked_at'] ?? null),
            'replacedAt' => $this->day($document['replaced_at'] ?? null),
        ]);
    }

    /** The issued PDF, passed through from HQ so the visitor never needs an address on hq.jbspraxis.com */
    public function file(string $token, HqApi $api): Response
    {
        $pdf = $api->documentFile($token);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $token . '.pdf"',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** "4 November 2026" / « 4 novembre 2026 », in the page's own language */
    private function day(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(
            fn () => Carbon::parse($value)->timezone('Africa/Lagos')->locale(app()->getLocale())->translatedFormat('j F Y'),
            null,
            false
        );
    }
}
