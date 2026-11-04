<?php

namespace App\Support\CompanyDocuments;

use App\Models\CompanyDeadline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Each company's compliance calendar (owner's decision 3, 4 Nov 2026; docs/COMPANY_DOCUMENTS.md): the CAC annual
 * return, the first accounts, tax filings. A deadline is due on a day, once or every year, with a bell some days
 * before (mdm:compliance-bell). Marking it done notes who and when; a yearly one then adds next year's. Archived,
 * never deleted. Who: mdm.create adds; mdm.edit edits and marks done; mdm.delete archives (the screens check).
 */
final class Calendar
{
    public static function rules(): array
    {
        return [
            'company' => ['required', 'string', Rule::in(Companies::keys())],
            'title' => ['required', 'string', 'max:190'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'repeats' => ['required', Rule::in(array_keys(CompanyDeadline::REPEATS))],
            'remind_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public const ATTRIBUTES = [
        'company' => 'company', 'title' => 'title', 'due_on' => 'due date', 'repeats' => 'repeats', 'remind_days' => 'reminder', 'notes' => 'notes',
    ];

    /** Checks a deadline's details; returns them as stored */
    public static function validated(array $values): array
    {
        $values = array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $values);
        $data = Validator::make($values, self::rules(), ['company.in' => 'Choose one of the companies in the list.'], self::ATTRIBUTES)->validate();

        return [
            'legal_entity_id' => Companies::idFor((string) $data['company']),
            'title' => $data['title'],
            'due_on' => $data['due_on'],
            'repeats' => $data['repeats'],
            'remind_days' => isset($data['remind_days']) ? (int) $data['remind_days'] : null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    public static function add(array $values, User $by, ?string $documentId = null): CompanyDeadline
    {
        $data = self::validated($values);
        $deadline = CompanyDeadline::create($data + ['document_id' => $documentId, 'created_by' => $by->id, 'updated_by' => $by->id]);
        Documents::log('COMPANY_DEADLINE_ADDED', $deadline, "Added ‘{$deadline->title}’, due " . Documents::day($data['due_on'])
            . ' (' . strtolower(CompanyDeadline::REPEATS[$data['repeats']]) . '), to the calendar of ' . Companies::label($deadline->legal_entity_id) . '.',
            null, self::auditValues($deadline), $by->id);

        return $deadline;
    }

    public static function update(CompanyDeadline $deadline, array $values, User $by): void
    {
        $before = self::auditValues($deadline);
        $deadline->fill(self::validated($values));
        $after = self::auditValues($deadline);
        $changed = array_keys(array_filter($after, fn ($v, $k) => $before[$k] !== $v, ARRAY_FILTER_USE_BOTH));
        if (! $changed) {
            return;
        }
        $deadline->updated_by = $by->id;
        $deadline->save();
        Documents::log('COMPANY_DEADLINE_EDITED', $deadline, "Edited ‘{$deadline->title}’ in the calendar of " . Companies::label($deadline->legal_entity_id) . '.',
            array_intersect_key($before, array_flip($changed)), array_intersect_key($after, array_flip($changed)), $by->id);
    }

    /** Done, by whom and when; a yearly deadline adds next year's, which is returned */
    public static function markDone(CompanyDeadline $deadline, User $by): ?CompanyDeadline
    {
        if ($deadline->isDone() || $deadline->archived_at) {
            return null;
        }

        return DB::transaction(function () use ($deadline, $by) {
            $deadline->forceFill(['done_at' => now(), 'done_by' => $by->id, 'updated_by' => $by->id])->save();
            $next = null;
            if ($deadline->repeats === 'yearly') {
                $next = CompanyDeadline::create([
                    'legal_entity_id' => $deadline->legal_entity_id, 'title' => $deadline->title,
                    'due_on' => CarbonImmutable::parse($deadline->due_on)->addYearNoOverflow()->format('Y-m-d'),
                    'repeats' => 'yearly', 'remind_days' => $deadline->remind_days, 'notes' => $deadline->notes,
                    'document_id' => $deadline->document_id, 'created_by' => $by->id, 'updated_by' => $by->id,
                ]);
            }
            Documents::log('COMPANY_DEADLINE_DONE', $deadline, "Marked ‘{$deadline->title}’ (due " . Documents::day($deadline->due_on->format('Y-m-d')) . ') done for '
                . Companies::label($deadline->legal_entity_id) . '.' . ($next ? ' Next year’s is due ' . Documents::day($next->due_on->format('Y-m-d')) . '.' : ''),
                null, ['done_at' => now()->toIso8601String(), 'next_due_on' => $next?->due_on?->format('Y-m-d')], $by->id);

            return $next;
        });
    }

    public static function archive(CompanyDeadline $deadline, User $by): void
    {
        if ($deadline->archived_at) {
            return;
        }
        $deadline->forceFill(['archived_at' => now(), 'archived_by' => $by->id])->save();
        Documents::log('COMPANY_DEADLINE_ARCHIVED', $deadline, "Archived ‘{$deadline->title}’ (due " . Documents::day($deadline->due_on->format('Y-m-d')) . ') from the calendar of '
            . Companies::label($deadline->legal_entity_id) . '.', null, null, $by->id);
    }

    /**
     * Done, overdue, due soon (within its reminder, 30 days when it has none) or upcoming.
     *
     * @return array{key: string, label: string, pill: string, days: ?int}
     */
    public static function status(CompanyDeadline $deadline, ?string $today = null): array
    {
        if ($deadline->isDone()) {
            return ['key' => 'done', 'label' => 'Done', 'pill' => 'pill-success', 'days' => null];
        }
        $days = Documents::daysUntil($deadline->due_on->format('Y-m-d'), $today);

        return match (true) {
            $days < 0 => ['key' => 'overdue', 'label' => 'Overdue', 'pill' => 'pill-danger', 'days' => $days],
            $days === 0 => ['key' => 'soon', 'label' => 'Due today', 'pill' => 'pill-warning', 'days' => 0],
            $days <= ($deadline->remind_days ?? Documents::DEFAULT_REMIND_DAYS) => ['key' => 'soon', 'label' => 'Due in ' . $days . ($days === 1 ? ' day' : ' days'), 'pill' => 'pill-warning', 'days' => $days],
            default => ['key' => 'upcoming', 'label' => 'Upcoming', 'pill' => 'pill-info', 'days' => $days],
        };
    }

    private static function auditValues(CompanyDeadline $d): array
    {
        return [
            'legal_entity_id' => $d->legal_entity_id, 'title' => $d->title, 'due_on' => $d->due_on?->format('Y-m-d'),
            'repeats' => $d->repeats, 'remind_days' => $d->remind_days, 'notes' => $d->notes,
        ];
    }
}
