<?php

namespace Tests\Feature;

use App\Livewire\Hq\Marketing\WebsiteSettings as Screen;
use App\Support\WebsiteSettings;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HQ → Marketing → Website settings (docs/SITE_API.md section 4): who can open and save it, that every field
 * reaches GET /settings, the calm wording of its messages, and the note about what the site shows before PR2c.
 * Runs on a throwaway MySQL database (see UsesThrowawayDatabase).
 */
class WebsiteSettingsScreenTest extends TestCase
{
    use UsesThrowawayDatabase;

    public function test_sales_saves_every_field_and_the_api_payload_follows(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));

        $screen = Livewire::test(Screen::class)
            ->assertSet('canEdit', true)
            ->set('settings.company.legal_name', 'JBS Praxis LLC (test)')
            ->set('settings.company.founded', '2023')
            ->set('settings.company.offices.0.address', '1 Test Street')
            ->set('settings.contact.email', 'hello@example.test')
            ->set('settings.contact.hours', 'Monday to Friday')
            ->set('settings.contact.phones.0.number', '+234 800 000 0000')
            ->set('settings.contact.whatsapp.0.number', '+234 800 000 0001')
            ->set('settings.social.x', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertStringStartsWith('Website settings saved', (string) $screen->get('flash'));

        $payload = WebsiteSettings::publicPayload();
        $this->assertSame('JBS Praxis LLC (test)', $payload['company']['legal_name']);
        $this->assertSame(2023, $payload['company']['founded']);
        $this->assertSame('1 Test Street', $payload['company']['offices'][0]['address']);
        $this->assertSame('Monday to Friday', $payload['contact']['hours']);
        $this->assertSame('+234 800 000 0001', $payload['contact']['whatsapp'][0]['number']);
        $this->assertNull($payload['social']['x']);
        $this->assertSame([], $payload['leadership']['people']);
    }

    public function test_messages_read_plainly(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));

        $errors = Livewire::test(Screen::class)
            ->set('settings.company.registrations.0.status', 'registered')
            ->set('settings.company.registrations.0.number', '')
            ->set('settings.contact.phones.0.number', 'call me')
            ->call('save')
            ->errors();

        // "registration number 1", "phone number 1" (parity review 14), never "number of registration 1"
        $this->assertSame('Add the registration number 1, or set its status to ‘Registration in progress’.',
            $errors->first('settings.company.registrations.0.number'));
        $this->assertSame('Enter the phone number 1 with digits and spaces only, starting with the country code, for example +234 800 000 0000.',
            $errors->first('settings.contact.phones.0.number'));
    }

    public function test_screen_says_what_the_site_shows_until_pr2c(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));

        Livewire::test(Screen::class)
            ->assertSee('WhatsApp numbers, opening hours, office addresses, the year founded and leadership are saved now.')
            ->assertSee('PR2c');
    }

    public function test_view_only_cannot_save_and_others_cannot_open(): void
    {
        $this->actingAs($this->staff('auditor', ['auditor']));
        $screen = Livewire::test(Screen::class)->assertSet('canEdit', false);
        try {
            $screen->set('settings.contact.email', 'x@example.test')->call('save');
        } catch (\Throwable $e) {
            // refused: either an exception or a 403 response
        }
        $this->assertNotSame('x@example.test', WebsiteSettings::get('contact.email'));

        $this->actingAs($this->staff('engineer', ['engineer']));
        Livewire::test(Screen::class)->assertForbidden();
    }

    public function test_route_opens_for_website_view_only(): void
    {
        $hq = 'http://' . config('app.domains.hq');

        $this->actingAs($this->staff('sales2', ['sales']));
        $this->get($hq . '/marketing/website-settings')->assertOk();

        $this->actingAs($this->staff('engineer2', ['engineer']));
        $this->get($hq . '/marketing/website-settings')->assertForbidden();
    }
}
