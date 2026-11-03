<?php

namespace Tests\Feature\Campaigns;

use App\Models\Campaigns\OutreachCampaign;
use App\Models\Campaigns\OutreachEmail;
use App\Models\Campaigns\OutreachProspect;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Support\Access;
use App\Support\Campaigns\Outreach\Outreach;
use App\Support\Campaigns\RichText;
use App\Support\Campaigns\Tracking;
use App\Support\Mail\Autodiscover;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailCredentials;
use App\Support\Mail\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Mail\FakeMailServer;

/**
 * Fictional salespeople, their mailboxes (on the fake mail server: nothing leaves the machine), campaigns and prospects
 * for the Outreach tests (docs/CAMPAIGNS.md section 10). Addresses end in .test.
 */
trait OutreachFixtures
{
    use CampaignFixtures;

    protected FakeMailServer $server;

    protected function setUpOutreach(): void
    {
        config(['mail.default' => 'array']);
        $this->server = FakeMailServer::install();
        MailSettings::set('open_to', 'everyone');
        MailAccess::flush();
        Access::flush();
    }

    /** A salesperson (Sales & Marketing) with their own connected mailbox */
    protected function salesperson(string $slug = 'gerald', bool $mailbox = true): User
    {
        $user = $this->staff($slug, ['sales']);
        if ($mailbox) {
            $this->personalMailbox($user);
        }

        return $user;
    }

    protected function personalMailbox(User $user, string $status = 'active'): MailAccount
    {
        $email = strtolower($user->first_name) . '.' . Str::lower(Str::random(4)) . '@jbspraxis.test';
        $this->server->addMailbox($email, 'secret-pass');
        $account = MailAccount::create([
            'kind' => 'personal', 'email' => $email, 'display_name' => $user->name, 'owner_user_id' => $user->id, 'username' => $email,
            'secret' => MailCredentials::encrypt('secret-pass'), 'status' => $status,
        ] + Autodiscover::for($email));
        MailAccess::flush();

        return $account;
    }

    protected function outreachCampaign(User $sender, array $values = []): OutreachCampaign
    {
        return Outreach::create($sender, $values['name'] ?? 'Lagos logistics (test)', $values['brief'] ?? null);
    }

    /**
     * A prospect with its two emails. $first: the first email's status (draft, approved, …); its words are filled in
     * unless it is still being written.
     */
    protected function prospect(OutreachCampaign $campaign, string $first = 'draft', array $values = [], ?User $by = null): OutreachProspect
    {
        $n = Str::lower(Str::random(6));
        $prospect = OutreachProspect::create($values + [
            'campaign_id' => $campaign->id, 'company' => "Kilo {$n} Ltd (test)", 'first_name' => 'Ada', 'last_name' => 'Obi', 'job_title' => 'Operations director',
            'email' => "ada.{$n}@kilo.test", 'country' => 'Nigeria', 'industry' => 'Logistics & Supply Chain', 'language' => 'en',
            'findings' => 'Opened a second warehouse in Onne in September.',
        ]);
        $written = ! in_array($first, ['writing', 'not_written'], true);
        $approved = in_array($first, ['approved', 'sending', 'sent'], true);
        foreach (['first', 'follow_up'] as $kind) {
            OutreachEmail::create([
                'campaign_id' => $campaign->id, 'prospect_id' => $prospect->id, 'kind' => $kind,
                'status' => $kind === 'first' ? $first : ($approved ? 'approved' : ($written ? 'draft' : $first)),
                'subject' => $kind === 'first' && $written ? 'Your second warehouse in Onne' : null,
                'body_html' => $written ? RichText::fromPlain($kind === 'first'
                    ? "Hello Ada,\n\nCongratulations on the new warehouse in Onne.\n\nKind regards,"
                    : "Hello Ada,\n\nOne more thought on Onne.\n\nKind regards,") : null,
                'approved_by' => $approved ? ($by?->id ?? $campaign->sender_id) : null,
                'approved_at' => $approved ? now() : null,
                'approval' => $approved ? 'hand' : null,
                'token' => Tracking::newToken(),
            ]);
        }

        return $prospect;
    }

    protected function firstOf(OutreachProspect $prospect): OutreachEmail
    {
        return OutreachEmail::where('prospect_id', $prospect->id)->where('kind', 'first')->sole();
    }

    protected function followUpOf(OutreachProspect $prospect): OutreachEmail
    {
        return OutreachEmail::where('prospect_id', $prospect->id)->where('kind', 'follow_up')->sole();
    }

    /** A message in HQ Mail's copy of a mailbox, as mail:sync stores it */
    protected function receivedMail(MailAccount $account, string $from, array $values = []): void
    {
        $folderId = DB::table('mail_folders')->insertGetId(['account_id' => $account->id, 'path' => 'INBOX.' . Str::random(6), 'name' => 'Inbox', 'role' => 'inbox',
            'selectable' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->insertRow('mail_messages', $values + [
            'account_id' => $account->id, 'folder_id' => $folderId, 'uid' => random_int(1, 99999), 'from_email' => $from, 'from_name' => 'Ada Obi',
            'subject' => 'Re: Your second warehouse in Onne', 'received_at' => now(), 'is_outbound' => 0, 'body_state' => 'cached',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
