<?php

namespace Tests\Feature\Projects;

use Database\Seeders\CoreSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Projects in today's shapes for the project page tests (needs UsesThrowawayDatabase) */
trait ProjectFixtures
{
    protected function project(array $values = []): string
    {
        $id = $values['id'] ?? (string) Str::uuid();
        $this->insertRow('projects', $values + [
            'id' => $id, 'organization_id' => CoreSeeder::INTERNAL_ORG_ID, 'title' => 'Finance system (test)', 'status' => 'Active',
            'total_budget' => 1000000, 'currency' => 'NGN', 'start_date' => '2026-10-03', 'target_end_date' => '2026-12-18',
            'lead_user_id' => null, 'opportunity_id' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'financial_override_by' => null,
        ]);

        return $id;
    }

    protected function phase(string $projectId, int $number, float $weight, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('project_phases', $values + [
            'id' => $id, 'project_id' => $projectId, 'phase_number' => $number, 'title' => "M{$number} · Milestone {$number}",
            'weight_percentage' => $weight, 'status' => 'Pending', 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);

        return $id;
    }

    protected function sprint(string $projectId, string $title, string $start, string $end, string $status = 'Planned'): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('sprints', ['id' => $id, 'project_id' => $projectId, 'title' => $title, 'start_date' => $start, 'end_date' => $end, 'status' => $status,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null]);

        return $id;
    }

    protected function task(string $phaseId, array $values = []): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('tasks', $values + [
            'id' => $id, 'phase_id' => $phaseId, 'sprint_id' => null, 'assigned_to' => null, 'title' => 'Task ' . Str::random(5),
            'status' => 'To-Do', 'story_points' => 1, 'due_date' => null, 'is_client_visible' => 1,
            'created_by' => null, 'updated_by' => null, 'deleted_by' => null, 'proposed_due_by' => null,
        ]);

        return $id;
    }

    protected function invoice(string $projectId, ?string $phaseId, string $status, float $total = 100000): string
    {
        $id = (string) Str::uuid();
        $this->insertRow('invoices', [
            'id' => $id, 'project_id' => $projectId, 'phase_id' => $phaseId, 'invoice_number' => 'INV/TEST/' . Str::random(6),
            'amount_due' => $total, 'vat_amount' => 0, 'total_amount' => $total, 'currency' => 'NGN', 'status' => $status,
            'due_date' => '2026-11-30', 'issued_at' => now(), 'paid_at' => $status === 'Paid' ? now() : null,
            'remittance_account_id' => null, 'created_by' => null, 'updated_by' => null, 'deleted_by' => null,
        ]);

        return $id;
    }

    /**
     * MyCity App ERP's shape after PR 7's migration (docs/PROJECTS.md section 6): M0 Foundation at 0% and M1–M11 by the
     * SOW's weights, each with the dates of its old sprint (kept as history, tasks still point at it), 148 tasks,
     * invoice 001 (deposit) and 002 (M1) paid. Returns [project, phases by number, sprints by number].
     */
    protected function myCityShape(array $project = []): array
    {
        $weights = [0 => 0, 1 => 12, 2 => 9, 3 => 5, 4 => 13, 5 => 11, 6 => 11, 7 => 10, 8 => 8, 9 => 7, 10 => 10, 11 => 4];
        $status = [0 => 'Active', 1 => 'Completed', 2 => 'QA', 3 => 'QA', 4 => 'Active', 9 => 'QA', 10 => 'Active'];
        $dates = [0 => ['2026-10-09', '2026-10-19'], 1 => ['2026-10-09', '2026-10-19'], 2 => ['2026-10-20', '2026-10-23'], 3 => ['2026-10-26', '2026-10-30'],
            4 => ['2026-11-02', '2026-11-13'], 5 => ['2026-11-16', '2026-11-20'], 6 => ['2026-11-23', '2026-11-27'], 7 => ['2026-11-30', '2026-12-04'],
            8 => ['2026-12-07', '2026-12-11'], 9 => ['2026-12-07', '2026-12-11'], 10 => ['2026-12-14', '2026-12-18'], 11 => ['2026-12-14', '2026-12-18']];
        $projectId = $this->project($project + ['title' => 'MyCity App ERP', 'total_budget' => 3700000]);
        $phases = $sprints = [];
        foreach ($weights as $n => $w) {
            $phases[$n] = $this->phase($projectId, $n, $w, ['status' => $status[$n] ?? 'Pending', 'title' => "M{$n} · Workstream {$n}",
                'start_date' => $dates[$n][0], 'due_date' => $dates[$n][1]]);
            $sprints[$n] = $this->sprint($projectId, "M{$n} · Workstream {$n}", $dates[$n][0], $dates[$n][1], $n === 4 ? 'Active' : ($n < 4 || $n === 9 ? 'Completed' : 'Planned'));
        }
        // 148 tasks: 12 or 13 a milestone, the delivered milestones done
        $rows = [];
        for ($i = 0; $i < 148; $i++) {
            $n = $i % 12;
            $rows[] = ['id' => (string) Str::uuid(), 'phase_id' => $phases[$n], 'sprint_id' => $sprints[$n], 'title' => "M{$n} task {$i}",
                'status' => in_array($n, [1, 2, 3, 9], true) ? 'Done' : ($n === 4 && intdiv($i, 12) % 3 === 0 ? 'In-Progress' : 'To-Do'),
                'story_points' => 1, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('tasks')->insert($rows);
        $this->invoice($projectId, null, 'Paid', 500000);
        $this->invoice($projectId, $phases[1], 'Paid', 384000);

        return [$projectId, $phases, $sprints];
    }
}
