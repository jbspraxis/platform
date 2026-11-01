<?php

namespace App\Livewire\Hq\Opportunities\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The lead and team pickers of an opportunity form (x-hq.picker): type part of a name, the list narrows, click or
 * press Enter to choose. The screen using it says who may pick (authorizePicking) and reads leadUserId and
 * teamUserIds when it saves. The lead is never also a member.
 */
trait PicksStaff
{
    public string $leadSearch = '';

    public string $leadUserId = '';

    public string $teamSearch = '';

    /** @var string[] user ids */
    public array $teamUserIds = [];

    abstract protected function authorizePicking(): void;

    public function chooseLead(string $userId): void
    {
        $this->authorizePicking();
        if ($this->activeStaff()->whereKey($userId)->exists()) {
            $this->leadUserId = $userId;
            $this->teamUserIds = array_values(array_diff($this->teamUserIds, [$userId]));
        }
        $this->leadSearch = '';
        $this->resetErrorBag('leadUserId');
    }

    public function clearLead(): void
    {
        $this->authorizePicking();
        $this->leadUserId = '';
        $this->leadSearch = '';
    }

    public function addMember(string $userId): void
    {
        $this->authorizePicking();
        if ($userId !== $this->leadUserId && ! in_array($userId, $this->teamUserIds, true) && $this->activeStaff()->whereKey($userId)->exists()) {
            $this->teamUserIds[] = $userId;
        }
        $this->teamSearch = '';
        $this->resetErrorBag('teamUserIds');
    }

    public function removeMember(string $userId): void
    {
        $this->authorizePicking();
        $this->teamUserIds = array_values(array_diff($this->teamUserIds, [$userId]));
    }

    protected function activeStaff()
    {
        return User::query()->where('status', 'Active')->whereNull('deleted_at');
    }

    /** What the two pickers show: options for the typed term, and what is chosen */
    protected function staffPickers(): array
    {
        $item = fn (User $u) => ['id' => $u->id, 'label' => trim($u->first_name . ' ' . $u->last_name), 'meta' => $u->job_title, 'user' => $u];
        $match = function (string $term, array $exclude) use ($item): Collection {
            $term = trim($term);

            return $this->activeStaff()
                ->when($exclude, fn ($q) => $q->whereNotIn('id', $exclude))
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%' . addcslashes($term, '%_\\') . '%';
                    $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])->orWhere('email', 'like', $like));
                })
                ->orderBy('first_name')->orderBy('last_name')->limit(30)
                ->get(['id', 'first_name', 'last_name', 'avatar_path', 'job_title'])->map($item)->values();
        };
        $chosen = fn (array $ids) => $ids ? $this->activeStaff()->whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'avatar_path', 'job_title'])
            ->sortBy(fn ($u) => array_search($u->id, $ids, true))->map($item)->values() : collect();

        $lead = $this->leadUserId !== '' ? $chosen([$this->leadUserId])->first() : null;
        $team = $chosen($this->teamUserIds);
        // Someone who stopped being active drops out of what is saved next
        $this->teamUserIds = $team->pluck('id')->all();

        return [
            'leadOptions' => $match($this->leadSearch, []),
            'leadSelected' => $lead,
            'teamOptions' => $match($this->teamSearch, array_filter([$this->leadUserId, ...$this->teamUserIds])),
            'teamSelected' => $team,
        ];
    }
}
