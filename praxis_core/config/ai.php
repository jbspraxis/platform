<?php

/*
|--------------------------------------------------------------------------
| AI jobs (docs/OPPORTUNITIES.md 6.2 and 8.3)
|--------------------------------------------------------------------------
| Every AI job goes through App\Support\Ai\Ai and is logged in ai_runs with its tokens and cost.
| Claude Sonnet writes the documents that win or lose work; Claude Haiku the short, high-volume jobs.
| Gemini stays for the website chat, Mail AI and the cost calculator (services.gemini), and can run any
| purpose here by setting its provider to 'gemini'.
|
| Keeping the cost down (the owner started with $5 of credit): the company brief, then the opportunity
| context, open every request as cached system blocks; effort is set per purpose; one draft per click (the
| job is never retried); the monthly budget (HQ › System › Integrations, default below) stops jobs before
| the Claude Console's own spend limit is reached.
*/

$line = ['type' => 'array', 'items' => ['type' => 'string']];
$nextSteps = ['type' => 'array', 'items' => [
    'type' => 'object',
    'properties' => [
        'action' => ['type' => 'string'],
        'owner' => ['type' => 'string', 'description' => 'A role or organisation, never a guess at a name; empty if not agreed'],
        'due' => ['type' => 'string', 'description' => 'As agreed in the meeting, or empty'],
    ],
    'required' => ['action', 'owner', 'due'],
    'additionalProperties' => false,
]];
$question = [
    'type' => 'object',
    'properties' => [
        'question' => ['type' => 'string'],
        'why_it_matters' => ['type' => 'string'],
        'options' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '3 to 5 suggested answers'],
        'recommended_option' => ['type' => 'integer', 'description' => 'Index in options of the answer you recommend, from 0'],
        'recommendation_reason' => ['type' => 'string'],
    ],
    'required' => ['question', 'why_it_matters', 'options', 'recommended_option', 'recommendation_reason'],
    'additionalProperties' => false,
];
$sections = ['type' => 'array', 'items' => [
    'type' => 'object',
    'properties' => [
        'key' => ['type' => 'string', 'description' => 'snake_case, stable between versions'],
        'title' => ['type' => 'string', 'description' => 'An action title: a full sentence stating the takeaway'],
        'body' => ['type' => 'string', 'description' => 'Plain paragraphs separated by blank lines; "- " starts a list item'],
    ],
    'required' => ['key', 'title', 'body'],
    'additionalProperties' => false,
]];

