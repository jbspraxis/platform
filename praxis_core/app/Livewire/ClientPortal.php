<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ClientPortal extends Component
{
    public $viewState = 'gatekeeper'; // gatekeeper, forgot_password, dashboard

    // Auth & Reset State
    public $clientId; // Maps to User Email
    public $projectId; // Maps to User Password
    public $resetEmail;
    public $authMessage = '';

    // Dynamic Dashboard Data
    public $project;
    public $organization;
    public $leadArchitect;
    public $overallProgress = 0;
    public $phases = [];
    public $activeTasks = [];
    public $liveLogs = [];

    // QA Submission State
    public $qaTaskId = '';
    public $qaTitle = '';
    public $qaDescription = '';
    public $qaSuccess = false;

    public function executeAuthentication()
    {
        $this->validate([
            'clientId' => 'required|email',
            'projectId' => 'required',
        ]);

        // Real Laravel Authentication
        if (Auth::attempt(['email' => $this->clientId, 'password' => $this->projectId, 'status' => 'Active'])) {
            $this->loadDashboardData();
            $this->viewState = 'dashboard';
            $this->dispatch('portal-authenticated');
        } else {
            $this->addError('clientId', '> ERR: INVALID CREDENTIALS OR SUSPENDED NODE.');
        }
    }

    public function triggerForgotPassword()
    {
        $this->viewState = 'forgot_password';
        $this->authMessage = '';
    }

    public function sendResetLink()
    {
        $this->validate(['resetEmail' => 'required|email']);
        
        // Laravel's native password broker
        $status = Password::sendResetLink(['email' => $this->resetEmail]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->authMessage = '> RECOVERY PROTOCOL INITIATED. CHECK INBOX.';
            $this->resetEmail = '';
        } else {
            $this->addError('resetEmail', '> ERR: IDENTITY NOT FOUND IN VAULT.');
        }
    }

    public function cancelReset()
    {
        $this->viewState = 'gatekeeper';
        $this->authMessage = '';
    }

    public function loadDashboardData()
    {
        $user = Auth::user();

        // Fetch Org
        $this->organization = DB::table('organizations')->where('id', $user->organization_id)->first();

        // Fetch Active Project
        $this->project = DB::table('projects')
            ->where('organization_id', $this->organization->id)
            ->whereIn('status', ['Active', 'Scoping', 'Locked'])
            ->first();

        if (!$this->project) {
            Auth::logout();
            $this->addError('clientId', '> ERR: NO ACTIVE DEPLOYMENT ASSIGNED TO THIS NODE.');
            return;
        }

        // Fetch Lead Architect
        $architect = DB::table('users')->where('id', $this->project->created_by)->first();
        $this->leadArchitect = $architect ? $architect->first_name . ' ' . $architect->last_name : 'JBS Systems';

        // Fetch Phases & Calculate Progress
        $phases = DB::table('project_phases')
            ->where('project_id', $this->project->id)
            ->orderBy('phase_number', 'asc')
            ->get();

        $completedWeight = 0;
        $this->phases = $phases->map(function ($phase) use (&$completedWeight) {
            if ($phase->status === 'Completed') {
                $completedWeight += $phase->weight_percentage;
            }
            return (array) $phase;
        })->toArray();

        $this->overallProgress = $completedWeight;

        // Fetch Active Tasks for QA Dropdown (Only client visible tasks)
        $this->activeTasks = DB::table('tasks')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->where('project_phases.project_id', $this->project->id)
            ->where('tasks.is_client_visible', true)
            ->whereIn('tasks.status', ['In-Progress', 'QA_Review', 'Done'])
            ->select('tasks.id', 'tasks.title', 'project_phases.title as phase_title')
            ->get()->toArray();

        // Fetch Live Sprint Logs
        $this->liveLogs = DB::table('task_comments')
            ->join('tasks', 'task_comments.task_id', '=', 'tasks.id')
            ->join('project_phases', 'tasks.phase_id', '=', 'project_phases.id')
            ->join('users', 'task_comments.user_id', '=', 'users.id')
            ->where('project_phases.project_id', $this->project->id)
            ->orderBy('task_comments.created_at', 'desc') // Newest first
            ->limit(10)
            ->select('task_comments.comment_text', 'task_comments.created_at', 'users.first_name', 'users.last_name')
            ->get()
            ->map(function($log) {
                return [
                    'time' => Carbon::parse($log->created_at)->format('M d - H:i'),
                    'author' => strtoupper(substr($log->first_name, 0, 1) . '. ' . $log->last_name),
                    'text' => $log->comment_text
                ];
            })->toArray();
    }

    public function submitQA()
    {
        $this->validate([
            'qaTaskId' => 'nullable|exists:tasks,id',
            'qaTitle' => 'required|string|max:200',
            'qaDescription' => 'required|string',
        ]);

        DB::table('qa_tickets')->insert([
            'id' => Str::uuid(),
            'project_id' => $this->project->id,
            'task_id' => $this->qaTaskId ?: null,
            'submitted_by' => Auth::id(),
            'title' => $this->qaTitle,
            'description' => $this->qaDescription,
            'status' => 'Open',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->qaTitle = '';
        $this->qaDescription = '';
        $this->qaTaskId = '';
        $this->qaSuccess = true;

        // Auto hide success message after a few seconds in UI
    }

    public function render()
    {
        return view('livewire.client-portal')->layout('components.layouts.portal');
    }
}