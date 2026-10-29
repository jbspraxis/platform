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

// HQ → System Health: heartbeat, new log errors, alerts, the 8am digest (app/Support/Monitor)
Schedule::command('monitor:run')->everyFiveMinutes()->withoutOverlapping(10);