return [

    // Default monthly cap in US dollars; HQ › System › Integrations overrides it (ai.monthly_budget_usd)
    'monthly_budget_usd' => (float) env('AI_MONTHLY_BUDGET_USD', 5),

    // Jobs wait on their own queue, run by the scheduler every minute (routes/console.php)
    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', 'database'),
        'name' => 'ai',
    ],

    'providers' => [
        'claude' => [
            // Sonnet only: a request its safety checks decline is retried server-side on another model
            'fallback_beta' => 'server-side-fallback-2026-07-01',
            'fallback_models' => ['claude-sonnet-5-5'],
            'timeout' => 600,
            'max_retries' => 2,
        ],
        'gemini' => [
            'model' => env('GEMINI_AI_MODEL', 'gemini-2.5-flash'),
            'timeout' => 120,
        ],
    ],

    /*
    | USD per million tokens. cache_write is the 5-minute cache (1.25 × input). Haiku's long_context rates
    | apply when a prompt is over 100,000 tokens. A model missing here is priced as the dearest one, so the
    | budget errs on the safe side. Gemini is billed by Google, not from the Claude credit.
    */
    'prices' => [
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125,
            'long_context' => ['above' => 100000, 'input' => 0.50, 'output' => 2.50, 'cache_read' => 0.05, 'cache_write' => 0.625]],
        // Where Sonnet 5.5's server-side fallback sends a declined request
        'claude-sonnet-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20, 'cache_write' => 2.50],
        'claude-opus-4-8' => ['input' => 5.00, 'output' => 25.00, 'cache_read' => 0.50, 'cache_write' => 6.25],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50, 'cache_read' => 0.03, 'cache_write' => 0.30],
    ],

    /*
    | Purposes. task: what the model is asked to do (the opportunity's language rule, the lead's instructions
    | and the material follow it). stream: long documents stream, so nothing times out and progress shows.
    | sync: short enough for Ai::now(). expected_chars: roughly how long the answer is, for the progress bar.
    | The PR that builds each screen refines its task and schema here (and notes it in the runbook, section 12).
    */
    'purposes' => [

        'meeting_notes' => [
            'provider' => 'claude', 'model' => 'claude-haiku-5-5', 'effort' => 'medium', 'max_tokens' => 8000,
            'stream' => false, 'sync' => false, 'expected_chars' => 4000,
            'task' => 'Write the internal notes of the meeting below for the JBS Praxis team: a short summary, the decisions taken, what the client needs, the risks you see, the questions still open and the next steps. Only what was said: never add a fact, a figure or a commitment.',
            'schema' => ['type' => 'object', 'properties' => [
                'summary' => ['type' => 'string'], 'decisions' => $line, 'needs' => $line, 'risks' => $line,
                'open_questions' => $line, 'next_steps' => $nextSteps,
            ], 'required' => ['summary', 'decisions', 'needs', 'risks', 'open_questions', 'next_steps'], 'additionalProperties' => false],
        ],

        'client_minutes' => [
            'provider' => 'claude', 'model' => 'claude-haiku-5-5', 'effort' => 'low', 'max_tokens' => 4000,
            'stream' => false, 'sync' => true, 'expected_chars' => 2000,
            'task' => 'Write short minutes of the meeting below for the client: decisions and next steps only, warm and brief. Leave out anything internal to JBS Praxis, prices not yet agreed and anything said in confidence.',
            'schema' => ['type' => 'object', 'properties' => [
                'subject' => ['type' => 'string'], 'summary' => ['type' => 'string'], 'decisions' => $line, 'next_steps' => $nextSteps,
            ], 'required' => ['subject', 'summary', 'decisions', 'next_steps'], 'additionalProperties' => false],
        ],

        // PR 3: the whole questionnaire, from the opportunity's context (meeting notes, not full transcripts). The
        // lead's count and focus areas come as instructions. App\Support\Opportunities\Questionnaires keeps the
        // first N questions that have 3 or more distinct answers and a recommendation that points at one of them.
        'questionnaire' => [
            'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'effort' => 'medium', 'max_tokens' => 16000,
            'stream' => true, 'sync' => false, 'expected_chars' => 12000,
            'task' => 'Draft the questions the opportunity’s lead answers before we write the proposal: what is still unclear, undecided or only assumed after the meetings, most important first. Ask exactly as many questions as the lead asks for, and never what the meetings already settled. Each question is one decision or fact, in plain words; why_it_matters says what it changes in the proposal (scope, approach, timeline, team or price). Offer 3 to 5 suggested answers that are realistic, distinct and specific to this client (never “other” or “not sure”: the lead can always write their own). Name the one you recommend (recommended_option is its index in options, from 0) and why, from what was said in the meetings or, when nothing was said, from good practice for a client like this one.',
            'schema' => ['type' => 'object', 'properties' => [
                'questions' => ['type' => 'array', 'items' => $question],
            ], 'required' => ['questions'], 'additionalProperties' => false],
        ],

        // PR 3: one question again, for "Redraft" (an AI question) or "Suggest answers" (the lead's own question).
        // Only that question goes with it (6.2, cost rule 3): the organisation, the questionnaire's focus, the
        // question and the others' wording, not the meetings.
        'questionnaire_question' => [
            'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'effort' => 'medium', 'max_tokens' => 6000,
            'stream' => false, 'sync' => false, 'expected_chars' => 1200,
            'task' => 'Write one question of the lead’s questionnaire, as MATERIAL says. To replace a question: ask a better one about what the old one was after, or what the lead’s instructions ask for, different from the other questions listed. For the lead’s own question: keep its wording exactly and suggest the answers. Either way, why_it_matters says what it changes in the proposal; offer 3 to 5 suggested answers that are realistic, distinct and specific to this client (never “other” or “not sure”); name the one you recommend (recommended_option is its index in options, from 0) and why.',
            'schema' => $question,
        ],

        'proposal' => [
            'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'effort' => 'high', 'max_tokens' => 32000,
            'stream' => true, 'sync' => false, 'expected_chars' => 18000,
            'task' => 'Draft the proposal for this opportunity from the meetings, the notes and the lead\'s answers. Open with a one-page executive summary (the situation, what we recommend, what it changes, what it takes), then the approach following the Praxis Method, scope and exclusions, the team and governance, and the timeline. Suggest line items by their financial dictionary code only: prices come from the dictionary.',
            'schema' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'], 'sections' => $sections,
                'suggested_line_items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'code' => ['type' => 'string'], 'reason' => ['type' => 'string'],
                ], 'required' => ['code', 'reason'], 'additionalProperties' => false]],
            ], 'required' => ['title', 'sections', 'suggested_line_items'], 'additionalProperties' => false],
        ],

        'proposal_changes' => [
            'provider' => 'claude', 'model' => 'claude-haiku-5-5', 'effort' => 'low', 'max_tokens' => 4000,
            'stream' => false, 'sync' => true, 'expected_chars' => 1500,
            'task' => 'Compare the two versions below and say what changed, section by section, in a few plain words each, for the client.',
            'schema' => ['type' => 'object', 'properties' => [
                'summary' => ['type' => 'string'],
                'changes' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'section_key' => ['type' => 'string'], 'change' => ['type' => 'string'],
                ], 'required' => ['section_key', 'change'], 'additionalProperties' => false]],
            ], 'required' => ['summary', 'changes'], 'additionalProperties' => false],
        ],

        'sow' => [
            'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'effort' => 'high', 'max_tokens' => 32000,
            'stream' => true, 'sync' => false, 'expected_chars' => 20000,
            'task' => 'Draft the statement of work from the approved proposal and the lead\'s answers: scope, deliverables and acceptance, exclusions, assumptions and dependencies, milestones and timeline, roles and governance, change control. Precise and contractual; do not promise outcomes.',
            'schema' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'], 'sections' => $sections,
            ], 'required' => ['title', 'sections'], 'additionalProperties' => false],
        ],

        'project_draft' => [
            'provider' => 'claude', 'model' => 'claude-sonnet-5-5', 'effort' => 'high', 'max_tokens' => 32000,
            'stream' => true, 'sync' => false, 'expected_chars' => 16000,
            'task' => 'Draft the project from the signed statement of work: phases, milestones, two-week sprints, tasks with the role that does each and an estimate in hours, and the roles needed.',
            'schema' => ['type' => 'object', 'properties' => [
                'project' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'description' => ['type' => 'string']],
                    'required' => ['title', 'description'], 'additionalProperties' => false],
                'phases' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'key' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'],
                    'start_week' => ['type' => 'integer'], 'weeks' => ['type' => 'integer'],
                ], 'required' => ['key', 'title', 'description', 'start_week', 'weeks'], 'additionalProperties' => false]],
                'milestones' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'phase_key' => ['type' => 'string'], 'title' => ['type' => 'string'], 'due_week' => ['type' => 'integer'],
                ], 'required' => ['phase_key', 'title', 'due_week'], 'additionalProperties' => false]],
                'sprints' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'number' => ['type' => 'integer'], 'goal' => ['type' => 'string'],
                ], 'required' => ['number', 'goal'], 'additionalProperties' => false]],
                'tasks' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'phase_key' => ['type' => 'string'], 'sprint_number' => ['type' => 'integer'], 'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'], 'role_key' => ['type' => 'string'], 'estimate_hours' => ['type' => 'number'],
                ], 'required' => ['phase_key', 'sprint_number', 'title', 'description', 'role_key', 'estimate_hours'], 'additionalProperties' => false]],
                'roles' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'key' => ['type' => 'string'], 'title' => ['type' => 'string'], 'responsibilities' => ['type' => 'string'],
                ], 'required' => ['key', 'title', 'responsibilities'], 'additionalProperties' => false]],
            ], 'required' => ['project', 'phases', 'milestones', 'sprints', 'tasks', 'roles'], 'additionalProperties' => false],
        ],

        // System › Integrations › Test the Claude key: one tiny Haiku request (a fraction of a cent)
        'connection_test' => [
            'provider' => 'claude', 'model' => 'claude-haiku-5-5', 'effort' => 'low', 'max_tokens' => 1024,
            'stream' => false, 'sync' => true, 'expected_chars' => 20, 'brief' => false,
            'task' => 'Reply with ok set to true.',
            'schema' => ['type' => 'object', 'properties' => ['ok' => ['type' => 'boolean']], 'required' => ['ok'], 'additionalProperties' => false],
        ],
    ],
];
