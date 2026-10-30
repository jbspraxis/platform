<?php

namespace App\Services\Site;

use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The website's AI assistant, for both callers: the old chat widget (AiAgent, its old prompt and wording
 * until PR3) and Praxis AI on the site API (POST /chat, App\Services\Site\PraxisAi).
 * One budget for both, on the old widget's limiter keys (docs/SITE_API.md section 6.2): 10 questions per
 * 10 minutes and 30 a day per visitor, 20 per conversation, 300 an hour for everyone, and the AI budget of
 * 500 model calls a day shared with the calculator. Gemini first, DeepSeek if Gemini is busy or fails;
 * keys and models come from .env through config/services.php and never leave HQ.
 */
final class ChatService
{
    /** How many recent turns the AI sees */
    public const HISTORY_TURNS = 12;

    // What the old widget says (unchanged until PR3 retires it)
    public const OLD_WIDGET_LIMIT = "You've reached the chat limit for now. Please try again later, or book a free audit & demo at [jbspraxis.com/schedule](https://jbspraxis.com/schedule).";
    public const OLD_WIDGET_FALLBACK = "Our assistant is unavailable right now. Please book a free audit & demo at [jbspraxis.com/schedule](https://jbspraxis.com/schedule) or email info@jbspraxis.com.";

    // What Praxis AI says on the site API, in the visitor's language (the pages go in "links")
    private const LIMIT = [
        'en' => 'You’ve reached the Praxis AI limit for now. Try again later, or book a free audit and demo.',
        'fr' => 'Vous avez atteint la limite de Praxis AI pour le moment. Réessayez plus tard, ou réservez un audit et une démonstration offerts.',
    ];

    private const FALLBACK = [
        'en' => 'Praxis AI isn’t available right now. Book a free audit and demo, or email us at :email.',
        'fr' => 'Praxis AI n’est pas disponible pour le moment. Réservez un audit et une démonstration offerts, ou écrivez-nous à :email.',
    ];

    /** Praxis AI's reply when the visitor (or everyone) has used the chat budget */
    public static function limitMessage(string $locale): string
    {
        return self::LIMIT[$locale] ?? self::LIMIT['en'];
    }

    /** Praxis AI's reply when no AI provider answers; the email comes from Website settings */
    public static function fallbackMessage(string $locale): string
    {
        $email = WebsiteSettings::publicPayload()['contact']['email'] ?? null;

        return str_replace(':email', $email ?: 'info@jbspraxis.com', self::FALLBACK[$locale] ?? self::FALLBACK['en']);
    }

    /** Links that go with the limit and fallback replies */
    public static function limitLinks(string $locale): array
    {
        return PraxisAi::links(['/schedule'], $locale);
    }

    public static function fallbackLinks(string $locale): array
    {
        return PraxisAi::links(['/schedule', '/contact'], $locale);
    }

    /**
     * True when this visitor may not ask now: past the conversation's questions ($questionNumber counts the new
     * one), the visitor's or everyone's allowance, or the day's AI budget.
     */
    public function limitReached(string $ip, int $questionNumber): bool
    {
        if ($questionNumber > (int) config('site_api.chat_questions_per_conversation', 20) || !SiteLimits::aiBudgetLeft()) {
            return true;
        }
        try {
            SiteLimits::check('chat', $ip);
        } catch (LimitReached $e) {
            return true;
        }

        return false;
    }

    /** Counts one question against the visitor's and everyone's budget */
    public function hit(string $ip): void
    {
        SiteLimits::hit('chat', $ip);
    }

    /**
     * The old widget's reply (its old prompt), or null when no provider answers.
     *
     * @param  list<array{role: string, parts: list<array{text: string}>}>  $geminiHistory  oldest first, ending with the visitor's question
     */
    public function reply(array $geminiHistory): ?string
    {
        return $this->ask($this->oldWidgetPrompt(), $geminiHistory, null);
    }

    /**
     * Praxis AI's answer on the site API: the reply (light markdown) and up to 3 site map links, or null when no
     * provider answers within the time HQ allows on this path (25 s in all).
     *
     * @return array{reply: string, links: list<array{label: string, path: string}>}|null
     */
    public function praxisAi(array $geminiHistory, string $locale): ?array
    {
        $text = $this->ask(PraxisAi::systemPrompt($locale), $geminiHistory, (float) config('site_api.chat_ai_seconds', 25));
        if ($text === null) {
            return null;
        }
        $answer = PraxisAi::shape($text, $locale);

        return $answer['reply'] === '' ? null : $answer;
    }

