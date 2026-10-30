<?php

namespace Tests\Feature;

use App\Livewire\Hq\Intelligence\LiveConsultation;
use App\Mail\FreeAuditDeclinedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ → Free audits → Decline: the request is Cancelled (its time is free again on the website), the reason goes to
 * the audit log and never to the client, and the polite email is optional. Runs on a throwaway MySQL database
 * (see UsesThrowawayDatabase).
 */
class FreeAuditDeclineTest extends TestCase
{
    use UsesThrowawayDatabase;

    /** A website booking: organisation, the person who booked (created_by) and the open request */
    private function request(string $language = 'EN'): array
    {
        $orgId = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $orgId, 'name' => 'Acme Ltd (test)', 'status' => 'Lead', 'preferred_language' => $language]);
        $userId = (string) Str::uuid();
        $email = 'ada.' . Str::random(6) . '@example.test';
        $this->insertRow('client_users', ['id' => $userId, 'organization_id' => $orgId, 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => $email, 'deleted_at' => null]);
        $apptId = (string) Str::uuid();
        $this->insertRow('appointments', [
            'id' => $apptId, 'organization_id' => $orgId, 'title' => 'Free audit and demo', 'status' => 'Requested', 'created_by' => $userId,
            'start_time' => '2026-11-27 16:00:00', 'end_time' => '2026-11-27 17:00:00',
        ]);

        return ['appt' => $apptId, 'email' => $email];
    }

    public function test_declining_frees_the_time_logs_the_reason_and_emails_the_client_politely(): void
    {
        Mail::fake();
        $request = $this->request();
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LiveConsultation::class)
            ->call('declineRequest', $request['appt'], 'Test booking from the website check', true)
            ->assertSet('requestedAppointments', fn ($list) => collect($list)->where('id', $request['appt'])->isEmpty())
            ->assertSee("Request declined. We emailed {$request['email']} to offer another time.");

        $this->assertSame('Cancelled', DB::table('appointments')->where('id', $request['appt'])->value('status'));

        $log = DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_DECLINED')->where('target_id', $request['appt'])->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Reason: Test booking from the website check', $log->description);
        // MySQL stores JSON with its own key order
        $this->assertEquals(['status' => 'Cancelled', 'reason' => 'Test booking from the website check', 'client_emailed' => true], json_decode($log->new_values, true));

        Mail::assertSent(FreeAuditDeclinedMail::class, function (FreeAuditDeclinedMail $mail) use ($request) {
            $html = $mail->render();
            $this->assertStringNotContainsString('website check', $html, 'the team’s reason never reaches the client');
            $this->assertStringContainsString('Choose another time', $html);
            $this->assertStringContainsString('16:00 (WAT)', $html);

            return $mail->hasTo($request['email']);
        });
    }

    public function test_the_email_is_optional(): void
    {
        Mail::fake();
        $request = $this->request();
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LiveConsultation::class)->call('declineRequest', $request['appt'], 'Spam', false)
            ->assertSee('Request declined. The time is free again on the website, and the client wasn’t emailed.');

        $this->assertSame('Cancelled', DB::table('appointments')->where('id', $request['appt'])->value('status'));
        Mail::assertNothingSent();
        $log = DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_DECLINED')->where('target_id', $request['appt'])->first();
        $this->assertFalse(json_decode($log->new_values, true)['client_emailed']);
    }

    public function test_a_reason_is_required_and_only_approvers_can_decline(): void
    {
        Mail::fake();
        $request = $this->request();

        $this->actingAs($this->staff('sales', ['sales']));
        Livewire::test(LiveConsultation::class)->call('declineRequest', $request['appt'], '   ', true)
            ->assertSee('Give a reason for declining, so the team knows why.');
        $this->assertSame('Requested', DB::table('appointments')->where('id', $request['appt'])->value('status'));

        $this->actingAs($this->staff('auditor', ['auditor']));
        Livewire::test(LiveConsultation::class)->call('declineRequest', $request['appt'], 'No', true)
            ->assertSee('You don’t have permission to decline free audits. Ask an administrator to change your role.');
        $this->assertSame('Requested', DB::table('appointments')->where('id', $request['appt'])->value('status'));

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('system_audit_logs')->where('action', 'FREE_AUDIT_DECLINED')->count());
    }

    public function test_a_request_already_approved_is_left_alone(): void
    {
        Mail::fake();
        $request = $this->request();
        DB::table('appointments')->where('id', $request['appt'])->update(['status' => 'Scheduled']);
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(LiveConsultation::class)->call('declineRequest', $request['appt'], 'Too late', true)
            ->assertSee('This request was already approved or declined, so nothing changed. The list is up to date now.');

        $this->assertSame('Scheduled', DB::table('appointments')->where('id', $request['appt'])->value('status'));
        Mail::assertNothingSent();
    }

    public function test_the_email_reads_well_in_both_languages(): void
    {
        $appointment = (object) ['start_time' => '2026-11-27 16:00:00', 'end_time' => '2026-11-27 17:00:00'];

        app()->setLocale('en');
        $en = new FreeAuditDeclinedMail($appointment, 'Acme Ltd (test)', 'Ada');
        $html = $en->render();
        $this->assertSame('Your free audit request for Friday 27 November 2026', $en->envelope()->subject);
        $this->assertStringContainsString('We’re not able to confirm the time you asked for.', $html);
        $this->assertMatchesRegularExpression('~href="https?://[^"]+/schedule"~', $html);

        app()->setLocale('fr');
        $fr = new FreeAuditDeclinedMail($appointment, 'Acme Ltd (test)');
        $html = $fr->render();
        $this->assertSame('Votre demande d’audit offert du vendredi 27 novembre 2026', $fr->envelope()->subject);
        $this->assertStringContainsString("16\u{202F}h\u{202F}00 (heure d’Afrique de l’Ouest, WAT)", $html);
        $this->assertMatchesRegularExpression('~href="https?://[^"]+/fr/schedule"~', $html);
    }
}
