<?php

namespace App\Http\Controllers;

use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Support\CompanyDocuments\Documents;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Serves company documents from the private disk (Master data › Company documents, docs/COMPANY_DOCUMENTS.md), as
 * SecureFileController does for other uploads. The route needs mdm.view; every view and download is an audit line.
 * PDFs and images open in the browser (view); anything else, and every download, comes as an attachment.
 */
class CompanyDocumentController extends Controller
{
    public function show(Request $request, CompanyDocument $document, CompanyDocumentVersion $version, string $mode)
    {
        abort_unless($request->user()?->can('mdm.view'), 403);
        abort_unless($version->document_id === $document->id, 404);
        abort_unless(Storage::disk('local')->exists($version->path), 404);

        $download = $mode === 'download' || ! $version->opensInline();
        Documents::logAccess($document, $version, $download, $request->user()->id);

        $ext = array_search($version->mime, Documents::TYPES, true) ?: 'bin';
        $name = Str::limit(trim(preg_replace('/[^\pL\pN ()._-]+/u', ' ', $document->title)), 120, '') . " (version {$version->number}).{$ext}";
        $headers = ['Content-Type' => $version->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $download
            ? Storage::disk('local')->download($version->path, $name, $headers)
            : Storage::disk('local')->response($version->path, $name, $headers, 'inline');
    }
}
