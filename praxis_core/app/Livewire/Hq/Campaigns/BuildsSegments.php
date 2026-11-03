<?php

namespace App\Livewire\Hq\Campaigns;

use App\Models\Campaigns\Broadcast;
use App\Models\Campaigns\Segment;
use App\Support\Campaigns\Audience;
use App\Support\Campaigns\SegmentFilters;
use Illuminate\Support\Facades\Auth;

/**
 * The segment builder (docs/CAMPAIGNS.md 4.2), shared by the broadcast editor and Audiences: who (clients, leads,
 * subscribers) and the filters, with a live count while it is open. The partial is
 * livewire/hq/campaigns/partials/segment-builder.
 */
trait BuildsSegments
{
    public bool $segmentOpen = false;

    public ?string $segmentEditing = null;

    public string $segmentName = '';

    public array $segmentWho = ['clients', 'leads'];

    public array $segmentFilters = [];

    public function newSegment(): void
    {
        $this->authorizeSegments();
        $this->resetErrorBag();
        $this->segmentEditing = null;
        $this->segmentName = '';
        $this->segmentWho = ['clients', 'leads'];
        $this->segmentFilters = $this->blankFilters();
        $this->segmentOpen = true;
    }

    public function editSegment(string $id): void
    {
        $this->authorizeSegments();
        $segment = Segment::findOrFail($id);
        $rules = Audience::rules($segment->rules);
        $this->resetErrorBag();
        $this->segmentEditing = $segment->id;
        $this->segmentName = $segment->name;
        $this->segmentWho = $rules['who'];
        $this->segmentFilters = $rules['filters'] + $this->blankFilters();
        $this->segmentOpen = true;
    }

    public function closeSegment(): void
    {
        $this->segmentOpen = false;
        $this->resetErrorBag(['segmentName', 'segmentWho']);
    }

    public function clearSegmentFilter(string $key): void
    {
        $this->segmentFilters[$key] = [];
    }

    /** Every filter as an empty list: a checkbox bound to a missing key would read as one yes/no for all its options */
    private function blankFilters(): array
    {
        return array_fill_keys(array_keys(SegmentFilters::definitions()), []);
    }

    public function saveSegment(): void
    {
        $this->storeSegment();
    }

    /** Saves the builder's segment; null when it doesn't pass validation (the errors are on the form) */
    protected function storeSegment(): ?Segment
    {
        $this->authorizeSegments();
        $this->segmentName = trim($this->segmentName);
        $this->validate([
            'segmentName' => 'required|string|max:120',
            'segmentWho' => 'required|array|min:1',
        ], [
            'segmentName.required' => 'Name the segment, for example “Clients in Cameroon”.',
            'segmentWho.required' => 'Choose who is in it: clients, leads or subscribers.',
            'segmentWho.min' => 'Choose who is in it: clients, leads or subscribers.',
        ]);

        $rules = Audience::rules(['who' => $this->segmentWho, 'filters' => $this->segmentFilters]);
        $by = Auth::id();
        $segment = $this->segmentEditing ? Segment::findOrFail($this->segmentEditing) : new Segment(['created_by' => $by]);
        $segment->fill(['name' => $this->segmentName, 'rules' => $rules, 'updated_by' => $by])->save();

        $this->segmentOpen = false;
        session()->flash('success', $this->segmentEditing ? "Segment “{$segment->name}” saved." : "Segment “{$segment->name}” created.");
        $this->segmentEditing = null;

        return $segment;
    }

    public function deleteSegment(string $id): void
    {
        $this->authorizeSegments();
        $segment = Segment::findOrFail($id);
        $waiting = Broadcast::where('segment_id', $segment->id)->where('status', 'Scheduled')->count();
        if ($waiting > 0) {
            session()->flash('error', "We couldn’t delete “{$segment->name}” because a scheduled broadcast goes to it. Unschedule the broadcast or choose another segment for it first.");

            return;
        }
        Broadcast::where('segment_id', $segment->id)->where('status', 'Draft')->update(['segment_id' => null]);
        $segment->delete();
        session()->flash('success', "Segment “{$segment->name}” deleted. Drafts that used it need a new segment.");
    }

    /** What the builder shows while it is open: the filters with their options, and the live numbers */
    protected function segmentView(): array
    {
        if (! $this->segmentOpen) {
            return ['segmentDefinitions' => [], 'segmentSummary' => null];
        }
        $definitions = [];
        foreach (SegmentFilters::definitions() as $key => $definition) {
            $definitions[$key] = ['label' => $definition['label'], 'groups' => $definition['groups'], 'options' => SegmentFilters::options($key)];
        }

        return [
            'segmentDefinitions' => $definitions,
            'segmentSummary' => Audience::summary(['who' => $this->segmentWho, 'filters' => $this->segmentFilters]),
        ];
    }

    private function authorizeSegments(): void
    {
        abort_unless(Auth::user()?->can('broadcasts.create'), 403);
    }
}
