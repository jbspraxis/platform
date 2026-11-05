<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The calculator as a conversation (partials/calculator-dialog, resources/js/calculator): the dialog is the
 * calculator's own form, so what matters on the server is that it carries the same guards, posts to the same
 * route, and never gets in the way of the page form that works without JavaScript.
 */
class CalculatorAgentTest extends TestCase
{
    private function reference(): array
    {
        return ['data' => [
            'industries' => [['value' => 'Banking and finance', 'label' => 'Banking and finance']],
            'systems' => [['tech_key' => 'excel', 'display_name' => 'Spreadsheets (Excel)']],
            'bottlenecks' => ['Approvals'],
            'currencies' => [['code' => 'XOF', 'name' => 'West African CFA franc'], ['code' => 'NGN', 'name' => 'Nigerian naira']],
        ]];
    }

    public function test_the_homepage_carries_the_dialog_and_its_lists(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);

        $page = $this->get('/')->assertOk()
            ->assertSee('data-calc-dialog', false)
            ->assertSee('data-calc-open', false);

        // HQ's lists reach the dialog as JSON, ready for the pills
        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/data-lists="[^"]*Banking and finance/', $html);
        $this->assertMatchesRegularExpression('/data-lists="[^"]*Spreadsheets/', $html);
        $this->assertMatchesRegularExpression('/data-lists="[^"]*XOF/', $html);
    }

    public function test_the_dialog_is_the_calculator_form_with_the_same_guards(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);

        $html = $this->get('/')->assertOk()->getContent();
        $dialog = substr($html, strpos($html, '<dialog class="agent"'));
        $dialog = substr($dialog, 0, strpos($dialog, '</dialog>'));

        $this->assertStringContainsString('action="' . url('/cost-calculator') . '"', $dialog);
        $this->assertStringContainsString('name="_token"', $dialog);
        $this->assertStringContainsString('name="' . \App\Http\Middleware\FormGuard::HONEYPOT . '"', $dialog);
        $this->assertStringContainsString('name="' . \App\Support\FormStamp::FIELD . '"', $dialog);
        // The work email is asked for in the ordinary way, as a real field of that form
        $this->assertStringContainsString('name="email"', $dialog);
    }

    public function test_the_conversation_posts_exactly_what_the_page_form_posts(): void
    {
        $this->fakeHq([
            'calculator/reference' => Http::response($this->reference()),
            'calculator/assessments' => Http::response(['data' => [
                'token' => 'PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026',
                'report_path' => '/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026',
            ]], 201),
        ]);

        // The hidden inputs the engine writes, including the hourly cost it worked out from "600,000 a month"
        $this->from('/')->post('/cost-calculator', [
            'email' => 'ada@example.com', 'name' => 'Ada', 'company' => 'Example Ltd',
            'industry' => 'Banking and finance', 'tool' => 'excel', 'bottleneck' => 'Approvals',
            'headcount' => '250', 'mgmt_nodes' => '50', 'manual_hrs' => '10', 'error_rate' => '3',
            'currency' => 'XOF', 'hourly_cost' => '3461.54', 'turnover' => '1200000000',
        ])->assertRedirect('/dossier/PRX-ABCDEFGHIJKLMNOPQRSTUVWX-2026');

        $sent = $this->sentJson('/calculator/assessments');
        $this->assertSame('ada@example.com', $sent['email']);
        $this->assertSame('3461.54', $sent['hourly_cost'], 'the hourly cost the agent worked out is what HQ receives');
        $this->assertSame('XOF', $sent['currency']);
        $this->assertSame('250', $sent['headcount']);
    }

    public function test_the_page_form_still_stands_on_its_own(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);

        $html = $this->get('/cost-calculator')->assertOk()
            ->assertSee('<option value="Banking and finance"', false)   // the page form is untouched
            ->assertSee('data-calc-dialog', false)
            ->getContent();

        // The opener is no use without JavaScript, so it is hidden until the script reveals it
        $this->assertStringContainsString('data-calc-reveal', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-calc-open[^>]*hidden/', $html);

        // Two forms on one page must not share an element id
        preg_match_all('/id="hp-[a-z-]+"/', $html, $ids);
        $this->assertSame(array_unique($ids[0]), $ids[0], 'the honeypot ids collide');
    }

    public function test_the_button_stays_a_plain_link_when_hq_has_no_lists(): void
    {
        // HQ down and nothing ever saved: there is nothing to offer as pills
        $this->fakeHq(['calculator/reference' => $this->down()]);

        $this->get('/')->assertOk()
            ->assertSee('href="' . url('/cost-calculator') . '"', false)
            ->assertSee('data-lists="[]"', false);
    }

    public function test_the_conversation_speaks_french_under_fr(): void
    {
        $this->fakeHq(['calculator/reference' => Http::response($this->reference())]);

        $this->get('/fr')->assertOk()
            ->assertSee('data-locale="fr"', false)
            ->assertSee('Combien la non-qualité vous coûte-t-elle', false);
    }

}
