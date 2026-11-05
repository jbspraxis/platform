<?php

namespace Tests\Feature\Letters;

use App\Mail\AgreementMail;
use App\Mail\SigningCode;
use App\Models\Letters\LetterCounterparty;
use App\Models\Letters\LetterDocument;
use App\Models\User;
use App\Support\Letters\Agreements;
use App\Support\Letters\Letters;
use App\Support\Signing\SigningCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * Countersigning an agreement online (docs/DOCUMENTS.md section 12), end to end: HQ signs and issues it, sends
 * the private link, the other party opens it, gets a code wrong, gets it right, and the signed PDF with its
 * signature record page goes to both sides. Also what must not happen: a wrong code, an expired link, a link
 * taken back, and sending without letters.sign.
 */
class AgreementCountersignTest extends TestCase
{
    use AgreementFixtures, LetterFixtures, UsesThrowawayDatabase;

    private const SIGNING = 'data:image/jpeg;base64,';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        RateLimiter::clear(SigningCodes::sendKey(Agreements::KIND, 'x'));
    }

    // ------------------------------------------------------------------ the happy path

    public function test_the_whole_flow_from_sending_to_the_signed_pdf(): void
    {
        [$owner, $document] = $this->issuedAgreement();

        // 1. HQ sends the private link, with the issued PDF attached
        $party = Agreements::send($document, $owner);
        $document->refresh();

        $this->assertSame(LetterDocument::SENT_FOR_SIGNATURE, $document->status);
        $this->assertSame(LetterCounterparty::SENT, $party->status);
        $this->assertNotNull($party->token);
        $this->assertTrue($party->token_expires_at->between(now()->addDays(13), now()->addDays(15)), 'the link lasts a fortnight');
        Mail::assertSent(AgreementMail::class, fn ($mail) => $mail->kind === AgreementMail::REQUEST
            && $mail->hasTo('jonathan@example.test') && $mail->pdf !== null);

        // 2. They open it and ask for a code
        $page = Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->assertSee('Project Contractor Agreement')
            ->assertSee('Full-Stack Engineer')
            ->set('signedName', 'Jonathan Ndamzi Destiny')
            ->call('setImage', $this->jpeg(), 'drawn', 300, 90)
            ->call('requestCode')
            ->assertHasNoErrors()
            ->assertSet('step', 'code');

        $code = $this->codeSent();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, $party->fresh()->otp, 'the row keeps the HMAC, never the code');

        // 3. A wrong code signs nothing
        $page->set('code', '000000')->call('verify')->assertHasErrors('code');
        $this->assertSame(LetterCounterparty::SENT, $party->fresh()->status);
        $this->assertSame(LetterDocument::SENT_FOR_SIGNATURE, $document->fresh()->status);

        // 4. The right one does
        $page->set('code', $code)->call('verify')->assertHasNoErrors()->assertDispatched('praxis-toast', $this->toast('Signed. Thank you.'));

        $party->refresh();
        $document->refresh();
        $this->assertSame(LetterCounterparty::SIGNED, $party->status);
        $this->assertSame('Jonathan Ndamzi Destiny', $party->signed_name);
        $this->assertNotNull($party->signed_at);
        $this->assertNotNull($party->code_verified_at);
        $this->assertNull($party->otp, 'the code is spent');
        $this->assertSame(LetterDocument::FULLY_SIGNED, $document->status);
        $this->assertNotNull($document->fully_signed_at);

        // 5. The signed PDF is a new file; the issued one is untouched
        $issued = Letters::file($document);
        $signed = Agreements::signedFile($document);
        $this->assertNotNull($signed);
        $this->assertNotSame($issued, $signed);
        $this->assertSame(hash('sha256', $issued), $document->file_sha256, 'the issued fingerprint never changes');
        $this->assertSame(hash('sha256', $signed), $document->signed_file_sha256);
        $this->assertGreaterThan($document->file_bytes, $document->signed_file_bytes);

        // 6. Both parties have the signed copy
        Mail::assertSent(AgreementMail::class, fn ($mail) => $mail->kind === AgreementMail::SIGNED && $mail->hasTo('jonathan@example.test'));
        Mail::assertSent(AgreementMail::class, fn ($mail) => $mail->kind === AgreementMail::SIGNED && $mail->hasTo($owner->email));
    }

    public function test_the_signed_pdf_carries_both_signatures_and_a_signature_record_page(): void
    {
        [$owner, $document, $party, $code] = $this->readyToSign();
        Agreements::countersign($party, $code, '102.89.33.10');
        $document->refresh();

        $html = \App\Support\Letters\LetterPdf::html($document, \App\Support\Letters\Letterhead::details($this->company()), final: true, countersigned: true);

        $this->assertStringContainsString('Signature record', $html);
        $this->assertStringContainsString('Jonathan Ndamzi Destiny', $html);
        $this->assertStringContainsString('Ada Test', $html, 'JBS Praxis’s own signatory');
        $this->assertStringContainsString('102.89.33.10', $html);
        $this->assertStringContainsString('jonathan@example.test', $html);
        // The signed version points back at the issued one by printing its fingerprint
        $this->assertStringContainsString($document->file_sha256, $html);
        // Two signature images, not one pen line
        $this->assertSame(2, substr_count($html, 'class="sig-image"'));
        $this->assertStringNotContainsString('<div class="sig-blank">', $html, 'no pen line is left once both have signed');

        $pages = $this->pageCount(Agreements::signedFile($document));
        $this->assertGreaterThan(4, $pages);
    }

    // ------------------------------------------------------------------ what must not work

    public function test_a_code_goes_only_to_the_address_the_agreement_names(): void
    {
        [, , $party] = $this->readyToSign();

        Mail::assertSent(SigningCode::class, fn ($mail) => $mail->hasTo('jonathan@example.test'));
        Mail::assertNotSent(SigningCode::class, fn ($mail) => ! $mail->hasTo('jonathan@example.test'));
        $this->assertSame('jonathan@example.test', $party->email);
    }

    public function test_a_signature_is_refused_before_a_code_is_asked_for(): void
    {
        [, , $party] = $this->sentAgreement();

        Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->set('signedName', 'Jonathan Ndamzi Destiny')
            ->call('requestCode')
            ->assertHasErrors('signature');

        Mail::assertNotSent(SigningCode::class);
    }

    public function test_a_name_is_required(): void
    {
        [, , $party] = $this->sentAgreement();

        Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->call('setImage', $this->jpeg(), 'drawn', 300, 90)
            ->set('signedName', '')
            ->call('requestCode')
            ->assertHasErrors('signedName');
    }

    public function test_five_wrong_codes_cancel_the_one_that_was_sent(): void
    {
        [, , $party, $code] = $this->readyToSign();
        $page = Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token]);

        for ($i = 0; $i < 5; $i++) {
            $page->set('code', '111111')->call('verify')->assertHasErrors('code');
        }

        $this->assertNull($party->fresh()->otp, 'the fifth wrong code throws the right one away');
        $page->set('code', $code)->call('verify')->assertHasErrors('code');
        $this->assertSame(LetterCounterparty::SENT, $party->fresh()->status);
    }

    public function test_a_code_cannot_be_asked_for_more_than_three_times_in_a_quarter_of_an_hour(): void
    {
        [, , $party] = $this->sentAgreement();
        $page = Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->set('signedName', 'Jonathan Ndamzi Destiny')
            ->call('setImage', $this->jpeg(), 'drawn', 300, 90);

        for ($i = 0; $i < 3; $i++) {
            $page->call('requestCode')->assertHasNoErrors();
            $page->call('startAgain');
        }
        $page->call('requestCode')->assertHasErrors('code');

        Mail::assertSentCount(4);       // one agreement email and three codes; the fourth was refused
    }

    public function test_a_link_that_has_run_out_signs_nothing(): void
    {
        [, $document, $party] = $this->sentAgreement();
        $party->forceFill(['token_expires_at' => now()->subDay()])->save();

        Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->assertSee('This link has run out')
            ->set('signedName', 'Jonathan Ndamzi Destiny')
            ->call('setImage', $this->jpeg(), 'drawn', 300, 90)
            ->call('requestCode')
            ->assertHasErrors('signedName');

        Mail::assertNotSent(SigningCode::class);
        $this->assertSame(LetterDocument::SENT_FOR_SIGNATURE, $document->fresh()->status);
    }

    public function test_sending_it_again_gives_a_new_link_and_kills_the_old_one(): void
    {
        [$owner, $document, $party] = $this->sentAgreement();
        $old = $party->token;
        $party->forceFill(['token_expires_at' => now()->subDay()])->save();

        $again = Agreements::send($document->fresh(), $owner);

        $this->assertNotSame($old, $again->token);
        $this->assertNull(Agreements::findByToken($old), 'the old link is nobody’s');
        $this->assertTrue($again->token_expires_at->isFuture());
        $this->assertSame(2, $again->sent_count);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'AGREEMENT_RESENT', 'target_id' => $document->id]);
    }

    public function test_a_link_taken_back_stops_working_at_once(): void
    {
        [$owner, $document, $party] = $this->sentAgreement();
        $token = $party->token;

        Agreements::cancel($document->fresh(), $owner);

        $this->assertNull(Agreements::findByToken($token));
        $this->assertSame(LetterDocument::ISSUED, $document->fresh()->status);
        $this->assertSame(LetterCounterparty::PENDING, $party->fresh()->status);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'AGREEMENT_SIGNING_CANCELLED', 'target_id' => $document->id]);
    }

    public function test_signing_twice_changes_nothing(): void
    {
        [, $document, $party, $code] = $this->readyToSign();

        $this->assertTrue(Agreements::countersign($party, $code, '1.2.3.4'));
        $first = $document->fresh()->signed_file_sha256;
        $this->assertFalse(Agreements::countersign($party->fresh(), $code, '1.2.3.4'));
        $this->assertSame($first, $document->fresh()->signed_file_sha256);
    }

    // ------------------------------------------------------------------ permissions

    public function test_only_letters_sign_may_send_an_agreement_for_signature(): void
    {
        [$owner, $document] = $this->issuedAgreement();
        $reader = $this->staffWith(['letters.view', 'letters.create', 'letters.manage']);

        $this->actingAs($reader);
        Livewire::test(\App\Livewire\Hq\Letters\DocumentPage::class, ['document' => $document->id])
            ->call('sendForSignature')
            ->assertForbidden();

        $this->assertSame(LetterDocument::ISSUED, $document->fresh()->status);
        Mail::assertNotSent(AgreementMail::class);

        $this->actingAs($owner);
        Livewire::test(\App\Livewire\Hq\Letters\DocumentPage::class, ['document' => $document->id])
            ->call('sendForSignature')
            ->assertHasNoErrors();
        $this->assertSame(LetterDocument::SENT_FOR_SIGNATURE, $document->fresh()->status);
    }

    public function test_only_letters_sign_may_take_the_link_back(): void
    {
        [, $document] = $this->sentAgreement();
        $reader = $this->staffWith(['letters.view', 'letters.manage']);

        $this->actingAs($reader);
        Livewire::test(\App\Livewire\Hq\Letters\DocumentPage::class, ['document' => $document->id])
            ->call('cancelSigning')
            ->assertForbidden();

        $this->assertSame(LetterDocument::SENT_FOR_SIGNATURE, $document->fresh()->status);
    }

    // ------------------------------------------------------------------ the audit log

    public function test_every_step_is_in_the_audit_log(): void
    {
        [, $document, $party, $code] = $this->readyToSign();
        Agreements::countersign($party, $code, '102.89.33.10');

        foreach (['LETTER_CREATED', 'LETTER_SIGNED', 'LETTER_ISSUED', 'AGREEMENT_SENT', 'AGREEMENT_CODE_SENT',
            'AGREEMENT_COUNTERSIGNED', 'AGREEMENT_FULLY_SIGNED'] as $action) {
            $this->assertDatabaseHas('system_audit_logs', ['action' => $action, 'target_id' => $document->id]);
        }

        $countersigned = DB::table('system_audit_logs')->where('action', 'AGREEMENT_COUNTERSIGNED')->where('target_id', $document->id)->first();
        $this->assertStringContainsString('Jonathan Ndamzi Destiny', $countersigned->description);
        $this->assertStringContainsString('102.89.33.10', (string) $countersigned->new_values);

        // The code itself is never anywhere near the log
        $this->assertSame(0, DB::table('system_audit_logs')->where('description', 'like', '%' . $code . '%')->count());
    }

    // ------------------------------------------------------------------ the PDF the signing page hands over

    public function test_the_signing_page_hands_over_the_agreement_and_then_the_signed_version(): void
    {
        [, $document, $party, $code] = $this->readyToSign();

        $this->get(route('portal.agreement.pdf', ['token' => $party->token]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        Agreements::countersign($party, $code, '1.2.3.4');
        $body = $this->get(route('portal.agreement.pdf', ['token' => $party->token]))->assertOk()->getContent();

        $this->assertSame(Agreements::signedFile($document->fresh()), $body);
    }

    public function test_an_unknown_token_is_nothing_at_all(): void
    {
        $this->get(route('portal.agreement.pdf', ['token' => str_repeat('a', 48)]))->assertNotFound();
        $this->assertNull(Agreements::findByToken(str_repeat('a', 48)));
        $this->assertNull(Agreements::findByToken('short'));
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{0: User, 1: LetterDocument} an agreement signed by JBS Praxis and issued */
    private function issuedAgreement(): array
    {
        Storage::disk(Letters::DISK);
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->signatureFor($owner);
        $document = $this->agreement($owner, $this->company());
        Letters::sign($document, $owner);
        Letters::issue($document->fresh(), $owner);

        return [$owner, $document->fresh()];
    }

    /** @return array{0: User, 1: LetterDocument, 2: LetterCounterparty} …and sent to the other party */
    private function sentAgreement(): array
    {
        [$owner, $document] = $this->issuedAgreement();
        $party = Agreements::send($document, $owner);

        return [$owner, $document->fresh(), $party];
    }

    /** @return array{0: User, 1: LetterDocument, 2: LetterCounterparty, 3: string} …with a code in their inbox */
    private function readyToSign(): array
    {
        [$owner, $document, $party] = $this->sentAgreement();
        Livewire::test(\App\Livewire\Portal\AgreementSigning::class, ['token' => $party->token])
            ->set('signedName', 'Jonathan Ndamzi Destiny')
            ->call('setImage', $this->jpeg(), 'drawn', 300, 90)
            ->call('requestCode')
            ->assertHasNoErrors();

        return [$owner, $document->fresh(), $party->fresh(), $this->codeSent()];
    }

    /** The code out of the last signing-code email, which is the only place it exists */
    private function codeSent(): string
    {
        $code = null;
        Mail::assertSent(SigningCode::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    /** An account whose one role has exactly these permissions, and nothing else */
    private function staffWith(array $permissions): User
    {
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->insertRow('users', [
            'id' => $id, 'organization_id' => \Database\Seeders\CoreSeeder::INTERNAL_ORG_ID,
            'first_name' => 'Reader', 'last_name' => 'Test',
            'email' => 'reader.' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)) . '@local.test',
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(24)), 'status' => 'Active',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'Test ' . \Illuminate\Support\Str::random(6), 'slug' => 'test-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8)),
            'audience' => 'staff', 'is_protected' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('permissions')->whereIn('slug', $permissions)->pluck('id') as $permissionId) {
            DB::table('role_permissions')->insert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        DB::table('staff_roles')->insert(['user_id' => $id, 'role_id' => $roleId]);
        \App\Support\Access::flush();
        \App\Support\SignIn\SignIn::markFresh();

        return User::findOrFail($id);
    }

    protected function pageCount(string $pdf): int
    {
        preg_match_all('~/Type\s*/Page[^s]~', $pdf, $matches);

        return count($matches[0]);
    }
}