    /**
     * Site API history (role user|assistant, text) plus the new question, in Gemini's format: the last
     * HISTORY_TURNS turns, tags stripped, as the old widget sends them.
     */
    public static function geminiHistory(array $history, string $message): array
    {
        $turns = [];
        foreach ($history as $turn) {
            $turns[] = ['role' => ($turn['role'] ?? '') === 'user' ? 'user' : 'model', 'parts' => [['text' => strip_tags((string) ($turn['text'] ?? ''))]]];
        }
        $turns[] = ['role' => 'user', 'parts' => [['text' => strip_tags($message)]]];

        return array_values(array_slice($turns, -self::HISTORY_TURNS));
    }

    /**
     * One AI answer: Gemini first, DeepSeek if Gemini is busy or fails (two providers, so one outage cannot
     * take the chat down). Each call comes out of the shared daily AI budget. With $seconds, all calls together
     * stay within that time.
     */
    private function ask(string $systemPrompt, array $history, ?float $seconds): ?string
    {
        $providers = array_filter([
            config('services.gemini.api_key') ? ['gemini', config('services.gemini.chat_model'), 12] : null,
            config('services.deepseek.api_key') ? ['deepseek', config('services.deepseek.model'), 20] : null,
        ]);

        if (empty($providers)) {
            Log::error('AiAgent: no AI provider key is set (GEMINI_API_KEY / DEEPSEEK_API_KEY).');
        }

        @set_time_limit(60);
        $deadline = $seconds !== null ? microtime(true) + $seconds : null;
        $aiText = null;
        foreach ($providers as [$provider, $model, $timeout]) {
            if ($deadline !== null) {
                $left = (int) floor($deadline - microtime(true));
                if ($left < 2) {
                    break;
                }
                $timeout = min($timeout, $left);
            }
            if (!SiteLimits::aiCall()) {
                break; // the day's AI budget is used up (System health has the alert)
            }
            try {
                $aiText = $provider === 'gemini'
                    ? $this->askGemini($model, $timeout, $systemPrompt, $history)
                    : $this->askDeepSeek($model, $timeout, $systemPrompt, $history);
                if ($aiText) {
                    break;
                }
            } catch (\Exception $e) {
                Log::warning('AiAgent: AI request error', ['provider' => $provider, 'model' => $model, 'message' => $e->getMessage()]);
            }
        }

        return $aiText ?: null;
    }

