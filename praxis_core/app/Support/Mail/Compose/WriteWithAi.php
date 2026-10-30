<?php

namespace App\Support\Mail\Compose;

use App\Models\Mail\MailMessage;
use App\Models\Mail\MailOutbox;
use App\Models\User;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Write with AI in the composer (docs/MAIL.md 7.6): only when the person presses it, Gemini only (never DeepSeek:
 * mail holds personal data), and the AI never sends anything; the person reads the draft and chooses Use draft
 * or Try again. It reads only what the person can read: their instruction, their draft, and for an answer the
 * conversation's last 30 messages (quoted copies cut, never Spam, never attachments).
 */
final class WriteWithAi
{
    /** @return string the draft as plain text (paragraphs separated by blank lines). Throws ValidationException. */
    public function draft(User $user, MailOutbox $outbox, string $instruction): string
    {
        Outbox::make()->own($user, $outbox, ['draft']);
        $instruction = trim(mb_substr($instruction, 0, 2000));
        $current = HtmlCleaner::toText($outbox->payload['html'] ?? '');
        if ($instruction === '' && $current === '' && ! $outbox->reply_to_message_id) {
            throw ValidationException::withMessages(['aiPrompt' => 'Say what the message should say, for example “Thank them and propose a call on Tuesday”.']);
        }
        if (! config('services.gemini.api_key')) {
            throw ValidationException::withMessages(['aiPrompt' => 'Write with AI isn’t set up on this server yet. Write the message yourself for now.']);
        }
        $key = 'mail-ai-write:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('mail_compose.ai.per_hour', 30))) {
            throw ValidationException::withMessages(['aiPrompt' => 'You have asked for many drafts this hour. Try again in a little while.']);
        }
        RateLimiter::hit($key, 3600);

        $prompt = $this->prompt($user, $outbox, $instruction, $current);
        $text = $this->ask($prompt);
        if ($text === null) {
            throw ValidationException::withMessages(['aiPrompt' => 'Praxis AI couldn’t write a draft just now. Try again in a minute.']);
        }

        return $text;
    }

    /** What Gemini is given (public for tests: nothing beyond what the person can read goes in) */
    public function prompt(User $user, MailOutbox $outbox, string $instruction, string $current): array
    {
        $account = $outbox->account;
        $person = Signatures::person($user);
        $first = trim(explode(' ', $person['name'])[0] ?? '') ?: $person['name'];

        $system = implode("\n", [
            'You write email for ' . $person['name'] . ($person['title'] ? ', ' . $person['title'] : '') . ' at JBS Praxis, a consulting and engineering firm working with African institutions.',
            $account->isShared() ? "They write from the shared mailbox {$account->email} for the team." : "They write from their own mailbox {$account->email}.",
            'Write only the body of the email: no subject line, no signature block (one is added automatically), no notes to the writer.',
            'Start with a greeting ("Hello <first name>," or "Dear <first name>," when formal). Close with "Kind regards," and then "' . $first . '" on its own line.',
            'Plain, warm, professional and short: one idea per paragraph, sentence case, no jargon, no emoji, no markdown, no bullet symbols unless a list is clearly needed.',
            'Write in the language of the conversation (English or French); if there is none, follow the instruction’s language.',
            'Never invent facts, prices, dates, names, promises or attachments. Where something is unknown, write a clear placeholder in square brackets, for example [date].',
        ]);

        $parts = [];
        if ($outbox->reply_to_message_id && ($original = MailMessage::find($outbox->reply_to_message_id)) && MailAccess::canMessage($user, $original, 'read')) {
            $conversation = $this->conversation($user, $original);
            if ($conversation !== '') {
                $parts[] = ($outbox->kind === 'forward' ? 'The message being forwarded' : 'The conversation so far, oldest first') . ":\n" . $conversation;
            }
            $parts[] = $outbox->kind === 'forward' ? 'Write a short note to go above the forwarded message.' : 'Write the reply to the latest message.';
        }
        $to = implode(', ', array_map(fn ($a) => $a['name'] ?: $a['email'], $outbox->payload['to'] ?? []));
        if ($to !== '') {
            $parts[] = "It goes to: {$to}.";
        }
        if (($subject = trim((string) ($outbox->payload['subject'] ?? ''))) !== '') {
            $parts[] = "Subject: {$subject}";
        }
        if ($current !== '') {
            $parts[] = "The writer's draft so far (improve and complete it, keep what they said):\n" . mb_substr($current, 0, 8000);
        }
        $parts[] = 'Instruction: ' . ($instruction !== '' ? $instruction : 'Write a helpful reply.');

        return ['system' => $system, 'user' => implode("\n\n", $parts)];
    }

    /** The conversation's last messages the person can read, quoted copies cut, never Spam or Trash */
    private function conversation(User $user, MailMessage $original): string
    {
        $limit = (int) config('mail_compose.ai.context_messages', 30);
        $messages = MailMessage::with('folder')
            ->where('account_id', $original->account_id)
            ->where('thread_id', $original->thread_id)
            ->whereHas('folder', fn ($q) => $q->whereNotIn('role', ['spam', 'trash']))
            ->orderByDesc('received_at')->limit($limit * 2)->get()
            ->unique('message_id_hash')->take($limit)->reverse();

        $out = [];
        foreach ($messages as $m) {
            if (! MailAccess::canMessage($user, $m, 'read')) {
                continue;
            }
            $text = $m->body_state === 'cached' ? (string) $m->body_text : (string) $m->snippet;
            $text = mb_substr(self::withoutQuotes($text), 0, 4000);
            $who = $m->from_name ?: $m->from_email;
            $out[] = "From: {$who}" . ($m->sent_at ? ' (' . $m->sent_at->format('j M Y') . ')' : '') . "\n" . $text;
        }

        return mb_substr(implode("\n\n---\n\n", $out), 0, 60000);
    }

    /** Cut quoted earlier messages: "> " lines and everything from "On … wrote:" / a forwarded header down */
    public static function withoutQuotes(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_split('/^\s*(On .{4,200}wrote:|Le .{4,200}a écrit\s*:|-{2,} ?(Original Message|Forwarded message|Message d’origine|Message transféré) ?-{2,}|From: .+\nSent: )/mu', $text)[0] ?? $text;
        $lines = array_filter(explode("\n", $text), fn ($l) => ! str_starts_with(ltrim($l), '>'));

        return trim(implode("\n", $lines));
    }

    private function ask(array $prompt): ?string
    {
        $model = (string) config('mail_compose.ai.model', 'gemini-2.5-flash');
        $timeout = (int) config('mail_compose.ai.timeout', 25);
        try {
            $response = Http::timeout($timeout)->connectTimeout(5)->withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => config('services.gemini.api_key'),
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $prompt['system']]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt['user']]]]],
                'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 1200],
            ]);
        } catch (Throwable $e) {
            Log::warning('Mail: Write with AI could not reach Gemini', ['error' => $e->getMessage()]);

            return null;
        }

        $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;
        if (! is_string($text) || trim($text) === '') {
            Log::warning('Mail: Write with AI got no draft', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);

            return null;
        }
        // Markdown the model may still add: bold markers, a "Subject:" line
        $text = preg_replace('/^\s*Subject:.*\n+/i', '', trim($text));
        $text = str_replace(['**', '__'], '', $text);

        return trim($text);
    }
}
