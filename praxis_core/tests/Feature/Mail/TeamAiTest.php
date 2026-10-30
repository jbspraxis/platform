<?php

namespace Tests\Feature\Mail;

use App\Livewire\Hq\Mail\AskMail;
use App\Livewire\Hq\Mail\ThreadSummary;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Support\Mail\Ai\MailSearch;
use App\Support\Mail\Sync\MailSync;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\Mail\FakeMailServer;
use Tests\TestCase;

/**
 * Praxis AI in Mail (owner decision 10, docs/MAIL.md 7.6): Summarise and Ask about your mail, Gemini only,
 * and never over mail the person can't read, Spam, or attachments.
 */
class TeamAiTest extends TestCase
{
    use MailTestKit;

    private const BOX = 'intelligence@jbspraxis.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMail();
        $this->openMailToEveryone();
        config(['services.gemini.api_key' => 'test-key', 'services.deepseek.api_key' => 'never-used']);
    }

    private function fakeGemini(string $text): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]])]);
    }

    private function sentPrompt(): string
    {
        $requests = Http::recorded();
        $this->assertCount(1, $requests, 'One call to Gemini');
        [$request] = $requests[0];
        $this->assertStringStartsWith('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', $request->url());
        $this->assertSame('test-key', $request->header('x-goog-api-key')[0]);
        $this->assertStringNotContainsString('test-key', $request->url());

        return $request['contents'][0]['parts'][0]['text'];
    }

    public function test_summarise_reads_the_conversation_without_quotes_or_attachments_and_keeps_it_until_new_mail(): void
    {
        $reader = $this->staff('rdr', ['sales']);
        $box = $this->mailbox(self::BOX);
        $this->member($box, $reader, 'read');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Proposal for Q4',
            text: 'Please send the brief by Friday.', messageId: 'p1@client.test', date: now()->subDays(2)->toRfc2822String(),
            attachments: [['name' => 'brief.pdf', 'mime' => 'application/pdf', 'content' => 'SECRET-PDF-CONTENT']]));
        $this->server->deliver(self::BOX, 'INBOX.Sent', FakeMailServer::message(from: 'JBS Praxis Intelligence <intelligence@jbspraxis.com>', to: 'ada@client.test',
            subject: 'Re: Proposal for Q4', text: "We will send it Thursday.\n\nOn Mon, Ada wrote:\n> QUOTED-OLD-TEXT", messageId: 'r1@jbspraxis.com',
            inReplyTo: 'p1@client.test', references: '<p1@client.test>', date: now()->subDay()->toRfc2822String()));
        MailSync::make()->account($box);
        $thread = MailThread::firstOrFail();

        $this->fakeGemini(json_encode(['gist' => 'Ada wants the brief; we promised Thursday.', 'key_points' => ['Brief due Friday'], 'asked_of_you' => ['Send the brief']]));
        Livewire::actingAs($reader)->test(ThreadSummary::class, ['threadId' => $thread->id])
            ->assertSee('Summarise')
            ->call('summarise')
            ->assertSet('error', null)
            ->assertSee('Ada wants the brief; we promised Thursday.')
            ->assertSee('Send the brief')
            ->assertDontSee('Summarise</button>', false);

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString('Please send the brief by Friday.', $prompt);
        $this->assertStringContainsString('We will send it Thursday.', $prompt);
        $this->assertStringNotContainsString('QUOTED-OLD-TEXT', $prompt);
        $this->assertStringNotContainsString('SECRET-PDF-CONTENT', $prompt);

        $thread->refresh();
        $this->assertSame('Ada wants the brief; we promised Thursday.', $thread->ai_summary['gist']);
        $this->assertSame($reader->id, $thread->ai_summary['by']);
        $this->assertNotNull($thread->ai_summary_at);

        // Everyone who reads the mailbox sees it, without a new call
        $owner = $this->staff('own', ['owner']);
        Livewire::actingAs($owner)->test(ThreadSummary::class, ['threadId' => $thread->id])->assertSee('Ada wants the brief');
        Http::assertSentCount(1);

        // New mail in the conversation clears it (sync)
        $this->travel(1)->minutes();
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Re: Proposal for Q4', text: 'Thanks!',
            messageId: 'p2@client.test', inReplyTo: 'r1@jbspraxis.com', references: '<p1@client.test> <r1@jbspraxis.com>'));
        MailSync::make()->account($box, null, true);
        $this->assertNull($thread->refresh()->ai_summary);
    }

    public function test_never_for_spam_people_without_access_or_without_a_key(): void
    {
        $reader = $this->staff('rdr', ['sales']);
        $outsider = $this->staff('out', ['sales']);
        $box = $this->mailbox(self::BOX);
        $this->member($box, $reader, 'read');
        $this->server->deliver(self::BOX, 'INBOX.Junk', FakeMailServer::message(from: 'prize@spam.test', subject: 'You won', text: 'Click here', messageId: 's1@spam.test'));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'ada@client.test', subject: 'Real mail', text: 'Hello', messageId: 'ok@client.test'));
        MailSync::make()->account($box);
        $spam = MailMessage::where('message_id', 's1@spam.test')->firstOrFail()->thread;
        $real = MailMessage::where('message_id', 'ok@client.test')->firstOrFail()->thread;
        $this->fakeGemini('{"gist": "x"}');

        Livewire::actingAs($reader)->test(ThreadSummary::class, ['threadId' => $spam->id])
            ->assertDontSee('Summarise')
            ->call('summarise')
            ->assertSet('error', 'Praxis AI doesn’t read mail in Spam.');

        Livewire::actingAs($outsider)->test(ThreadSummary::class, ['threadId' => $real->id])
            ->assertDontSee('Summarise')
            ->call('summarise')->assertForbidden();

        config(['services.gemini.api_key' => null]);
        Livewire::actingAs($reader)->test(ThreadSummary::class, ['threadId' => $real->id])->assertDontSee('Summarise');
        Livewire::actingAs($reader)->test(AskMail::class, ['accountId' => $box->id])->assertDontSee('Ask about your mail');

        Http::assertNothingSent();
        $this->assertNull($spam->refresh()->ai_summary);
    }

    public function test_mail_search_only_finds_mail_the_person_can_read(): void
    {
        $owner = $this->staff('own', ['owner']);
        $me = $this->staff('me', ['sales']);
        $colleague = $this->staff('col', ['sales']);
        $mine = $this->mailbox('me@jbspraxis.com', 'personal', $me);
        $theirs = $this->mailbox('col@jbspraxis.com', 'personal', $colleague);
        $shared = $this->mailbox(self::BOX);
        $other = $this->mailbox('billing@jbspraxis.com');
        $this->member($shared, $me, 'read');
        $this->member($other, $colleague, 'manage');

        $mail = [
            ['me@jbspraxis.com', 'INBOX', 'Invoice MINE', 'mine@x.test'],
            ['col@jbspraxis.com', 'INBOX', 'Invoice COLLEAGUE-PRIVATE', 'col@x.test'],
            [self::BOX, 'INBOX', 'Invoice SHARED', 'shared@x.test'],
            [self::BOX, 'INBOX.Junk', 'Invoice SPAM', 'spam@x.test'],
            [self::BOX, 'INBOX.Trash', 'Invoice DELETED', 'trash@x.test'],
            ['billing@jbspraxis.com', 'INBOX', 'Invoice BILLING-NOT-MINE', 'bill@x.test'],
        ];
        foreach ($mail as [$email, $folder, $subject, $id]) {
            $this->server->deliver($email, $folder, FakeMailServer::message(from: 'ada@client.test', subject: $subject, text: "About the invoice for {$subject}", messageId: $id));
        }
        foreach ([$mine, $theirs, $shared, $other] as $a) {
            MailSync::make()->account($a);
        }

        $found = MailSearch::for($me, 'Did anyone send the invoice?');
        $this->assertEqualsCanonicalizing(['Invoice MINE', 'Invoice SHARED'], array_column($found, 'subject'));
        $row = collect($found)->firstWhere('subject', 'Invoice SHARED');
        $this->assertSame($shared->id, $row['account_id']);
        $this->assertSame(MailMessage::where('message_id', 'shared@x.test')->value('thread_id'), $row['thread_id']);
        $this->assertStringContainsString('invoice', $row['excerpt']);
        $this->assertSame(['message_id', 'thread_id', 'account_id', 'subject', 'from', 'date', 'excerpt'], array_keys($row));

        // Narrowed to one mailbox, and never widened past what they can read
        $this->assertSame(['Invoice SHARED'], array_column(MailSearch::for($me, 'invoice', 20, [$shared->id, $other->id, $theirs->id]), 'subject'));
        // The Owner role manages shared mailboxes, never someone's personal one
        $this->assertContains('Invoice BILLING-NOT-MINE', array_column(MailSearch::for($owner, 'invoice'), 'subject'));
        $this->assertNotContains('Invoice MINE', array_column(MailSearch::for($owner, 'invoice'), 'subject'));
        $this->assertNotContains('Invoice COLLEAGUE-PRIVATE', array_column(MailSearch::for($owner, 'invoice'), 'subject'));
    }

    public function test_ask_answers_from_readable_mail_only_with_links_to_the_messages(): void
    {
        $me = $this->staff('me', ['sales']);
        $colleague = $this->staff('col', ['sales']);
        $shared = $this->mailbox(self::BOX);
        $theirs = $this->mailbox('col@jbspraxis.com', 'personal', $colleague);
        $this->member($shared, $me, 'read');
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ada <ada@client.test>', subject: 'Workshop in Lagos', text: 'The workshop is on 12 November at 10:00.', messageId: 'w1@client.test'));
        $this->server->deliver(self::BOX, 'INBOX', FakeMailServer::message(from: 'Ben <ben@client.test>', subject: 'Workshop catering', text: 'Lunch for 20 at the workshop.', messageId: 'w2@client.test'));
        $this->server->deliver('col@jbspraxis.com', 'INBOX', FakeMailServer::message(from: 'hr@x.test', subject: 'Workshop salary review PRIVATE', text: 'Confidential workshop pay', messageId: 'pv@x.test'));
        MailSync::make()->account($shared);
        MailSync::make()->account($theirs);

        $this->fakeGemini("The workshop is on 12 November at 10:00 [1].\n- Lunch is for 20 people [2]\n<script>alert(1)</script> [7]");
        $page = Livewire::actingAs($me)->test(AskMail::class, ['accountId' => $shared->id])
            ->assertSee('Ask about your mail')
            ->call('openPanel')
            ->set('question', 'When is the workshop?')
            ->call('ask')
            ->assertHasNoErrors()
            ->assertSet('question', '')
            ->assertSet('asked', 'When is the workshop?');

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString('Workshop in Lagos', $prompt);
        $this->assertStringContainsString('Workshop catering', $prompt);
        $this->assertStringNotContainsString('PRIVATE', $prompt);
        $this->assertStringNotContainsString('Confidential', $prompt);

        $sources = $page->get('sources');
        $this->assertCount(2, $sources);
        foreach ($sources as $s) {
            $thread = MailMessage::where('subject', $s['subject'])->value('thread_id');
            $this->assertSame('/mail?box=' . $shared->id . '&thread=' . $thread, $s['url']);
            $this->assertArrayNotHasKey('excerpt', $s, 'The page keeps only what it shows');
        }
        $page->assertSee('Messages used')
            ->assertSeeHtml('<a class="am-cite" href="' . e($sources[0]['url']) . '"')
            ->assertSeeHtml('<li>Lunch is for 20 people')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertSeeHtml('&lt;script&gt;');
    }

    public function test_ask_without_matching_mail_does_not_call_the_ai(): void
    {
        $me = $this->staff('me', ['sales']);
        $mine = $this->mailbox('me@jbspraxis.com', 'personal', $me);
        $this->server->deliver('me@jbspraxis.com', 'INBOX', FakeMailServer::message(subject: 'Lunch', text: 'Pizza', messageId: 'l@x.test'));
        MailSync::make()->account($mine);
        Http::fake();

        Livewire::actingAs($me)->test(AskMail::class, ['accountId' => $mine->id])
            ->call('openPanel')
            ->set('question', 'Where is the zebra contract?')
            ->call('ask')
            ->assertSee('I couldn’t find any mail about that')
            ->set('question', '')
            ->call('ask')
            ->assertHasErrors('question');
        Http::assertNothingSent();
    }
}