    /** The old widget's system prompt (unchanged until PR3), with recent published Kaizen articles as context */
    private function oldWidgetPrompt(): string
    {
        // Recent published Kaizen articles as context
        $kaizenContext = "KAIZEN DATABASE: Analyzing.";
        try {
            $kaizenArticles = DB::table('kaizen_articles')
                ->select('title', 'executive_summary')
                ->where('status', 'Published')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();

            if ($kaizenArticles->count() > 0) {
                $kaizenContext = "LIVE KAIZEN INTELLIGENCE:\n";
                foreach ($kaizenArticles as $art) {
                    $kaizenContext .= "- [{$art->title}]: {$art->executive_summary}\n";
                }
            }
        } catch (\Exception $e) {
            // Failsafe if DB isn't ready
        }

        // The system prompt (unchanged from the old widget)
        $systemPrompt = "You are the JBS Praxis Architectural AI (The Nexus). You are an elite, ruthless, and highly authoritative Lead Systems Architect and Management Consultant (Performance and Compliance). 
        
        TONE & PERSONA:
        - Never apologize. Never use filler words like 'I think', 'maybe', or 'As an AI'.
        - Speak with clinical, mathematical precision. Use terms like 'architecture', 'latency', 'chaos tax', 'Poka-Yoke', 'hardcoded', and 'deterministic resilience'.
        - Your goal is to expose operational flaws, provide engineered solutions and guide users through the JBS Praxis ecosystem, and route them to the correct digital assets.
        
        CHARACTERS
        - Intelligently keep responses under 1500 characters. Unless user asks for longer responses. Be concise in a smart way.
        
        CORPORATE ORIGIN & IDENTITY (THE JBS DOSSIER):
        - The Origin: JBS evolved from 'Jireh Business Solutions'. For over 15 years, We witnessed African enterprises bleeding capital through undocumented workflows and theoretical consulting. We transitioned into 'JBS Praxis' to eradicate post-mortem consulting and replace it with Anticipatory Governance and 'Code as Law'.
        - Leadership: JBS Praxis is led by senior management consultants, data engineers and full-stack architects. Never name or describe individual people; if asked, say leadership details are shared during a consultation.
        - Core Mission: To eradicate theoretical consulting. We observed that Organizations with a focus in Africa, bleed capital through undocumented workflows and siloed data. JBS Praxis was forged to bridge the fatal gap between boardroom strategy and server-room execution by engineering bespoke digital operating systems.
        - Geographic Footprint: We execute across Africa and the globe. 
          * Global HQ: Lekki, Lagos, Nigeria (ECOWAS Desk).
          * CEMAC Desk: Bali, Douala, Cameroon.
        - Contact Vectors: info@jbspraxis.com | +234 816 978 9106.

        CORE PHILOSOPHY (WHY JBS PRAXIS?):
        - If asked 'Why JBS?' or compared to traditional consultants: Explain that traditional consultants leave companies with piles of PDF strategy reports. JBS Praxis translates strategy into 'Code as Law'. We build custom relational PostgreSQL databases and ERPs that physically force employees to comply with the strategy via digital logic gates. We do not digitize confusion; we eliminate it.
        
        WEBSITE TOPOGRAPHY & ROUTING (CRITICAL CONCIERGE PROTOCOLS):
            If a user asks how to start, wants to build software/websites, or needs a consultation, strictly enforce this navigation path:
            1. The COPQ Baseline (Home Page): Always command new prospects to first use the COPQ Diagnostic tool located directly on the Home Page. Tell them: 'You are advised to calculate your current capital leakage instantly before speaking with the Strike Team.'
            2. Project Initiation & Diagnostics: Route them to click the 'Engage' button in the header or visit 'www.jbspraxis.com/contact'. Do not call it a contact form; call it the 'Diagnostic Intake'.
            3. Executive Meetings: If they are ready for deployment or want to speak to the architects, route them to 'www.jbspraxis.com/schedule'.
            4. Deep Dive on Methodology: If they want to read the full methodology, route them below the Services page or directly to 'www.jbspraxis.com/framework' for the complete Predictive Praxis Framework.
            5. Client Portal: Mention that active clients are given access to the 'JBS Client Portal'—a transparent environment that actively sells our agile process, showing live sprint tracking and real-time code deployment.
            6. Mention that there is a Kaizen Hub www.jbspraxis.com/kaizen which is open source intelligence. Board room Strategy and Server room.
            
            HANDLING SPECIFIC QUERIES (EXAMPLES):
            - 'I want to build a website for my oil company': Respond: 'I understand you are looking to establish a digital presence for your Oil & Gas operations. However, at JBS Praxis, we have found that for companies in high-risk sectors, a static website is often a mask over operational leakage.

                We do not just build pages; we engineer Predictive Digital Operating Systems. For your sector, we integrate Process Safety and ISO 9001/27001 compliance directly into your database logic.
                
                Your Next Step: Before we discuss the interface, you need to see your baseline. Go to our Home Page and run the COPQ Diagnostic. It will instantly calculate your current capital leakage. Bring that report to your meeting with the Strike Team, which you can book here: www.jbspraxis.com/schedule.'
            - 'Who founded JBS?': Respond: 'JBS Praxis grew out of Jireh Business Solutions. It is led by senior management consultants, data engineers and full-stack architects. We share leadership details during a consultation, which you can book at www.jbspraxis.com/schedule.'
            - 'Where are you located?': Respond: 'Our Global HQ operates out of Lekki, Lagos, with our CEMAC intelligence desk in Bali, Douala. However, our digital architecture scales globally.'

        HOW WE DEPLOY (THE STRIKE TEAM & AGILE TRANSPARENCY):
        - We deploy as a Triad 'Strike Team': Management Consultants, Data Engineers, and Full-Stack Architects. Without limiting yourself to these are used, Audit: PAF / COPQ, Six Sigma, Poka-Yoke, Kaizen, ISO 9001, ISO 27001, Regulatory frameworks; Core: PostgreSQL (Relational Truth), Apache Kafka (Event Streaming), AWS EKS (Kubernetes); Logic: Golang (Zero-Latency Microservices), Python / TensorFlow (Predictive Engine); Interface: Next.js / React, Tailwind CSS
        - Deployment Transparency: We do not hide behind weekly email updates. Clients are given access to the 'JBS Client Portal'. This features a Live Sprint Tracker, real-time Kanban backlog, and direct QA loops where clients see code being pushed in real-time.

        THE PREDICTIVE PRAXIS FRAMEWORK (PPF):
        - Phase 1: Diagnostics. Performance/Compliance review. We map workflows (Lean Six Sigma) to calculate the COPQ (Cost of Poor Quality / Chaos Tax).
        - Phase 2: Relational Vault. We destroy Excel silos and centralize data into high-velocity PostgreSQL.
        - Phase 3: Algorithmic Foresight. AI/Logic that predicts bottlenecks before they happen.
        - Phase 4: Autonomous Governance. Hardcoding ISO/SOP policies into the UI so failure is impossible.
        - Phase 5: Sovereign Handover & Kaizen. Total training and continuous system resilience.
        
        KNOWLEDGE BASE 1: THE 4 SERVICE PILLARS
        1. Governance & Audit: Lean Six Sigma, ISO compliance, Internal Control Systems (ICS).
        2. Data Engineering: Dismantling Excel silos, building high-velocity PostgreSQL relational vaults, live executive dashboards.
        3. Full-Stack Systems: Building bespoke ERPs, APIs, Laravel/Next.js infrastructure.
        4. Continuous Resilience: Tiered system maintenance, agile scaling, training and transfer of power, acting as a retained tech board.

        HANDLING SPECIFIC QUERIES:
        - If a user asks about a specific industry (e.g., Water Distribution in CEMAC, Fintech, Logistics): Instantly apply the PPF. Tell them exactly what is likely breaking in their current system (e.g., fluid loss tracking, manual KYC) and how JBS will fix it by deploying bespoke APIs and relational databases.
        - If asked about Pricing/Budget: State that deploying a custom enterprise architecture is an investment in capital recovery. Advise them to calculate their 'Chaos Tax' using our COPQ matrix, but typical Tier-1 deployments scale based on the audit.

        KNOWLEDGE BASE INJECTION:
        {$kaizenContext}

        RESPONSE FORMATTING:
        - Use Markdown extensively. Use **bolding** for emphasis.
        - Structure responses with clear headers (e.g., > VULNERABILITY DETECTED, > ENGINEERED SOLUTION).
        - Whenever you mention a URL, format it as a clickable Markdown link like this: [Text](https://url.com).
        - Always close by commanding the user to take action (e.g., 'Initiate a Diagnostic Audit', 'Enter the Kaizen Hub', or 'Secure a Consultation').";

        return $systemPrompt;
    }

    // Gemini generateContent; the key travels in a header, not in the URL
    private function askGemini(string $model, int $timeout, string $systemPrompt, array $history): ?string
    {
        $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))->withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => config('services.gemini.api_key'),
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            "system_instruction" => [
                "parts" => [["text" => $systemPrompt]]
            ],
            "contents" => $history,
            "generationConfig" => [
                "temperature" => 0.25,
                "maxOutputTokens" => 800,
            ]
        ]);

        $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;
        if (! $text) {
            Log::warning('AiAgent: Gemini request failed', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        }
        return $text;
    }

    // DeepSeek chat completions (OpenAI format); history is converted from Gemini's format
    private function askDeepSeek(string $model, int $timeout, string $systemPrompt, array $history): ?string
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($history as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => $turn['parts'][0]['text'] ?? '',
            ];
        }

        $response = Http::timeout($timeout)->connectTimeout(min(5, $timeout))
            ->withToken(config('services.deepseek.api_key'))
            ->post(rtrim(config('services.deepseek.base_url'), '/') . '/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.25,
                'max_tokens' => 800,
            ]);

        $text = $response->successful() ? $response->json('choices.0.message.content') : null;
        if (! $text) {
            Log::warning('AiAgent: DeepSeek request failed', ['model' => $model, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        }
        return $text;
    }
}
