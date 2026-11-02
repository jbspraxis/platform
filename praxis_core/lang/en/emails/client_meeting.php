<?php

// MeetingInvitationMail: a client meeting arranged in HQ or booked in the portal (Opportunities PR 2), next to Google's own invitation.
// Variants: invite, moved, cancelled. Times are West Africa Time.
return [
    'invite' => [
        'subject' => 'Invitation: :title on :date at :time WAT',
        'preheader' => 'Join with the Google Meet link in this email. Google Calendar has sent the invitation too.',
        'headline' => 'You’re invited to a meeting with JBS Praxis',
        'intro' => 'We’ve arranged a meeting between :company and JBS Praxis. Google Calendar has sent you an invitation as well; here are the details.',
    ],
    'moved' => [
        'subject' => 'New time: :title on :date at :time WAT',
        'preheader' => 'Our meeting has moved. The link stays the same.',
        'headline' => 'Our meeting has a new time',
        'intro' => 'We’ve moved the meeting between :company and JBS Praxis. Here are the new details; the link to join stays the same.',
    ],
    'cancelled' => [
        'subject' => 'Cancelled: :title on :date',
        'preheader' => 'This meeting won’t take place. Book another time whenever suits you.',
        'headline' => 'Our meeting is cancelled',
        'intro' => 'We’ve cancelled the meeting of :date between :company and JBS Praxis. Google Calendar will remove it from your calendar. Book another time below, or reply to this email.',
    ],
    'label_meeting' => 'Meeting',
    'format' => 'Online, with Google Meet',
    'agenda' => 'Agenda',
    'notice' => 'We record a transcript of this meeting with Google Meet to write the minutes, which we send you afterwards. If you’d rather we didn’t, tell us at the start of the meeting.',
    'another_time' => 'Need another time?',
    'book_link' => 'Book a meeting with us',
    'book_button' => 'Book another time',
    'reason' => 'You are receiving this because you were invited to a meeting between :company and JBS Praxis.',
];
