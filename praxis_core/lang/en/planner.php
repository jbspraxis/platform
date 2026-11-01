<?php

// Plan milestones (App\Livewire\Hq\Operations\ScrumMaster, PR 5b). Same keys as lang/fr/planner.php.
return [
    'title' => 'Plan milestones',
    'draft_pending' => 'This project’s plan is in the project draft made from its signed statement of work. Review it and click Create project there; then come back here to plan a milestone at a time.',
    'open_draft' => 'Open the project draft',
    'ready' => 'The suggestions are ready below.',
    'failed' => 'The AI couldn’t plan this: :message',

    'scope' => [
        'label' => 'Scope',
        'sources' => 'Sources used: :list. Edit the text before you ask; the AI reads it as it is.',
        'no_sources' => 'No opportunity holds this project’s scope: type or paste it here.',
        'help' => 'The assistant plans from this text and the adopted plan. Never the full transcripts: it may ask for the one meeting that would settle a question.',
    ],

    'sources' => [
        'sow' => 'signed statement of work :ref',
        'proposal' => 'the approved proposal',
        'questionnaire' => 'the questionnaire’s answers',
        'notes' => 'notes from :count meeting|notes from :count meetings',
        'change_reports' => ':count approved change report|:count approved change reports',
        'legacy' => 'signed proposal :ref',
    ],

    'milestone' => [
        'label' => 'Milestone to plan',
        'suggest' => 'Suggest tasks for this milestone',
        'help' => 'The AI suggests new tasks and edits to this milestone’s tasks, and flags tasks that look duplicated or outside the scope. It never removes a task or names a person. About $0.05 to $0.15 of the AI budget.',
    ],

    'progress' => [
        'title' => 'The AI is planning',
        'text' => 'It reads the scope and the adopted plan. This takes a minute or two; you can leave this page and come back.',
        'transcript' => 'It asked to read the transcript of “:meeting”, and is planning again with it.',
        'why' => 'Why: :why',
    ],

    'review' => [
        'title' => 'The adopted plan',
        'intro' => 'Suggestions for :milestone are inside it. Tick the ones to keep.',
        'none' => 'The AI had nothing to add to this milestone.',
        'expand_all' => 'Expand all',
        'collapse_all' => 'Collapse all',
        'done' => ':done of :total done',
        'suggestions' => ':count suggestion|:count suggestions',
        'no_tasks' => 'No tasks yet.',
        'no_lead' => 'No lead',
        'hours' => ':hours h',
        'no_estimate' => 'no estimate',
        'new' => 'New',
        'edit' => 'Suggested edit',
        'note' => 'Note',
        'why' => 'Why: :why',
    ],

    'save' => 'Save the ticked suggestion (:count)|Save the ticked suggestions (:count)',
    'save_help' => 'Unticked suggestions are set aside. Nothing is ever removed, and no milestone is added.',
    'saved' => 'Saved :count suggestion. The others were set aside.|Saved :count suggestions. The others were set aside.',

    'whole' => [
        'title' => 'Plan for review',
        'draft' => 'Draft the plan',
        'help' => 'For a project with no milestones yet: the AI drafts its milestones and tasks from the scope. About $0.05 to $0.15 of the AI budget.',
        'intro' => 'Check each milestone and task. Saving adds them to the project, two weeks each from today, with the weights shared out; set their real dates and weights on the project page.',
        'code' => 'M:n',
        'tasks' => ':count task|:count tasks',
        'remove_task' => 'Leave out “:task”',
        'remove_milestone' => 'Leave out this milestone',
        'remove_confirm' => 'Leave out this milestone and its tasks?',
        'save' => 'Save the plan',
        'save_help' => 'The milestones and tasks are added to the project page.',
        'saved' => 'Plan saved. Its milestones and tasks are on the project page.',
        'empty_title' => 'No draft yet',
        'empty' => 'Draft the plan from the scope. Its milestones and tasks appear here for you to check before you save them.',
    ],

    'errors' => [
        'rights' => 'Only the project’s lead or someone who can create projects plans its milestones.',
        'scope' => 'Give the AI a scope of at least 50 characters.',
        'scope_long' => 'Keep the scope under :max characters.',
        'milestone' => 'Choose the milestone to plan.',
        'state' => 'This project can’t be planned that way now. Reload the page.',
        'stale' => 'The suggestions changed since this page loaded them, so nothing was saved. Here they are as they stand now.',
        'gone' => 'These suggestions were saved or replaced in another tab, so nothing was saved here.',
        'plan_exists' => 'This project has milestones now, so this draft wasn’t added. Plan a milestone at a time instead.',
        'empty' => 'The draft has no milestones left.',
    ],

    'bell' => [
        'title' => 'Your tasks were updated',
        'changed' => 'The plan for “:project” changed :count of your tasks.|The plan for “:project” changed :count of your tasks.',
    ],
];
