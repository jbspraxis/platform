<?php

namespace Tests\Unit\Leads;

use App\Support\Leads\LeadIntel;
use PHPUnit\Framework\TestCase;

/**
 * The stored layout of an intel report and its cost totals (docs/LEADS.md section 13): any answer, Claude's or a
 * person's edit, comes out in one shape, and a total never rests on a number nobody gave. No database needed.
 */
class LeadIntelLayoutTest extends TestCase
{
    public function test_a_blank_report_has_every_section_empty(): void
    {
        $blank = LeadIntel::blank();

        $this->assertSame(['snapshot', 'decision_makers', 'tools', 'pain_points', 'how_we_can_help', 'meeting_approach', 'source', 'open_questions', 'other_findings'], array_keys($blank));
        $this->assertSame([], $blank['tools']['items']);
        $this->assertSame('low', $blank['source']['confidence']);
        $this->assertSame(['currency' => '', 'counted' => 0, 'free' => 0, 'unknown' => 0, 'estimate' => false, 'monthly' => 0.0, 'yearly' => 0.0], LeadIntel::totals($blank));
    }

    public function test_costs_need_an_amount_and_a_period_or_they_count_as_not_known(): void
    {
        $layout = LeadIntel::normalise(['tools' => ['currency' => 'ngn', 'items' => [
            ['tool' => 'Notion', 'used_for' => 'Records', 'cost_amount' => 96, 'cost_period' => 'year', 'cost_basis' => 'estimated', 'note' => ''],
            ['tool' => 'Shopify', 'used_for' => 'Sales', 'cost_amount' => 79, 'cost_period' => 'month', 'cost_basis' => 'stated', 'note' => ''],
            ['tool' => 'Figma', 'used_for' => 'Design', 'cost_amount' => 15, 'cost_period' => 'month', 'cost_basis' => 'free', 'note' => ''],
            ['tool' => 'Slack', 'used_for' => 'Chat', 'cost_amount' => 8, 'cost_period' => 'none', 'cost_basis' => 'stated', 'note' => ''],
            ['tool' => 'N8N', 'used_for' => 'Automation', 'cost_amount' => 'a lot', 'cost_period' => 'month', 'cost_basis' => 'stated', 'note' => ''],
            ['tool' => '', 'used_for' => 'No name, dropped', 'cost_amount' => 1, 'cost_period' => 'month', 'cost_basis' => 'stated', 'note' => ''],
        ]]]);

        $items = collect($layout['tools']['items'])->keyBy('tool');
        $this->assertSame('NGN', $layout['tools']['currency']);
        $this->assertCount(5, $items, 'A tool without a name is dropped');
        $this->assertSame([0.0, 'none'], [$items['Figma']['cost_amount'], $items['Figma']['cost_period']], 'Free has no amount');
        foreach (['Slack', 'N8N'] as $tool) {
            $this->assertSame(['unknown', 0.0, 'none'], [$items[$tool]['cost_basis'], $items[$tool]['cost_amount'], $items[$tool]['cost_period']], "{$tool}: no usable amount, so not known");
        }

        // A year's price counts a twelfth a month; free and unknown are counted beside the total, not in it
        $totals = LeadIntel::totals($layout);
        $this->assertSame(87.0, $totals['monthly']);
        $this->assertSame(1044.0, $totals['yearly']);
        $this->assertSame([2, 1, 2, true], [$totals['counted'], $totals['free'], $totals['unknown'], $totals['estimate']]);
    }

    public function test_roles_confidence_and_lists_are_kept_to_their_choices_and_sizes(): void
    {
        $layout = LeadIntel::normalise([
            'decision_makers' => [
                ['name' => 'Paul', 'role' => 'Co-founder', 'deal_role' => 'boss', 'contact' => 'paul@jewel.test', 'approach' => 'Email first'],
                ['name' => '', 'role' => '', 'deal_role' => 'finance', 'contact' => 'nobody@jewel.test', 'approach' => ''],
                'not a person',
            ],
            'source' => ['how' => 'An interview', 'confidence' => 'certain', 'note' => ''],
            'open_questions' => [' What is spent on ads? ', '', 'Who signs?'],
            'snapshot' => ['summary' => str_repeat('x', 9000)],
        ]);

        $this->assertCount(1, $layout['decision_makers'], 'A row with neither a name nor a role is dropped');
        $this->assertSame('unknown', $layout['decision_makers'][0]['deal_role']);
        $this->assertSame('low', $layout['source']['confidence']);
        $this->assertSame(['What is spent on ads?', 'Who signs?'], $layout['open_questions']);
        $this->assertSame(6000, mb_strlen($layout['snapshot']['summary']));

        $many = LeadIntel::normalise(['pain_points' => array_fill(0, 50, ['point' => 'Turnover', 'detail' => ''])]);
        $this->assertCount(30, $many['pain_points']);
    }

    public function test_only_pdf_word_text_and_markdown_can_be_uploaded(): void
    {
        $this->assertSame(['pdf', 'docx', 'txt', 'md'], array_keys(LeadIntel::FILES));
        $this->assertSame(10, LeadIntel::MAX_KB / 1024);
    }
}
