<?php

namespace Tests\Feature;

use App\Livewire\Portal\ProposalGateway;
use App\Mail\SigningCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Client Portal → proposal signing code: at most 3 codes every 15 minutes per proposal and per email address
 * (from any device), and 5 wrong codes per proposal, after which the code is cancelled and the signer waits
 * 15 minutes. Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class ProposalSigningCodeLimitsTest extends TestCase
{
    use UsesThrowawayDatabase;

    private string $org;
    private string $email;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = (string) Str::uuid();
        $this->insertRow('organizations', ['id' => $this->org, 'name' => 'Acme Ltd (test)', 'status' => 'Lead', 'preferred_language' => 'EN']);
        $this->email = 'ada.' . Str::random(6) . '@example.test';
        $this->insertRow('client_users', ['id' => (string) Str::uuid(), 'organization_id' => $this->org, 'first_name' => 'Ada', 'last_name' => 'Test', 'email' => $this->email, 'deleted_at' => null]);
    }

    /** A proposal HQ has signed and sent: it waits for the client's signature */
    private function proposal(): array
    {
        $id = (string) Str::uuid();
        $token = Str::random(40);
        $this->insertRow('proposals', [
            'id' => $id, 'organization_id' => $this->org, 'proposal_ref' => 'PRP-' . Str::random(4), 'token_hash' => $token,
            'title' => 'Finance system', 'status' => 'Pending Auth', 'hq_signature_hash' => 'SHA256-HQ-TEST', 'validity_days' => 30,
            'subtotal' => 1000, 'total_value' => 1075, 'payment_schedule_type' => '50_50', 'updated_at' => now(),
        ]);

        return ['id' => $id, 'token' => $token];
    }

    /** The signer opens the page once the last code has run out (so it shows the signing form) and asks for a code */
    private function askForCode(string $token, string $ip = '203.0.113.10')
    {
        DB::table('proposals')->where('token_hash', $token)->whereNotNull('auth_otp')->update(['otp_expires_at' => now()->subMinute()]);
        $this->app['request']->server->set('REMOTE_ADDR', $ip);

        return Livewire::test(ProposalGateway::class, ['token' => $token])
            ->set('signerName', 'Ada Test')->set('signerTitle', 'Finance director')->set('signerEmail', $this->email)
            ->call('requestAuthorizationOtp', 'data:image/png;base64,iVBORw0KGgo=');
    }

    public function test_three_codes_per_proposal_then_a_wait_whichever_device_asks(): void
    {
        Mail::fake();
        $proposal = $this->proposal();

        foreach (['203.0.113.10', '203.0.113.11', '198.51.100.7'] as $ip) {
            $this->askForCode($proposal['token'], $ip)->assertHasNoErrors()->assertSet('viewState', 'otp_verification');
        }

        // A fourth code from yet another address is refused, with the time left to wait
        $this->askForCode($proposal['token'], '192.0.2.99')
            ->assertHasErrors('signingCode')
            ->assertSee('We’ve sent 3 signing codes for this proposal in the last 15 minutes. Wait 15 minutes, then ask for a new code.');

        Mail::assertSent(SigningCode::class, 3);
    }

    public function test_three_codes_per_email_address_across_proposals(): void
    {
        Mail::fake();
        $first = $this->proposal();
        $second = $this->proposal();

        $this->askForCode($first['token'])->assertHasNoErrors();
        $this->askForCode($first['token'])->assertHasNoErrors();
        $this->askForCode($second['token'])->assertHasNoErrors();

        $this->askForCode($second['token'])
            ->assertHasErrors('signingCode')
            ->assertSee('We’ve sent 3 signing codes to this email address in the last 15 minutes.');

        Mail::assertSent(SigningCode::class, 3);
    }

    public function test_five_wrong_codes_cancel_the_code_and_lock_the_proposal_for_15_minutes(): void
    {
        Mail::fake();
        $proposal = $this->proposal();
        $this->askForCode($proposal['token'])->assertHasNoErrors();
        $code = Mail::sent(SigningCode::class)->last()->code; // the row keeps only its HMAC
        $wrong = $code === '000000' ? '111111' : '000000';
        $validFrom = DB::table('proposals')->where('id', $proposal['id'])->value('updated_at');

        for ($i = 1; $i <= 4; $i++) {
            Livewire::test(ProposalGateway::class, ['token' => $proposal['token']])->set('otpInput', $wrong)->call('verifyOtpAndExecuteLaunch')
                ->assertSee('That code isn’t right or has expired.');
        }
        Livewire::test(ProposalGateway::class, ['token' => $proposal['token']])->set('otpInput', $wrong)->call('verifyOtpAndExecuteLaunch')
            ->assertSee('Too many wrong codes, so this code no longer works. Wait 15 minutes, then reload the page and ask for a new code.');

        // The code can no longer be used, and the proposal's validity hasn't moved
        $row = DB::table('proposals')->where('id', $proposal['id'])->first();
        $this->assertNull($row->auth_otp);
        $this->assertNull($row->otp_expires_at);
        $this->assertSame((string) $validFrom, (string) $row->updated_at);

        // Even the right code is refused during the wait, and nothing is signed
        Livewire::test(ProposalGateway::class, ['token' => $proposal['token']])->set('otpInput', $code)->call('verifyOtpAndExecuteLaunch')
            ->assertHasErrors('otpInput')->assertSet('viewState', 'review');
        $this->assertSame('Pending Auth', DB::table('proposals')->where('id', $proposal['id'])->value('status'));
    }

    public function test_the_right_code_signs_and_clears_the_wrong_code_count(): void
    {
        Mail::fake();
        $proposal = $this->proposal();
        $this->askForCode($proposal['token'])->assertHasNoErrors();
        $code = Mail::sent(SigningCode::class)->last()->code; // the row keeps only its HMAC

        Livewire::test(ProposalGateway::class, ['token' => $proposal['token']])->set('otpInput', $code === '000000' ? '111111' : '000000')->call('verifyOtpAndExecuteLaunch');
        Livewire::test(ProposalGateway::class, ['token' => $proposal['token']])->set('otpInput', $code)->call('verifyOtpAndExecuteLaunch')
            ->assertHasNoErrors()->assertSet('viewState', 'completed');

        $this->assertSame('Authorized', DB::table('proposals')->where('id', $proposal['id'])->value('status'));
        $this->assertFalse(\Illuminate\Support\Facades\RateLimiter::tooManyAttempts(ProposalGateway::checkKey($proposal['id']), 1));
    }

    public function test_a_code_email_that_fails_is_not_logged_and_the_code_is_cancelled(): void
    {
        $proposal = $this->proposal();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP unavailable'));
        Log::spy();

        $this->askForCode($proposal['token'])
            ->assertHasErrors('signerEmail')
            ->assertSet('viewState', 'review')
            ->assertSee('We couldn’t email your signing code just now.');

        // Nobody can read the code in the log, and a reload shows the signing form, not a code that never came
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $message === 'Proposal signing code email failed'
            && $context === ['proposal_id' => $proposal['id'], 'message' => 'SMTP unavailable']);
        Log::shouldNotHaveReceived('info');
        $row = DB::table('proposals')->where('id', $proposal['id'])->first();
        $this->assertNull($row->auth_otp);
        $this->assertNotNull($row->client_signature_b64, 'the drawn signature stays saved');
    }

    public function test_the_messages_read_well_in_french(): void
    {
        Mail::fake();
        DB::table('organizations')->where('id', $this->org)->update(['preferred_language' => 'FR']);
        $proposal = $this->proposal();
        for ($i = 0; $i < 3; $i++) {
            $this->askForCode($proposal['token'])->assertHasNoErrors();
        }

        $this->askForCode($proposal['token'])->assertSee(
            "Nous avons envoyé 3\u{202F}codes de signature pour cette proposition au cours des 15\u{202F}dernières minutes. Patientez 15\u{202F}minutes, puis demandez un nouveau code.",
            false
        );

        app()->setLocale('fr');
        $this->assertSame("1\u{202F}minute", trans_choice('gateway.proposal.wait_minutes', 1, ['count' => 1]));
        app()->setLocale('en');
        $this->assertSame('1 minute', trans_choice('gateway.proposal.wait_minutes', 1, ['count' => 1]));
        $this->assertSame('7 minutes', trans_choice('gateway.proposal.wait_minutes', 7, ['count' => 7]));
    }
}
