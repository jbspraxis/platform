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
    // The same page for an outreach email's link (/email/unsubscribe/o/…, docs/CAMPAIGNS.md section 10): the person
    // never asked for news, so it speaks of emails from us. Keys missing here use the ones above.
    'outreach_unsubscribe' => [
        'heading' => 'Stop emails from JBS Praxis?',
        'body' => 'We won’t write to :email again: no more emails from our team, and no news or invitations.',
        'body_plain' => 'We won’t write to this address again: no more emails from our team, and no news or invitations.',
        'keeps' => 'If you write to us one day, we’ll still answer you.',
        'done_body' => 'We won’t write to :email again.',
        'done_body_plain' => 'We won’t write to this address again.',
        'mistake' => 'If this was a mistake, reply to the email you received or write to :contact.',
    ],
];
