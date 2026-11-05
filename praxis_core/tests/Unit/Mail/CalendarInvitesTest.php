<?php

namespace Tests\Unit\Mail;

use App\Support\Mail\Compose\CalendarInvites;
use PHPUnit\Framework\TestCase;

/**
 * Quoted calendar invitations are left out of replies (CalendarInvites). The first case is the message MyCity App
 * sent on 5 November 2026: our one-line reply quoting it was refused by their spam filter as "high probability of spam".
 */
class CalendarInvitesTest extends TestCase
{
    private const MYCITY = <<<'TXT'
Hello Tom,

Please see attached

Get Outlook for Mac <https://aka.ms/GetOutlookForMac>

From: JBS Praxis <praxisjbs@gmail.com>
Date: Thursday, 5 November 2026 at 13:14
To: etudor@mycityapp.io <etudor@mycityapp.io>, tom.asaah@jbspraxis.com <tom.asaah@jbspraxis.com>, tomblakeasaah@gmail.com <tomblakeasaah@gmail.com>
Subject: Updated invitation: Partnerships Workflow Discovery - Mycityapp @ Thu Nov 5, 2026 12pm - 1pm (WAT) (etudor@mycityapp.io)

This event has been updated
Changed: attachments

Join with Google Meet<https://meet.google.com/orf-kbqz-xzh?hs=224>
Meeting link
meet.google.com/orf-kbqz-xzh<https://meet.google.com/orf-kbqz-xzh?hs=224>
Attachments
CHANGED
Notes by Gemini<https://docs.google.com/document/d/abc/edit?usp=meet_tnfm_calendar>
To understand Partnership workflow in Mycityapp.
When
Thursday Nov 5, 2026 ⋅ 12pm – 1pm (West Africa Time - Lagos)
Guests
JBS Praxis<mailto:praxisjbs@gmail.com> - organizer
etudor@mycityapp.io<mailto:etudor@mycityapp.io>
View all guest info<https://calendar.google.com/calendar/event?action=VIEW&eid=NTNu&tok=MTkj&ctz=Africa%2FLagos&hl=en&es=0>
Reply for etudor@mycityapp.io<mailto:etudor@mycityapp.io>
Yes<https://calendar.google.com/calendar/event?action=RESPOND&eid=NTNu&rst=1&tok=MTkj&ctz=Africa%2FLagos&hl=en&es=0>
No<https://calendar.google.com/calendar/event?action=RESPOND&eid=NTNu&rst=2&tok=MTkj&ctz=Africa%2FLagos&hl=en&es=0>
Maybe<https://calendar.google.com/calendar/event?action=RESPOND&eid=NTNu&rst=3&tok=MTkj&ctz=Africa%2FLagos&hl=en&es=0>
More options<https://calendar.google.com/calendar/event?action=VIEW&eid=NTNu&tok=MTkj&ctz=Africa%2FLagos&hl=en&es=0>

Sent by Google Calendar<https://calendar.google.com/calendar/>

You are receiving this email because you are an attendee on the event.

Forwarding this invitation could allow any recipient to send a response to the organizer, be added to the guest list, invite others regardless of their own invitation status, or modify your RSVP. Learn more<https://support.google.com/calendar/answer/37135#forwarding>
TXT;

    public function test_the_mycity_message_keeps_its_own_words_and_loses_the_invitation(): void
    {
        $out = CalendarInvites::stripFromQuote(self::MYCITY);

        $this->assertStringContainsString("Hello Tom,\n\nPlease see attached", $out, 'What the person wrote stays');
        $this->assertStringContainsString('Get Outlook for Mac', $out);
        $this->assertStringContainsString('[Calendar invitation “Partnerships Workflow Discovery - Mycityapp” left out of this quote]', $out);

        foreach (['calendar.google.com', 'Sent by Google Calendar', 'Forwarding this invitation', 'action=RESPOND', 'meet.google.com', 'Subject: Updated invitation', 'From: JBS Praxis'] as $gone) {
            $this->assertStringNotContainsString($gone, $out, "'{$gone}' is left out");
        }
        $this->assertFalse(CalendarInvites::contains($out));
    }

    public function test_an_invitation_that_is_the_whole_message_becomes_one_line(): void
    {
        $invite = <<<'TXT'
Internal meeting - Modules Review mycityapp
Wednesday Nov 4, 2026 ⋅ 3pm – 4pm
West Africa Time - Lagos

Join with Google Meet
meet.google.com/zcg-tspg-drz

Guests
JBS Praxis - organizer
View all guest info https://calendar.google.com/calendar/event?action=VIEW&eid=abc

Reply for tom.asaah@jbspraxis.com
Yes https://calendar.google.com/calendar/event?action=RESPOND&eid=abc&rst=1

Invitation from Google Calendar

You are receiving this email because you are an attendee on the event.
TXT;

        $this->assertSame('[Calendar invitation left out of this quote]', CalendarInvites::stripFromQuote($invite));
    }

