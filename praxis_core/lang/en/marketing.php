<?php

// The page behind the unsubscribe link in broadcasts (portal host, /email/unsubscribe/…, docs/CAMPAIGNS.md 4.5).
// Unsubscribing stops all marketing to the address: broadcasts and outreach. Counsel to confirm the wording.
return [
    'unsubscribe' => [
        'title' => 'Email preferences',
        'heading' => 'Unsubscribe from JBS Praxis news?',
        'body' => 'We’ll stop sending news, insights and invitations to :email.',
        'body_plain' => 'We’ll stop sending news, insights and invitations to this address.',
        'keeps' => 'Emails about work we do with you, such as invoices and proposals, still reach you.',
        'button' => 'Unsubscribe',
        'done_heading' => 'You’re unsubscribed',
        'done_body' => 'We won’t send news, insights or invitations to :email again.',
        'done_body_plain' => 'We won’t send news, insights or invitations to this address again.',
        'mistake' => 'If this was a mistake, reply to one of our emails or write to :contact.',
        'invalid_heading' => 'We couldn’t use this link',
        'invalid_body' => 'It may be incomplete. Write to :contact and we’ll remove your address.',
        'preview' => 'This is a preview from a test email: the button doesn’t unsubscribe anyone.',
        'other_language' => 'Français',
    ],
];
