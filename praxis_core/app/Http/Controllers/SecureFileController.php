<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HqStaff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Serves uploads kept on the private disk (storage/app/private).
 *
 * Staff need the matching permission (see canView); a client sees only QA
 * evidence attached to tickets on their own organisation's projects.
 */
class SecureFileController extends Controller
{
    private const FOLDERS = ['receipts', 'qa_evidence', 'pop_receipts', 'pop_receipts_wht', 'vault'];

    public function show(Request $request, string $path)
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $folder = explode('/', $path)[0];

        if (str_contains($path, '..') || ! in_array($folder, self::FOLDERS, true)) {
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
        if (HqStaff::allows($user)) {
            return match ($folder) {
                // Cash-request receipts: expense approvers, or the person who asked for the cash
                'receipts' => $user->can('expenses.view')
                    || DB::table('cash_requests')->where('receipt_url', $path)->where('requester_id', $user->id)->exists(),
                'qa_evidence' => $user->can('qa.view') || $user->can('projects.view'),
                'pop_receipts', 'pop_receipts_wht' => $user->can('finance.view'),
                'vault' => $user->can('hr.view'), // candidate CVs
                default => false,
            };
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