    public function test_a_french_invitation_quoted_with_marks_keeps_the_marks_and_what_follows(): void
    {
        $text = <<<'TXT'
Merci, à demain.

> Le jeu. 5 nov. 2026 à 09:00, JBS Praxis <praxisjbs@gmail.com> a écrit :
> Cet événement a été modifié
> Participer avec Google Meet https://meet.google.com/abc-defg-hij
> Répondre pour client@exemple.test
> Oui https://calendar.google.com/calendar/event?action=RESPOND&eid=xyz&rst=1
> Envoyé par Google Agenda
> Le transfert de cette invitation peut permettre à tout destinataire de répondre à l’organisateur.
>
> Le mer. 4 nov. 2026 à 18:00, Client <client@exemple.test> a écrit :
>> Pouvons-nous nous voir jeudi ?
TXT;

        $out = CalendarInvites::stripFromQuote($text);

        $this->assertStringContainsString('Merci, à demain.', $out);
        $this->assertStringContainsString('> [Calendar invitation left out of this quote]', $out, 'The note sits at the quote’s depth');
        $this->assertStringContainsString('>> Pouvons-nous nous voir jeudi ?', $out, 'The earlier message below the invitation stays');
        $this->assertStringNotContainsString('calendar.google.com', $out);
        $this->assertStringNotContainsString('Google Agenda', $out);
    }

    public function test_two_invitations_in_one_thread_are_both_left_out(): void
    {
        $text = "Agreed.\n\n" . self::MYCITY . "\n\nFrom: Etudor <etudor@mycityapp.io>\nSubject: Re: earlier\n\nFine by me.\n\n" . self::MYCITY;

        $out = CalendarInvites::stripFromQuote($text);

        $this->assertSame(2, substr_count($out, '[Calendar invitation “Partnerships Workflow Discovery - Mycityapp” left out of this quote]'));
        $this->assertStringContainsString('Fine by me.', $out);
        $this->assertFalse(CalendarInvites::contains($out));
    }

    public function test_ordinary_mail_is_left_exactly_as_it_was(): void
    {
        $plain = "Hi Tom,\n\nI will send you a Google Calendar invitation for Thursday.\nJoin us on meet.google.com/abc-defg-hij.\n\n> On Mon, Ada wrote:\n> See you then";

        $this->assertFalse(CalendarInvites::contains($plain));
        $this->assertSame($plain, CalendarInvites::stripFromQuote($plain), 'Mentioning a calendar or a Meet link is not an invitation');
    }

    public function test_an_invitations_subject_is_cut_to_the_events_name(): void
    {
        $cases = [
            'Updated invitation: Partnerships Workflow Discovery - Mycityapp @ Thu Nov 5, 2026 12pm - 1pm (WAT) (etudor@mycityapp.io)' => 'Partnerships Workflow Discovery - Mycityapp',
            'Invitation: Internal meeting - Modules Review mycityapp @ Wed Nov 4, 2026 3pm - 4pm (WAT) (tom.asaah@jbspraxis.com)' => 'Internal meeting - Modules Review mycityapp',
            'RE: Updated invitation: Partnerships Workflow Discovery - Mycityapp @ Thu Nov 5, 2026 12pm - 1pm (WAT) (etudor@mycityapp.io)' => 'Partnerships Workflow Discovery - Mycityapp',
            'Invitation mise à jour : Revue des modules @ jeu. 5 nov. 2026 12:00 - 13:00 (WAT) (client@exemple.test)' => 'Revue des modules',
            'Accepted: Partnerships Workflow Discovery - Mycityapp @ Thu Nov 5, 2026 12pm - 1pm (WAT) (tom.asaah@jbspraxis.com)' => 'Partnerships Workflow Discovery - Mycityapp',
        ];

        foreach ($cases as $subject => $name) {
            $this->assertTrue(CalendarInvites::isInvitationSubject($subject), $subject);
            $this->assertSame($name, CalendarInvites::cleanSubject($subject), $subject);
        }
    }

    public function test_other_subjects_are_left_alone(): void
    {
        foreach (['Invitation: speak at our conference', 'Re: Proposal', 'Invitation to tender @ the ministry', 'MyCity App ERP: Milestone 4 requirements (recruitment)'] as $subject) {
            $this->assertFalse(CalendarInvites::isInvitationSubject($subject), $subject);
            $this->assertSame($subject, CalendarInvites::cleanSubject($subject));
        }
    }
}
