<?php

// SystemAlert: System Health emails to the team (MonitorAlerts, MonitorWatchdog). Internal, English only.
return [
    'alert_subject' => 'System Health: :what_failed',
    'report_subject' => 'System Health: daily report, :status',
    'button' => 'Open System Health',

    // New or returning error types (monitor:run, every 5 minutes)
    'new_errors' => ':count new error on jbspraxis.com|:count new errors on jbspraxis.com',
    'new_errors_outro' => 'Open the Errors tab in System Health and use ‘Copy for AI’ to get a suggested fix.',

    // A health check turned red (scheduler, email, AI providers, live settings)
    'check_failing' => ':check check failing',
    'check_failing_note' => 'While this check stays red, this alert repeats at most every 6 hours.',
    'fact_check' => 'Check',
    'fact_detail' => 'What it found',
    'fact_fix' => 'What to do',

    // The daily report (8:00 in Lagos)
    'report_headline' => 'Daily report for jbspraxis.com',
    'report_intro' => 'Health checks and open errors for jbspraxis.com on :date.',
    'report_errors_title' => 'Open errors seen in the last 24 hours',
    'report_no_errors' => 'None.',
    'state_ok' => 'Passing',
    'state_warn' => 'Warning',
    'state_bad' => 'Failing',
    'state_unknown' => 'Could not run',
    'status_ok' => 'all checks passing',
    'status_warn' => 'warnings',
    'status_bad' => 'checks failing',
    'status_unknown' => 'some checks could not run',

    // The scheduled jobs stopped (noticed by page visits; at most every 6 hours)
    'scheduler_stopped' => 'scheduled jobs stopped on jbspraxis.com',
    'scheduler_intro' => 'The scheduled jobs on jbspraxis.com have not run since :time.',
    'scheduler_effect' => 'Until they run again, there are no error alerts, no exchange-rate sync and no QA auto-approval.',
    'scheduler_fix' => 'Check that cPanel → Cron Jobs has this line:',
];
