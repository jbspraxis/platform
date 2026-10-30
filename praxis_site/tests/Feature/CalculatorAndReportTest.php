<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** /cost-calculator (GET /calculator/reference, POST /calculator/assessments) and /dossier/{token} (GET /reports/{token}). */
class CalculatorAndReportTest extends TestCase
{
    private function reference(): array
    {
        return ['data' => [
            'industries' => [['value' => 'Banking and finance', 'label' => 'Banking and finance'], ['industry_name' => 'Oil and gas']],
            'systems' => [['tech_key' => 'excel', 'display_name' => 'Spreadsheets (Excel)']],
            'bottlenecks' => ['Approvals', 'Reporting'],
            'currencies' => [['code' => 'NGN', 'name' => 'Nigerian naira', 'rate_to_usd' => 1500], ['currency_code' => 'XAF', 'rate_to_usd' => 600]],
        ]];
    }

    private function report(): array
    {
        return ['data' => [
            'token' => 'PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026',
            'created_at' => '2026-10-30T10:00:00+01:00',
            'company' => 'Example Ltd', 'industry' => 'Banking and finance', 'tool_label' => 'Spreadsheets (Excel)', 'bottleneck' => 'Approvals',
            'currency' => 'NGN',
            'inputs' => ['headcount' => 250, 'mgmt_nodes' => 50, 'turnover' => 4200000000, 'manual_hrs' => 5, 'hourly_cost' => 12000, 'error_rate' => 3.5],
            'figures' => [
                'total' => 1250000000, 'appraisal' => 156000000, 'internal_failure' => 88200000, 'external_failure' => 1005800000,
                'error_rate' => 3.5, 'error_floor' => 1.2, 'final_deploy_cost' => 99999999, 'break_even_months' => 4.2,
            ],
            'analysis' => "Approvals wait in email.\n\nWe recommend **one approval route** for payments.",
        ]];
    }

    public function test_the_calculator_renders_the_lists_from_hq(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);

        $this->get('/cost-calculator')->assertOk()
            ->assertSee('Cost of poor quality calculator')
            ->assertSee('<option value="Banking and finance"', false)
            ->assertSee('<option value="Oil and gas"', false)
            ->assertSee('<option value="excel"', false)
            ->assertSee('Spreadsheets (Excel)')
            ->assertSee('NGN: Nigerian naira')
            ->assertSee('<option value="XAF"', false);
    }

    public function test_a_calculation_redirects_to_the_report(): void
    {
        $this->fakeHq([
            'calculator/assessments' => Http::response(['data' => [
                'token' => 'PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026', 'report_path' => '/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026', 'figures' => ['total' => 1],
            ]], 201),
            'reports/*' => Http::response($this->report()),
        ]);

        $this->from('/cost-calculator')->post('/cost-calculator', [
            'email' => 'ada@example.com', 'company' => 'Example Ltd', 'industry' => 'Banking and finance', 'tool' => 'excel', 'currency' => 'NGN',
            'headcount' => '250', 'turnover' => '4,200,000,000', 'error_rate' => '3.5',
        ])->assertRedirect('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026');

        $sent = $this->sentJson('/calculator/assessments');
        $this->assertSame('4200000000', $sent['turnover'], 'thousands separators are removed');
        $this->assertSame('excel', $sent['tool']);

        $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->assertOk()
            ->assertSee('We’ve also sent a copy to your email address.')
            ->assertDontSee('We couldn’t email a copy');
    }

    public function test_a_report_hq_could_not_email_says_so(): void
    {
        $this->fakeHq([
            'calculator/assessments' => Http::response(['data' => [
                'token' => 'PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026', 'report_path' => '/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026', 'emailed' => false, 'figures' => ['total' => 1],
            ]], 201),
            'reports/*' => Http::response($this->report()),
        ]);

        $this->from('/cost-calculator')->post('/cost-calculator', ['email' => 'ada@example.com', 'company' => 'Example Ltd'])
            ->assertRedirect('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')
            ->assertSessionHas('report_emailed', false);

        $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->assertOk()
            ->assertSee('Your report is ready. We couldn’t email a copy, so save this page.')
            ->assertDontSee('We’ve also sent a copy to your email address.');

        // Opening the link again later shows neither message
        $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->assertOk()->assertDontSee('We couldn’t email a copy');
    }

    public function test_calculator_errors_from_hq_are_shown_on_the_form(): void
    {
        $this->fakeHq([
            'calculator/assessments' => Http::response(['error' => ['code' => 'invalid', 'message' => 'Check the figures.', 'fields' => [
                'email' => ['Enter your email address.'], 'error_rate' => ['Enter a share between 0 and 100.'],
            ]]], 422),
            'calculator/reference' => Http::response($this->reference()),
        ]);

        $this->from('/cost-calculator')->post('/cost-calculator', ['error_rate' => '140', 'company' => 'Example Ltd'])
            ->assertRedirect('/cost-calculator')
            ->assertSessionHasErrors(['email', 'error_rate']);

        $this->get('/cost-calculator')
            ->assertSee('Check the figures.')
            ->assertSee('Enter a share between 0 and 100.')
            ->assertSee('value="Example Ltd"', false);
    }

    public function test_the_calculator_serves_the_saved_lists_while_hq_is_down(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);
        $this->get('/cost-calculator')->assertOk();

        $this->travel(11)->minutes();
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/cost-calculator')->assertOk()
            ->assertSee('Spreadsheets (Excel)')
            ->assertSee('you’re seeing the last saved copy');
    }

    public function test_the_calculator_without_any_copy_shows_the_calm_message(): void
    {
        Http::fake([self::HQ . '/*' => $this->down()]);

        $this->get('/cost-calculator')->assertStatus(503)->assertSee('Try again in a few minutes, or email us at fallback@jbspraxis.com');
    }

    public function test_the_report_shows_the_figures_and_never_a_price(): void
    {
        $this->fakeHq(['reports/*' => Http::response($this->report())]);

        $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->assertOk()
            ->assertSee('Cost of poor quality report')
            ->assertSee('Prepared for Example Ltd')
            ->assertSee('₦1,250,000,000')
            ->assertSee('We estimate that poor quality costs Example Ltd about ₦1,250,000,000 a year.');

        $html = $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->getContent();
        $this->assertStringContainsString('That is about ₦104,166,667 a month.', $html);
        $this->assertStringContainsString('Manual checking', $html);
        $this->assertStringContainsString('₦156,000,000', $html);
        $this->assertStringContainsString('3.5%', $html);
        $this->assertStringContainsString('1.2%', $html);
        $this->assertStringContainsString('<strong>one approval route</strong>', $html);
        $this->assertStringContainsString('Spreadsheets (Excel)', $html);
        $this->assertStringContainsString('Friday 30 October 2026', $html);
        $this->assertStringNotContainsString('99,999,999', $html, 'no fee is ever shown');
        $this->assertStringNotContainsString('4.2', $html, 'no break-even time is shown');
    }

    public function test_an_unknown_report_is_a_404(): void
    {
        $this->fakeHq(['reports/*' => Http::response(['error' => ['code' => 'not_found']], 404)]);

        $this->get('/dossier/PRX-UNKNOWN-2026')->assertNotFound()->assertSee('We couldn’t find that page')->assertSee('info@jbspraxis.com');
    }

    public function test_the_report_while_hq_is_down_is_a_calm_message(): void
    {
        $this->fakeHq(['reports/*' => Http::response(['error' => ['code' => 'server']], 500)]);

        $this->get('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026')->assertStatus(503)
            ->assertSee('This page isn’t available right now')
            ->assertSee('info@jbspraxis.com');
    }
}
