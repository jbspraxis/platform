<?php

namespace App\Livewire\Hq\Campaigns;

use App\Models\AiRun;
use App\Models\Campaigns\Broadcast;
use App\Models\Campaigns\Segment;
use App\Support\Ai\Ai;
use App\Support\Ai\AiUnavailable;
use App\Support\Campaigns\Audience;
use App\Support\Campaigns\Blocks;
use App\Support\Campaigns\Broadcasts;
use App\Support\Campaigns\Images;
use App\Support\Campaigns\Layouts;
use App\Support\Campaigns\Renderer;
use App\Support\Campaigns\RichText;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * One draft broadcast (docs/CAMPAIGNS.md 4.4): Write (layout, the English and French versions, blocks, AI), Audience
 * (a saved segment and its live count) and Send (problems, a test to yourself, Send now or Schedule). Every change is
 * saved as it is made. A scheduled broadcast opens read-only until it is unscheduled; one that has started opens its
 * report instead.
 */
#[Layout('components.layouts.hq')]
class BroadcastEditor extends Component
{
    use BuildsSegments, WithFileUploads;

    #[Locked]
    public string $broadcastId;

    #[Locked]
    public bool $canEdit = false;

    #[Locked]
    public bool $canSend = false;

    public string $step = 'write';

    public string $tab = 'en';

    public string $layout = Layouts::DEFAULT;

    public string $language = 'both';

    public bool $greeting = true;

    public array $content = [];

    public ?string $segmentId = null;

    // AI: a brief for a draft, or a translation of one version into the other
    public string $brief = '';

    public bool $aiOpen = false;

    #[Locked]
    public ?string $aiRunId = null;

    #[Locked]
    public ?string $aiKind = null;

    #[Locked]
    public ?string $aiTarget = null;

    // A picture for the image block named by uploadFor
    public $upload;

    public ?string $uploadFor = null;

    public string $scheduleDate = '';

    public string $scheduleTime = '09:00';

    public ?string $savedAt = null;

    // The preview is drawn once per visit to Write; after that the 'broadcast-preview' event keeps it current
    #[Locked]
    public bool $previewDrawn = false;

    public function mount(string $broadcast): void
    {
        $model = Broadcast::findOrFail($broadcast);
        $this->broadcastId = $model->id;
        $user = Auth::user();
        if (! in_array($model->status, ['Draft', 'Scheduled'], true) || $model->isLegacy()) {
            $this->redirectRoute('hq.campaigns.report', ['broadcast' => $model->id], navigate: true);

            return;
        }
        $this->broadcastId = $model->id;
        $this->canEdit = $user->can('broadcasts.create') && $model->status === 'Draft';
        $this->canSend = $user->can('broadcasts.approve');
        $this->layout = Layouts::has($model->layout) ? $model->layout : Layouts::DEFAULT;
        $this->language = array_key_exists($model->language, Broadcast::LANGUAGES) ? $model->language : 'both';
        $this->greeting = (bool) $model->greeting;
        $this->segmentId = $model->segment_id;
        foreach (['en', 'fr'] as $locale) {
            $this->content[$locale] = Broadcasts::cleanVersion($model->version($locale) ?: Layouts::starter($this->layout, $locale));
        }
        $this->tab = $model->locales()[0];
        $this->scheduleDate = now('Africa/Lagos')->addDay()->format('Y-m-d');
        if (in_array(request()->query('step'), ['write', 'audience', 'send'], true)) {
            $this->step = request()->query('step');
        }
    }

    // ------------------------------------------------------------------ writing

    public function updated(string $property): void
    {
        if ($property === 'step') {
            $this->previewDrawn = false;
        }
        if (str_starts_with($property, 'content.') || in_array($property, ['language', 'greeting', 'segmentId'], true)) {
            if ($property === 'language' && ! in_array($this->tab, $this->locales(), true)) {
                $this->tab = $this->locales()[0];
            }
            $this->save();
        }
        if (in_array($property, ['tab', 'language', 'greeting'], true) || str_starts_with($property, 'content.')) {
            $this->refreshPreview();
        }
    }

    /** A text block's words, from the editor (by block id, so moving blocks can't mix them up) */
    public function setText(string $locale, string $blockId, string $html): void
    {
        if (! $this->editable() || ! isset($this->content[$locale])) {
            return;
        }
        foreach ($this->content[$locale]['blocks'] as $n => $block) {
            if ($block['id'] === $blockId && $block['type'] === 'text') {
                $this->content[$locale]['blocks'][$n]['html'] = RichText::clean(mb_substr($html, 0, 20000));
                $this->save();
                $this->refreshPreview();

                return;
            }
        }
    }

