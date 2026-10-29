<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HqStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Serves uploads kept on the private disk (storage/app/private).
 *
 * Staff see cash-request receipts and QA evidence; clearance 4+ also sees
 * client payment proofs and candidate CVs. A client sees only QA evidence
 * attached to tickets on their own organisation's projects.
 */
class SecureFileController extends Controller
{
    private const STAFF = ['receipts', 'qa_evidence'];
    private const SENIOR = ['pop_receipts', 'pop_receipts_wht', 'vault'];

    public function show(Request $request, string $path)
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $folder = explode('/', $path)[0];

        if (str_contains($path, '..') || ! in_array($folder, array_merge(self::STAFF, self::SENIOR), true)) {
            abort(404);
        }

        abort_unless($this->canView($request->user(), $folder, $path), 403);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function canView($user, string $folder, string $path): bool
    {
        if (HqStaff::allows($user, in_array($folder, self::SENIOR, true) ? 4 : null)) {
            return true;
        }

        // Clients: only QA evidence on their own organisation's projects
        if (! $user instanceof \App\Models\ClientUser || $folder !== 'qa_evidence' || ! $user->organization_id) {
            return false;
        }

        return DB::table('qa_attachments')
            ->join('qa_tickets', 'qa_tickets.id', '=', 'qa_attachments.ticket_id')
            ->join('projects', 'projects.id', '=', 'qa_tickets.project_id')
            ->where('qa_attachments.file_path', $path)
            ->where('projects.organization_id', $user->organization_id)
            ->exists();
    }
}
