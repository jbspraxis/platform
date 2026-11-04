<?php

namespace Tests\Feature\Mdm;

use App\Livewire\Hq\Mdm\CompanyDocuments;
use App\Models\CompanyDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Company documents fixtures (docs/COMPANY_DOCUMENTS.md): fictional companies, people with each level of Master data
 * access, uploads through the screen and Claude's reading as FakeAi scripts it. No real company or person data.
 */
trait CompanyDocumentFixtures
{
    /** Address HQ holds for the Nigerian company, and the misprint the test certificate carries */
    protected const HQ_ADDRESS = '21B Sample Close, Lekki, Lagos';

    protected const MISPRINTED_ADDRESS = '12, Sample Close, Lekki Peninsula II, Lagos';

    /** A JBS Praxis company in Legal entities; Nigeria's is still being registered, as live was before the certificate */
    protected function company(string $region, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('mdm_legal_entities', $values + [
            'id' => $id, 'region_code' => $region,
            'legal_name' => ['NG' => 'JBS Praxis LLC', 'CM' => 'JBS Praxis SARL'][$region] ?? 'JBS Praxis Holdings',
            'registration_number' => null, 'tax_identification_number' => null,
            'registered_address' => $region === 'NG' ? self::HQ_ADDRESS : '1 Rue Exemple, Douala',
            'legal_representative_name' => 'Ada Director', 'legal_representative_title' => 'Managing Director', 'is_active' => 1,
        ]);

        return $id;
    }

    /** Owner: everything. finance: mdm view, create, edit (no delete, no website). hr: mdm.view only. engineer: no Master data */
    protected function owner(): User
    {
        return $this->staff('owner', ['owner']);
    }

    protected function editor(): User
    {
        return $this->staff('finance', ['finance']);
    }

    protected function viewer(): User
    {
        return $this->staff('hr', ['hr']);
    }

    protected function outsider(): User
    {
        return $this->staff('engineer', ['engineer']);
    }

    protected function pdf(string $name = 'certificate.pdf', int $kb = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    /** Uploads through Master data › Company documents as $user, and returns the new document */
    protected function upload(User $user, string $companyKey, string $kind, array $details = [], ?UploadedFile $file = null): CompanyDocument
    {
        $this->actingAs($user);
        Livewire::test(CompanyDocuments::class)
            ->call('openUpload')
            ->set('newDoc', array_merge(['company' => $companyKey, 'kind' => $kind, 'title' => 'Test ' . $kind, 'issued_on' => '', 'expires_on' => '',
                'remind_days' => '30', 'notes' => ''], $details))
            ->set('file', $file ?? $this->pdf())
            ->call('saveUpload')
            ->assertHasNoErrors();

        return CompanyDocument::latest('created_at')->latest('id')->firstOrFail();
    }

    /** What Claude copies from the fictional certificate of incorporation */
    protected function certificateReading(array $values = []): array
    {
        return $values + [
            'company_name' => 'JBS PRAXIS LIMITED',
            'rc_number' => '1234567',
            'tin' => '9876543210',
            'registered_on' => '2026-11-04',
            'company_type' => 'Private company limited by shares',
            'registered_address' => self::MISPRINTED_ADDRESS,
            'share_capital' => 'NGN 1,000,000',
            'issued_on' => '2026-11-04',
            'expires_on' => '',
            'deadlines' => [],
            'unreadable' => '',
        ];
    }
}
