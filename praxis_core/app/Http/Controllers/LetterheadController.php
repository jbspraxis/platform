<?php

namespace App\Http\Controllers;

use App\Models\Letters\LetterDocument;
use App\Support\Letters\Agreements;
use App\Support\Letters\DocxLetterhead;
use App\Support\Letters\Letterhead;
use App\Support\Letters\LetterPdf;
use App\Support\Letters\Letters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The files HQ › Documents hands over (docs/DOCUMENTS.md): a document's PDF, and the blank letterhead as a PDF
 * or a Word document. Every one of them checks letters.view first; the routes already sit behind the HQ host's
 * signed-in staff group.
 */
class LetterheadController extends Controller
{
    /**
     * An issued document's PDF is the file that was stored when it was issued, byte for byte, because that is
     * the file the reader's own copy is checked against. A draft is drawn from its fields (and kept until they
     * change, LetterPdf::render). The signatures, with their images, are read only when the page is drawn.
     */
    public function documentPdf(Request $request, string $document): Response
    {
        Gate::authorize('letters.view');
        $record = LetterDocument::findOrFail($document);

        // A signed agreement has two files: the one that was issued, which never changes, and the countersigned
        // version. The countersigned one is what the screen shows; ?issued=1 asks for the first one instead.
        $signed = ! $request->boolean('issued') ? Agreements::signedFile($record) : null;
        $pdf = $signed ?? ($record->isDraft() ? LetterPdf::render($record) : (Letters::file($record) ?? LetterPdf::render($record, final: true)));
        $name = $signed ? Agreements::signedFilename($record) : Letters::filename($record);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                . '; filename="' . $name . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The agreement as the other party reads it, from the signing page (docs/DOCUMENTS.md section 12). Nobody is
     * signed in: the link's token is the key, and it stops working when the link does. Once everybody has signed,
     * the same address hands over the signed version, so their own copy is always the one that counts.
     */
    public function agreementPdf(string $token): Response
    {
        $party = Agreements::findByToken($token);
        abort_unless($party && $party->token_expires_at, 404);
        abort_if($party->token_expires_at->isPast() && ! $party->hasSigned(), 410, 'This link has run out.');

        // Only stored files are handed over here, so nothing is drawn and no signature image is read
        $record = LetterDocument::findOrFail($party->document_id);
        abort_unless($record->isIssued(), 404);

        $signed = Agreements::signedFile($record);
        $pdf = $signed ?? Letters::file($record);
        abort_if($pdf === null, 404);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($signed ? Agreements::signedFilename($record) : Letters::filename($record)) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function blankPdf(Request $request, string $entity): Response
    {
        Gate::authorize('letters.view');
        $company = Letterhead::company($entity);
        abort_if($company === null, 404);

        return response(LetterPdf::blank($company), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                . '; filename="' . preg_replace('/[^A-Za-z0-9 ]+/', '', (string) $company['legal_name']) . ' letterhead.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function blankDocx(string $entity): Response
    {
        Gate::authorize('letters.view');
        $company = Letterhead::company($entity);
        abort_if($company === null, 404);

        return response(DocxLetterhead::build($company), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . DocxLetterhead::filename($company) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
