<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailFetch;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Files from mail (docs/MAIL.md, PR 2): attachments to download or view, and `cid:` pictures inside a
 * message. HQ keeps no copy (owner decision 8): each is read from the mail server when asked for, through
 * MailFetch, which checks the person can read that mailbox.
 *
 * What is shown in the browser rather than saved is limited to pictures (not SVG), PDFs and plain text, with
 * nosniff; everything else downloads as bytes, so a file someone mailed can never run as part of HQ. (A
 * sandbox policy would be a second wall, but SecurityHeaders replaces every response's CSP: docs/MAIL.md 12.)
 */
class AttachmentController extends Controller
{
    /** Types that may open in the browser; everything else downloads */
    private const VIEWABLE = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'];

    /** An attachment of a stored message: ?view=1 opens it in the browser when that is safe */
    public function show(Request $request, MailAttachment $attachment): Response
    {
        return $this->send(fn () => MailFetch::make()->attachment($request->user(), $attachment), $request->boolean('view'));
    }

    /** A cid: picture in a stored message */
    public function inline(Request $request, MailMessage $message, string $cid): Response
    {
        abort_unless(MailAccess::canMessage($request->user(), $message, 'read'), 403);
        $file = $this->fetch(fn () => MailFetch::make()->inline($request->user(), $message, $cid));
        if ($file instanceof Response) {
            return $file;
        }
        abort_unless($file && $this->isPicture($file['mime']), 404);

        // What a message id and Content-ID point to never changes: the browser keeps it for a week without asking again
        return $this->respond($file, true, 'private, max-age=604800, immutable');
    }

    /** An attachment (or cid: picture) of an older message read live from the server */
    public function live(Request $request, MailFolder $folder, int $uid, string $section): Response
    {
        abort_unless(preg_match('/^\d+(\.\d+)*$/', $section) === 1, 404);

        return $this->send(fn () => MailFetch::make()->liveAttachment($request->user(), $folder, $uid, $section), $request->boolean('view'));
    }

    private function send(callable $read, bool $view): Response
    {
        $file = $this->fetch($read);

        return $file instanceof Response ? $file : $this->respond($file, $view);
    }

    private function fetch(callable $read): mixed
    {
        try {
            return $read();
        } catch (ImapException) {
            return response("The mail server didn't send this file. Go back and try again in a minute.", 502, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    private function respond(array $file, bool $view, string $cache = 'private, no-store'): Response
    {
        $mime = strtolower((string) $file['mime']);
        $inline = $view && in_array($mime, self::VIEWABLE, true);
        $name = trim(str_replace(["\r", "\n", '/', '\\'], ' ', (string) $file['filename'])) ?: 'attachment';
        $fallback = preg_replace('/[^\x20-\x7E]|[%"]/', '_', $name) ?: 'attachment';

        return response($file['content'], 200, [
            'Content-Type' => $inline ? ($mime === 'text/plain' ? 'text/plain; charset=utf-8' : $mime) : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $name, $fallback),
            'Content-Length' => (string) strlen($file['content']),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => $cache,
        ]);
    }

    private function isPicture(string $mime): bool
    {
        return in_array(strtolower($mime), ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp'], true);
    }
}
