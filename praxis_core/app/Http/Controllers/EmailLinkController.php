<?php

namespace App\Http\Controllers;

use App\Models\Campaigns\OutreachProspect;
use App\Support\Campaigns\Images;
use App\Support\Campaigns\Marketing;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\Tracking;
use App\Support\Hosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Links inside broadcasts (routes/email-links.php, docs/CAMPAIGNS.md 4.5) and outreach emails' unsubscribe links
 * (section 10). Every link carries the recipient's own token; an unknown token does nothing and says so calmly (the
 * click goes to the website's home page instead).
 */
class EmailLinkController extends Controller
{
    /** A transparent 1x1 PNG */
    private const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** The page behind the email's unsubscribe link: one button (GET never unsubscribes: scanners open links) */
    public function unsubscribePage(Request $request, string $token)
    {
        $log = Tracking::log($token);
        $this->speak($log, $request);

        return response()->view('marketing.unsubscribe', [
            'token' => $token,
            'state' => $token === 'preview' ? 'preview' : (! $log ? 'invalid' : (Tracking::isUnsubscribed($log) ? 'done' : 'ask')),
            'email' => $log ? Tracking::masked($log->email) : null,
        ], $log || $token === 'preview' ? 200 : 404);
    }

    /** The page's button, or the inbox's one-click unsubscribe (body List-Unsubscribe=One-Click) */
    public function unsubscribe(Request $request, string $token)
    {
        $log = Tracking::log($token);
        $oneClick = $request->input('List-Unsubscribe') === 'One-Click';
        if ($log) {
            Tracking::unsubscribe($log, $oneClick ? 'one_click' : 'page');
        }
        if ($oneClick) {
            return response($log ? 'Unsubscribed' : 'Unknown link', $log ? 200 : 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }
        $this->speak($log, $request);

        return response()->view('marketing.unsubscribe', [
            'token' => $token,
            'state' => $token === 'preview' ? 'preview' : ($log ? 'done' : 'invalid'),
            'email' => $log ? Tracking::masked($log->email) : null,
        ], $log || $token === 'preview' ? 200 : 404);
    }

    /** The same page for an outreach email's link (C2, docs/CAMPAIGNS.md section 10), in the prospect's language */
    public function outreachPage(Request $request, string $token)
    {
        $email = Outreach::emailByToken($token);
        $prospect = $email ? OutreachProspect::find($email->prospect_id) : null;
        $this->speakIn($prospect?->language, $request);

        return response()->view('marketing.unsubscribe', [
            'token' => $token,
            'state' => ! $prospect ? 'invalid' : ($prospect->unsubscribed_at || ! Marketing::canEmail($prospect->email) ? 'done' : 'ask'),
            'email' => $prospect ? Tracking::masked($prospect->email) : null,
            'copy' => 'outreach_unsubscribe', 'pageRoute' => 'email.unsubscribe.outreach', 'confirmRoute' => 'email.unsubscribe.outreach.confirm',
        ], $prospect ? 200 : 404);
    }

    /** The outreach page's button, or the inbox's one-click unsubscribe: all marketing to the address stops */
    public function outreachUnsubscribe(Request $request, string $token)
    {
        $email = Outreach::emailByToken($token);
        $prospect = $email ? OutreachProspect::find($email->prospect_id) : null;
        $oneClick = $request->input('List-Unsubscribe') === 'One-Click';
        if ($email && $prospect) {
            Outreach::unsubscribe($email, $oneClick ? 'one_click' : 'page');
        }
        if ($oneClick) {
            return response($prospect ? 'Unsubscribed' : 'Unknown link', $prospect ? 200 : 404)->header('Content-Type', 'text/plain; charset=utf-8');
        }
        $this->speakIn($prospect?->language, $request);

        return response()->view('marketing.unsubscribe', [
            'token' => $token,
            'state' => $prospect ? 'done' : 'invalid',
            'email' => $prospect ? Tracking::masked($prospect->email) : null,
            'copy' => 'outreach_unsubscribe', 'pageRoute' => 'email.unsubscribe.outreach', 'confirmRoute' => 'email.unsubscribe.outreach.confirm',
        ], $prospect ? 200 : 404);
    }

    public function open(string $token)
    {
        if ($log = Tracking::log($token)) {
            Tracking::opened($log);
        }

        return response(base64_decode(self::PIXEL), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function click(Request $request, string $token, string $link)
    {
        $log = Tracking::log($token);
        $url = $log ? Tracking::clicked($log, (int) $link) : null;

        return redirect()->away($url ?? Hosts::url(Hosts::site(), '', $request));
    }

    public function image(string $file)
    {
        $path = storage_path('app/' . Images::DIR . '/' . $file);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** The page speaks the language the email was sent in; else the browser's (French first if it asks for it) */
    private function speak(?object $log, Request $request): void
    {
        $this->speakIn($log?->locale, $request);
    }

    private function speakIn(?string $locale, Request $request): void
    {
        $locale = $locale ?: ($request->getPreferredLanguage(['en', 'fr']) ?? 'en');
        if (in_array($request->query('lang'), ['en', 'fr'], true)) {
            $locale = $request->query('lang');
        }
        App::setLocale($locale === 'fr' ? 'fr' : 'en');
    }
}
