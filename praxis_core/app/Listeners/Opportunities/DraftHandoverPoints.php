<?php

namespace App\Listeners\Opportunities;

use App\Events\Opportunities\TranscriptCollected;
use App\Support\Ai\AiUnavailable;
use App\Support\Opportunities\Handovers;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * A milestone's handover meeting has its transcript: the AI drafts the points raised in it (docs/OPPORTUNITIES.md
 * section 12, PR 6), for the lead to confirm as QA tickets. Without a key or over the budget nothing is drafted;
 * the meeting offers *Draft the points*.
 */
class DraftHandoverPoints
{
    public function handle(TranscriptCollected $event): void
    {
        $meeting = $event->transcript->meeting;
        if (! $meeting?->isHandover()) {
            return;
        }
        try {
            Handovers::draftPoints($event->transcript);
        } catch (AiUnavailable $e) {
            Log::info('Handovers: the AI did not draft the points', ['transcript' => $event->transcript->id, 'reason' => $e->reason]);
        } catch (LogicException $e) {
            Log::info('Handovers: no points to draft', ['transcript' => $event->transcript->id, 'why' => $e->getMessage()]);
        }
    }
}
