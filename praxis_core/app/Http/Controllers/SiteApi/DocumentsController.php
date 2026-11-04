<?php

namespace App\Http\Controllers\SiteApi;

use App\Support\Letters\Letters;
use App\Support\Letters\Verification;
use Illuminate\Http\Response;

/**
 * What jbspraxis.com's /verify/{token} page reads (docs/DOCUMENTS.md section 7, docs/SITE_API.md). Two routes:
 * the document's own details, and — only when the document says its copy may be shown — the issued PDF itself.
 *
 * Nothing here needs anyone to be signed in, so it gives only what the document already says in print. An
 * unknown token is 404 with nothing else, so nobody can tell a wrong token from a document that was deleted.
 */
class DocumentsController extends SiteApiController
{
    public function show(string $token)
    {
        $document = Verification::find($token);
        if (! $document) {
            return $this->error(404, ['code' => 'not_found']);
        }
        if (Verification::tooMany($token)) {
            return $this->error(429, ['code' => 'too_many', 'retry_after' => 300]);
        }

        return $this->data(Verification::payload($document), 200, 60);
    }

    /** The issued PDF, byte for byte, so the reader can compare it with their own copy */
    public function file(string $token)
    {
        $document = Verification::find($token);
        if (! $document || ! $document->copy_public) {
            return $this->error(404, ['code' => 'not_found']);
        }
        if (Verification::tooMany($token)) {
            return $this->error(429, ['code' => 'too_many', 'retry_after' => 300]);
        }

        $pdf = Letters::file($document);
        if ($pdf === null) {
            return $this->error(404, ['code' => 'not_found']);
        }

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . Letters::filename($document) . '"',
            'Cache-Control' => 'max-age=300',
        ]);
    }
}
