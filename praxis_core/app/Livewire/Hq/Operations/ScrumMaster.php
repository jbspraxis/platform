<?php

namespace App\Livewire\Hq\Operations;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Support\Access;

/**
 * Plan milestones: the assistant drafts a project's milestones and their tasks from the scope of work; the lead checks
 * them and saves them to the project page. Milestones are the unit of work (sprints were folded into milestones on
 * 1 Nov 2026, docs/PROJECTS.md); PR 5 replaces this with the project draft from the signed SOW.
 */
#[Layout('components.layouts.hq')]
class ScrumMaster extends Component
{
    // Set in mount; the browser must not point the plan at another project
    #[Locked]
    public $projectId;
    #[Locked]
    public $project;

    // Staging Data
    public $sowContext = '';
    public $generatedBacklog = []; // Holds the AI JSON before commit: [{milestone_title, goal, tasks: [{title, description}]}]
    public $isGenerating = false;

    public function mount($id)
    {
        // Strict Access Control: this screen only builds plans (milestones and their tasks), so projects.create
        if (! Auth::user()->can('projects.create')) {
            abort(403, 'You don’t have permission to plan milestones. Ask an administrator to change your role.');
        }

        $this->projectId = $id;
        $this->project = DB::table('projects')->where('id', $id)->first();

        if (!$this->project) abort(404);

        $this->extractProposalContext();
    }

    private function extractProposalContext()
    {
        // Find the winning proposal linked to this project or organization
        $proposal = DB::table('proposals')
            ->where('organization_id', $this->project->organization_id)
            ->whereIn('status', ['Authorized', 'Pending Auth'])
            ->orderBy('created_at', 'desc')
            ->first();

        if ($proposal) {
            $narrative = DB::table('proposal_ai_narratives')->where('proposal_id', $proposal->id)->first();

            $this->sowContext = "Executive summary:\n" . ($proposal->executive_summary ?? 'Not provided') . "\n\n";
            if ($narrative) {
                $this->sowContext .= "Operational strategy:\n" . ($narrative->ai_operational_strategy ?? 'Not provided');
            }
        } else {
            $this->sowContext = "No approved proposal is linked to this client. Type or paste the scope of work here, then draft the plan.";
        }
    }

    public function generateScrumBacklog()
    {
        abort_unless(Auth::user()->can('projects.create'), 403);

        $this->validate([
            'sowContext' => 'required|string|min:50'
        ], [], ['sowContext' => 'scope of work']);

        $this->isGenerating = true;

        $prompt = "You are the delivery planner at JBS Praxis. Read the following scope of work.
        Break this project down into its milestones, in delivery order, as the scope of work sets them out. Inside each
        milestone, define the specific tasks required, and say in one sentence what the milestone delivers (its goal).
        Write a 3-point acceptance criteria checklist in HTML <ul> format for the task description.
        Write every title and criterion in plain British English and sentence case, with no hype and no military or technical jargon.

        You must return ONLY a valid JSON array with this exact structure, nothing else:
        [
          {
            \"milestone_title\": \"Discovery and design\",
            \"goal\": \"An agreed design for the finance system\",
            \"tasks\": [
              {
                \"title\": \"Interview the finance team\",
                \"description\": \"<ul><li>Interview the finance director</li><li>Map the month-end close</li><li>Agree the findings with the primary contact</li></ul>\"
              }
            ]
          }
        ]

        SCOPE OF WORK TO PARSE:
        " . $this->sowContext;

