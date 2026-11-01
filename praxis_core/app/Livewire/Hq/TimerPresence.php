<?php

namespace App\Livewire\Hq;

use App\Support\Projects\Tasks;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The 45-minute presence check (docs/PROJECTS.md), on every HQ page through the layout: while the signed-in person
 * has a timer running (an open task_time_logs row), after 45 minutes on a page HQ asks whether they are still working;
 * unanswered for 5 minutes, their timers stop and their tasks go back to To do. It used to live on the sprint board
 * only, so a timer started from the task drawer or left running on another page escaped it. The board and the task
 * drawer dispatch timers-changed when they start or stop one.
 */
class TimerPresence extends Component
{
    public string $notice = '';

    #[On('timers-changed')]
    public function refresh(): void {}

    /** The 5-minute answer window passed (called from the browser) */
    public function pause(): void
    {
        if (! Auth::check() || Tasks::pauseOpenTimers(Auth::id()) === 0) {
            return;
        }
        $this->notice = __('projects.presence.paused');
        $this->dispatch('project-changed');
    }

    public function dismiss(): void
    {
        $this->notice = '';
    }

    public function render()
    {
        return view('livewire.hq.timer-presence', [
            'running' => Auth::check() && DB::table('task_time_logs')->where('user_id', Auth::id())->whereNull('end_time')->exists(),
        ]);
    }
}
