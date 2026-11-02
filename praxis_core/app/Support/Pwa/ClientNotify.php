<?php

namespace App\Support\Pwa;

use App\Models\ClientUser;
use App\Models\PushSubscription;
use App\Support\Access;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The Client Portal's inbox and its push notifications (docs/PWA.md, "Client Portal"). HQ screens call
 * organization() or clients() when something happens that a client should know about; each row is shown
 * in the portal's bell in the reader's language and pushed once to their devices, each in its own language.
 */
class ClientNotify
{
    // What happened => the portal permission of the people who hear about it, and the bell's icon
    public const KINDS = [
        'invoice_issued' => ['portal.billing.view', 'receipt'],
        'invoice_overdue' => ['portal.billing.view', 'clock-alert'],
        'invoice_cancelled' => ['portal.billing.view', 'receipt'],
        'payment_verified' => ['portal.billing.view', 'badge-check'],
        'payment_rejected' => ['portal.billing.view', 'circle-alert'],
        'proposal_ready' => ['portal.projects.approve', 'file-signature'],
        'signoff_ready' => ['portal.projects.approve', 'clipboard-check'],
        'task_review' => ['portal.projects.view', 'list-checks'],
        'task_review_reminder' => ['portal.projects.view', 'clock'],
        'ticket_investigating' => ['portal.qa.view', 'search'],
        'ticket_reply' => ['portal.qa.view', 'message-square'],
        'ticket_resolved' => ['portal.qa.view', 'circle-check'],
    ];

    // Where each kind opens in the portal (the dashboard tab)
    private const TABS = [
        'invoice_issued' => 'financials', 'invoice_overdue' => 'financials', 'invoice_cancelled' => 'financials',
        'payment_verified' => 'financials', 'payment_rejected' => 'financials',
        'task_review' => 'sprints', 'task_review_reminder' => 'sprints', 'ticket_investigating' => 'qa_hub', 'ticket_reply' => 'qa_hub', 'ticket_resolved' => 'qa_hub',
    ];

    private static bool $pushQueued = false;

    private static ?bool $ready = null;

