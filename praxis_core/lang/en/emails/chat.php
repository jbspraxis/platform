<?php

// ChatUnreadMail: messages in a project's conversation (docs/CHAT.md) that nobody on the other side read within an hour.
return [
    'client_subject' => '{1} New message from your delivery team|[2,*] :count new messages from your delivery team',
    'client_preheader' => ':name wrote about :project.',
    'client_headline' => 'Your delivery team wrote to you',
    'client_intro' => '{1} You have 1 unread message about :project in your Client Portal.|[2,*] You have :count unread messages about :project in your Client Portal.',
    'client_button' => 'Open the conversation',
    'client_reason' => 'You are receiving this because a message for you in the JBS Praxis Client Portal wasn’t read within an hour. We send at most one email an hour for each conversation.',
    'staff_subject' => 'Unread message from :client',
    'staff_preheader' => ':name wrote about :project and it hasn’t been read at JBS Praxis for an hour.',
    'staff_headline' => ':client is waiting for a reply',
    'staff_intro' => '{1} 1 message from :client about :project hasn’t been read for an hour.|[2,*] :count messages from :client about :project haven’t been read for an hour.',
    'staff_button' => 'Reply in Client messages',
    'staff_reason' => 'Sent to the delivery team of :project when a client’s message isn’t read within an hour.',
    'more' => '{1} And 1 earlier message.|[2,*] And :count earlier messages.',
    'at' => ':name · :time',
];
