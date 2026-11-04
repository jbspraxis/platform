<?php

namespace App\Http\Controllers;

use App\Models\Letters\LetterDocument;
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
     * the file the reader's own copy is checked against. A draft is rendered afresh each time it is asked for.
     */
    public function documentPdf(Request $request, string $document): Response
    {
        Gate::authorize('letters.view');
        $record = LetterDocument::with('signatures')->findOrFail($document);

        $pdf = $record->isDraft() ? LetterPdf::render($record) : (Letters::file($record) ?? LetterPdf::render($record, final: true));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                . '; filename="' . Letters::filename($record) . '"',
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
