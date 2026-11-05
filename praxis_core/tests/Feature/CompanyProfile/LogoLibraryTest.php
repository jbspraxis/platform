<?php

namespace Tests\Feature\CompanyProfile;

use App\Livewire\Hq\CompanyProfile\LogoLibrary as LogoLibraryScreen;
use App\Models\CompanyProfile\ClientLogo;
use App\Support\CompanyProfile\ClientLogos;
use App\Support\CompanyProfile\LogoFiles;
use App\Support\CompanyProfile\LogoLibrary;
use App\Support\Hosts;
use App\Services\Site\SiteSignature;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\TestCase;

/**
 * HQ › Company profile › Client logos (docs/COMPANY_PROFILE.md section 2): adding a logo, naming it, the
 * order, retiring and restoring, who may do any of it, what the company profile is handed, and what
 * jbspraxis.com is served.
 */
class LogoLibraryTest extends TestCase
{
    use LogoFixtures, UsesThrowawayDatabase;

    private const KEY_ID = 'test-key';

    private const SECRET = 'test-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        config(['site_api.keys' => [self::KEY_ID => self::SECRET]]);
        // The throwaway database is seeded, logos and all; these tests build their own small library
        ClientLogo::query()->delete();
    }

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    private function add(string $name, array $renditions = [], array $attributes = []): ClientLogo
    {
        return LogoLibrary::save(
            ['name' => $name] + $attributes,
            $renditions ?: ['print' => $this->jpeg(), 'png' => $this->png(), 'web' => $this->webp()],
        );
    }

    // ------------------------------------------------------------------ who may do what

    public function test_the_library_is_closed_until_the_owner_opens_it_to_a_role(): void
    {
        // A role with nothing granted sees nothing (docs/COMPANY_PROFILE.md section 2)
        $this->actingAs($this->staff('delivery', ['delivery']))->get($this->hq('/company-profile/logos'))->assertForbidden();

        // Sales & Marketing has company_profile.view from the engine's grant migration: they read the wall,
        // and the screen offers them nothing to change
        $this->actingAs($this->staff('sales', ['sales']))->get($this->hq('/company-profile/logos'))
            ->assertOk()->assertSee('Client logos')->assertDontSee('Add a logo');

        $this->actingAs($this->staff('owner', ['owner']))->get($this->hq('/company-profile/logos'))
            ->assertOk()->assertSee('Client logos')->assertSee('Add a logo');
    }

    public function test_someone_who_may_only_read_cannot_add_or_retire_a_logo(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $logo = $this->add('Read only (test)');

        $sales = $this->staff('sales', ['sales']);
        Livewire::actingAs($sales)->test(LogoLibraryScreen::class)->call('add')->assertForbidden();
        Livewire::actingAs($sales)->test(LogoLibraryScreen::class)->call('retire', $logo->id)->assertForbidden();

        $this->assertNull($logo->fresh()->retired_at);
    }

    public function test_a_starter_roles_wildcard_never_sweeps_the_module_up(): void
    {
        // company_profile is 'wildcard' => false in config/access.php: the profile holds team data
        $this->assertFalse((bool) (config('access.modules.staff.company_profile.wildcard') ?? true));
        $this->assertSame(
            ['view', 'edit', 'approve', 'manage'],
            config('access.modules.staff.company_profile.actions'),
        );
    }

    public function test_the_picture_of_a_logo_is_behind_the_permission_too(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $logo = $this->add('Foxtrot Foods (test)');

        $this->get($this->hq('/company-profile/logos/' . $logo->id . '/web'))
            ->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->actingAs($this->staff('delivery', ['delivery']))
            ->get($this->hq('/company-profile/logos/' . $logo->id . '/web'))->assertForbidden();
    }

    // ------------------------------------------------------------------ the library itself

    public function test_a_logo_is_added_named_and_given_a_sector(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);

        Livewire::test(LogoLibraryScreen::class)
            ->call('add')
            ->set('name', 'Smart Logistics and Services')
            ->set('sector', 'Logistics')
            ->set('onWebsite', false)
            ->call('setFiles', ['print' => $this->jpeg(), 'png' => $this->png(), 'web' => $this->webp(), 'width' => 220, 'height' => 160, 'filename' => 'smart.png'])
            ->call('save')
            ->assertHasNoErrors();

        $logo = ClientLogo::query()->firstOrFail();
        $this->assertSame('smart-logistics-and-services', $logo->key);
        $this->assertSame('Logistics', $logo->sector);
        $this->assertTrue($logo->on_profile);
        $this->assertTrue($logo->on_deck);
        $this->assertFalse($logo->on_website);
        $this->assertSame('jpeg', $logo->print_kind);
        $this->assertSame(220, $logo->width);

        foreach ([$logo->print_path, $logo->png_path, $logo->web_path] as $path) {
            $this->assertTrue(Storage::disk('local')->exists($path), $path . ' is on the private disk');
            $this->assertStringStartsWith('company-profile/logos/', $path, 'never a public path');
        }
    }

    public function test_a_logo_needs_a_name_and_a_file_the_profile_can_print(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));

        Livewire::test(LogoLibraryScreen::class)->call('add')->call('save')->assertHasErrors('name');

        Livewire::test(LogoLibraryScreen::class)->call('add')->set('name', 'No file (test)')
            ->call('save')->assertHasErrors('printUri');

        $this->assertSame(0, ClientLogo::query()->count());
    }

    public function test_a_second_logo_of_the_same_name_gets_its_own_key(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));

        $this->assertSame('orika-living', $this->add('Orika Living')->key);
        $this->assertSame('orika-living-2', $this->add('Orika Living')->key);
    }

    public function test_an_svg_is_kept_for_print_and_stripped_of_anything_that_runs(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));

        $logo = $this->add('Vector Mark (test)', [
            'print' => $this->svg('<script>alert(1)</script><image href="https://example.com/x.png"/><rect onclick="x()" width="1" height="1"/>'),
            'web' => $this->webp(),
        ]);

        $this->assertSame('svg', $logo->print_kind);
        $kept = (string) Storage::disk('local')->get($logo->print_path);
        $this->assertStringNotContainsString('<script', $kept);
        $this->assertStringNotContainsString('onclick', $kept);
        $this->assertStringNotContainsString('example.com', $kept);
        $this->assertStringContainsString('<rect', $kept);
    }

    public function test_an_svg_that_draws_its_lettering_with_a_font_is_refused_rather_than_losing_the_words(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));

        // The PDF has only DejaVu and cannot fetch the real face, so such a logo would print the wrong
        // lettering or none. The screen's own script draws it to a JPEG first; this is the guard behind that.
        try {
            $this->add('Live text (test)', ['print' => $this->svg('<text x="4" y="20">Foxtrot</text>')]);
            $this->fail('an SVG with live text should be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Outline the text', $e->getMessage());
        }

        $this->assertSame(0, ClientLogo::query()->count());

        // Outlined lettering is just shapes, and goes in as it is
        $logo = $this->add('Outlined (test)', ['print' => $this->svg('<path d="M0 0h10v10H0z"/>')]);
        $this->assertSame('svg', $logo->print_kind);
    }

    public function test_a_file_that_is_not_what_it_claims_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        LogoFiles::decode('data:image/png;base64,' . base64_encode('not a png at all'), ['image/png']);
    }

    public function test_a_webp_may_not_go_in_the_slot_the_pdf_reads(): void
    {
        $this->expectException(RuntimeException::class);
        LogoFiles::decode($this->webp(), LogoLibrary::RENDITIONS['print']);
    }

    // ------------------------------------------------------------------ order, retiring, restoring

    public function test_logos_keep_the_order_they_are_put_in(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $first = $this->add('First (test)');
        $second = $this->add('Second (test)');
        $third = $this->add('Third (test)');

        $this->assertSame(['First (test)', 'Second (test)', 'Third (test)'], LogoLibrary::live()->pluck('name')->all());

        Livewire::test(LogoLibraryScreen::class)->call('moveDown', $first->id);
        $this->assertSame(['Second (test)', 'First (test)', 'Third (test)'], LogoLibrary::live()->pluck('name')->all());

        Livewire::test(LogoLibraryScreen::class)->call('moveUp', $third->id);
        $this->assertSame(['Second (test)', 'Third (test)', 'First (test)'], LogoLibrary::live()->pluck('name')->all());

        // The ends hold: nothing moves off either edge
        Livewire::test(LogoLibraryScreen::class)->call('moveUp', $second->id)->call('moveDown', $first->id);
        $this->assertSame(['Second (test)', 'Third (test)', 'First (test)'], LogoLibrary::live()->pluck('name')->all());
    }

    public function test_a_retired_logo_leaves_every_wall_and_can_be_put_back(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $kept = $this->add('Kept (test)');
        $gone = $this->add('Gone (test)');

        Livewire::test(LogoLibraryScreen::class)->call('retire', $gone->id);

        $this->assertSame(['Kept (test)'], LogoLibrary::live()->pluck('name')->all());
        $this->assertSame(['Gone (test)'], LogoLibrary::retired()->pluck('name')->all());
        $this->assertCount(1, app(ClientLogos::class)->all());
        // The row and its files are still there: a profile version that carried it stays true
        $this->assertTrue(Storage::disk('local')->exists($gone->fresh()->print_path));

        Livewire::test(LogoLibraryScreen::class)->call('restore', $gone->id);
        $this->assertSame(['Kept (test)', 'Gone (test)'], LogoLibrary::live()->pluck('name')->all());
        $this->assertSame($kept->id, LogoLibrary::live()->first()->id);
    }

    public function test_retiring_needs_manage_not_only_edit(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $logo = $this->add('Managed (test)');

        $editor = $this->staff('editor', ['delivery']);
        $this->grant($editor, ['company_profile.view', 'company_profile.edit']);

        Livewire::actingAs($editor)->test(LogoLibraryScreen::class)
            ->call('retire', $logo->id)->assertForbidden();

        $this->assertNull($logo->fresh()->retired_at);
    }

    // ------------------------------------------------------------------ what the profile is handed

    public function test_the_provider_gives_the_profile_a_name_and_a_file_it_can_draw(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $this->add('On the profile (test)', [], ['on_website' => false]);
        $this->add('Website only (test)', [], ['on_profile' => false, 'on_deck' => false, 'on_website' => true]);

        // The engine reads the interface: the binding in AppServiceProvider is this library, not a folder
        $this->assertInstanceOf(LogoLibrary::class, app(ClientLogos::class));

        $plain = app(ClientLogos::class)->all();
        $this->assertSame([['name' => 'On the profile (test)', 'path' => $plain[0]['path'] ?? '']], $plain);

        $rows = app(ClientLogos::class)->forProfile();

        $this->assertCount(1, $rows);
        $this->assertSame('On the profile (test)', $rows[0]['name']);
        $this->assertSame('on-the-profile-test', $rows[0]['key']);
        $this->assertTrue(is_file($rows[0]['path']), 'the path is a file on disk');
        $this->assertContains(pathinfo($rows[0]['path'], PATHINFO_EXTENSION), ['jpg', 'svg'], 'never a PNG: the live PHP has no gd');
        $this->assertSame('jpeg', $rows[0]['kind']);
        $this->assertTrue(is_file((string) $rows[0]['png']));

        $this->assertSame(['Website only (test)'], collect(app(ClientLogos::class)->forProfile('website'))->pluck('name')->all());
    }

    public function test_a_logo_whose_file_has_gone_missing_is_left_out_rather_than_breaking_the_render(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $logo = $this->add('Missing file (test)');
        Storage::disk('local')->delete($logo->print_path);

        $this->assertSame([], app(ClientLogos::class)->forProfile());
        $this->assertSame([], app(ClientLogos::class)->all());
    }

    // ------------------------------------------------------------------ what the website is served

    public function test_the_site_api_lists_the_website_logos_in_order_with_the_bytes_behind_them(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $this->add('Shown (test)', [], ['on_website' => true]);
        $hidden = $this->add('Not on the site (test)', [], ['on_website' => false]);
        $retired = $this->add('Retired (test)', [], ['on_website' => true]);
        LogoLibrary::retire($retired);

        $body = $this->signedGet('/clients')->assertOk()->json('data.clients');

        $this->assertCount(1, $body);
        $this->assertSame('shown-test', $body[0]['key']);
        $this->assertSame('Shown (test)', $body[0]['name']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $body[0]['sha256']);

        $this->signedGet('/clients/shown-test.webp')->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->signedGet('/clients/' . $hidden->key . '.webp')->assertNotFound();
        $this->signedGet('/clients/' . $retired->key . '.webp')->assertNotFound();
    }

    public function test_the_client_list_is_signed_like_every_other_site_read(): void
    {
        $this->get($this->hq('/api/site/v1/clients'))->assertUnauthorized();
    }

    private function grant($user, array $slugs): void
    {
        $role = \Illuminate\Support\Facades\DB::table('roles')->where('audience', 'staff')->where('slug', 'delivery')->value('id');
        foreach ($slugs as $slug) {
            $permission = \Illuminate\Support\Facades\DB::table('permissions')->where('slug', $slug)->value('id');
            \Illuminate\Support\Facades\DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => $permission]);
        }
        \App\Support\Access::flush();
    }

    private function signedGet(string $path, string $locale = 'en')
    {
        $full = '/api/site/v1' . $path;
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(12));
        $ip = '203.0.113.5';
        $canonical = SiteSignature::canonical('GET', $full, $timestamp, $nonce, $ip, $locale, self::KEY_ID, '', hash('sha256', ''));

        return $this->call('GET', 'https://' . Hosts::hq() . $full, [], [], [], [
            'HTTP_X_SITE_KEY' => self::KEY_ID,
            'HTTP_X_SITE_TIMESTAMP' => $timestamp,
            'HTTP_X_SITE_NONCE' => $nonce,
            'HTTP_X_SITE_VISITOR_IP' => $ip,
            'HTTP_X_SITE_LOCALE' => $locale,
            'HTTP_X_SITE_SIGNATURE' => SiteSignature::sign(self::SECRET, $canonical),
        ]);
    }
}
