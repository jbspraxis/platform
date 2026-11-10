<?php

// AI jobs (App\Support\Ai\Ai, docs/OPPORTUNITIES.md 8.3): what a failed run says, and the purposes' names.
// ai_runs.error holds the key (ai.errors.…), so each person reads it in their own language. Same keys as lang/fr/ai.php.

return [
    'errors' => [
        'budget' => 'This month’s AI budget is spent, so nothing was drafted. The owner can raise it in Settings and access › Integrations.',
        'not_configured' => 'The AI isn’t set up yet: add the Claude key in Settings and access › Integrations.',
        'key' => 'Claude refused the API key. Check it in Settings and access › Integrations.',
        'credit' => 'The Claude credit has run out, so nothing was drafted. Add credit on platform.claude.com, then try again.',
        'busy' => 'Claude is busy right now. Try again in a few minutes.',
        'unreachable' => 'HQ couldn’t reach Claude. Try again in a minute.',
        'refused' => 'Claude declined to write this. Reword the instructions, then try again.',
        'too_long' => 'The draft was cut off because it ran too long. Narrow the instructions, then try again.',
        'invalid' => 'The draft came back incomplete, so it wasn’t saved. Try again.',
        'stuck' => 'The job stopped before it finished. Try again; if it happens again, check System health.',
        'failed' => 'Something went wrong while drafting. Try again in a minute; if it keeps happening, check System health.',
    ],

    'purposes' => [
        'meeting_notes' => 'Meeting notes',
        'client_minutes' => 'Client minutes',
        'handover_points' => 'Milestone handover points',
        'questionnaire' => 'Questionnaire',
        'questionnaire_question' => 'Questionnaire: one question',
        'proposal' => 'Proposal',
        'proposal_changes' => 'What changed',
        'sow' => 'Statement of work',
        'project_draft' => 'Project draft',
        'plan_milestone' => 'Milestone plan',
        'plan_project' => 'Project plan',
        'broadcast_draft' => 'Broadcast draft',
        'broadcast_translate' => 'Broadcast translation',
        'outreach_email' => 'Outreach email',
        'outreach_reply' => 'Outreach reply sorting',
        'company_document_read' => 'Company document reading',
        'lead_intel_read' => 'Intel report reading',
        'lead_intel_prefill' => 'Add a lead: fill from report',
        'lead_pitch_brief' => 'Pitch brief',
        'consultation_prep' => 'Consultation line',
        'connection_test' => 'Key test',
    ],
];
