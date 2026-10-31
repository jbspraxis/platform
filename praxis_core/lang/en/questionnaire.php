<?php

// The opportunity page's Questionnaire tab (docs/OPPORTUNITIES.md section 4, row 3; voice: docs/BRAND.md sections
// 4, 5 and 12). Same keys as lang/fr/questionnaire.php.

return [
    'empty_title' => 'No questionnaire yet',
    'intro' => 'Before the proposal is written, the AI asks the lead what is still unclear. It reads everything filed on this opportunity: the organisation, the cost of poor quality estimate and the meeting notes.',
    'only_lead' => ':name, the lead, drafts and answers this questionnaire.',
    'no_lead' => 'Name a lead in Edit details on the Overview tab: only the lead drafts and answers the questionnaire.',
    'read_only' => 'Only :name, the lead, answers this questionnaire. You can read it.',
    'closed' => 'This opportunity is closed, so its questionnaire can’t change.',
    'drafted' => '{1} 1 question drafted. Your answers save as you go.|[2,*] :count questions drafted. Your answers save as you go.',
    'fewer' => 'The AI drafted :drafted of the :asked questions you asked for. Add your own if something is missing.',
    'summary' => '{1} :answered of 1 question answered|[2,*] :answered of :count questions answered',
    'saved' => 'Answers save as you go',
    'saving' => 'Saving…',
    'statuses' => ['drafting' => 'Being drafted', 'open' => 'Open', 'complete' => 'Complete'],
    'language_names' => ['en' => 'English', 'fr' => 'French'],

    'setup' => [
        'title' => 'Draft the questionnaire',
        'count' => 'Number of questions',
        'count_help' => 'Between :min and :max. Each takes a minute or two to answer.',
        'focus' => 'Focus areas (optional)',
        'focus_help' => 'Tick what the questions should dig into, or leave them all clear to let the AI choose.',
        'note' => 'Anything else the AI should know (optional)',
        'note_help' => 'For example: ‘Payroll is out of scope’ or ‘The new finance director decides’.',
        'language' => 'The questions are written in :language, this opportunity’s language.',
        'cost' => 'Drafting uses Claude Sonnet and costs a few cents of this month’s AI budget.',
        'no_notes' => 'No meeting notes are filed yet, so the AI only has the organisation’s details. The questions get sharper once the discovery meeting’s notes are in.',
        'submit' => 'Draft the questions',
        'submitting' => 'Asking the AI…',
        'failed' => 'The last draft didn’t work. :message',
    ],

    'focus' => [
        'scope' => 'Scope and priorities',
        'processes' => 'Processes and controls',
        'systems' => 'Systems and data',
        'people' => 'People and change',
        'timeline' => 'Timeline and milestones',
        'commercial' => 'Budget and commercial terms',
        'risks' => 'Risks and compliance',
        'governance' => 'Governance and decision-makers',
    ],

    'drafting' => [
        'title' => '{1} Drafting 1 question…|[2,*] Drafting :count questions…',
        'text' => 'Usually ready within 2 minutes. You can leave this page: the questions will be here when you come back.',
        'progress' => 'Drafting progress',
        'queued' => 'Waiting for the AI to start.',
    ],

    'question' => [
        'number' => 'Question :n',
        'added_by_lead' => 'Added by the lead',
        'answered' => 'Answered',
        'not_answered' => 'Not answered',
        'why' => 'Why it matters:',
        'recommended' => 'Recommended',
        'reason' => 'Why the AI recommends it:',
        'own' => 'My own answer',
        'own_label' => 'Your answer',
        'detail' => 'Add detail (optional)',
        'answer' => 'Answer',
        'detail_label' => 'Detail',
        'no_suggestions' => 'No suggested answers yet. Write your answer, or ask the AI to suggest some.',
        'redraft' => 'Redraft',
        'suggest' => 'Suggest answers',
        'suggest_again' => 'Suggest other answers',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove question :n and its answer?',
        'removed' => 'Question :n removed.',
        'redrafting' => 'The AI is redrafting this question…',
        'suggesting' => 'The AI is suggesting answers…',
        'failed' => 'The redraft didn’t work. :message',
        'dismiss' => 'Dismiss this message',
    ],

    'redraft' => [
        'title' => 'Redraft question :n',
        'suggest_title' => 'Suggest answers to question :n',
        'note' => 'What should change? (optional)',
        'suggest_note' => 'Anything the AI should know? (optional)',
        'note_help' => 'For example: ‘Ask about the branches, not head office’. Only this question is sent to the AI.',
        'suggest_help' => 'The AI keeps your wording and suggests answers, with the one it recommends. Only this question is sent.',
        'clears' => 'Its answer will be cleared.',
        'submit' => 'Redraft the question',
        'suggest_submit' => 'Suggest answers',
    ],

    'add' => [
        'button' => 'Add a question',
        'title' => 'Add your own question',
        'question' => 'Question',
        'why' => 'Why it matters (optional)',
        'why_help' => 'Once it’s added, you can answer it yourself or ask the AI to suggest answers.',
        'submit' => 'Add the question',
        'done' => 'Question :n added.',
    ],

    'complete' => [
        'button' => 'Mark complete',
        'confirm' => 'Mark the questionnaire complete? The answers are locked and the proposal is drafted from them.',
        'blocked' => 'Answer every question, or remove the ones that don’t apply, to mark it complete.',
        'done' => 'Questionnaire complete. The proposal will be drafted from these answers.',
        'locked' => 'Completed by :name on :date. The answers are locked and feed the proposal.',
    ],

    'restart' => [
        'button' => 'Start again',
        'confirm' => 'Start again? These questions and their answers are set aside, and you draft a new questionnaire.',
        'done' => 'The questionnaire was set aside. Draft a new one below.',
    ],

    'errors' => [
        'count' => 'Choose between :min and :max questions.',
        'focus' => 'Choose focus areas from the list.',
        'note_long' => 'Shorten this to 1,000 characters or fewer.',
        'question' => 'Write the question you want to answer.',
        'question_long' => 'Shorten the question to 1,000 characters or fewer.',
        'answer_long' => 'Shorten the answer to 5,000 characters or fewer, so it can be saved.',
        'incomplete' => '{1} 1 question still needs an answer.|[2,*] :count questions still need an answer.',
        'redrafting' => 'Wait for the AI to finish redrafting, then mark it complete.',
        'empty' => 'Add at least one question, then mark it complete.',
    ],
];
