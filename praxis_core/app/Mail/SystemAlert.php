<?php

namespace App\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Carbon;

/**
 * System Health emails to the team (MONITOR_ALERT_EMAIL): new errors, a failing check, the daily report
 * (MonitorAlerts) and "scheduled jobs stopped" (MonitorWatchdog). Internal and English only: send with ->locale('en').
 * Build one with the named constructors below; the wording is in lang/en/emails/system.php.
 */
class SystemAlert extends BrandedMail
{
    public $kind;
    public $data;

    public function __construct(string $kind, array $data)
    {
        $this->kind = $kind;
        $this->data = $data;
    }

    /** @param list<array{summary: string, hint: ?string}> $errors  one entry per new or returning error type */
    public static function newErrors(array $errors): static
    {
        return new static('new_errors', ['errors' => array_values($errors)]);
    }

    /** A health check turned red: its label, what it found and what to do. */
    public static function checkFailing(string $check, string $detail, ?string $fix = null): static
    {
        return new static('check_failing', ['check' => $check, 'detail' => $detail, 'fix' => $fix]);
    }

    /**
     * @param  string  $status  the worst check state (HealthChecks::worst): ok, warn, bad or unknown
     * @param  string  $date  the day of the report, Y-m-d in Lagos time
     * @param  list<array{label: string, state: string, detail: string}>  $checks
     * @param  list<string>  $errors  one line per open error seen in the last 24 hours
     */
    public static function dailyReport(string $status, string $date, array $checks, array $errors): static
    {
        return new static('daily_report', ['status' => $status, 'date' => $date, 'checks' => array_values($checks), 'errors' => array_values($errors)]);
    }

    /** @param  string  $lastRun  the monitor's last heartbeat, Y-m-d H:i:s in UTC */
    public static function schedulerStopped(string $lastRun, string $cronLine): static
    {
        return new static('scheduler_stopped', ['last_run' => $lastRun, 'cron' => $cronLine]);
    }

    protected function subjectLine(): string
    {
        if ($this->kind === 'daily_report') {
            return __('emails.system.report_subject', ['status' => __('emails.system.status_' . self::state($this->data['status'] ?? null))]);
        }

        return __('emails.system.alert_subject', ['what_failed' => $this->whatFailed()]);
    }

    /** "2 new errors on jbspraxis.com", "email sending check failing", "scheduled jobs stopped on jbspraxis.com". */
    private function whatFailed(): string
    {
        return match ($this->kind) {
            'new_errors' => trans_choice('emails.system.new_errors', count($this->data['errors']), ['count' => count($this->data['errors'])]),
            'check_failing' => __('emails.system.check_failing', ['check' => self::lowerFirst((string) $this->data['check'])]),
            default => __('emails.system.scheduler_stopped'),
        };
    }

