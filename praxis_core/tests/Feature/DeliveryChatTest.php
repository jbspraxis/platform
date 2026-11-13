<?php

namespace Tests\Feature;

use App\Livewire\ClientOs\Dashboard;
use App\Livewire\ClientOs\DeliveryChat;
use App\Livewire\Hq\ClientMessages\Inbox;
use App\Livewire\Hq\ClientMessages\Launcher;
use App\Livewire\Hq\ClientMessages\Thread;
use App\Livewire\Hq\Operations\QaHub;
use App\Mail\ChatUnreadMail;
use App\Models\ClientUser;
use App\Support\Access;
use App\Support\Chat\ProjectChat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Feature\Projects\ProjectFixtures;
use Tests\TestCase;

/**
 * The conversation between a client and its delivery team, one per project (docs/CHAT.md, owner 13 Nov 2026): both
 * sides write in the same thread, each hears of the other (bells, pushes, an email after an hour unread), read
 * receipts and "is typing", issues tracked from the chat to the approved fix, files and voice notes kept private.
 */
class DeliveryChatTest extends TestCase
{
    use ProjectFixtures;
    use UsesThrowawayDatabase;

    private string $org;
    private string $projectId;
    private string $taskId;
    private $lead;
    private $engineer;
    private $outsider;
    private ClientUser $admin;
    private ClientUser $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Fleet Logistics Ltd', 'status' => 'Active Client', 'preferred_language' => 'EN']);
        $this->lead = $this->staff('delivery', ['delivery']);
        $this->engineer = $this->staff('engineer', ['engineer']);
        $this->outsider = $this->staff('outsider', ['engineer']); // an engineer on nothing of this client's
        $this->projectId = $this->project(['organization_id' => $this->org, 'title' => 'Fleet tracking portal', 'lead_user_id' => $this->lead->id]);
        $phase = $this->phase($this->projectId, 1, 100, ['status' => 'Active']);
        $this->taskId = $this->task($phase, ['title' => 'Live map', 'status' => 'QA_Review', 'is_client_visible' => 1, 'assigned_to' => $this->engineer->id]);
        $this->admin = $this->client(['first_name' => 'Adaeze', 'last_name' => 'Okafor']);
        $this->finance = $this->client(['first_name' => 'Chidi', 'last_name' => 'Nwosu', 'is_primary_client' => 0,
            'role_id' => DB::table('roles')->where('audience', 'client')->where('slug', 'client-finance')->value('id')]);
    }

    private function client(array $values = [], ?string $org = null): ClientUser
    {
        return ClientUser::forceCreate($values + [
            'organization_id' => $org ?? $this->org, 'role_id' => Access::clientAdminRoleId(),
            'first_name' => 'Ada', 'last_name' => 'Client', 'email' => Str::lower(Str::random(10)) . '@client.test',
            'password' => Str::random(24), 'status' => 'Active', 'lifecycle_stage' => 'Active', 'is_primary_client' => 1,
        ]);
    }

    private function portalChat(?ClientUser $client = null, ?string $project = null)
    {
        return Livewire::actingAs($client ?? $this->admin, 'client')->test(DeliveryChat::class, ['projectId' => $project ?? $this->projectId]);
    }

    private function hqThread($user, ?string $project = null)
    {
        return Livewire::actingAs($user)->test(Thread::class, ['projectId' => $project ?? $this->projectId]);
    }

    public function test_a_client_message_reaches_the_delivery_team_and_their_reply_reaches_the_client(): void
    {
        $this->portalChat()->set('draft', 'Hello team, the map is blank on my phone.')->call('send')
            ->assertHasNoErrors()->assertDispatched('chat-sent')->assertSee('Hello team, the map is blank on my phone.')->assertSet('draft', '');

        // The lead and the people on the project's tasks hear of it; nobody else
        $bells = DB::table('notifications')->where('title', 'Message from Fleet Logistics Ltd')->pluck('user_id')->all();
        $this->assertEqualsCanonicalizing([$this->lead->id, $this->engineer->id], $bells);
        $this->assertStringContainsString('Adaeze Okafor on Fleet tracking portal', DB::table('notifications')->where('user_id', $this->lead->id)->value('message'));
        $this->assertStringContainsString('/operations/client-messages?project=' . $this->projectId, DB::table('notifications')->where('user_id', $this->lead->id)->value('link_url'));
        // A second message while the bell is unread updates it instead of ringing again
        $this->portalChat()->set('draft', 'It works on the laptop.')->call('send');
        $this->assertSame(1, DB::table('notifications')->where('user_id', $this->lead->id)->count());
        $this->assertStringContainsString('It works on the laptop.', DB::table('notifications')->where('user_id', $this->lead->id)->value('message'));

        // HQ reads the same thread and replies in it; every portal account that reads the chat hears of it
        $this->hqThread($this->lead)->assertSee('Hello team, the map is blank on my phone.')->assertSee('Adaeze Okafor')
            ->assertSee('Adaeze Okafor and Chidi Nwosu read this conversation in the Client Portal.')
            ->set('draft', 'Thanks Adaeze, we are on it.')->call('send')->assertHasNoErrors();
        $rows = DB::table('client_notifications')->where('kind', 'chat_message')->get();
        $this->assertEqualsCanonicalizing([$this->admin->id, $this->finance->id], $rows->pluck('client_user_id')->all());
        $this->assertSame('/?chat=' . $this->projectId, $rows->first()->link_url);
        [$title, $body] = \App\Support\Pwa\ClientNotify::text('chat_message', $rows->first()->params, 'fr');
        $this->assertSame('Delivery T. vous a répondu', $title);
        $this->assertStringContainsString('Thanks Adaeze, we are on it.', $body);

        // An internal note stays at JBS Praxis: no bell for the client, never in the portal
        $this->hqThread($this->lead)->set('internal', true)->set('draft', 'Check the GPS key on staging.')->call('send')
            ->assertSee('Check the GPS key on staging.')->assertSee('Internal note')->assertSet('internal', false);
        $this->assertSame(2, DB::table('client_notifications')->count());
        $this->portalChat()->assertSee('Thanks Adaeze, we are on it.')->assertDontSee('Check the GPS key on staging.')
            ->assertSee('Delivery T.')->assertSee('JBS Praxis');
    }

    public function test_read_receipts_typing_and_unread_counts_follow_each_side(): void
    {
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'First update']);
        $latest = ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'Second update']);
        // Two replies within minutes: one bell per account, updated with the latest
        $this->assertSame(1, DB::table('client_notifications')->where('client_user_id', $this->admin->id)->count());
        $this->assertStringContainsString('Second update', DB::table('client_notifications')->where('client_user_id', $this->admin->id)->value('params'));

        $chat = $this->portalChat()->assertSee('Unread messages');
        $this->assertSame(2, ProjectChat::unread($this->projectId, 'client', $this->admin->id));
        // Open but scrolled up: present, nothing read
        $chat->call('heartbeat', true, false)->assertDispatched('chat-state');
        $this->assertSame(2, ProjectChat::unread($this->projectId, 'client', $this->admin->id));
        // At the newest message, in front: read, and the bell about it too
        $chat->call('heartbeat', true, true);
        $this->assertSame(0, ProjectChat::unread($this->projectId, 'client', $this->admin->id));
        $this->assertSame(0, DB::table('client_notifications')->where('client_user_id', $this->admin->id)->where('is_read', 0)->count());
        // HQ sees Seen up to the last message: one of the client's accounts read it
        [$seen] = ProjectChat::state($this->projectId, 'staff', $this->lead->id);
        $this->assertSame($latest, $seen);
        // Nobody on the client's side present: still a bell for the other account
        $this->assertSame(1, DB::table('client_notifications')->where('client_user_id', $this->finance->id)->where('is_read', 0)->count());

        // Nothing new: no HTML, only the state
        $chat->call('heartbeat', true, true)->assertNotDispatched('chat-changed');
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'Third update']);
        $chat->call('heartbeat', true, true)->assertDispatched('chat-changed')->assertSee('Third update');

        // Typing shows on the other side, with the client's full name for HQ and the staff member's short name for the client
        $this->portalChat()->call('typing');
        [, $typing] = ProjectChat::state($this->projectId, 'staff', $this->lead->id);
        $this->assertSame(['Adaeze Okafor'], $typing);
        $this->hqThread($this->lead)->call('typing');
        [, $typing] = ProjectChat::state($this->projectId, 'client', $this->admin->id);
        $this->assertSame(['Delivery T.'], $typing);
        // Sending stops it
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'Done']);
        [, $typing] = ProjectChat::state($this->projectId, 'client', $this->admin->id);
        $this->assertSame([], $typing);

        // Someone looking at the chat right now gets no bell for what they see arrive
        $before = DB::table('client_notifications')->where('client_user_id', $this->admin->id)->count();
        $chat->call('heartbeat', true, true);
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'While you watch']);
        $this->assertSame($before, DB::table('client_notifications')->where('client_user_id', $this->admin->id)->count());
    }

    public function test_an_issue_reported_in_the_conversation_is_tracked_until_the_client_approves_the_fix(): void
    {
        $this->portalChat()->call('startIssue', $this->taskId)->assertSet('issueMode', true)->assertSet('issueTaskId', $this->taskId)
            ->assertSet('issueTitle', 'Issue: Live map')->assertSee('Report an issue')
            ->set('issueSeverity', 'Critical')->set('draft', 'The map stays blank after sign-in.')->call('send')
            ->assertHasNoErrors()->assertSet('issueMode', false)->assertSee('Issue: Live map')->assertSee('Critical');
        $ticket = DB::table('qa_tickets')->where('project_id', $this->projectId)->first();
        $this->assertSame(['Issue: Live map', $this->taskId, 'Critical', 'Open', 'client'], [$ticket->title, $ticket->task_id, $ticket->severity, $ticket->status, $ticket->submitted_by_type]);
        $card = DB::table('chat_messages')->where('qa_ticket_id', $ticket->id)->where('kind', 'issue')->first();
        $this->assertSame('The map stays blank after sign-in.', $card->body);
        $this->assertSame(2, DB::table('notifications')->where('title', 'Issue reported by Fleet Logistics Ltd')->count());

        // Quality and issues in HQ: opening it says someone is on it, the reply goes into the conversation
        $this->actingAs($this->lead);
        Livewire::test(QaHub::class)->call('openTicket', $ticket->id)->assertSee('The map stays blank after sign-in.')
            ->set('newCommentText', 'Fixed on staging, please check.')->call('postComment')->assertSee('Fixed on staging, please check.')
            ->call('markResolved');
        $this->assertSame(['investigating', 'resolved'], DB::table('chat_messages')->where('qa_ticket_id', $ticket->id)->where('kind', 'event')->orderBy('id')->pluck('event')->all());
        $reply = DB::table('chat_messages')->where('qa_ticket_id', $ticket->id)->where('kind', 'text')->first();
        $this->assertSame([(int) $card->id, 0], [(int) $reply->reply_to_id, (int) $reply->is_internal]);

        // The client reads it all in the chat and approves the fix: the issue closes and its task is done
        $this->portalChat()->assertSee('Fixed on staging, please check.')->assertSee('Delivery T. marked “Issue: Live map” as resolved')
            ->assertSee('Approve the fix')->call('approveFix', $ticket->id)->assertSee('Adaeze Okafor approved the fix for “Issue: Live map”');
        $this->assertSame('Closed', DB::table('qa_tickets')->where('id', $ticket->id)->value('status'));
        $this->assertSame('Done', DB::table('tasks')->where('id', $this->taskId)->value('status'));

        // The portal's list of issues opens each one's card, with its replies counted
        $listed = collect(Livewire::actingAs($this->admin, 'client')->test(Dashboard::class)->call('switchTab', 'qa_hub')->assertSee('1 reply')->get('qaTickets'))->first();
        $this->assertSame((int) $card->id, $listed->card_id);
    }

    public function test_tickets_made_outside_the_chat_get_their_card_and_a_read_only_role_cannot_write(): void
    {
        // A handover point became a ticket in HQ: the client's issue list gives it a card in the conversation
        $ticketId = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $ticketId, 'project_id' => $this->projectId, 'title' => 'Totals in naira', 'description' => 'Show NGN on the dashboard',
            'status' => 'Open', 'severity' => 'Medium', 'submitted_by' => $this->lead->id, 'submitted_by_type' => 'staff']);
        Livewire::actingAs($this->admin, 'client')->test(Dashboard::class)->call('switchTab', 'qa_hub')->assertSee('Totals in naira');
        $this->assertSame(1, DB::table('chat_messages')->where('qa_ticket_id', $ticketId)->where('kind', 'issue')->count());

        // Client Finance reads the conversation but can't write in it
        $this->portalChat($this->finance)->assertSee('Totals in naira')->assertSee('Your role lets you read this conversation but not write in it.')
            ->set('draft', 'Hello')->call('send')->assertForbidden();
        $this->assertSame(0, DB::table('chat_messages')->where('kind', 'text')->count());

        // Another organisation, and an account limited to another project, have no conversation here
        $otherOrg = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $otherOrg, 'name' => 'Other Client Ltd', 'status' => 'Active Client']);
        $this->portalChat($this->client([], $otherOrg))->assertForbidden();
        $second = $this->project(['organization_id' => $this->org, 'title' => 'Second project']);
        $this->portalChat($this->client(['is_primary_client' => 0, 'restricted_project_id' => $second,
            'role_id' => DB::table('roles')->where('audience', 'client')->where('slug', 'client-member')->value('id')]))->assertForbidden();

        // A notification about the chat opens the dashboard on that project with the conversation open
        Livewire::withQueryParams(['chat' => $second])->actingAs($this->admin, 'client')->test(Dashboard::class)
            ->assertSet('openChat', true)->assertSet('activeProjectId', $second);
    }

    public function test_a_ticket_imported_without_its_author_type_shows_as_the_lead_s(): void
    {
        // opportunities:import saved the lead's tickets without submitted_by_type: the column's default said client
        $ticketId = (string) Str::uuid();
        $this->insertRow('qa_tickets', ['id' => $ticketId, 'project_id' => $this->projectId, 'title' => 'M1 handover point 10: Record the demos',
            'description' => 'Milestone demos are recorded.', 'status' => 'Closed', 'severity' => 'Low', 'submitted_by' => $this->lead->id]);
        $this->assertSame('client', DB::table('qa_tickets')->where('id', $ticketId)->value('submitted_by_type'));

        // A card made now is the lead's: on JBS Praxis's side, with the name
        $card = ProjectChat::cardFor($ticketId);
        $this->assertSame('staff', DB::table('chat_messages')->where('id', $card)->value('author_type'));
        $this->hqThread($this->engineer)->assertSee('M1 handover point 10: Record the demos')->assertSee('Delivery Test');
        $this->portalChat()->assertSee('Delivery T.');

        // Rows made before the fix are repaired by the migration: the ticket and its card
        DB::table('chat_messages')->where('id', $card)->update(['author_type' => 'client']);
        (require database_path('migrations/2026_11_13_400001_fix_authors_of_imported_tickets.php'))->up();
        $this->assertSame('staff', DB::table('qa_tickets')->where('id', $ticketId)->value('submitted_by_type'));
        $this->assertSame('staff', DB::table('chat_messages')->where('id', $card)->value('author_type'));
        // A client's own ticket stays the client's
        ProjectChat::reportIssue($this->projectId, $this->admin, null, 'Real client issue', 'Medium', 'Details');
        (require database_path('migrations/2026_11_13_400001_fix_authors_of_imported_tickets.php'))->up();
        $this->assertSame('client', DB::table('qa_tickets')->where('title', 'Real client issue')->value('submitted_by_type'));
    }

    public function test_files_and_voice_notes_travel_with_messages_and_open_only_for_those_who_may_see_them(): void
    {
        Storage::fake('local');
        $this->portalChat()->set('files', [UploadedFile::fake()->image('screen.png', 400, 300), UploadedFile::fake()->create('report.pdf', 120, 'application/pdf')])
            ->set('draft', 'Two files')->call('send')->assertHasNoErrors()->assertSee('screen.png')->assertSee('report.pdf');
        $files = DB::table('chat_attachments')->orderBy('id')->get();
        $this->assertSame(['image', 'file'], $files->pluck('kind')->all());
        $this->assertStringStartsWith('chat/' . $this->projectId . '/', $files[0]->file_path);
        Storage::disk('local')->assertExists($files[0]->file_path);

        // A file alone: the bell says a file arrived, in the reader's language
        $this->hqThread($this->lead)->set('files', [UploadedFile::fake()->image('fix.png')])->call('send')->assertHasNoErrors();
        $this->assertSame(2, DB::table('client_notifications')->where('kind', 'chat_file')->count());

        // A voice note, with its length
        $this->portalChat()->set('voice', UploadedFile::fake()->create('voice-note.webm', 40, 'audio/webm'))->call('sendVoice', 4200)->assertHasNoErrors();
        $voice = DB::table('chat_attachments')->where('kind', 'voice')->first();
        $this->assertSame(4200, (int) $voice->duration_ms);

        // Something that isn't allowed is refused with a plain message
        $this->portalChat()->set('files', [UploadedFile::fake()->create('page.html', 2, 'text/html')])->call('send')
            ->assertHasErrors(['files.0'])->assertSee('This type of file can’t be sent.');

        // Opening a file: the client's own accounts and the delivery team, nobody else
        $this->actingAs($this->admin, 'client')->get(route('portal.chat.file', $files[0]->id))->assertOk();
        $this->actingAs($this->client([], $this->otherOrganisation()), 'client')->get(route('portal.chat.file', $files[0]->id))->assertNotFound();
        $this->actingAs($this->engineer)->get(route('hq.chat.file', $files[1]->id))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->outsider)->get(route('hq.chat.file', $files[1]->id))->assertNotFound();

        // A file in an internal note never reaches the client
        $this->hqThread($this->lead)->set('internal', true)->set('files', [UploadedFile::fake()->image('internal.png')])->call('send');
        $internal = DB::table('chat_attachments')->where('file_name', 'internal.png')->value('id');
        $this->actingAs($this->admin, 'client')->get(route('portal.chat.file', $internal))->assertNotFound();
        $this->actingAs($this->lead)->get(route('hq.chat.file', $internal))->assertOk();
    }

    private function otherOrganisation(): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $id, 'name' => 'Another Org ' . Str::random(4), 'status' => 'Active Client']);

        return $id;
    }

    public function test_a_message_unread_for_an_hour_is_emailed_once_to_the_other_side(): void
    {
        Mail::fake();
        ProjectChat::post($this->projectId, 'client', $this->admin->id, ['body' => 'Is the map fixed?']);
        $this->artisan('chat:email-unread')->assertSuccessful();
        Mail::assertNothingSent(); // not an hour yet

        // An hour later nobody at JBS Praxis has read it: the lead and the engineer on its task are emailed
        $this->travel(61)->minutes();
        $this->artisan('chat:email-unread')->assertSuccessful();
        Mail::assertSent(ChatUnreadMail::class, 2);
        Mail::assertSent(ChatUnreadMail::class, fn ($m) => $m->hasTo($this->lead->email) && ! $m->forClient && $m->count === 1
            && $m->lines[0]['name'] === 'Adaeze Okafor' && $m->lines[0]['text'] === 'Is the map fixed?' && str_contains($m->url, '/operations/client-messages?project=' . $this->projectId));
        Mail::assertSent(ChatUnreadMail::class, fn ($m) => $m->hasTo($this->engineer->email));

        // The reply waits an hour unread at the client: both of its accounts are emailed; the internal note never is
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'Yes. Can you send the logo files?']);
        ProjectChat::post($this->projectId, 'staff', $this->lead->id, ['body' => 'Only for us', 'internal' => true]);
        $this->travel(61)->minutes();
        $this->artisan('chat:email-unread')->assertSuccessful();
        Mail::assertSent(ChatUnreadMail::class, 4);
        Mail::assertSent(ChatUnreadMail::class, fn ($m) => $m->hasTo($this->finance->email) && $m->forClient && $m->count === 1
            && $m->lines[0]['text'] === 'Yes. Can you send the logo files?' && $m->lines[0]['name'] === 'Delivery T.' && str_contains($m->url, '?chat=' . $this->projectId));
        Mail::assertSent(ChatUnreadMail::class, fn ($m) => $m->hasTo($this->admin->email));
        Mail::assertNotSent(ChatUnreadMail::class, fn ($m) => collect($m->lines)->contains('text', 'Only for us'));

        // Once only: nothing new, nothing sent
        $this->travel(61)->minutes();
        $this->artisan('chat:email-unread');
        Mail::assertSent(ChatUnreadMail::class, 4);

        // Once someone on a side has read a message, nobody on that side is emailed about it
        ProjectChat::post($this->projectId, 'client', $this->admin->id, ['body' => 'Sent them by email.']);
        ProjectChat::markRead($this->projectId, 'staff', $this->engineer->id, ProjectChat::latestId($this->projectId, 'staff'));
        $this->travel(61)->minutes();
        $this->artisan('chat:email-unread');
        Mail::assertSent(ChatUnreadMail::class, 4);

        // The email itself, in French for a French client
        $parts = (new ChatUnreadMail('Chidi', true, 'Fleet Logistics Ltd', 'Fleet tracking portal', [['name' => 'Delivery T.', 'time' => '10:05 WAT', 'text' => 'Bonjour']], 1, 'https://portal.test/?chat=x'))
            ->locale('fr')->renderParts();
        $this->assertSame('Nouveau message de votre équipe projet', $parts['subject']);
        $this->assertStringContainsString('Ouvrir la conversation', $parts['html']);
        $this->assertStringContainsString('Bonjour', $parts['text']);
    }

    public function test_hq_shows_each_person_only_the_conversations_they_may_open(): void
    {
        $otherOrg = $this->otherOrganisation();
        $other = $this->project(['organization_id' => $otherOrg, 'title' => 'Payroll system']);
        ProjectChat::post($other, 'client', $this->client([], $otherOrg)->id, ['body' => 'About payroll']);
        $this->travel(1)->minutes();
        ProjectChat::post($this->projectId, 'client', $this->admin->id, ['body' => 'About the map']);

        // The engineer works on the fleet project only
        Livewire::actingAs($this->engineer)->test(Inbox::class)->assertSee('Fleet Logistics Ltd')->assertSee('About the map')->assertDontSee('Payroll system');
        $this->hqThread($this->engineer, $other)->assertForbidden();
        $this->assertSame(1, ProjectChat::staffUnreadTotal($this->engineer->id));
        Livewire::actingAs($this->engineer)->test(Launcher::class)->assertSee('Client messages, 1 unread')
            ->call('show')->assertSee('Fleet tracking portal')->assertDontSee('Payroll system')
            ->call('choose', $other)->assertSet('project', '')->call('choose', $this->projectId)->assertSet('project', $this->projectId);

        // The delivery lead (QA approver) sees every client's conversation, unread first
        $inbox = Livewire::actingAs($this->lead)->test(Inbox::class);
        $this->assertSame(2, ProjectChat::staffUnreadTotal($this->lead->id));
        $inbox->assertSeeInOrder(['Fleet tracking portal', 'Payroll system']); // both unread: the latest first
        $this->hqThread($this->lead)->call('heartbeat', true, true);
        $this->assertSame(1, ProjectChat::staffUnreadTotal($this->lead->id));
        Livewire::actingAs($this->lead)->test(Inbox::class)->assertSeeInOrder(['Payroll system', 'Fleet tracking portal']); // unread first
        Livewire::actingAs($this->lead)->test(Inbox::class)->call('open', $this->projectId)->assertSet('project', $this->projectId);

        // The page itself
        $this->actingAs($this->engineer)->get(route('hq.ops.messages'))->assertOk()->assertSee('Client messages');
    }
}