    public function addBlock(string $type, ?int $after = null): void
    {
        if (! $this->editable() || ! isset(Blocks::TYPES[$type])) {
            return;
        }
        $blocks = $this->content[$this->tab]['blocks'];
        if (count($blocks) >= Blocks::MAX_BLOCKS) {
            session()->flash('error', 'A broadcast holds ' . Blocks::MAX_BLOCKS . ' blocks at most. Remove one to add another.');

            return;
        }
        $position = $after === null ? count($blocks) : min(count($blocks), max(0, $after + 1));
        array_splice($blocks, $position, 0, [Blocks::blank($type, $this->tab)]);
        $this->content[$this->tab]['blocks'] = $blocks;
        $this->save();
        $this->refreshPreview();
    }

    public function removeBlock(int $index): void
    {
        if (! $this->editable() || ! isset($this->content[$this->tab]['blocks'][$index])) {
            return;
        }
        array_splice($this->content[$this->tab]['blocks'], $index, 1);
        $this->save();
        $this->refreshPreview();
    }

    public function moveBlock(int $index, int $by): void
    {
        $blocks = $this->content[$this->tab]['blocks'];
        $to = $index + $by;
        if (! $this->editable() || ! isset($blocks[$index], $blocks[$to])) {
            return;
        }
        [$blocks[$index], $blocks[$to]] = [$blocks[$to], $blocks[$index]];
        $this->content[$this->tab]['blocks'] = $blocks;
        $this->save();
        $this->refreshPreview();
    }

    public function addRow(int $index): void
    {
        $block = $this->content[$this->tab]['blocks'][$index] ?? null;
        if (! $this->editable() || ! $block) {
            return;
        }
        if ($block['type'] === 'figures' && count($block['items']) < 3) {
            $this->content[$this->tab]['blocks'][$index]['items'][] = ['value' => '', 'label' => ''];
        } elseif ($block['type'] === 'details' && count($block['rows']) < 6) {
            $this->content[$this->tab]['blocks'][$index]['rows'][] = ['label' => '', 'value' => ''];
        }
        $this->save();
    }

    public function removeRow(int $index, int $row): void
    {
        $block = $this->content[$this->tab]['blocks'][$index] ?? null;
        $field = ($block['type'] ?? '') === 'figures' ? 'items' : 'rows';
        if (! $this->editable() || ! $block || count($block[$field] ?? []) <= 1) {
            return;
        }
        array_splice($this->content[$this->tab]['blocks'][$index][$field], $row, 1);
        $this->save();
        $this->refreshPreview();
    }

    /** Another layout: an untouched version takes the new layout's blocks; written words are kept */
    public function setLayout(string $layout): void
    {
        if (! $this->editable() || ! Layouts::has($layout)) {
            return;
        }
        foreach (['en', 'fr'] as $locale) {
            if ($this->isUntouched($locale)) {
                $this->content[$locale] = ['subject' => $this->content[$locale]['subject'], 'preheader' => $this->content[$locale]['preheader'],
                    'headline' => $this->content[$locale]['headline'], 'blocks' => Layouts::starter($layout, $locale)['blocks']];
            }
        }
        $this->layout = $layout;
        if ($layout === 'insight') {
            $this->greeting = false; // a newsletter opens with its subject, not "Hello Ada,"
        }
        $this->save();
        $this->refreshPreview();
    }

    /** One version copied over the other, to edit by hand */
    public function copyVersion(string $from, string $to): void
    {
        if (! $this->editable() || ! in_array($from, ['en', 'fr'], true) || ! in_array($to, ['en', 'fr'], true) || $from === $to) {
            return;
        }
        $version = $this->content[$from];
        $version['blocks'] = array_map(fn ($b) => ['id' => Blocks::newId()] + $b, $version['blocks']);
        $this->content[$to] = Broadcasts::cleanVersion($version);
        $this->tab = $to;
        $this->save();
        $this->refreshPreview();
        session()->flash('success', ($to === 'fr' ? 'The French' : 'The English') . ' version now has the same text. Rewrite it in ' . ($to === 'fr' ? 'French.' : 'English.'));
    }

