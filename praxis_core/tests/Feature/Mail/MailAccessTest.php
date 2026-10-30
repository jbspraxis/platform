<?php

namespace Tests\Feature\Mail;

use App\Support\Mail\MailAccess;
use Tests\TestCase;

/** Who may use Mail and what each level allows (docs/MAIL.md, "Access"; owner decisions 1–3). */
class MailAccessTest extends TestCase
{
    use MailTestKit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
    }

    public function test_mail_is_owner_only_until_opened_to_everyone(): void
    {
        $owner = $this->staff('own', ['owner']);
        $engineer = $this->staff('eng', ['engineer']);

        $this->assertTrue(MailAccess::canUse($owner));
        $this->assertFalse(MailAccess::canUse($engineer));
        $this->get('https://' . \App\Support\Hosts::hq() . '/mail/mailboxes')->assertRedirect(); // signed out

        $this->actingAs($engineer)->get('https://' . \App\Support\Hosts::hq() . '/mail')->assertForbidden();

        $this->openMailToEveryone();
        $this->assertTrue(MailAccess::canUse($engineer));
        $this->actingAs($engineer)->get('https://' . \App\Support\Hosts::hq() . '/mail')->assertOk();
    }

    public function test_personal_mailboxes_are_private_even_from_the_owner_role(): void
    {
        $this->openMailToEveryone();
        $owner = $this->staff('own', ['owner']);
        $ada = $this->staff('ada', ['sales']);
        $mine = $this->mailbox('ada@jbspraxis.com', 'personal', $ada);

        $this->assertSame('owner', MailAccess::level($ada, $mine));
        $this->assertNull(MailAccess::level($owner, $mine));
        $this->assertSame([$mine->id], MailAccess::accountIds($ada));
        $this->assertSame([], MailAccess::accountIds($owner));
        $this->assertSame([$ada->id], MailAccess::readers($mine));
    }

    public function test_shared_mailbox_levels(): void
    {
        $this->openMailToEveryone();
        $owner = $this->staff('own', ['owner']);
        $reader = $this->staff('rea', ['engineer']);
        $sender = $this->staff('sen', ['sales']);
        $manager = $this->staff('man', ['delivery']);
        $outsider = $this->staff('out', ['hr']);
        $box = $this->mailbox('intelligence@jbspraxis.com');
        $this->member($box, $reader, 'read');
        $this->member($box, $sender, 'send');
        $this->member($box, $manager, 'manage');

        $this->assertTrue(MailAccess::can($reader, $box, 'read'));
        $this->assertFalse(MailAccess::can($reader, $box, 'send'));
        $this->assertFalse(MailAccess::can($reader, $box, 'organise'));
        $this->assertTrue(MailAccess::can($sender, $box, 'send'));
        $this->assertTrue(MailAccess::can($sender, $box, 'organise'));
        $this->assertFalse(MailAccess::can($sender, $box, 'manage'));
        $this->assertTrue(MailAccess::can($manager, $box, 'manage'));
        $this->assertNull(MailAccess::level($outsider, $box));
        // Owner accounts manage every shared mailbox without being listed
        $this->assertSame('manage', MailAccess::level($owner, $box));

        $readers = MailAccess::readers($box);
        sort($readers);
        $expected = [$owner->id, $reader->id, $sender->id, $manager->id];
        sort($expected);
        $this->assertSame($expected, $readers);

        // Someone who left loses everything
        $sender->forceFill(['status' => 'Offboarded'])->save();
        \App\Support\Access::flush();
        MailAccess::flush();
        $this->assertNull(MailAccess::level($sender->fresh(), $box));
        $this->assertNotContains($sender->id, MailAccess::readers($box));
    }

    public function test_adding_shared_mailboxes_needs_the_permission(): void
    {
        $this->openMailToEveryone();
        $this->assertTrue(MailAccess::canAddShared($this->staff('par', ['partner'])));
        $this->assertFalse(MailAccess::canAddShared($this->staff('sal', ['sales'])));
    }
}
