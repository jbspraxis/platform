<?php

namespace Tests\Feature\Opportunities;

use App\Livewire\Hq\System\Integrations;
use App\Models\AiRun;
use App\Support\Google\GoogleWorkspace;
use App\Support\Hosts;
use App\Support\Integrations as Settings;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\UsesThrowawayDatabase;
use Tests\Support\Ai\FakeAi;
use Tests\TestCase;

/**
 * HQ › System › Integrations (docs/OPPORTUNITIES.md 6): secrets saved encrypted and never shown again, the Google
 * OAuth round trip on /integrations/google/callback, the Claude key test and the monthly budget. Fake values only.
 */
class IntegrationsScreenTest extends TestCase
{
    use UsesThrowawayDatabase;

    private const CLIENT_ID = '123456789012-abcdefghij0123456789.apps.googleusercontent.com';
    private const CLIENT_SECRET = 'GOCSPX-fake-secret-for-tests-only';
    private const CLAUDE_KEY = 'sk-ant-api03-FAKEKEYFORTESTSONLY_0123456789abcdef';

    private function hq(string $path): string
    {
        return 'https://' . Hosts::hq() . $path;
    }

    public function test_secrets_are_encrypted_and_never_shown_again(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);

        Livewire::test(Integrations::class)
            ->set('clientId', 'not-a-client-id')->call('saveGoogleClient')
            ->assertHasErrors(['clientId'])->assertSee('ending in .apps.googleusercontent.com')
            ->set('clientId', self::CLIENT_ID)->call('saveGoogleClient')
            ->assertHasErrors(['clientSecret'])
            ->set('clientSecret', self::CLIENT_SECRET)->call('saveGoogleClient')
            ->assertHasNoErrors()->assertSet('clientSecret', '')->assertSee('Client saved. Now click Connect Google.')
            ->set('anthropicKey', 'sk-wrong')->call('saveAnthropicKey')->assertHasErrors(['anthropicKey'])
            ->set('anthropicKey', self::CLAUDE_KEY)->call('saveAnthropicKey')->assertHasNoErrors()->assertSet('anthropicKey', '')
            ->set('budget', '-3')->call('saveBudget')->assertHasErrors(['budget'])
            ->set('budget', '5')->call('saveBudget')->assertHasNoErrors()->assertSee('Monthly AI budget set to $5.00.');

        $stored = DB::table('integration_settings')->pluck('value', 'key');
        $this->assertSame(self::CLIENT_ID, $stored['google.client_id']);
        $this->assertNotSame(self::CLIENT_SECRET, $stored['google.client_secret']);
        $this->assertSame(self::CLIENT_SECRET, Crypt::decryptString($stored['google.client_secret']));
        $this->assertNotSame(self::CLAUDE_KEY, $stored['anthropic.api_key']);
        $this->assertSame(self::CLAUDE_KEY, Settings::get('anthropic.api_key'));
        $this->assertSame('5.00', $stored['ai.monthly_budget_usd']);