    public function updatedUpload(): void
    {
        if (! $this->editable() || ! $this->uploadFor) {
            return;
        }
        $this->validate(['upload' => 'required|image|mimes:jpg,jpeg,png|max:' . Images::MAX_KB], [
            'upload.image' => 'Choose a picture: a JPG or PNG file.',
            'upload.mimes' => 'Choose a JPG or PNG picture: inboxes don’t all show other formats.',
            'upload.max' => 'Choose a picture under 2 MB. A photo exported 1200 pixels wide is plenty for an email.',
        ]);
        $url = Images::store($this->upload);
        foreach ($this->content[$this->tab]['blocks'] as $n => $block) {
            if ($block['id'] === $this->uploadFor) {
                $this->content[$this->tab]['blocks'][$n]['src'] = $url;
            }
        }
        $this->reset('upload', 'uploadFor');
        $this->save();
        $this->refreshPreview();
    }

    // ------------------------------------------------------------------ AI

    public function writeWithAi(): void
    {
        if (! $this->editable()) {
            return;
        }
        $this->brief = trim($this->brief);
        $this->validate(['brief' => 'required|string|min:15|max:3000'], [
            'brief.required' => 'Write a few lines about what the email should say.',
            'brief.min' => 'Write a little more: what is it about, who is it for, and what should people do?',
        ]);
        $layout = Layouts::get($this->layout);
        $this->startAi('broadcast_draft', $this->tab, [
            'language' => $this->tab,
            'instructions' => $this->brief,
            'material' => "LAYOUT: {$layout['label']}. {$layout['ai']}\nTHE BLOCKS IT USUALLY HAS: " . implode(', ', $layout['blocks']) . '.',
        ]);
    }

