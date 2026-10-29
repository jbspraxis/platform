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

#[Layout('components.layouts.hq')]
class ScrumMaster extends Component
{
    // Set in mount; the browser must not point the backlog at another project
    #[Locked]
    public $projectId;
    #[Locked]
    public $project;

    // Staging Data
    public $sowContext = '';
    public $generatedBacklog = []; // Holds the AI JSON before commit
    public $isGenerating = false;

    public function mount($id)
    {
        // Strict Access Control: this screen only builds backlogs (sprints, phases, tasks), so projects.create
        if (! Auth::user()->can('projects.create')) {
            abort(403, 'You don’t have permission to plan sprints. Ask an administrator to change your role.');
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
            $this->sowContext = "No approved proposal is linked to this client. Type or paste the scope of work here, then draft the backlog.";
        }
    }

    public function generateScrumBacklog()
    {
        abort_unless(Auth::user()->can('projects.create'), 403);

        $this->validate([
            'sowContext' => 'required|string|min:50'
        ], [], ['sowContext' => 'scope of work']);

        $this->isGenerating = true;

        $prompt = "You are the elite AI Scrum Master for JBS Praxis. Analyze the following Scope of Work.
        Break this project down into logical 14-day Sprints. Inside each sprint, define the specific Tasks required.
        Every task MUST belong to a logical Phase (e.g., 'Phase 1: Discovery', 'Phase 2: Execution').
        Assign a Fibonacci story point value (1, 2, 3, 5, 8, 13) to each task based on complexity.
        Generate a 3-point 'Acceptance Criteria' checklist in HTML <ul> format for the task description.
        
        You must return ONLY a valid JSON array with this exact structure, nothing else:
        [
          {
            \"sprint_title\": \"Sprint 1: Foundation\",
            \"tasks\": [
              {
                \"phase_title\": \"Phase 1: Discovery\",
                \"title\": \"Conduct Stakeholder Interviews\",
                \"story_points\": 5,
                \"description\": \"<ul><li>Interview CEO</li><li>Draft initial architecture</li><li>Obtain sign-off</li></ul>\"
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
                    $this->generatedBacklog = $parsedData;
                    session()->flash('success', 'Backlog drafted. Check the sprints and tasks, then save the backlog.');
                } else {
                    session()->flash('error', 'We couldn’t read the assistant’s draft. Draft the backlog again.');
                }
            } else {
                session()->flash('error', 'We couldn’t reach the assistant. Try again in a few minutes; if it keeps failing, ask an administrator to check the AI settings.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'The assistant took too long to answer. Shorten the scope of work and try again.');
        }

        $this->isGenerating = false;
    }

    public function removeTask($sprintIndex, $taskIndex)
    {
        unset($this->generatedBacklog[$sprintIndex]['tasks'][$taskIndex]);
        // Re-index array to prevent Livewire rendering issues
        $this->generatedBacklog[$sprintIndex]['tasks'] = array_values($this->generatedBacklog[$sprintIndex]['tasks']);
    }

    public function commitBacklogToDatabase()
    {
        abort_unless(Auth::user()->can('projects.create'), 403);

        if (empty($this->generatedBacklog)) {
            session()->flash('error', 'There is no draft to save. Draft the backlog first.');
            return;
        }

        DB::transaction(function () {
            $sprintStartDate = Carbon::now();
            $phaseMap = []; // To cache phase IDs and avoid duplicates
            $phaseNumberCounter = 1;

            foreach ($this->generatedBacklog as $sprintData) {
                
                // 1. Create Sprint
                $sprintId = (string) Str::uuid();
                $sprintEndDate = $sprintStartDate->copy()->addDays(14);
                
                DB::table('sprints')->insert([
                    'id' => $sprintId,
                    'project_id' => $this->projectId,
                    'title' => Str::limit($sprintData['sprint_title'], 100),
                    'start_date' => $sprintStartDate->format('Y-m-d'),
                    'end_date' => $sprintEndDate->format('Y-m-d'),
                    'status' => 'Planned',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // 2. Loop Tasks in Sprint
                foreach ($sprintData['tasks'] as $taskData) {
                    
                    $phaseTitle = $taskData['phase_title'] ?? 'General phase';
                    
                    // Generate Phase dynamically if it doesn't exist in our map
                    if (!isset($phaseMap[$phaseTitle])) {
                        $phaseId = (string) Str::uuid();
                        DB::table('project_phases')->insert([
                            'id' => $phaseId,
                            'project_id' => $this->projectId,
                            'phase_number' => $phaseNumberCounter++,
                            'title' => Str::limit($phaseTitle, 150),
                            'weight_percentage' => 0, // Will distribute evenly later or manually
                            'status' => 'Pending',
                            'created_by' => Auth::id(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $phaseMap[$phaseTitle] = $phaseId;
                    }

                    // 3. Insert Task
                    DB::table('tasks')->insert([
                        'id' => (string) Str::uuid(),
                        'phase_id' => $phaseMap[$phaseTitle],
                        'sprint_id' => $sprintId,
                        'title' => Str::limit($taskData['title'], 255),
                        'description' => $taskData['description'] ?? '',
                        'story_points' => (int) ($taskData['story_points'] ?? 1),
                        'status' => 'To-Do',
                        'is_client_visible' => 1,
                        'created_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }

                // Advance the start date for the next sprint
                $sprintStartDate = $sprintEndDate->copy()->addDay();
            }

            // Distribute Phase Weights Evenly (e.g., if 4 phases created, each is 25%)
            $totalPhases = count($phaseMap);
            if ($totalPhases > 0) {
                $weight = round(100 / $totalPhases, 2);
                DB::table('project_phases')->where('project_id', $this->projectId)->update(['weight_percentage' => $weight]);
            }

            // System Audit Log
            DB::table('system_audit_logs')->insert([
                'user_id' => Auth::id(),
                'action' => 'BACKLOG_ENGINEERED',
                'description' => "Sprint backlog saved for project: {$this->project->title}",
                'ip_address' => request()->ip(),
                'created_at' => now()
            ]);
            
            // Notify the delivery team that a new board is ready
            foreach(Access::staffWith('projects.edit') as $memberId) {
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(), 'user_id' => $memberId,
                    'title' => 'Sprint backlog ready',
                    'message' => "The backlog for ‘{$this->project->title}’ is saved. Tasks can now be assigned on its sprint board.",
                    'type' => 'Project', 'created_at' => now()
                ]);
            }
        });

        session()->flash('success', 'Backlog saved. The sprints, phases and tasks are now on the project’s sprint board.');
        return $this->redirect(route('hq.ops.deployments'), navigate: true);
    }

    public function render()
    {
        return view('livewire.hq.operations.scrum-master');
    }
}