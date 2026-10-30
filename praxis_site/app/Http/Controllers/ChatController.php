<?php

namespace App\Http\Controllers;

use App\Exceptions\Hq\HqApiException;
use App\Exceptions\Hq\HqRateLimited;
use App\Exceptions\Hq\HqValidationException;
use App\Support\HqApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * POST /chat: one turn with Praxis AI, the website assistant (POST /chat on HQ; the AI keys stay in HQ).
 * The conversation lives in the visitor's session (last 20 turns) with a question counter that HQ uses
 * for its 20-questions-per-conversation limit (docs/SITE_API.md 6.2 and 6.4). Works as a plain form
 * (redirect back to the open chat) and, with JavaScript, as a JSON call that adds the reply in place.
 */
class ChatController extends Controller
{
    public const SESSION_KEY = 'chat.turns';

    /** Questions asked in this session, never reset by trimming the turns. */
    public const COUNTER_KEY = 'chat.questions';

    public function store(Request $request, HqApi $api)
    {
        // HQ may take up to 25 s for the AI and the site waits 35 s: PHP must not stop first
        if (function_exists('set_time_limit')) {
            @set_time_limit((int) config('hq_api.timeouts.chat', 35) + 25);
        }

        $validator = Validator::make($request->all(), ['chat_message' => ['required', 'string', 'max:1500']], [
            'chat_message.required' => __('site.chat.required'),
            'chat_message.string' => __('site.chat.required'),
            'chat_message.max' => __('site.chat.too_long'),
        ]);
        if ($validator->fails()) {
            return $this->failed($request, $validator->errors()->first('chat_message'), 422);
        }

        $message = trim((string) $request->input('chat_message'));
        $keep = (int) config('site.chat_turns', 20);
        $turns = array_slice((array) $request->session()->get(self::SESSION_KEY, []), -$keep);

        // A question sent is a question asked, whatever HQ answers
        $number = (int) $request->session()->get(self::COUNTER_KEY, 0) + 1;
        $request->session()->put(self::COUNTER_KEY, $number);

        try {
            $answer = $api->chat($this->history($turns), $message, $number);
        } catch (HqValidationException $e) {
            return $this->failed($request, collect($e->errors())->flatten()->first() ?: __('site.chat.required'), 422);
        } catch (HqRateLimited $e) {
            return $this->failed($request, $e->hqMessage ?: __('site.errors.too_many', ['email' => $api->contactEmail()]), 429);
        } catch (HqApiException) {
            return $this->failed($request, __('site.chat.unavailable', ['email' => $api->contactEmail()]), 503);
        }

        // HQ's calm limit text (limited: true) is shown like any other reply
        $reply = $answer['reply'] !== '' ? $answer['reply'] : __('site.chat.no_reply', ['email' => $api->contactEmail()]);
        $links = $answer['links'];

        $turns = array_slice(array_merge($turns, [
            ['role' => 'user', 'text' => $message],
            ['role' => 'assistant', 'text' => mb_substr($reply, 0, 1500), 'links' => $links],
        ]), -$keep);
        $request->session()->put(self::SESSION_KEY, $turns);

        if ($request->expectsJson()) {
            return response()->json(['reply' => $reply, 'links' => $links, 'limited' => $answer['limited']]);
        }

        return $this->back();
    }

    /** The turns as HQ expects them: role and text only. */
    private function history(array $turns): array
    {
        $out = [];
        foreach ($turns as $turn) {
            $role = is_array($turn) ? ($turn['role'] ?? null) : null;
            $text = is_array($turn) ? ($turn['text'] ?? null) : null;
            if (in_array($role, ['user', 'assistant'], true) && is_string($text) && $text !== '') {
                $out[] = ['role' => $role, 'text' => mb_substr($text, 0, 1500)];
            }
        }

        return $out;
    }

    private function failed(Request $request, string $text, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $text], $status);
        }

        return $this->back()->withErrors(['chat_message' => $text], 'chat')->withInput($request->only('chat_message'));
    }

    /** Back to the page the chat was used on, with the chat open. */
    private function back()
    {
        $previous = url()->previous();
        $previous = preg_replace('/#.*$/', '', $previous) ?: url('/');

        return redirect()->to($previous . '#chat')->with('chat_open', true);
    }
}