        // The page never prints a secret, and the audit log never holds one
        $html = $this->get($this->hq('/system/integrations'))->assertOk()->getContent();
        $this->assertStringNotContainsString(self::CLIENT_SECRET, $html);
        $this->assertStringNotContainsString(self::CLAUDE_KEY, $html);
        $this->assertStringContainsString('https://' . Hosts::hq() . '/integrations/google/callback', $html);
        $this->assertStringContainsString('Saved. It is never shown again', $html);
        $logs = DB::table('system_audit_logs')->where('action', 'like', 'INTEGRATION_%')->get();
        $this->assertCount(3, $logs);
        foreach ($logs as $log) {
            $this->assertStringNotContainsString('sk-ant', json_encode($log));
            $this->assertStringNotContainsString('GOCSPX', json_encode($log));
        }
    }

    public function test_redirect_uri_is_exactly_the_registered_path(): void
    {
        $this->assertSame('/integrations/google/callback', parse_url(GoogleWorkspace::redirectUri(), PHP_URL_PATH));
        $this->assertSame(Hosts::hq(), parse_url(GoogleWorkspace::redirectUri(), PHP_URL_HOST));
    }

    public function test_google_oauth_round_trip(): void
    {
        $owner = $this->staff('owner', ['owner']);
        $this->actingAs($owner);

        // Without a client, Connect Google sends the owner back
        $this->get($this->hq('/integrations/google/connect'))->assertRedirect(route('hq.system.integrations'));

        Settings::set('google.client_id', self::CLIENT_ID);
        Settings::set('google.client_secret', self::CLIENT_SECRET);

        $redirect = $this->get($this->hq('/integrations/google/connect'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        $this->assertSame([self::CLIENT_ID, 'https://' . Hosts::hq() . '/integrations/google/callback', 'offline', 'consent', 'code'],
            [$query['client_id'], $query['redirect_uri'], $query['access_type'], $query['prompt'], $query['response_type']]);
        $this->assertSame(GoogleWorkspace::SCOPES, explode(' ', $query['scope']));
        $state = $query['state'];

        // A callback HQ didn't start is refused
        $this->get($this->hq('/integrations/google/callback?state=forged&code=x'))->assertRedirect(route('hq.system.integrations'))
            ->assertSessionHas('error');
        $this->assertNull(Settings::get('google.refresh_token'));

        // The real one (the state is used once, so start again)
        $redirect = $this->get($this->hq('/integrations/google/connect'))->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $claims = rtrim(strtr(base64_encode(json_encode(['email' => 'praxisjbs@gmail.com'])), '+/', '-_'), '=');
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.fake', 'expires_in' => 3599, 'refresh_token' => '1//fake-refresh',
            'scope' => implode(' ', ['openid', 'https://www.googleapis.com/auth/userinfo.email', ...array_slice(GoogleWorkspace::SCOPES, 2)]),
            'id_token' => "e30.{$claims}.sig", 'token_type' => 'Bearer',
        ])]);

        $this->get($this->hq('/integrations/google/callback?state=' . $query['state'] . '&code=4/fake-code'))
            ->assertRedirect(route('hq.system.integrations'))
            ->assertSessionHas('success', 'Google connected as praxisjbs@gmail.com.')
            ->assertSessionMissing('warning');

        Settings::flush();
        $google = app(GoogleWorkspace::class);
        $this->assertTrue($google->isConnected());
        $this->assertSame('praxisjbs@gmail.com', $google->accountEmail());
        $this->assertSame([], $google->missingScopes());
        $this->assertNotSame('1//fake-refresh', DB::table('integration_settings')->where('key', 'google.refresh_token')->value('value'));
        Http::assertSent(fn ($r) => $r->url() === 'https://oauth2.googleapis.com/token' && $r['grant_type'] === 'authorization_code'
            && $r['redirect_uri'] === 'https://' . Hosts::hq() . '/integrations/google/callback' && $r['client_secret'] === self::CLIENT_SECRET);
        $this->assertDatabaseHas('system_audit_logs', ['action' => 'INTEGRATION_GOOGLE_CONNECTED', 'user_id' => $owner->id]);
        $this->assertNotSame($state, $query['state']);
    }

    public function test_a_different_account_or_missing_permissions_warn(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        Settings::set('google.client_id', self::CLIENT_ID);
        Settings::set('google.client_secret', self::CLIENT_SECRET);
        $claims = rtrim(strtr(base64_encode(json_encode(['email' => 'someone.else@gmail.com'])), '+/', '-_'), '=');
        Http::fake(['oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.fake', 'expires_in' => 3599, 'refresh_token' => '1//fake', 'scope' => 'openid email', 'id_token' => "e30.{$claims}.sig",
        ])]);

        $redirect = $this->get($this->hq('/integrations/google/connect'))->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->get($this->hq('/integrations/google/callback?state=' . $query['state'] . '&code=c'))
            ->assertSessionHas('warning', 'Some permissions weren’t granted, so meetings or transcripts won’t work. Click Reconnect Google and tick every box.');

        // Declining on Google's page
        $redirect = $this->get($this->hq('/integrations/google/connect'))->headers->get('Location');
        parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
        $this->get($this->hq('/integrations/google/callback?state=' . $query['state'] . '&error=access_denied'))
            ->assertSessionHas('error', 'Google wasn’t connected because access wasn’t allowed. Click Connect Google and allow every permission.');
    }

    public function test_only_integrations_manage_reaches_the_oauth_routes(): void
    {
        $this->actingAs($this->staff('sales', ['sales']));
        $this->get($this->hq('/integrations/google/connect'))->assertForbidden();
        $this->get($this->hq('/integrations/google/callback?state=x&code=y'))->assertForbidden();
    }

    public function test_claude_key_test_and_the_months_spend(): void
    {
        $this->actingAs($this->staff('owner', ['owner']));
        $ai = FakeAi::install()->respond('connection_test', ['ok' => true], 40, 12);

        Livewire::test(Integrations::class)->call('testClaude')->assertSee('The key works (claude-haiku-5-5, $0.0000).')
            ->assertSee('Key test');
        $run = AiRun::where('purpose', 'connection_test')->firstOrFail();
        $this->assertSame(['done', 40, 12], [$run->status, $run->input_tokens, $run->output_tokens]);
        $ai->assertRan('connection_test', fn ($r) => $r->system === [] && $r->model === 'claude-haiku-5-5');

        $ai->unconfigured();
        Livewire::test(Integrations::class)->call('testClaude')->assertSee('The AI isn’t set up yet');

        App::setLocale('fr');
        Livewire::test(Integrations::class)->assertSee('Intégrations')->assertSee('Budget mensuel de l’IA')->assertSee('Test de la clé');
    }
}
