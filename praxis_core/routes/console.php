<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// HQ Mail (docs/MAIL.md): copy new mail from every connected mailbox. First and in the background: a run takes up to
// 50 s, and in the foreground every command below waited for it each minute (scheduled sends, the AI queue, pushes),
// while new mail waited for the commands above it
Schedule::command('mail:sync')->everyMinute()->withoutOverlapping(10)->runInBackground();

// Opportunities (docs/OPPORTUNITIES.md 8.3): AI jobs wait on their own queues and run here. The worker stays up for most
// of the minute and looks for work every second, so a job someone is waiting for starts within seconds (it used to stop
// as soon as the queue was empty, and the next job waited up to a minute for the next one); the bulk queue (outreach
// drafts and reply sorting, config/ai.php) only when the first is empty. A long document keeps its worker until it is
// done (no overlap). Only the AI queues: anything else in the jobs table is left alone. Each job is tried once
// (RunAiJob). Runs left behind by a worker that stopped fail after 30 minutes, so screens stop waiting.
Schedule::command('queue:work ' . config('ai.queue.connection') . ' --queue=' . implode(',', array_filter([config('ai.queue.name'), config('ai.queue.bulk')]))
    . ' --sleep=1 --max-time=50 --tries=2')
    ->everyMinute()->withoutOverlapping(30)->runInBackground();

// The 6-Hour Shift Marketing Telemetry Cron Job
Schedule::command('marketing:sync-telemetry')->everySixHours();

// Broadcasts (docs/CAMPAIGNS.md 4.6): starts scheduled ones and sends 30 emails a run, the cPanel SMTP pace; never two runs at once
Schedule::command('broadcast:process')->everyFiveMinutes()->withoutOverlapping(10);

// Outreach (docs/CAMPAIGNS.md section 10): Claude writes the next imported prospects' emails (on the 'ai' queue below);
// the sender sends one approved email per salesperson at most, within the owner's limits and window, and stops
// follow-ups when a prospect replies. Each never runs twice at once.
Schedule::command('outreach:write')->everyMinute()->withoutOverlapping(10);
Schedule::command('outreach:send')->everyMinute()->withoutOverlapping(10);
// C3 (section 12): replies, bounce notices and spam complaints found in HQ Mail, and Claude's labels for the replies;
// on working days at 07:30 Lagos time, a bell to the sender for each prospect who said not now and whose day has come
Schedule::command('outreach:replies')->everyMinute()->withoutOverlapping(10);
Schedule::command('outreach:remind')->weekdays()->dailyAt('07:30')->timezone('Africa/Lagos')->withoutOverlapping(10);

// The The 120-Minute Idle Killswitch Command on hours spent working and the The QA SLA Auto-Approver Command from clients.
Schedule::command('ops:qa-auto-approve')->everyMinute();
Schedule::command('ops:timer-killswitch')->everyFiveMinutes();

// The Fetch fresh Exchange Rates right at Midnight
Schedule::command('finance:sync-exchange-rates')->dailyAt('00:00');

// 'finance:run-automations' (00:30) was removed: no such command exists, so it failed every night.
// Add it back here once the ledger automation command is built.

// Company documents (docs/COMPANY_DOCUMENTS.md): on working days at 07:30 Lagos time, documents nearing their expiry and
// compliance deadlines nearing their due date ring the owner and everyone with mdm.edit, once per item and date
Schedule::command('mdm:compliance-bell')->weekdays()->dailyAt('07:30')->timezone('Africa/Lagos')->withoutOverlapping(10);

// Leads (docs/LEADS.md section 9): on working days at 07:30 Lagos time, each lead owner gets one bell about overdue and
// today's follow-ups and open leads with no next step; it opens My follow-ups. Rings nobody twice in a day.
Schedule::command('leads:morning-bell')->weekdays()->dailyAt('07:30')->timezone('Africa/Lagos')->withoutOverlapping(10);

// Free audit requests nobody approved before their start time close by themselves (Cancelled, logged, no email)
Schedule::command('crm:expire-audit-requests')->hourly()->withoutOverlapping(10);

// HQ → System Health: heartbeat, new log errors, alerts, the 8am digest (app/Support/Monitor)
Schedule::command('monitor:run')->everyFiveMinutes()->withoutOverlapping(10);

// HQ app (docs/PWA.md): push bell notifications that weren't pushed as they were written, and tidy up old delivery records
Schedule::command('pwa:push-pending')->everyMinute()->withoutOverlapping(5);
Schedule::command('pwa:push-prune')->dailyAt('03:10');

// Server backups (docs/BACKUPS.md): every database of the cPanel account at 02:00 Lagos time (restore test on Sundays),
// every site's uploaded files on Sundays at 03:00 (it waits for the databases if they are still running), each encrypted
// and sent to Drive in its own process; hourly, a bell to the owner for any site whose last good backup is too old
Schedule::command('backups:run')->dailyAt('02:00')->timezone('Africa/Lagos')->withoutOverlapping(360)->runInBackground()
    ->appendOutputTo(storage_path('logs/backups.log'));
Schedule::command('backups:files')->sundays()->at('03:00')->timezone('Africa/Lagos')->withoutOverlapping(360)->runInBackground()
    ->appendOutputTo(storage_path('logs/backups.log'));
Schedule::command('backups:check')->hourlyAt(17)->withoutOverlapping(10);

// HQ Mail (docs/MAIL.md): send what is due, tidy up (mail:sync is at the top of this file)
Schedule::command('mail:send-due')->everyMinute()->withoutOverlapping(5);
Schedule::command('mail:prune')->dailyAt('03:40');

Schedule::call(fn () => \App\Support\Ai\Ai::expireStuck())->name('ai:expire-stuck')->everyTenMinutes()->withoutOverlapping(5);

// Meetings (docs/OPPORTUNITIES.md 6.1): ended meetings become held; transcripts are collected from Google from 20
// minutes after the end, every 10 minutes until 6 hours after it, then the lead is asked to add it by hand
Schedule::command('meetings:collect-transcripts')->everyFiveMinutes()->withoutOverlapping(10);

// Leads (docs/LEADS.md section 9): mail to and from a lead's contacts goes on the lead's timeline (who wrote to whom, never the text)
Schedule::command('leads:file-emails')->everyFiveMinutes()->withoutOverlapping(10);

// The privacy policy on jbspraxis.com: the website's visitor limits keep IP addresses 24 hours at most, so expired
// cache rows (whose keys hold them) go within minutes instead of whenever the same key is read again
Schedule::command('cache:prune-expired')->everyFiveMinutes()->withoutOverlapping(5);

// Careers (the privacy policy on jbspraxis.com): CVs deleted and applications anonymised 12 months after they
// arrive. Only once the owner sets CV_RETENTION_PURGE=true (config/praxis.php); try it with --dry-run first
if (config('praxis.cv_retention_purge')) {
    Schedule::command('careers:purge-cvs')->dailyAt('03:25')->withoutOverlapping(30);
}
