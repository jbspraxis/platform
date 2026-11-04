<?php

namespace App\Support\CompanyDocuments;

use App\Models\AiRun;
use App\Models\CompanyDocument;
use App\Models\CompanyDocumentVersion;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Import\Xlsx\Zip;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Claude reads an uploaded certificate (docs/COMPANY_DOCUMENTS.md, purpose company_document_read on the 'ai' queue):
 * a PDF goes as a document block, a JPG or PNG as an image block (the server has no PDF tools and needs none), a Word
 * file as its text. What Claude copies only ever becomes a suggestion (Suggestions); when it can't read the file, the
 * version says why (read_error) and the person types the details instead.
 */
final class Reader
{
    public const PURPOSE = 'company_document_read';

    /** Claude takes images up to 5 MB */
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public static function reads(CompanyDocument $doc): bool
    {
        return in_array($doc->kind, Documents::READABLE, true);
    }

    /** Asks Claude to read this version (again); a kind it doesn't read is left alone */
    public static function start(CompanyDocumentVersion $version): void
    {
        $doc = $version->document;
        if (! $doc || ! self::reads($doc)) {
            return;
        }
        $version->forceFill(['ai_run_id' => null, 'read_error' => null])->save();

        try {
            $run = Ai::queue(self::PURPOSE, $version, self::input($version, $doc));
            $version->forceFill(['ai_run_id' => $run->id])->save();
        } catch (AiUnavailable $e) {
            $version->forceFill(['read_error' => $e->key()])->save();
        } catch (CannotRead $e) {
            $version->forceFill(['read_error' => $e->getMessage()])->save();
        }
    }

    /**
     * Where the reading stands: none (a kind Claude doesn't read, or never asked), reading, read (with Claude's
     * output) or failed (with what to tell the person).
     *
     * @return array{state: string, output: ?array, message: ?string, progress: int}
     */
    public static function state(CompanyDocumentVersion $version): array
    {
        if ($version->read_error) {
            return ['state' => 'failed', 'output' => null, 'message' => __($version->read_error), 'progress' => 0];
        }
        $run = $version->ai_run_id ? AiRun::find($version->ai_run_id) : null;
        if (! $run) {
            return ['state' => 'none', 'output' => null, 'message' => null, 'progress' => 0];
        }

        return match ($run->status) {
            'done' => ['state' => 'read', 'output' => (array) $run->output, 'message' => null, 'progress' => 100],
            'failed' => ['state' => 'failed', 'output' => null, 'message' => $run->errorMessage(), 'progress' => 0],
            default => ['state' => 'reading', 'output' => null, 'message' => null, 'progress' => (int) $run->progress],
        };
    }

    /** The words of a Word file (.docx), paragraph by paragraph; '' when it has none or isn't one */
    public static function docxText(string $bytes): string
    {
        try {
            $xml = Zip::open($bytes)->get('word/document.xml');
        } catch (Throwable) {
            return '';
        }
        if (! $xml) {
            return '';
        }
        $xml = preg_replace(['#</w:p>#', '#<w:tab/>#', '#<w:br/>#'], ["\n", "\t", "\n"], $xml);

        return trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8')));
    }

    private static function input(CompanyDocumentVersion $version, CompanyDocument $doc): array
    {
        $entity = Companies::entity($doc->legal_entity_id);
        $country = ['NG' => 'Nigeria', 'CM' => 'Cameroon', 'KE' => 'Kenya'][$entity->region_code ?? ''] ?? 'not stated';
        $material = 'KIND: ' . Documents::KINDS[$doc->kind] . "\nCOUNTRY: {$country}\nTODAY: " . Documents::today();

        if ($version->mime === Documents::TYPES['docx']) {
            $text = self::docxText((string) Storage::disk('local')->get($version->path));
            if ($text === '') {
                throw new CannotRead('company_documents.read.no_text');
            }

            return ['material' => $material . "\n\nTHE DOCUMENT (the text of a Word file):\n" . Str::limit($text, 60000)];
        }
        if (str_starts_with($version->mime, 'image/') && $version->size > self::MAX_IMAGE_BYTES) {
            throw new CannotRead('company_documents.read.image_too_big');
        }

        return ['material' => $material, 'files' => [['path' => $version->path, 'media_type' => $version->mime]]];
    }
}
