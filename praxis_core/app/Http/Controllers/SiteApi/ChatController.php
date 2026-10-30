<?php

namespace App\Http\Controllers\SiteApi;

use App\Services\Site\ChatService;
use App\Services\Site\SiteText;
use Illuminate\Http\Request;

/**
 * POST /chat: one turn with Praxis AI (docs/SITE_API.md sections 3 and 6.4-6.5). The AI keys stay in HQ.
 * → {"reply": light markdown, "links": up to 3 site map pages, "limited": bool}
 */
class ChatController extends SiteApiController
{
    public function store(Request $request, ChatService $chat)
    {
        $in = $this->input($request, [
            'message' => 'required|string|max:1500',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.text' => 'required|string|max:1500',
            'question_number' => 'nullable|integer|min:1|max:100000',
        ], ['message', 'history', 'question_number']);

        $history = array_values($in['history'] ?? []);
        $locale = SiteText::locale();
        $ip = $this->visitorIp($request);

        // The conversation's question count: the site's counter, or the history when it is longer
        $userTurns = count(array_filter($history, fn ($t) => ($t['role'] ?? '') === 'user')) + 1;
        $questionNumber = max((int) ($in['question_number'] ?? 0), $userTurns);

        // One budget with the old widget: per visitor, per conversation, everyone, and the day's AI calls
        if ($chat->limitReached($ip, $questionNumber)) {
            return $this->data(['reply' => ChatService::limitMessage($locale), 'links' => ChatService::limitLinks($locale), 'limited' => true]);
        }
        $chat->hit($ip);

        $answer = $chat->praxisAi(ChatService::geminiHistory($history, $in['message']), $locale);
        if ($answer === null) {
            return $this->data(['reply' => ChatService::fallbackMessage($locale), 'links' => ChatService::fallbackLinks($locale), 'limited' => false]);
        }

        return $this->data(['reply' => $answer['reply'], 'links' => $answer['links'], 'limited' => false]);
    }
}