        try {
             $apiKey = config('services.gemini.api_key');

            $response = Http::timeout(45)->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}", [
                "contents" => [["parts" => [["text" => $prompt]]]]
            ]);

            if ($response->successful()) {
                $rawText = $response->json('candidates.0.content.parts.0.text');

                // Clean markdown wrappers
                $jsonString = preg_replace('/```(?:json)?\n?|\n?```/', '', $rawText);
                $parsedData = json_decode($jsonString, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($parsedData)) {
                    // Only what the review screen shows: a title, a goal, and tasks with a title and criteria
                    $this->generatedBacklog = array_values(array_map(fn ($m) => [
                        'milestone_title' => (string) ($m['milestone_title'] ?? 'Milestone'),
                        'goal' => (string) ($m['goal'] ?? ''),
                        'tasks' => array_values(array_map(fn ($t) => ['title' => (string) ($t['title'] ?? ''), 'description' => (string) ($t['description'] ?? '')],
                            array_filter((array) ($m['tasks'] ?? []), 'is_array'))),
                    ], array_filter($parsedData, 'is_array')));
                    session()->flash('success', 'Plan drafted. Check the milestones and tasks, then save the plan.');
                } else {
                    session()->flash('error', 'We couldn’t read the assistant’s draft. Draft the plan again.');
                }
            } else {
                session()->flash('error', 'We couldn’t reach the assistant. Try again in a few minutes; if it keeps failing, ask an administrator to check the AI settings.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'The assistant took too long to answer. Shorten the scope of work and try again.');
        }

        $this->isGenerating = false;
    }

    public function removeTask($milestoneIndex, $taskIndex)
    {
        unset($this->generatedBacklog[$milestoneIndex]['tasks'][$taskIndex]);
        // Re-index array to prevent Livewire rendering issues
        $this->generatedBacklog[$milestoneIndex]['tasks'] = array_values($this->generatedBacklog[$milestoneIndex]['tasks']);
    }

    /**
     * Each drafted milestone joins the project after its existing ones, two weeks long to start with (the lead sets the
     * real dates and weights on the project page), with its tasks. Weights are shared out only when the project had no
     * milestones yet: an existing plan's weights are never touched.
     */
    public function commitBacklogToDatabase()
    {
        abort_unless(Auth::user()->can('projects.create'), 403);

        if (empty($this->generatedBacklog)) {
            session()->flash('error', 'There is no draft to save. Draft the plan first.');
            return;
        }

        DB::transaction(function () {
            $existing = fn () => DB::table('project_phases')->where('project_id', $this->projectId)->whereNull('deleted_at');
            $hadMilestones = $existing()->exists();
            $number = (int) $existing()->max('phase_number') + 1;
            $lastDue = $existing()->max('due_date');
            $start = $lastDue ? Carbon::parse($lastDue)->addDay() : Carbon::now();
            $weight = $hadMilestones ? 0 : round(100 / max(1, count($this->generatedBacklog)), 2);

            foreach ($this->generatedBacklog as $milestone) {
                $phaseId = (string) Str::uuid();
                $due = $start->copy()->addDays(13);
                DB::table('project_phases')->insert([
                    'id' => $phaseId,
                    'project_id' => $this->projectId,
                    'phase_number' => $number++,
                    'title' => Str::limit(trim($milestone['milestone_title'] ?? '') ?: 'Milestone', 140),
                    'goal' => trim($milestone['goal'] ?? '') ?: null,
                    'weight_percentage' => $weight,
                    'amount' => round((float) $this->project->total_budget * $weight / 100, 2),
                    'status' => 'Pending',
                    'start_date' => $start->format('Y-m-d'),
                    'due_date' => $due->format('Y-m-d'),
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                foreach ($milestone['tasks'] ?? [] as $taskData) {
                    if (trim($taskData['title'] ?? '') === '') {
                        continue;
                    }
                    DB::table('tasks')->insert([
                        'id' => (string) Str::uuid(),
                        'phase_id' => $phaseId,
                        'title' => Str::limit($taskData['title'], 255),
                        'description' => $taskData['description'] ?? '',
                        'status' => 'To-Do',
                        'is_client_visible' => 1,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                $start = $due->copy()->addDay();
            }

            // System Audit Log
            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'BACKLOG_ENGINEERED',
                'description' => "Milestones and tasks saved for project: {$this->project->title}",
                'target_table' => 'projects',
                'target_id' => $this->projectId,
                'ip_address' => request()->ip(),
                'created_at' => now()
            ]);

            // Notify the delivery team that the plan is ready
            foreach(Access::staffWith('projects.edit') as $memberId) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $memberId,
                    'title' => 'Project plan ready',
                    'message' => "The milestones for ‘{$this->project->title}’ are saved. Tasks can now be assigned on their boards.",
                    'link_url' => route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'milestones']),
                    'type' => 'Project', 'created_at' => now()
                ]);
            }
        });

        session()->flash('success', 'Plan saved. The milestones and their tasks are on the project page.');
        return $this->redirect(route('hq.ops.project', ['project' => $this->projectId, 'tab' => 'milestones']), navigate: true);
    }

    public function render()
    {
        return view('livewire.hq.operations.scrum-master');
    }
}
