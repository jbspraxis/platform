<?php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use App\Services\Site\ChatService;

class AiAgent extends Component
{
    public $userInput = '';

    // Locked: the browser cannot rewrite the conversation or trigger extra replies
    #[Locked]
    public array $messages = [];

    #[Locked]
    public bool $awaitingReply = false;

    // Per visitor IP: 10 messages per 10 minutes, 40 per day. Conversations end at 20 questions.
    // The budget, the prompt and the AI call: App\Services\Site\ChatService (also used by the site API).

    // Universal Event Listener (Crash-Proof)
    protected $listeners = ['processAiQuery' => 'fetchAiResponse'];

    public function mount()
    {
        $this->messages[] = [
            'id' => 0,
            'role' => 'ai',
            'content' => "**[ PRAXIS NEXUS ONLINE ]**\n\n> Welcome. I am the JBS Praxis Architectural AI.\n> Think of me as your direct line to our Senior Consulting Partners.\n\n> Tell me a bit about your enterprise. What operational bottlenecks or scaling challenges can we help you solve today?",
        ];
    }

    public function submitUserMessage()
    {
        $this->validate([
            'userInput' => 'required|string|max:1500'
        ]);

        // Push User Message (escaped when rendered)
        $this->messages[] = [
            'id' => count($this->messages),
            'role' => 'user',
            'content' => $this->userInput,
        ];

        $this->userInput = '';
        $this->dispatch('scrollToBottom');

        $ip = request()->ip();
        $chat = app(ChatService::class);
        $userTurns = count(array_filter($this->messages, fn ($m) => $m['role'] === 'user'));
        if ($chat->limitReached($ip, $userTurns)) {
            $this->messages[] = [
                'id' => count($this->messages),
                'role' => 'ai',
                'content' => ChatService::OLD_WIDGET_LIMIT,
            ];
            return;
        }
        $chat->hit($ip);

        // Trigger AI response instantly
        $this->awaitingReply = true;
        $this->dispatch('processAiQuery');
    }

    public function fetchAiResponse()
    {
        // Only answer a message the visitor actually sent through submitUserMessage()
        if (! $this->awaitingReply) {
            return;
        }
        $this->awaitingReply = false;

        $geminiHistory = [];
        foreach (array_slice($this->messages, -ChatService::HISTORY_TURNS) as $msg) {
            if ($msg['id'] === 0) continue;
            
            $geminiHistory[] = [
                "role" => $msg['role'] === 'user' ? 'user' : 'model',
                "parts" => [["text" => strip_tags($msg['content'])]]
            ];
        }

        // Gemini first, DeepSeek if Gemini fails; the fallback text if neither answers
        $aiText = app(ChatService::class)->reply($geminiHistory);

        $this->messages[] = [
            'id' => count($this->messages),
            'role' => 'ai',
            'content' => $aiText ?: ChatService::OLD_WIDGET_FALLBACK,
        ];

        $this->dispatch('scrollToBottom');
    }

    // NATIVE REGEX MARKDOWN PARSER (100% Crash-Proof)
    public function renderMarkdown($text)
    {
        // 0. Escape everything first; only the markup below is turned back into HTML
        $text = e((string) $text);

        // 1. Convert **bold** to <strong>
        $text = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $text);
        
        // 2. Convert *bold* or _bold_ to <strong>
        $text = preg_replace('/(?<!\*)\*(?!\*)(.*?)(?<!\*)\*(?!\*)/s', '<strong>$1</strong>', $text);
        
        // 3. Convert [Link Text](https://url.com) to clickable <a href>
        $text = preg_replace('/\[([^\[\]]+)\]\((https?:\/\/[^\s\)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
        
        // 4. Convert basic - bullet points to •
        $text = preg_replace('/^- (.*)/m', '• $1', $text);
        $text = preg_replace('/^\* (.*)/m', '• $1', $text);
        
        // 5. Convert Linebreaks
        return nl2br($text);
    }

    public function render()
    {
        return view('livewire.ai-agent');
    }
}