    public function translateFrom(string $from): void
    {
        $to = $from === 'en' ? 'fr' : 'en';
        if (! $this->editable() || ! in_array($from, ['en', 'fr'], true)) {
            return;
        }
        $source = $this->content[$from];
        $written = trim($source['subject']) !== '' || collect($source['blocks'])->contains(fn ($b) => $b['type'] === 'text' && ! RichText::isBlank($b['html']));
        if (! $written) {
            session()->flash('error', 'Write the ' . ($from === 'en' ? 'English' : 'French') . ' version first, then translate it.');

            return;
        }
        $this->startAi('broadcast_translate', $to, [
            'language' => $to,
            'material' => json_encode([
                'subject' => $source['subject'], 'preheader' => $source['preheader'], 'headline' => $source['headline'],
                'blocks' => Blocks::forTranslation($source['blocks']),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ]);
    }

    /** Polled every 2 s while a draft or a translation runs: nothing is redrawn until it is done, then it is applied */
    public function checkAi(): void
    {
        if (! $this->applyAi()) {
            $this->skipRender();
        }
    }

    /** Applies the finished draft or translation; false while it still runs (or when there is none) */
    private function applyAi(): bool
    {
        if (! $this->aiRunId) {
            return false;
        }
        // The status alone while it runs; the row (input holds the whole brief) only once it has finished
        $status = AiRun::whereKey($this->aiRunId)->value('status');
        if ($status !== null && ! in_array($status, ['done', 'failed'], true)) {
            return false;
        }
        $run = $status === null ? null : AiRun::whereKey($this->aiRunId)->first(['id', 'status', 'output', 'error']);
        $target = $this->aiTarget;
        $kind = $this->aiKind;
        $this->aiRunId = $this->aiKind = $this->aiTarget = null;

        if (! $run || ! $run->succeeded() || ! $this->editable()) {
            session()->flash('error', (string) ($run?->errorMessage() ?: __('ai.errors.failed')));

            return true;
        }
        $out = (array) $run->output;
        $buttonUrl = collect($this->content[$target]['blocks'])->firstWhere('type', 'button')['url'] ?? null;
        $this->content[$target] = Broadcasts::cleanVersion([
            'subject' => $out['subject'] ?? '',
            'preheader' => $out['preheader'] ?? '',
            'headline' => $out['headline'] ?? '',
            'blocks' => $kind === 'translate'
                ? Blocks::translated($this->content[$target === 'fr' ? 'en' : 'fr']['blocks'], (array) ($out['blocks'] ?? []))
                : Blocks::fromAi((array) ($out['blocks'] ?? []), $target, $buttonUrl),
        ]);
        $this->tab = $target;
        $this->aiOpen = false;
        $this->save();
        $this->refreshPreview();
        session()->flash('success', $kind === 'translate'
            ? ($target === 'fr' ? 'French' : 'English') . ' version translated. Read it through and adjust it before you send it.'
            : 'Draft written. Read it through, check every fact and figure, and adjust it before you send it.');

        return true;
    }

    private function startAi(string $purpose, string $target, array $input): void
    {
        try {
            $run = Ai::queue($purpose, ['type' => 'broadcast', 'id' => $this->broadcastId], $input);
        } catch (AiUnavailable $e) {
            session()->flash('error', $e->getMessage());

            return;
        }
        $this->aiRunId = $run->id;
        $this->aiKind = $purpose === 'broadcast_translate' ? 'translate' : 'draft';
        $this->aiTarget = $target;
        $this->applyAi(); // on the sync queue (tests) it has already finished
    }

    // ------------------------------------------------------------------ test and send

    public function sendTest(): void
    {
        abort_unless(Auth::user()->can('broadcasts.create'), 403);
        $key = 'broadcast-test:' . Auth::id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            session()->flash('error', 'You’ve sent 10 tests in the last hour. Try again in ' . ceil(RateLimiter::availableIn($key) / 60) . ' minutes.');

            return;
        }
        $broadcast = $this->fresh();
        $problems = collect($broadcast->locales())->flatMap(fn ($l) => Blocks::problems(Broadcasts::cleanVersion($broadcast->version($l))))->all();
        if ($problems) {
            session()->flash('error', 'Fix these first, then send the test: ' . implode(' ', $problems));

            return;
        }
        RateLimiter::hit($key, 3600);
        try {
            $sent = Broadcasts::sendTest($broadcast, Auth::user());
        } catch (Throwable $e) {
            report($e);
            session()->flash('error', 'We couldn’t send the test because the mail server didn’t accept it. Try again in a minute; if it keeps happening, check System health.');

            return;
        }
        $what = count($sent) > 1 ? 'The English and French versions are' : 'The test is';
        session()->flash('success', "{$what} on the way to " . Auth::user()->email . '. Links in a test are not counted.');
    }

    public function sendNow(): void
    {
        $broadcast = $this->readyToSend();
        if (! $broadcast) {
            return;
        }
        $count = Broadcasts::sendNow($broadcast, Auth::user());
        session()->flash($count ? 'success' : 'error', $count
            ? "Sending to {$count} " . ($count === 1 ? 'person' : 'people') . '. The report fills in as emails go out.'
            : 'Nobody in this segment can receive it, so nothing was sent.');
        $this->redirectRoute('hq.campaigns.report', ['broadcast' => $broadcast->id], navigate: true);
    }

    public function schedule(): void
    {
        $broadcast = $this->readyToSend();
        if (! $broadcast) {
            return;
        }
        $this->validate(['scheduleDate' => 'required|date_format:Y-m-d', 'scheduleTime' => 'required|date_format:H:i'], [
            'scheduleDate.required' => 'Choose the day.', 'scheduleDate.date_format' => 'Choose the day.',
            'scheduleTime.required' => 'Choose the time.', 'scheduleTime.date_format' => 'Choose the time.',
        ]);
        $at = Carbon::createFromFormat('Y-m-d H:i', "{$this->scheduleDate} {$this->scheduleTime}", 'Africa/Lagos');
        if ($at->lte(now()->addMinutes(5))) {
            $this->addError('scheduleDate', 'Choose a time at least 5 minutes from now, or use Send now.');

            return;
        }
        if ($at->gt(now()->addDays(90))) {
            $this->addError('scheduleDate', 'Choose a day within the next 90 days.');

            return;
        }
        Broadcasts::schedule($broadcast, $at, Auth::user());
        session()->flash('success', 'Scheduled for ' . Broadcasts::wat($at) . '. The audience is worked out when it starts, so people who unsubscribe before then are left out.');
        $this->redirectRoute('hq.campaigns.broadcasts', navigate: true);
    }

    public function unschedule(): void
    {
        abort_unless($this->canSend, 403);
        session()->flash(...(Broadcasts::unschedule($this->fresh(), Auth::user())
            ? ['success', 'Unscheduled: it is a draft again, and nothing has been sent.']
            : ['error', 'It has already started sending, so it can’t be unscheduled. Its report shows where it stands.']));
        $this->redirectRoute('hq.campaigns.broadcast', ['broadcast' => $this->broadcastId], navigate: true);
    }

    private function readyToSend(): ?Broadcast
    {
        abort_unless($this->canSend, 403);
        $broadcast = $this->fresh();
        if ($broadcast->status !== 'Draft') {
            session()->flash('error', 'This broadcast has already been sent or scheduled.');

            return null;
        }
        if ($problems = Broadcasts::problems($broadcast)) {
            session()->flash('error', 'It isn’t ready yet: ' . implode(' ', $problems));
            $this->step = 'send';

            return null;
        }

        return $broadcast;
    }

    // ------------------------------------------------------------------ segments

    public function useSegment(string $id): void
    {
        if (! $this->editable() || ! Segment::whereKey($id)->exists()) {
            return;
        }
        $this->segmentId = $id;
        $this->save();
    }

    public function saveSegmentAndUse(): void
    {
        $segment = $this->storeSegment();
        if ($segment && $this->editable()) {
            $this->segmentId = $segment->id;
            $this->save();
        }
    }

    // ------------------------------------------------------------------ helpers

    private function editable(): bool
    {
        return $this->canEdit && $this->fresh()->status === 'Draft';
    }

    private function fresh(): Broadcast
    {
        return Broadcast::findOrFail($this->broadcastId);
    }

    private function locales(): array
    {
        return in_array($this->language, ['en', 'fr'], true) ? [$this->language] : ['en', 'fr'];
    }

    /** Nothing written in this version yet (only empty blocks), so a new layout may replace its blocks */
    private function isUntouched(string $locale): bool
    {
        return collect($this->content[$locale]['blocks'])->every(fn ($b) => match ($b['type']) {
            'text' => RichText::isBlank($b['html']),
            'heading', 'quote' => $b['text'] === '',
            'image' => $b['src'] === '',
            'figures' => collect($b['items'])->every(fn ($i) => $i['value'] === ''),
            'details' => collect($b['rows'])->every(fn ($r) => $r['value'] === ''),
            default => true,
        });
    }

    private function save(): void
    {
        if (! $this->editable()) {
            return;
        }
        $this->language = array_key_exists($this->language, Broadcast::LANGUAGES) ? $this->language : 'both';
        // The cleaned versions are stored; the fields keep what the person typed, so a trailing space mid-sentence survives
        $clean = [];
        foreach (['en', 'fr'] as $locale) {
            $clean[$locale] = Broadcasts::cleanVersion($this->content[$locale] ?? []);
        }
        Broadcast::whereKey($this->broadcastId)->update([
            'layout' => $this->layout,
            'language' => $this->language,
            'greeting' => $this->greeting,
            'content' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'subject' => $clean[$this->locales()[0]]['subject'],
            'segment_id' => $this->segmentId && Segment::whereKey($this->segmentId)->exists() ? $this->segmentId : null,
            'updated_by' => Auth::id(),
            'updated_at' => now(),
        ]);
        $this->savedAt = now('Africa/Lagos')->format('H:i');
    }

    private function refreshPreview(): void
    {
        $this->dispatch('broadcast-preview', html: $this->previewHtml());
    }

    private function previewHtml(): string
    {
        try {
            return Renderer::preview($this->fresh(), $this->tab);
        } catch (Throwable $e) {
            report($e);

            return '<p style="font-family: Arial, sans-serif; padding: 24px; color: #5E6679;">The preview couldn’t be drawn. Reload the page; your changes are saved.</p>';
        }
    }

    public function render()
    {
        $broadcast = $this->fresh();
        $drawPreview = $this->step === 'write' && ! $this->previewDrawn;
        $this->previewDrawn = $this->step === 'write';
        $segment = $this->segmentId ? Segment::find($this->segmentId) : null;
        // Audience: the numbers and the first 25 people from one pass over the segment; Send: the numbers
        $audience = $segment && $this->step === 'audience' ? Audience::preview($segment->rules, 25) : null;

        return view('livewire.hq.campaigns.broadcast-editor', [
            'broadcast' => $broadcast,
            'version' => $this->content[$this->tab],
            'previewHtml' => $drawPreview ? $this->previewHtml() : '',
            'segments' => $this->step === 'audience' ? Segment::orderBy('name')->get() : collect(),
            'segment' => $segment,
            'summary' => $audience['summary'] ?? ($segment && $this->step === 'send' ? Audience::summary($segment->rules) : null),
            'recipients' => $audience['recipients'] ?? collect(),
            'problems' => $this->step === 'send' ? Broadcasts::problems($broadcast) : [],
        ] + $this->segmentView())->title($broadcast->title() . ' · Broadcasts');
    }
}
