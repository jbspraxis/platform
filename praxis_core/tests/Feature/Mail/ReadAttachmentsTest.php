<?php

namespace Tests\Feature\Mail;

use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use App\Support\Hosts;
use App\Support\Mail\MailFetch;
use App\Support\Mail\Sync\MailSync;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/** Attachments, cid: pictures and older mail's files, read from the server per request (docs/MAIL.md, PR 2). */
class ReadAttachmentsTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
    }

    public function test_download_view_and_access(): void
    {
        $account = $this->mailbox(self::BOX);
        $reader = $this->staff('rea', ['engineer']);
        $this->member($account, $reader, 'read');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Files', html: '<p>Logo: <img src="cid:logo@x"></p>', text: 'Logo', attachments: [
            ['name' => 'logo.png', 'mime' => 'image/png', 'content' => "\x89PNG-logo", 'inline' => true, 'cid' => 'logo@x'],
            ['name' => 'Réunion notes.txt', 'mime' => 'text/plain', 'content' => "line one\nline two"],
            ['name' => 'page.html', 'mime' => 'text/html', 'content' => '<script>alert(1)</script>'],
        ]));
        MailSync::make()->account($account);
        $message = MailMessage::firstOrFail();
        $notes = MailAttachment::where('filename', 'Réunion notes.txt')->firstOrFail();
        $page = MailAttachment::where('filename', 'page.html')->firstOrFail();
        $base = 'https://' . Hosts::hq();

        $download = $this->actingAs($reader)->get($base . '/mail/read/attachment/' . $notes->id)->assertOk();
        $this->assertSame("line one\nline two", $download->getContent());
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString("filename*=utf-8''R%C3%A9union%20notes.txt", $download->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));

        $view = $this->actingAs($reader)->get($base . '/mail/read/attachment/' . $notes->id . '?view=1')->assertOk();
        $this->assertStringStartsWith('inline;', $view->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('text/plain', $view->headers->get('Content-Type'));

        // HTML never opens in the browser: it downloads, as bytes
        $html = $this->actingAs($reader)->get($base . '/mail/read/attachment/' . $page->id . '?view=1')->assertOk();
        $this->assertStringStartsWith('attachment;', $html->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $html->headers->get('Content-Type'));

        $inline = $this->actingAs($reader)->get($base . '/mail/read/inline/' . $message->id . '/logo@x')->assertOk();
        $this->assertSame("\x89PNG-logo", $inline->getContent());
        $this->assertSame('image/png', $inline->headers->get('Content-Type'));
        $this->actingAs($reader)->get($base . '/mail/read/inline/' . $message->id . '/nothing@x')->assertNotFound();

        $outsider = $this->staff('out', ['sales']);
        $this->actingAs($outsider)->get($base . '/mail/read/attachment/' . $notes->id)->assertForbidden();
        $this->actingAs($outsider)->get($base . '/mail/read/inline/' . $message->id . '/logo@x')->assertForbidden();
    }

    public function test_older_mail_files_are_read_live_and_a_server_failure_is_calm(): void
    {
        $account = $this->mailbox(self::BOX);
        $reader = $this->staff('rea', ['engineer']);
        $this->member($account, $reader, 'read');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'Old file', date: now()->subMonths(14)->toRfc2822String(),
            attachments: [['name' => 'old.csv', 'mime' => 'text/csv', 'content' => "a,b\n1,2"]]));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(subject: 'New file', attachments: [['name' => 'new.txt', 'mime' => 'text/plain', 'content' => 'new']]));
        MailSync::make()->account($account);
        $inbox = $account->folder('inbox');
        $base = 'https://' . Hosts::hq();

        $older = MailFetch::make()->older($reader, $inbox);
        $uid = $older['items'][0]['uid'];
        $file = $this->actingAs($reader)->get($base . "/mail/read/older/{$inbox->id}/{$uid}/2")->assertOk();
        $this->assertSame("a,b\n1,2", $file->getContent());
        $this->actingAs($reader)->get($base . "/mail/read/older/{$inbox->id}/{$uid}/9")->assertNotFound();
        $this->actingAs($this->staff('out', ['sales']))->get($base . "/mail/read/older/{$inbox->id}/{$uid}/2")->assertForbidden();

        $this->server->setPassword(self::BOX, 'changed');
        $new = MailAttachment::where('filename', 'new.txt')->firstOrFail();
        $this->actingAs($reader)->get($base . '/mail/read/attachment/' . $new->id)->assertStatus(502)->assertSee("The mail server didn't send this file.", false);
    }
}
