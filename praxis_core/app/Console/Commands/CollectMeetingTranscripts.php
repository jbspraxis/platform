<?php

namespace App\Console\Commands;

use App\Support\Opportunities\TranscriptCollector;
use Illuminate\Console\Command;

/** Every 5 minutes (routes/console.php): collect the transcripts of meetings that ended (docs/OPPORTUNITIES.md 6.1) */
class CollectMeetingTranscripts extends Command
{
    protected $signature = 'meetings:collect-transcripts';

    protected $description = 'Mark ended meetings as held and collect their transcripts from Google (Meet API, then the Gemini notes in Calendar)';

    public function handle(): int
    {
        $r = TranscriptCollector::run();
        $this->line(sprintf('Ended %d, looked up %d, filed %d, not found %d, to add by hand %d%s.', $r['ended'], $r['tried'], $r['filed'], $r['not_found'], $r['manual'],
            $r['google'] ? ", Google: {$r['google']}" : ''));

        return self::SUCCESS;
    }
}
