<?php

// The project draft on the opportunity's Project tab (Opportunities PR 5): milestones from the signed SOW, tasks, roles,
// then Create project. Same keys as lang/fr/project_draft.php.
return [
    'title' => 'Project draft',
    'from' => 'From statement of work :ref, version :n · contract total :total before VAT',
    'lead' => 'Project lead: :name (the opportunity’s lead)',
    'read_only' => 'You can read this draft. The owner or the opportunity’s lead edits it and creates the project.',
    'later' => 'Later',
    'unavailable' => 'The AI couldn’t plan the tasks (no Claude key, or the monthly budget is spent), so the milestones from the statement of work are here without tasks. Add them by hand, or draft again once the AI is available.',

    'statuses' => [
        'drafting' => 'Being drafted',
        'ready' => 'Ready to review',
        'created' => 'Project created',
    ],

    'drafting' => [
        'title' => 'The AI is planning the project',
        'text' => 'It gives each milestone of the signed statement of work its goal and tasks, and lists the roles the work needs. This takes a few minutes; you can leave this page and come back.',
        'progress' => 'Planning progress',
        'failed' => 'The AI couldn’t plan the tasks: :message The milestones are here to plan by hand.',
    ],

    'redraft' => [
        'button' => 'Plan again with AI',
        'title' => 'Plan the tasks again with the AI',
        'intro' => 'It replaces every milestone’s goal and tasks and the roles. The dates, weights and amounts stay as they are.',
        'help' => 'Optional: what to stress, how small the tasks should be, roles to use.',
        'confirm' => 'Plan again? Your changes to the goals, tasks and roles are replaced.',
        'submit' => 'Plan again',
    ],

    'project' => [
        'title' => 'The project',
        'intro' => 'Check the name, the description and the start date. Changing the start date moves every milestone by as many days.',
        'name' => 'Project name',
        'description' => 'Description',
        'start' => 'Start date',
        'start_help' => 'The first working day after signing, unless you change it.',
    ],

    'roles' => [
        'title' => 'Roles and who takes them',
        'intro' => 'Pick a person for each role: they become the lead on the role’s tasks. A role without a person leaves its tasks unassigned.',
        'role' => 'Role',
        'person' => 'Person',
        'nobody' => 'Nobody yet',
        'picked' => 'Picked',
        'none' => 'No roles yet.',
        'add' => 'Add a role',
        'new' => 'New role',
        'remove' => 'Remove the role “:role”',
    ],

    'milestones' => [
        'label' => 'Milestones and their tasks',
        'name' => 'Milestone',
        'start' => 'Starts',
        'due' => 'Due',
        'goal' => 'Goal',
        'own' => 'added here, 0%',
        'add' => 'Add a 0% milestone',
        'remove' => 'Remove',
        'remove_confirm' => 'Remove this milestone and its tasks from the draft?',
    ],

    'tasks' => [
        'count' => ':count task|:count tasks',
        'task' => 'Task :n',
        'role' => 'Role',
        'no_role' => 'No role',
        'hours' => 'Hours',
        'milestone' => 'Milestone',
        'description' => 'Description and acceptance criteria',
        'none' => 'No tasks in this milestone yet.',
        'add' => 'Add a task',
        'remove' => 'Remove task :n',
    ],

    'create' => [
        'title' => 'Create the project',
        'intro' => 'Creating it writes these milestones and tasks to the project, sets its lead and dates, and makes it active. The delivery team is told the plan is ready.',
        'summary' => ':count task in :milestones milestones|:count tasks in :milestones milestones',
        'button' => 'Create project',
        'confirm' => 'Create the project from this draft? The plan is then edited on the project page.',
        'done' => 'Project created. Its milestones and tasks are below.',
        'who' => 'The owner or the opportunity’s lead (:name) creates the project from this draft.',
        'errors' => [
            'created' => 'The project was already created from this draft.',
            'running' => 'The AI is still planning. Create the project once its draft is in.',
            'title' => 'Give the project a name.',
            'start' => 'Pick the project’s start date.',
            'dates' => 'Every milestone needs a start and a due date, and must end after it starts.',
            'weights' => 'The milestones’ weights no longer add up to 100%.',
            'project' => 'The project made when the statement of work was signed is missing. Ask an administrator.',
            'lead' => 'The opportunity has no lead, or its lead isn’t an active member of staff. Set the lead on the opportunity (Overview › Edit details); this draft then picks it up.',
            'plan_exists' => 'This project already has milestones, so a second plan would stack on them. Add to the plan on the project page instead.',
            'stale' => 'The draft changed since this page loaded it, so nothing was created. Here it is as it stands now: check it and click Create project again.',
        ],
    ],

    'errors' => [
        'not_saved' => 'Your last change wasn’t saved: the draft changed meanwhile. Here it is as it stands now.',
        'unavailable' => 'The AI isn’t available just now (no Claude key, or the monthly budget is spent).',
    ],

    'notify' => [
        'ready_title' => 'Project draft ready: :title',
        'ready' => 'The milestones and tasks are drafted from the signed statement of work. Review them and click Create project.',
        'failed_title' => 'Project draft without tasks: :title',
        'failed' => 'The AI couldn’t plan the tasks: :message The milestones are ready to plan by hand.',
        'manual_title' => 'Project draft to plan by hand: :title',
        'manual' => 'The statement of work is signed. The AI isn’t available, so add the tasks to its milestones yourself, then click Create project.',
    ],
];
