<?php

// Sprint handovers (docs/OPPORTUNITIES.md section 12, PR 6): the handover meeting scheduled from the sprint board, the
// points raised in it, confirmed as QA tickets. defaults, ticket and signoff are in the opportunity's language (the
// client reads them); the rest is HQ's.
return [

    'severity' => ['Low' => 'Low', 'Medium' => 'Medium', 'Critical' => 'Critical'],

    'defaults' => [
        'title' => 'Handover: :sprint',
        'agenda' => 'Handing over :sprint (:start to :end).',
        'delivered' => 'What this sprint delivered:',
        'more' => '…and :count more task|…and :count more tasks',
        'points' => 'Your questions and any points to fix: we record each one and tell you how it was closed.',
    ],

    'form' => [
        'title' => 'Schedule the handover',
        'callout' => 'This meeting hands over :sprint. Once its transcript is in, the AI lists the points raised for you to confirm as issues.',
    ],

    'tag' => 'Handover: :sprint',
    'counts' => [
        'draft' => '{1} 1 point to review|[2,*] :count points to review',
        'confirmed' => '{1} 1 issue|[2,*] :count issues',
    ],

    'points' => [
        'title' => 'Points raised',
        'help' => 'Confirm each point to create an issue on the project: it appears in Quality and issues, on the sprint board and in the client’s portal. Nothing is created until you confirm.',
        'help_read' => 'The opportunity’s lead confirms each point as an issue on the project.',
        'waiting' => 'Once the transcript is in, the AI lists the points raised here.',
        'drafting' => 'The AI is listing the points raised…',
        'failed' => 'The AI couldn’t list the points: :reason',
        'none_found' => 'The AI found no points to act on in this meeting. Add one by hand if it missed something.',
        'not_drafted' => 'The points raised haven’t been listed yet.',
        'draft' => 'List the points',
        'redraft' => 'List the points again',
        'retry' => 'Try again',
        'redraft_help' => 'A new list replaces the points still to review. Confirmed and left-out points stay.',
        'empty' => 'No points yet.',
        'awaiting' => 'Waiting for the lead to confirm.',
        'by_hand' => 'Added by hand',
        'left_out' => 'Left out (:count)',
        'statuses' => ['draft' => 'To review', 'confirmed' => 'Issue created', 'dismissed' => 'Left out'],
        'issues' => 'Issues created',
        'issue' => 'Issue: :status',
        'labelled' => ':label: :value',
        'closed_on' => ':status on :date',
        'removed' => 'Issue removed',
        'reply' => 'Last reply: :reply',
        'open_issue' => 'Open in Quality and issues',
    ],

    'fields' => [
        'title' => 'Point',
        'detail' => 'What was asked',
        'raised_by' => 'Raised by',
        'severity' => 'Severity',
        'task' => 'Sprint task',
        'no_task' => 'No task',
    ],

    'actions' => [
        'confirm' => 'Create the issue',
        'dismiss' => 'Leave out',
        'restore' => 'Bring back',
        'add' => 'Add a point',
        'add_title' => 'Add a point',
        'add_submit' => 'Add the point',
        'confirm_all' => '{1} Create the issue|[2,*] Create all :count issues',
        'confirm_all_ask' => 'Create an issue for each point still to review? The client sees them in their portal.',
        'copy' => 'Copy for the sign-off',
        'copied' => 'Copied',
    ],

    'done' => [
        'confirmed' => 'Issue created: “:title”. It’s in Quality and issues and on the sprint board.',
        'confirmed_all' => '{0} No issues created: every point was already confirmed or left out.|{1} 1 issue created. It’s in Quality and issues and on the sprint board.|[2,*] :count issues created. They’re in Quality and issues and on the sprint board.',
        'dismissed' => '“:title” left out: no issue created.',
    ],

    'errors' => [
        'sprint' => 'That sprint isn’t on this opportunity’s project. Open the project’s sprint board and try again.',
        'cannot_schedule' => 'You can’t schedule meetings for this opportunity. Ask someone who manages opportunities to schedule the handover.',
        'decided' => 'This point was already confirmed or left out. The list is up to date now.',
        'no_project' => 'This opportunity has no project, so no issue can be created.',
        'title' => 'Give the point a title.',
        'title_long' => 'Shorten the title to 200 characters or fewer.',
        'detail_long' => 'Shorten what was asked to 2,000 characters or fewer.',
        'raised_by_long' => 'Shorten the name to 150 characters or fewer.',
        'severity' => 'Choose Low, Medium or Critical.',
        'task' => 'Choose a task from this project, or no task.',
        'invalid' => 'That can’t be saved. Refresh the page and try again.',
        'full' => 'This meeting already holds :max points, the most it can.',
    ],

    'ticket' => [
        'raised_by' => 'Raised by :name in the handover meeting “:meeting” on :date.',
        'raised' => 'Raised in the handover meeting “:meeting” on :date.',
    ],

    'signoff' => [
        'heading' => 'Points raised in “:title” (:date)',
        'line' => ':number. :title (:severity): :status.',
        'closed_on' => ':status on :date',
        'removed' => 'Issue removed',
        'reply' => 'Last reply: “:reply”',
        'none' => 'No points were confirmed as issues.',
    ],

    'notify' => [
        'title' => 'Handover point on your task',
        'message' => '“:title”, raised in a handover meeting, concerns a task assigned to you. See it in Quality and issues.',
    ],

    'board' => [
        'title' => 'Sprint handover',
        'none' => 'No handover meeting for this sprint yet.',
        'schedule' => 'Schedule the handover',
        'schedule_another' => 'Schedule another',
        'no_opportunity' => 'This project has no opportunity, so its handover meeting can’t be scheduled here: meetings belong to an opportunity.',
        'no_permission' => 'Someone who manages opportunities schedules the handover meeting.',
        'when' => ':date, :time WAT',
        'open' => 'Open the meeting',
        'review' => '{0} No points to review|{1} 1 point to review|[2,*] :count points to review',
        'issue_count' => '{0} no issues yet|{1} 1 issue (:open open)|[2,*] :count issues (:open open)',
        'issues' => '{1} Open issue|[2,*] :count open issues',
    ],
];
