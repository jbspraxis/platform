<?php

namespace App\Http\Middleware;

use App\Support\FormStamp;
use App\Support\Turnstile;
use App\Support\VisitorIp;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every form, after CSRF (docs/SITE_API.md section 6.6), before anything reaches HQ:
 *
 * 1. Honeypot: an empty "website" field that people never see. Filled means a bot.
 * 2. A signed page-load time (App\Support\FormStamp): missing, forged or under 3 seconds old means a bot.
 *    A bot gets the normal success answer and nothing is sent. The unsubscribe button is the one
 *    exception: a person can click it within 3 seconds, so they are asked to click once more.
 * 3. Cloudflare Turnstile, when its keys are set: the token is checked with Cloudflare first. Chat checks
 *    it once, then trusts the session for a while (the widget's token can be used only once).
 */
class FormGuard
{
    public const HONEYPOT = 'website';

    /** Inputs never flashed back into a form. */
    public const NOT_KEPT = ['_token', self::HONEYPOT, FormStamp::FIELD, Turnstile::FIELD, 'cv'];

    /** Session key: until when (Unix seconds) chat questions skip the Turnstile check. */
    public const CHAT_CLEARANCE = 'turnstile.chat_until';

    public function handle(Request $request, Closure $next, string $form = 'form'): Response
    {
        if (trim((string) $request->input(self::HONEYPOT, '')) !== '') {
            return $this->quietSuccess($request, $form);
        }

        if (! FormStamp::passes($request->input(FormStamp::FIELD))) {
            if ($form === 'unsubscribe') {
                return redirect()->to(url()->previous())->with('hq_error', __('site.unsubscribe.confirm_again'));
            }

            return $this->quietSuccess($request, $form);
        }

        if (Turnstile::enabled() && ! self::chatCleared($request, $form)) {
            if (! Turnstile::verify($request->input(Turnstile::FIELD), VisitorIp::of($request))) {
                return $this->challengeFailed($request, $form);
            }
            if ($form === 'chat' && $request->hasSession()) {
                $minutes = (int) config('services.turnstile.chat_clearance_minutes', 30);
                $request->session()->put(self::CHAT_CLEARANCE, now()->addMinutes($minutes)->getTimestamp());
            }
        }

        return $next($request);
    }

    /** True while this session's chat needs no new Turnstile check. */
    public static function chatCleared(Request $request, string $form = 'chat'): bool
    {
        return $form === 'chat'
            && $request->hasSession()
            && (int) $request->session()->get(self::CHAT_CLEARANCE, 0) >= now()->getTimestamp();
    }

    /** What a person would see after sending: the form's success state. Nothing reaches HQ. */
    private function quietSuccess(Request $request, string $form): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['reply' => '', 'links' => [], 'limited' => false]);
        }

        return redirect()->to(url()->previous())->with('sent', $form);
    }

    private function challengeFailed(Request $request, string $form): Response
    {
        $message = __('site.form.challenge_failed');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }
        if ($form === 'chat') {
            $previous = preg_replace('/#.*$/', '', url()->previous()) ?: url('/');

            return redirect()->to($previous . '#chat')
                ->withErrors(['chat_message' => $message], 'chat')
                ->withInput($request->only('chat_message'))
                ->with('chat_open', true);
        }

        return redirect()->to(url()->previous())
            ->withInput($request->except(self::NOT_KEPT))
            ->with('hq_error', $message);
    }
}
