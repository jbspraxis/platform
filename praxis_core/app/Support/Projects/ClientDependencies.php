<?php

namespace App\Support\Projects;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What a milestone needs from the client (owner, 13 Nov 2026), table milestone_dependencies.
 *
 * Statuses: needed → requested (we asked) → sent (the client marked it sent in the portal, maybe with a file) →
 * received (we have it); or waived. "Late" is an item not received or waived past its needed-by date. Under the SOW
 * (clause 8.2) the affected timetable extends day for day: a milestone's extension is the longest delay among its
 * items, in calendar days (items running late at the same time overlap, they don't add up).
 */
final class ClientDependencies
{
    public const STATUSES = ['needed', 'requested', 'sent', 'received', 'waived'];

    /** Still waiting on the client (or on us to check what they sent) */
    public const OPEN = ['needed', 'requested', 'sent'];

    /** A milestone's items, in order, each with ->late_days */
    public static function forPhase(string $phaseId, ?CarbonImmutable $today = null): Collection
    {
        return self::decorate(DB::table('milestone_dependencies')->where('phase_id', $phaseId)->whereNull('deleted_at')
            ->orderBy('position')->orderBy('id')->get(), $today);
    }

    /** A project's items (optionally open ones only), in milestone order, each with ->late_days and ->phase_number */
    public static function forProject(string $projectId, bool $openOnly = false, ?CarbonImmutable $today = null): Collection
    {
        return self::decorate(DB::table('milestone_dependencies')
            ->join('project_phases', 'project_phases.id', '=', 'milestone_dependencies.phase_id')
            ->where('milestone_dependencies.project_id', $projectId)->whereNull('milestone_dependencies.deleted_at')->whereNull('project_phases.deleted_at')
            ->when($openOnly, fn ($q) => $q->whereIn('milestone_dependencies.status', self::OPEN))
            ->orderBy('project_phases.phase_number')->orderBy('milestone_dependencies.position')->orderBy('milestone_dependencies.id')
            ->get(['milestone_dependencies.*', 'project_phases.phase_number', 'project_phases.title as phase_title']), $today);
    }

    /** Per milestone: total, done (received or waived), open, late, extension (days), keyed by phase id */
    public static function summary(iterable $phaseIds, ?CarbonImmutable $today = null): Collection
    {
        $ids = collect($phaseIds)->map(fn ($id) => (string) $id)->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }
        $rows = self::decorate(DB::table('milestone_dependencies')->whereIn('phase_id', $ids)->whereNull('deleted_at')->get(), $today);

        return $rows->groupBy('phase_id')->map(fn (Collection $items) => (object) [
            'total' => $items->count(),
            'done' => $items->whereIn('status', ['received', 'waived'])->count(),
            'open' => $items->whereIn('status', self::OPEN)->count(),
            'late' => $items->filter(fn ($i) => in_array($i->status, self::OPEN, true) && $i->late_days > 0)->count(),
            'extension' => self::extensionDays($items),
        ]);
    }

    /**
     * Days past its needed-by date: until today while it's still open, until it was received once it was received late
     * (a waived item is never late)
     */
    public static function lateDays(object $item, ?CarbonImmutable $today = null): int
    {
        if (! $item->due_date || $item->status === 'waived') {
            return 0;
        }
        // Whole days, Lagos calendar: compare the dates themselves, never times in two time zones
        $due = CarbonImmutable::parse(substr((string) $item->due_date, 0, 10), 'UTC');
        $until = $item->status === 'received'
            ? ($item->received_at ? CarbonImmutable::parse($item->received_at)->setTimezone('Africa/Lagos')->toDateString() : $due->toDateString())
            : ($today ?? CarbonImmutable::now('Africa/Lagos'))->setTimezone('Africa/Lagos')->toDateString();

        return max(0, (int) round($due->diffInDays(CarbonImmutable::parse($until, 'UTC'), false)));
    }

    /** Clause 8.2: the milestone moves by the longest delay among its items */
    public static function extensionDays(Collection $items): int
    {
        return (int) $items->max(fn ($i) => $i->late_days ?? 0) ?: 0;
    }

    /**
     * The usual needed-by date: the milestone's start; for an integration, 7 business days before it (clause 8.3).
     */
    public static function defaultDue(object $phase, bool $integration = false): ?string
    {
        if (empty($phase->start_date)) {
            return null;
        }
        $day = CarbonImmutable::parse($phase->start_date);
        if ($integration) {
            for ($left = 7; $left > 0;) {
                $day = $day->subDay();
                if (! $day->isWeekend()) {
                    $left--;
                }
            }
        }

        return $day->toDateString();
    }

    public static function add(object $phase, array $data, ?string $userId): int
    {
        $position = (int) DB::table('milestone_dependencies')->where('phase_id', $phase->id)->max('position') + 1;

        return (int) DB::table('milestone_dependencies')->insertGetId([
            'project_id' => $phase->project_id,
            'phase_id' => $phase->id,
            'group_code' => self::clean($data['group_code'] ?? null, 12),
            'group_title' => self::clean($data['group_title'] ?? null, 150),
            'title' => trim((string) $data['title']),
            'position' => $data['position'] ?? $position,
            'due_date' => ($data['due_date'] ?? '') !== '' ? $data['due_date'] : self::defaultDue($phase, (bool) ($data['integration'] ?? false)),
            'status' => in_array($data['status'] ?? 'needed', self::STATUSES, true) ? ($data['status'] ?? 'needed') : 'needed',
            'note' => self::clean($data['note'] ?? null, 5000),
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** HQ moves an item: requested, received, waived, or back to needed */
    public static function setStatus(int $id, string $status, ?string $userId): void
    {
        abort_unless(in_array($status, self::STATUSES, true), 422);
        $values = ['status' => $status, 'updated_by' => $userId, 'updated_at' => now()];
        if ($status === 'requested') {
            $values['requested_at'] = now();
        }
        if ($status === 'received') {
            $values['received_at'] = now();
            $values['received_by'] = $userId;
        } else {
            $values['received_at'] = null;
            $values['received_by'] = null;
        }
        DB::table('milestone_dependencies')->where('id', $id)->update($values);
    }

    /** The client marks an item sent, with a note and maybe a file; the project's lead hears about it */
    public static function markSent(object $item, object $clientUser, ?string $note, ?string $filePath, ?string $fileName): void
    {
        DB::table('milestone_dependencies')->where('id', $item->id)->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_by_client_user_id' => $clientUser->id,
            'client_note' => self::clean($note, 5000),
            'file_path' => $filePath ?? $item->file_path,
            'file_name' => $fileName !== null ? Str::limit($fileName, 196, '') : $item->file_name,
            'updated_at' => now(),
        ]);

        $project = DB::table('projects')->where('id', $item->project_id)->first(['id', 'title', 'lead_user_id', 'created_by']);
        $phase = DB::table('project_phases')->where('id', $item->phase_id)->first(['phase_number']);
        $who = trim(($clientUser->first_name ?? '') . ' ' . ($clientUser->last_name ?? '')) ?: 'The client';
        $to = collect([$project->lead_user_id ?? null, $project->created_by ?? null])->filter()->unique()->values();
        if ($to->isNotEmpty()) {
            DB::table('notifications')->insert($to->map(fn ($userId) => [
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'title' => 'The client sent something you needed',
                'message' => Str::limit("{$who} marked as sent for M{$phase->phase_number} of {$project->title}: " . Str::limit((string) $item->title, 120), 250),
                'link_url' => route('hq.ops.project', ['project' => $project->id, 'tab' => 'milestones']),
                'type' => 'Project',
                'created_at' => now(),
            ])->all());
        }
    }

    private static function decorate(Collection $rows, ?CarbonImmutable $today): Collection
    {
        return $rows->map(function ($row) use ($today) {
            $row->late_days = self::lateDays($row, $today);

            return $row;
        });
    }

    private static function clean($value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::limit($value, $max, '');
    }
}
