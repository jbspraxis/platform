<?php

// HQ › Settings and access › Integrations (docs/OPPORTUNITIES.md section 6). Same keys as lang/fr/integrations.php.

return [
    'nav' => 'Integrations',
    'title' => 'Integrations',
    'subtitle' => 'The Google account that holds meetings, the Claude key that drafts documents, and the monthly AI budget.',
    'copy' => 'Copy',
    'copied' => 'Copied',
    'testing' => 'Testing…',
    'secret_saved' => 'Saved. It is never shown again: enter a new one only to replace it.',

    'google' => [
        'title' => 'Google (Calendar, Meet and Docs)',
        'intro' => 'HQ creates meetings with a Meet link in :account’s calendar and collects their transcripts. Enter the OAuth client from the Google Cloud project, save it, then connect the account.',
        'status' => [
            'connected' => 'Connected as :email',
            'not_connected' => 'Not connected',
            'reconnect' => 'Reconnect needed',
        ],
        'redirect_label' => 'Authorised redirect URI',
        'redirect_help' => 'It must be listed exactly like this on the OAuth client in Google Cloud.',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'secret_help' => 'From the JSON file you downloaded for the OAuth client.',
        'save_client' => 'Save the client',
        'connect' => 'Connect Google',
        'reconnect' => 'Reconnect Google',
        'test' => 'Test the connection',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Disconnect Google? HQ stops creating meetings and collecting transcripts until you connect again.',
        'flash' => [
            'client_first' => 'Save the client ID and secret first, then connect Google.',
            'state' => 'That sign-in to Google didn’t start from HQ, so nothing was connected. Click Connect Google again.',
            'cancelled' => 'Google wasn’t connected because access wasn’t allowed. Click Connect Google and allow every permission.',
            'failed' => 'Google didn’t confirm the connection. Click Connect Google again.',
            'connected' => 'Google connected as :email.',
            'missing_scopes' => 'Some permissions weren’t granted, so meetings or transcripts won’t work. Click Reconnect Google and tick every box.',
            'other_account' => 'Connected as :email. Meetings will go into that calendar; the agreed account is :expected.',
            'client_saved' => 'Client saved. Now click Connect Google.',
            'test_ok' => 'The connection works (:email).',
            'disconnected' => 'Google disconnected.',
        ],
        'errors' => [
            'not_connected' => 'Google isn’t connected yet. Connect it in Settings and access › Integrations.',
            'revoked' => 'Google withdrew HQ’s access. Reconnect it in Settings and access › Integrations.',
            'quota' => 'Google is limiting requests for now. Try again in a few minutes.',
            'scopes' => 'Google refused because a permission wasn’t granted. Reconnect Google and tick every box.',
            'not_found' => 'Google couldn’t find that meeting, recording or file.',
            'client' => 'Google refused the client ID or secret. Check both in Settings and access › Integrations.',
            'failed' => 'Google didn’t answer as expected. Try again in a minute; if it keeps happening, check System health.',
            'client_id_required' => 'Enter the client ID.',
            'client_id_format' => 'Enter the client ID as Google shows it, ending in .apps.googleusercontent.com.',
            'secret_required' => 'Enter the client secret.',
        ],
    ],

    'claude' => [
        'title' => 'Claude (Anthropic API)',
        'intro' => 'Claude drafts the questionnaire, proposal, statement of work and project, and writes meeting notes and minutes. Create the key in the HQ workspace on platform.claude.com.',
        'status' => [
            'saved' => 'Key saved',
            'env' => 'Key from the server settings',
            'missing' => 'No key',
        ],
        'key' => 'API key',
        'key_help' => 'Starts with sk-ant-. Stored encrypted.',
        'save' => 'Save the key',
        'test' => 'Test the key',
        'remove' => 'Remove the key',
        'remove_confirm' => 'Remove the Claude key? AI drafts stop until a new key is saved.',
        'models' => 'Claude Sonnet writes the questionnaire, proposal, statement of work and project draft; Claude Haiku writes meeting notes, client minutes and change summaries.',
        'flash' => [
            'saved' => 'Claude key saved.',
            'removed' => 'Claude key removed.',
            'test_ok' => 'The key works (:model, $:cost).',
        ],
        'errors' => [
            'key_required' => 'Enter the API key.',
            'key_format' => 'Enter the whole key as the Console shows it, starting with sk-ant-.',
        ],
    ],

    'gemini' => [
        'on' => 'Gemini is set up on the server for the website chat, Mail and the cost calculator.',
        'off' => 'Gemini has no key on the server: the website chat, Mail AI and the cost calculator can’t use it.',
    ],

    'budget' => [
        'title' => 'Monthly AI budget',
        'status' => [
            'ok' => 'Within budget',
            'close' => 'Over 80% spent',
            'reached' => 'Budget spent: AI jobs stopped',
        ],
        'this_month' => 'Spent this month',
        'spent_of' => '$:spent of $:cap',
        'help' => 'A job that could take the month over the budget doesn’t start, so HQ stops before the Claude Console’s own spend limit. The month starts on the 1st (Lagos time).',
        'label' => 'Budget a month (US$)',
        'save' => 'Save the budget',
        'by_purpose' => 'This month by purpose',
        'purpose' => 'Purpose',
        'runs' => 'Runs',
        'cost' => 'Cost',
        'usd' => '$:amount',
        'none' => 'No AI jobs this month.',
        'flash' => [
            'saved' => 'Monthly AI budget set to $:amount.',
        ],
        'errors' => [
            'amount' => 'Enter an amount in US dollars between 0 and 1,000.',
        ],
    ],
];
