<?php

// HQ › Leads and proposals › Opportunities (docs/OPPORTUNITIES.md; voice: docs/BRAND.md sections 4, 5 and 12).
// Same keys as lang/fr/opportunities.php.

return [
    'nav' => 'Opportunities',
    'title' => 'Opportunities',
    'subtitle' => 'Every piece of work with a client, from the first meeting to a signed statement of work and a staffed project.',
    'no_lead' => 'No lead',
    'none_here' => 'None at this stage',
    'cancel' => 'Cancel',
    'count' => '{0} No opportunities|{1} 1 opportunity|[2,*] :count opportunities',
    'meetings_count' => '{1} 1 meeting|[2,*] :count meetings',
    'proposals_count' => '{1} 1 proposal|[2,*] :count proposals',

    'filters' => [
        'search' => 'Search',
        'search_hint' => 'Title or organisation',
        'lead' => 'Lead',
        'everyone' => 'Everyone',
        'mine' => 'Mine',
        'closed' => 'Show won and lost',
        'view' => 'View',
        'board' => 'Board',
        'table' => 'Table',
    ],

    'empty' => [
        'title' => 'No opportunities yet',
        'text' => 'Open one for an organisation to keep its meetings, questionnaire, proposal and statement of work together.',
        'filtered_title' => 'No opportunities match',
        'filtered_text' => 'Change the search or the lead, or show won and lost opportunities too.',
        'clear' => 'Clear the filters',
    ],

    'fields' => [
        'title' => 'Title',
        'organisation' => 'Organisation',
        'stage' => 'Stage',
        'lead' => 'Lead',
        'language' => 'Language',
        'updated' => 'Updated',
        'lost_reason' => 'Why it was lost',
    ],

    'stages' => [
        'discovery' => 'Discovery',
        'questionnaire' => 'Questionnaire',
        'proposal' => 'Proposal in progress',
        'proposal_sent' => 'Proposal sent',
        'proposal_approved' => 'Proposal approved',
        'sow' => 'SOW in progress',
        'sow_sent' => 'SOW sent',
        'won' => 'Won',
        'lost' => 'Lost',
        'on_hold' => 'On hold',
    ],

    'languages' => [
        'en' => 'English',
        'fr' => 'French',
    ],

    'sources' => [
        'backfill' => 'From earlier records',
        'website_booking' => 'From a website booking',
    ],

    'create' => [
        'button' => 'Open an opportunity',
        'title' => 'Open an opportunity',
        'intro' => 'One opportunity for each piece of work. An organisation can have several.',
        'find_organisation' => 'Find the organisation',
        'find_hint' => 'Type part of the name',
        'choose_organisation' => 'Choose an organisation',
        'title_help' => 'What the work is about, for example ‘Payroll for 3 subsidiaries’.',
        'choose_lead' => 'Choose a staff member',
        'lead_help' => 'Runs the opportunity, approves its documents and becomes the project lead.',
        'language_help' => 'AI drafts and client emails are written in this language.',
        'submit' => 'Open the opportunity',
        'submitting' => 'Opening…',
        'done' => 'Opportunity ‘:title’ opened.',
        'errors' => [
            'organisation' => 'Choose the organisation this work is for.',
            'title' => 'Add a title, so the team knows what the work is about.',
            'title_long' => 'Shorten the title to 200 characters or fewer.',
            'lead' => 'Choose the staff member who leads this opportunity.',
            'language' => 'Choose English or French.',
        ],
    ],

    'tabs' => [
        'overview' => 'Overview',
        'meetings' => 'Meetings',
        'questionnaire' => 'Questionnaire',
        'proposals' => 'Proposals',
        'sow' => 'Statement of work',
        'project' => 'Project',
    ],

    'page' => [
        'back' => 'All opportunities',
        'led_by' => 'Led by :name',
        'edit' => 'Edit details',
        'sections' => 'Opportunity sections',
        'save' => 'Save details',
        'saved' => 'Details saved.',
        'stage_title' => 'Stage',
        'opened' => 'Opened',
        'closed' => 'Closed',
        'move_to' => 'Move to',
        'save_stage' => 'Save the stage',
        'stage_saved' => 'Stage changed to ‘:stage’.',
        'organisation' => 'Organisation',
        'name' => 'Name',
        'industry' => 'Industry',
        'location' => 'Location',
        'contact' => 'Primary contact',
        'copq' => 'Cost of poor quality',
        'copq_amount' => 'Estimated cost a year',
        'bottleneck' => 'Main bottleneck',
        'estimated_on' => 'Estimated on',
        'no_copq' => 'No estimate yet. Add one during the free audit.',
        'history' => 'History',
        'system' => 'HQ',
        'history_backfill' => 'Created from the proposals, projects and free audits recorded before opportunities existed.',
        'history_none' => 'No changes yet.',
        'errors' => [
            'stage' => 'Choose a stage from the list.',
            'lost_reason' => 'Say why the opportunity was lost, so the team can learn from it.',
            'lost_reason_long' => 'Shorten the reason to 2,000 characters or fewer.',
        ],
    ],

    'formats' => [
        'date' => 'j F Y',
        'time' => 'H:i',
    ],

    'history' => [
        'OPPORTUNITY_OPENED' => 'Opened the opportunity.',
        'OPPORTUNITY_UPDATED' => 'Changed the details.',
        'OPPORTUNITY_STAGE_CHANGED' => 'Changed the stage.',
    ],

    'stubs' => [
        'meetings' => 'Booking meetings from here, with a Google Meet link, invitations and transcripts collected automatically, is coming next. Meetings from earlier free audits are listed below.',
        'questionnaire' => 'Once meetings are filed, the AI drafts the questions the lead answers before the proposal is written. This is coming soon.',
        'proposals' => 'Drafting the proposal from this opportunity is coming soon. Until then, proposals made in Proposals are listed here.',
        'sow' => 'The statement of work is drafted from the approved proposal and signed by the client. This is coming soon.',
        'project' => 'The project is drafted here once the statement of work is signed.',
    ],

    'meetings' => [
        'when' => 'When',
        'title' => 'Meeting',
        'kind' => 'Type',
        'status' => 'Status',
        'transcript' => 'Transcript',
        'attendees' => '{0} No attendees recorded|{1} 1 attendee|[2,*] :count attendees',
        'kinds' => ['client' => 'Client', 'internal' => 'Internal'],
        'statuses' => ['requested' => 'Requested', 'scheduled' => 'Scheduled', 'held' => 'Held', 'cancelled' => 'Cancelled'],
        'transcripts' => [
            'waiting' => 'Waiting for the meeting to end',
            'collecting' => 'Being collected',
            'ready' => 'Filed',
            'not_found' => 'Not found: add it by hand',
            'manual' => 'Add by hand',
        ],
        'empty_title' => 'No meetings yet',
        'empty_text' => 'Meetings of this opportunity will be listed here.',
    ],

    'questionnaire' => [
        'status' => 'Status',
        'questions' => 'Questions',
        'statuses' => ['drafting' => 'Being drafted', 'open' => 'Open', 'complete' => 'Complete'],
        'empty_title' => 'No questionnaire yet',
    ],

    'proposals' => [
        'reference' => 'Reference',
        'title' => 'Title',
        'status' => 'Status',
        'value' => 'Value',
        'created' => 'Created',
        'draft_ref' => 'No reference yet',
        'statuses' => [
            'draft' => 'Draft',
            'pending_auth' => 'Waiting for signature',
            'authorized' => 'Signed',
            'converted' => 'Signed, project created',
            'expired' => 'Expired',
            'rejected' => 'Declined',
        ],
        'empty_title' => 'No proposals yet',
        'empty_text' => 'Proposals of this opportunity will be listed here.',
    ],

    'sow' => [
        'empty_title' => 'No statement of work yet',
        'version' => 'version :n',
        'statuses' => [
            'draft' => 'Draft',
            'awaiting_approval' => 'Waiting for approval',
            'sent' => 'Sent',
            'changes_requested' => 'Changes requested',
            'signed' => 'Signed',
            'superseded' => 'Replaced by a later version',
        ],
    ],

    'project' => [
        'status' => 'Status',
        'start' => 'Start',
        'end' => 'Target end',
        'open_board' => 'Open the sprint board',
        'empty_title' => 'No project yet',
        'statuses' => ['scoping' => 'Scoping', 'active' => 'Active', 'locked' => 'Locked', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
    ],
];
