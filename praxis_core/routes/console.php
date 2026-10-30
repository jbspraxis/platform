<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

// The 6-Hour Shift Marketing Telemetry Cron Job
Schedule::command('marketing:sync-telemetry')->everySixHours();

// The Stealth Broadcast Sender (30 emails every 5 minutes to avoid being pushed to spams)
Schedule::command('broadcast:process')->everyFiveMinutes();

// The The 120-Minute Idle Killswitch Command on hours spent working and the The QA SLA Auto-Approver Command from clients.
Schedule::command('ops:qa-auto-approve')->everyMinute();
Schedule::command('ops:timer-killswitch')->everyFiveMinutes();

// The Fetch fresh Exchange Rates right at Midnight
Schedule::command('finance:sync-exchange-rates')->dailyAt('00:00');

// 'finance:run-automations' (00:30) was removed: no such command exists, so it failed every night.
// Add it back here once the ledger automation command is built.

// Free audit requests nobody approved before their start time close by themselves (Cancelled, logged, no email)
Schedule::command('crm:expire-audit-requests')->hourly()->withoutOverlapping(10);

// HQ → System Health: heartbeat, new log errors, alerts, the 8am digest (app/Support/Monitor)
Schedule::command('monitor:run')->everyFiveMinutes()->withoutOverlapping(10);

// HQ app (docs/PWA.md): push bell notifications that weren't pushed as they were written, and tidy up old delivery records
Schedule::command('pwa:push-pending')->everyMinute()->withoutOverlapping(5);
Schedule::command('pwa:push-prune')->dailyAt('03:10');

// HQ Mail (docs/MAIL.md): copy new mail from every connected mailbox, send what is due, tidy up
Schedule::command('mail:sync')->everyMinute()->withoutOverlapping(10);
Schedule::command('mail:send-due')->everyMinute()->withoutOverlapping(5);
Schedule::command('mail:prune')->dailyAt('03:40');
