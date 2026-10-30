<?php

namespace App\Livewire\Hq\Mail;

use App\Support\Mail\Ai\Gemini;
use App\Support\Mail\Ai\MailAsk;
use App\Support\Mail\MailAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Ask Praxis AI about your mail: a question box in the Mail header that answers from the mail the person can read, with links to the messages. Mounted with :account-id (the open mailbox; it may search all the person's mailboxes).
 * Gemini only, on the person's press; nothing is stored (docs/MAIL.md 7.6, MailAsk and MailSearch).
 */
class AskMail extends Component
{
    public ?string $accountId = null;

    public bool $open = false;

    public string $question = '';

    /** 'box' (the open mailbox) or 'all' */
    public string $scope = 'all';

    public ?string $asked = null;

    public ?string $answer = null;

    /** @var list<array{n: int, url: string, subject: string, from: string, date: ?string}> */
    public array $sources = [];

    public ?string $error = null;

    public function mount(?string $accountId = null): void
    {
        $this->accountId = $accountId;
    }

    public function openPanel(): void
    {
        $this->open = true;
    }

    public function closePanel(): void
    {
        $this->open = false;
    }

    public function ask(): void
    {
        abort_unless(MailAccess::canUse(Auth::user()), 403);
        $this->validate(['question' => 'required|string|min:3|max:500'], [
            'question.required' => 'Type a question first.',
            'question.min' => 'Ask a little more, for example a name or a subject.',
            'question.max' => 'Keep the question under 500 characters.',
        ]);
        $this->error = null;

        $boxes = null;
        if ($this->scope === 'box' && $this->accountId) {
            $boxes = [$this->accountId];
        }
        $result = MailAsk::answer(Auth::user(), $this->question, $boxes);

        if (! $result['ok']) {
            $this->error = MailAsk::ERRORS[$result['error']] ?? MailAsk::ERRORS['unavailable'];

            return;
        }
        $this->asked = trim($this->question);
        $this->answer = $result['answer'];
        // Only what the screen shows: never the excerpts the AI read
        $this->sources = array_map(fn ($s) => [
            'n' => $s['n'], 'url' => $s['url'], 'subject' => $s['subject'], 'from' => $s['from'], 'date' => $s['date'],
        ], $result['sources']);
        $this->question = '';
    }

    public function render()
    {
        $user = Auth::user();
        $accounts = MailAccess::accounts($user);
        $box = $this->accountId ? $accounts->firstWhere('id', $this->accountId) : null;

        return view('livewire.hq.mail.ask-mail', [
            'enabled' => MailAccess::canUse($user) && $accounts->isNotEmpty() && Gemini::configured(),
            'box' => $box,
            'manyBoxes' => $accounts->count() > 1,
            'answerHtml' => $this->answer !== null ? self::answerHtml($this->answer, $this->sources) : null,
        ]);
    }

    /** The answer as safe HTML: escaped, "- " lines as a list, [n] as links to the messages used */
    public static function answerHtml(string $answer, array $sources): string
    {
        $urls = array_column($sources, 'url', 'n');
        $cite = fn (string $html) => preg_replace_callback('/\[(\d{1,2})\]/', function ($m) use ($urls) {
            $n = (int) $m[1];

            return isset($urls[$n])
                ? '<a class="am-cite" href="' . e($urls[$n]) . '" wire:navigate title="Open message ' . $n . '">' . $n . '</a>'
                : '';
        }, $html);

        $out = '';
        $list = [];
        $flush = function () use (&$out, &$list) {
            if ($list) {
                $out .= '<ul>' . implode('', array_map(fn ($li) => "<li>{$li}</li>", $list)) . '</ul>';
                $list = [];
            }
        };
        foreach (preg_split('/\n+/', trim($answer)) as $line) {
            $line = trim(str_replace(['**', '__'], '', $line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^[-*•]\s+(.*)$/u', $line, $m)) {
                $list[] = $cite(e($m[1]));
            } else {
                $flush();
                $out .= '<p>' . $cite(e($line)) . '</p>';
            }
        }
        $flush();

        return $out !== '' ? $out : '<p>' . e(Str::limit($answer, 2000)) . '</p>';
    }
}