    /**
     * Tell the organisation's portal accounts that hold the kind's permission (and, for a project, can see
     * that project). Never throws: a notification must not stop the HQ action that caused it.
     */
    public static function organization(?string $organizationId, string $kind, array $params = [], ?string $link = null, ?string $projectId = null): int
    {
        if (! $organizationId || ! isset(self::KINDS[$kind])) {
            return 0;
        }

        try {
            return self::clients(self::recipients($organizationId, self::KINDS[$kind][0], $projectId), $kind, $params, $link, $organizationId);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Tell these portal accounts (ids from client_users). */
    public static function clients(array $clientUserIds, string $kind, array $params = [], ?string $link = null, ?string $organizationId = null): int
    {
        $clientUserIds = array_values(array_unique(array_filter($clientUserIds)));
        if (! $clientUserIds || ! isset(self::KINDS[$kind]) || ! self::ready()) {
            return 0;
        }

        try {
            $link ??= isset(self::TABS[$kind]) ? '/?tab=' . self::TABS[$kind] : '/';
            $rows = array_map(fn ($id) => [
                'id' => (string) Str::uuid(),
                'client_user_id' => $id,
                'organization_id' => $organizationId,
                'kind' => $kind,
                'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'link_url' => $link,
                'is_read' => 0,
                'created_at' => now(),
            ], $clientUserIds);
            DB::table('client_notifications')->insert($rows);
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }

        // Pushed once the response (or command) is out, so the HQ screen isn't kept waiting
        if (! self::$pushQueued) {
            self::$pushQueued = true;
            app()->terminating(function () {
                self::$pushQueued = false;
                ClientPush::dispatchPendingQuietly();
            });
        }

        return count($rows);
    }

    // ---- What HQ screens call (one line each); every one is quiet on failure -------------------------------

    /** An invoice event (invoice_issued, invoice_overdue, invoice_cancelled, payment_verified, payment_rejected) to the client's billing people. */
    public static function invoice(?string $invoiceId, string $kind): int
    {
        return self::quietly(function () use ($invoiceId, $kind) {
            $invoice = DB::table('invoices')->where('id', $invoiceId)->first();
            $project = $invoice ? DB::table('projects')->where('id', $invoice->project_id)->first() : null;

            return $project ? self::organization($project->organization_id, $kind, [
                'ref' => $invoice->invoice_number,
                'amount' => trim(($invoice->currency ?? '') . ' ' . number_format((float) $invoice->total_amount, 2)),
                'due' => $invoice->due_date ? Carbon::parse($invoice->due_date)->toDateString() : '',
            ], null, $project->id) : 0;
        });
    }

    /** An issue event (ticket_investigating, ticket_reply, ticket_resolved) to the client who reported it. */
    public static function ticket(?string $ticketId, string $kind): int
    {
        return self::quietly(function () use ($ticketId, $kind) {
            $ticket = DB::table('qa_tickets')->where('id', $ticketId)->first();
            if (! $ticket || ($ticket->submitted_by_type ?? 'client') !== 'client') {
                return 0;
            }
            $client = ClientUser::where('id', $ticket->submitted_by)->where('status', 'Active')->first();

            return $client && Access::allows($client, self::KINDS[$kind][0] ?? 'portal.qa.view')
                ? self::clients([$client->id], $kind, ['ticket' => $ticket->title], null, $client->organization_id) : 0;
        });
    }

    /**
     * A task now waiting for the client's review (the QA_Review column) to the project's client accounts. An internal
     * task (tasks.is_client_visible = 0, docs/PROJECTS.md) is never announced: the client doesn't see it.
     */
    public static function task(?string $taskId): int
    {
        return self::quietly(function () use ($taskId) {
            $task = DB::table('tasks')->where('id', $taskId)->whereNull('deleted_at')->first();
            if (! $task || ! $task->is_client_visible) {
                return 0;
            }
            $projectId = $task ? (DB::table('project_phases')->where('id', $task->phase_id)->value('project_id')
                ?? ($task->sprint_id ? DB::table('sprints')->where('id', $task->sprint_id)->value('project_id') : null)) : null;
            $project = $projectId ? DB::table('projects')->where('id', $projectId)->first() : null;

            return $project ? self::organization($project->organization_id, 'task_review', ['task' => $task->title, 'project' => $project->title ?? ''], null, $project->id) : 0;
        });
    }

    /** A task still waiting for the client's review the day before it is approved automatically (D9) */
    public static function reviewReminder(object $task): int
    {
        return self::quietly(function () use ($task) {
            $project = DB::table('projects')->where('id', DB::table('project_phases')->where('id', $task->phase_id)->value('project_id'))->first();
            if (! $project || ! $task->is_client_visible) {
                return 0;
            }
            $when = Carbon::parse($task->qa_sla_expires_at)->setTimezone('Africa/Lagos');

            return self::organization($project->organization_id, 'task_review_reminder',
                ['task' => $task->title, 'project' => $project->title ?? '', 'date' => $when->format('d/m/Y H:i') . ' WAT'], null, $project->id);
        });
    }

    /** A project handover (sign-off) ready to sign, to the client's approvers, with its signing link. */
    public static function signoff(?string $signoffId): int
    {
        return self::quietly(function () use ($signoffId) {
            $signoff = DB::table('project_signoffs')->where('id', $signoffId)->first();
            $project = $signoff ? DB::table('projects')->where('id', $signoff->project_id)->first() : null;

            return $project ? self::organization($project->organization_id, 'signoff_ready', ['project' => $project->title ?? ''], '/signoff/' . $signoff->token, $project->id) : 0;
        });
    }

    /** A proposal signed by JBS Praxis and ready for the client to sign, to the client's approvers. */
    public static function proposal(?string $proposalId): int
    {
        return self::quietly(function () use ($proposalId) {
            $proposal = DB::table('proposals')->where('id', $proposalId)->first();

            return $proposal && $proposal->token_hash
                ? self::organization($proposal->organization_id, 'proposal_ready', ['ref' => $proposal->title ?: $proposal->token_hash], '/proposal/' . $proposal->token_hash) : 0;
        });
    }

    private static function quietly(\Closure $send): int
    {
        try {
            return self::ready() ? (int) $send() : 0;
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Active portal accounts of the organisation with that permission, limited to the project when given. */
    public static function recipients(string $organizationId, string $permission, ?string $projectId = null): array
    {
        return ClientUser::where('organization_id', $organizationId)
            ->where('status', 'Active')
            ->whereNotNull('password')
            ->when($projectId, fn ($q) => $q->where(fn ($q) => $q->whereNull('restricted_project_id')->orWhere('restricted_project_id', $projectId)))
            ->get()
            ->filter(fn (ClientUser $client) => Access::allows($client, $permission))
            ->pluck('id')
            ->all();
    }

    /** [title, body] of a notification in a language ('en' or 'fr'); dates in the params are shown in that language. */
    public static function text(string $kind, $params, ?string $locale = null): array
    {
        $locale = in_array($locale, ['en', 'fr'], true) ? $locale : app()->getLocale();
        $params = is_string($params) ? (json_decode($params, true) ?: []) : (array) $params;
        foreach (['due', 'date'] as $date) {
            if (! empty($params[$date])) {
                $params[$date] = Carbon::parse($params[$date])->locale($locale)->isoFormat('D MMM YYYY');
            }
        }

        return [
            __('portal.inbox.kinds.' . $kind . '.title', $params, $locale),
            __('portal.inbox.kinds.' . $kind . '.body', $params, $locale),
        ];
    }

    public static function unread(string $clientUserId): int
    {
        return DB::table('client_notifications')->where('client_user_id', $clientUserId)->where('is_read', 0)->count();
    }

    /** Whether the portal's tables are there (on the server the code arrives a few minutes before the migration). */
    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('client_notifications') && Schema::hasColumn('push_subscriptions', 'client_user_id');
    }

    /** Forget the table check (tests). */
    public static function flush(): void
    {
        self::$ready = null;
        self::$pushQueued = false;
    }

    /** A portal account's devices (push_subscriptions rows with its client_user_id). */
    public static function devices(string $clientUserId)
    {
        return PushSubscription::where('client_user_id', $clientUserId)->orderBy('id')->get();
    }
}
