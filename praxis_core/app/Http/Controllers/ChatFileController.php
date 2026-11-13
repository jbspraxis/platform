<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HqStaff;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Chat\ProjectChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A photo, file or voice note of a project's conversation (docs/CHAT.md), from the private disk. Staff: anyone who
 * may open that project's chat. Clients: an active account of the project's organisation that reads the chat
 * (portal Quality and issues) and isn't limited to another project; never a file of an internal note.
 */
class ChatFileController extends Controller
{
    // Shown in the page (previews, the voice player); anything else is downloaded
    private const INLINE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
        'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg', 'audio/aac', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'video/webm', 'video/mp4', 'application/ogg'];

    public function show(Request $request, int $attachment)
    {
        $file = DB::table('chat_attachments as a')->join('chat_messages as m', 'm.id', '=', 'a.message_id')
            ->leftJoin('qa_tickets as t', 't.id', '=', 'm.qa_ticket_id')
            ->where('a.id', $attachment)->whereNull('m.deleted_at')->whereNull('t.deleted_at')
            ->first(['a.file_path', 'a.file_name', 'a.mime_type', 'a.kind', 'm.project_id', 'm.is_internal']);
        abort_unless($file && $this->mayOpen($request->user(), $file), 404);
        abort_unless(Storage::disk('local')->exists($file->file_path), 404);

        // No SVG or HTML is ever accepted (ProjectChat::FILE_MIMES), so what opens in the browser is a picture, a PDF or sound
        $headers = ['Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        $mime = strtolower((string) $file->mime_type);
        // A voice note recorded as webm may be read as video/webm: it plays as audio either way
        if (in_array($mime, self::INLINE, true)) {
            return Storage::disk('local')->response($file->file_path, $file->file_name, ['Content-Type' => $mime] + $headers);
        }

        return Storage::disk('local')->download($file->file_path, $file->file_name, $headers);
    }

    private function mayOpen($user, object $file): bool
    {
        if (HqStaff::allows($user)) {
            return ProjectChat::staffMaySee($file->project_id);
        }
        if (! $user instanceof ClientUser || $user->status !== 'Active' || $file->is_internal) {
            return false;
        }
        if ($user->restricted_project_id && $user->restricted_project_id !== $file->project_id) {
            return false;
        }

        return Access::allows($user, 'portal.qa.view')
            && DB::table('projects')->where('id', $file->project_id)->where('organization_id', $user->organization_id)->exists();
    }
}