    public function content(): Content
    {
        // paragraphs, facts (label => value), an optional titled block of the monitor's own text, then a closing line and a note
        $view = ['paragraphs' => [], 'facts' => [], 'detailsTitle' => null, 'details' => null, 'outro' => null, 'note' => null];

        switch ($this->kind) {
            case 'new_errors':
                $view['details'] = collect($this->data['errors'])
                    ->map(fn ($e) => trim($e['summary'] . (filled($e['hint'] ?? null) ? "\n" . $e['hint'] : '')))
                    ->implode("\n\n");
                $view['outro'] = __('emails.system.new_errors_outro');
                $preheader = $this->data['errors'][0]['summary'] ?? '';
                break;

            case 'check_failing':
                $view['facts'] = [
                    __('emails.system.fact_check') => $this->data['check'],
                    __('emails.system.fact_detail') => $this->data['detail'],
                    __('emails.system.fact_fix') => $this->data['fix'],
                ];
                $view['note'] = __('emails.system.check_failing_note');
                $preheader = $this->data['detail'];
                break;

            case 'daily_report':
                $view['paragraphs'][] = $preheader = __('emails.system.report_intro', ['date' => self::formatDate($this->data['date'], 'dddd D MMMM YYYY')]);
                foreach ($this->data['checks'] as $check) {
                    $view['facts'][$check['label']] = __('emails.system.state_' . self::state($check['state'])) . '. ' . $check['detail'];
                }
                $view['detailsTitle'] = __('emails.system.report_errors_title');
                $view['details'] = $this->data['errors'] ? implode("\n", $this->data['errors']) : __('emails.system.report_no_errors');
                break;

            default: // scheduler_stopped
                $view['paragraphs'][] = $preheader = __('emails.system.scheduler_intro', [
                    'time' => self::formatDate($this->data['last_run'], 'dddd D MMMM YYYY, HH:mm') . ' (WAT)',
                ]);
                $view['paragraphs'][] = __('emails.system.scheduler_effect');
                $view['paragraphs'][] = __('emails.system.scheduler_fix');
                $view['details'] = $this->data['cron'];
        }

        return new Content(view: 'emails.system-alert', with: $view + [
            'headline' => $this->kind === 'daily_report' ? __('emails.system.report_headline') : ucfirst($this->whatFailed()),
            'preheader' => $preheader,
            'healthUrl' => route('hq.system.health'),
        ]);
    }

    /** A UTC date or date-time shown in English in Lagos time (WAT); the raw value if it cannot be read, so an alert never fails on a date. */
    private static function formatDate(string $value, string $format): string
    {
        try {
            $date = strlen($value) === 10 ? Carbon::parse($value, 'Africa/Lagos') : Carbon::parse($value, 'UTC')->setTimezone('Africa/Lagos');

            return $date->locale('en')->isoFormat($format);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    private static function state(?string $state): string
    {
        return in_array($state, ['ok', 'warn', 'bad', 'unknown'], true) ? $state : 'unknown';
    }

    /** "Email sending" becomes "email sending" after the colon; an acronym such as "AI providers" stays. */
    private static function lowerFirst(string $text): string
    {
        return preg_match('/^\p{Lu}{2}/u', $text) ? $text : lcfirst($text);
    }

    /** Internal team alert: English only. */
    public static function locales(): array
    {
        return ['en'];
    }

    public static function samples(): array
    {
        return [
            'new-errors' => static::newErrors([
                ['summary' => '[error] QueryException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'ada.okafor@example.com\' (app/Services/Site/EnquiryService.php:144) ×3', 'hint' => 'The database refused to save a second copy of something that must be unique (an email, a number, a link).'],
                ['summary' => '[warning] Monitor: alert email failed (app/Support/Monitor/MonitorAlerts.php:120) ×1', 'hint' => null],
            ]),
            'check-failing' => static::checkFailing('Email sending', '2 emails failed in the last 24 hours.', 'Check the mail server settings (MAIL_* in .env) and the mailbox in cPanel.'),
            'daily-report' => static::dailyReport('warn', '2026-11-05', [
                ['label' => 'Scheduler (cron)', 'state' => 'ok', 'detail' => 'Last run 2 minutes ago. It runs every minute; the monitor every 5 minutes.'],
                ['label' => 'Errors (last 24 h)', 'state' => 'warn', 'detail' => '1 open error type(s) seen, 0 new.'],
                ['label' => 'Email sending', 'state' => 'ok', 'detail' => 'No failed emails in the last 24 hours.'],
                ['label' => 'Exchange rates', 'state' => 'ok', 'detail' => 'Last automatic sync 8 hours ago.'],
            ], [
                '[error] QueryException: SQLSTATE[23000]: Integrity constraint violation (app/Services/Site/EnquiryService.php:144) ×3',
            ]),
            'scheduler-stopped' => static::schedulerStopped('2026-11-05 08:12:00', '* * * * * /usr/local/bin/ea-php83 /home/example/praxis_core/artisan schedule:run'),
        ];
    }
}
