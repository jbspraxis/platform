<?php

namespace App\Livewire\Hq\Campaigns;

use App\Models\Campaigns\Segment;
use App\Models\Campaigns\Subscriber;
use App\Support\Campaigns\Audience;
use App\Support\Campaigns\Marketing;
use App\Support\MailLocale;
use App\Support\WebsiteSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HQ › Campaigns › Audiences (docs/CAMPAIGNS.md 4.2): saved segments with their live counts, the subscriber list
 * (people who asked to hear from us, added here until the website has a sign-up form) and the do-not-email list that
 * Broadcasts and Outreach both respect.
 */
#[Layout('components.layouts.hq')]
class Audiences extends Component
{
    use BuildsSegments, WithPagination;

    #[Url(as: 'tab', except: 'segments')]
    public string $tab = 'segments';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Locked]
    public bool $canCreate = false;

    #[Locked]
    public bool $canApprove = false;

    // Add subscribers
    public bool $adding = false;

    public string $addLines = '';

    public string $addCountry = '';

    public string $addLanguage = '';

    public string $addConsent = '';

    // Add to the do-not-email list
    public bool $blocking = false;

    public string $blockEmail = '';

    public string $blockNote = '';

    public function mount(): void
    {
        $this->canCreate = Auth::user()->can('broadcasts.create');
        $this->canApprove = Auth::user()->can('broadcasts.approve');
        if (! in_array($this->tab, ['segments', 'subscribers', 'suppressed'], true)) {
            $this->tab = 'segments';
        }
    }

    public function updatingTab(): void
    {
        $this->resetPage();
        $this->search = '';
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    // ------------------------------------------------------------------ subscribers

    /**
     * One person per line: "ada@example.com", "Ada Okafor <ada@example.com>" or "ada@example.com, Ada, Okafor, Acme Ltd".
     * Addresses already on the list, or on the do-not-email list, are skipped and counted.
     */
    public function addSubscribers(): void
    {
        abort_unless($this->canCreate, 403);
        $this->addConsent = trim($this->addConsent);
        $this->validate([
            'addLines' => 'required|string|max:50000',
            'addConsent' => 'required|string|min:5|max:255',
            'addCountry' => 'nullable|string|max:100',
            'addLanguage' => 'nullable|in:en,fr',
        ], [
            'addLines.required' => 'Paste at least one email address.',
            'addConsent.required' => 'Say how these people agreed to hear from us, for example “Signed up at the Lagos breakfast, 20 Nov 2026”.',
            'addConsent.min' => 'Say how these people agreed to hear from us, for example “Signed up at the Lagos breakfast, 20 Nov 2026”.',
        ]);

        $added = $existing = $blocked = $invalid = 0;
        $now = now();
        foreach (preg_split('/\R/u', $this->addLines) as $line) {
            $person = self::parseLine($line);
            if ($person === null) {
                $invalid += trim($line) !== '' ? 1 : 0;
                continue;
            }
            if (! Marketing::canEmail($person['email'])) {
                $blocked++;
                continue;
            }
            $inserted = DB::table('marketing_subscribers')->insertOrIgnore($person + [
                'country' => $this->addCountry !== '' ? $this->addCountry : null,
                'language' => $this->addLanguage !== '' ? $this->addLanguage : null,
                'source' => 'hq', 'consent' => $this->addConsent, 'added_by' => Auth::id(), 'created_at' => $now, 'updated_at' => $now,
            ]);
            $inserted ? $added++ : $existing++;
        }

        $parts = ["{$added} " . ($added === 1 ? 'subscriber' : 'subscribers') . ' added'];
        if ($existing) {
            $parts[] = "{$existing} already on the list";
        }
        if ($blocked) {
            $parts[] = "{$blocked} skipped because they are on the do-not-email list";
        }
        if ($invalid) {
            $parts[] = "{$invalid} " . ($invalid === 1 ? 'line wasn’t' : 'lines weren’t') . ' an email address';
        }
        session()->flash($added ? 'success' : 'warning', implode('; ', $parts) . '.');
        $this->reset('adding', 'addLines', 'addCountry', 'addLanguage', 'addConsent');
    }

    public function removeSubscriber(int $id): void
    {
        abort_unless($this->canCreate, 403);
        $subscriber = Subscriber::find($id);
        if ($subscriber) {
            $subscriber->delete();
            session()->flash('success', "{$subscriber->email} removed from the subscriber list. To make sure we never email them again, add the address to the do-not-email list.");
        }
    }

    // ------------------------------------------------------------------ do not email

    public function block(): void
    {
        abort_unless($this->canCreate, 403);
        $this->blockEmail = Marketing::normalize($this->blockEmail);
        $this->validate(['blockEmail' => 'required|email|max:190', 'blockNote' => 'nullable|string|max:255'], [
            'blockEmail.required' => 'Enter the email address.', 'blockEmail.email' => 'Enter a valid email address, such as name@example.com.',
        ]);
        $added = Marketing::suppress($this->blockEmail, 'manual', ['source' => 'hq', 'note' => trim($this->blockNote) ?: null, 'by' => Auth::id()]);
        session()->flash($added ? 'success' : 'info', $added
            ? "{$this->blockEmail} will get no more marketing from Broadcasts or Outreach."
            : "{$this->blockEmail} is already on the do-not-email list.");
        $this->reset('blocking', 'blockEmail', 'blockNote');
    }

    public function unblock(int $id): void
    {
        abort_unless($this->canApprove, 403);
        $entry = DB::table('marketing_suppressions')->where('id', $id)->first();
        if (! $entry) {
            return;
        }
        if (Marketing::lift($entry->email)) {
            session()->flash('success', "{$entry->email} can receive marketing again.");
        } else {
            session()->flash('error', "{$entry->email} unsubscribed or reported an email as spam, so only they can change that. HQ can’t take the address off the list.");
        }
    }

    /** "Ada Okafor <ada@example.com>", "ada@example.com, Ada, Okafor, Acme Ltd" or a bare address; null if no address */
    public static function parseLine(string $line): ?array
    {
        $line = trim($line, " \t,;");
        if ($line === '') {
            return null;
        }
        $first = $last = $org = null;
        if (preg_match('/^"?([^"<]*?)"?\s*<([^<>\s]+)>\s*$/u', $line, $m)) {
            $email = $m[2];
            $names = preg_split('/\s+/u', trim($m[1]), 2);
            [$first, $last] = [$names[0] ?? null, $names[1] ?? null];
        } else {
            $parts = array_map('trim', preg_split('/[,;\t]/u', $line));
            $email = array_shift($parts);
            [$first, $last, $org] = array_pad($parts, 3, null);
        }
        if (! Marketing::isValid($email)) {
            return null;
        }
        $clip = fn ($v, $n) => filled($v) ? mb_substr(trim($v), 0, $n) : null;

        return ['email' => Marketing::normalize($email), 'first_name' => $clip($first, 60), 'last_name' => $clip($last, 60), 'organisation' => $clip($org, 150)];
    }

    public function render()
    {
        $search = trim($this->search);
        $data = ['countries' => WebsiteSettings::COUNTRIES];

        if ($this->tab === 'segments') {
            $data['segments'] = Segment::orderBy('name')->get()->map(fn ($s) => ['segment' => $s, 'summary' => Audience::summary($s->rules)]);
        } elseif ($this->tab === 'subscribers') {
            $data['subscribers'] = Subscriber::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")->orWhere('organisation', 'like', "%{$search}%")))
                ->orderByRaw('unsubscribed_at IS NOT NULL')->orderByDesc('created_at')->paginate(50);
            $data['kaizenWriters'] = DB::table('client_users')->where('lifecycle_stage', 'Public Contributor')->whereNull('deleted_at')->count();
        } else {
            $data['entries'] = DB::table('marketing_suppressions')
                ->when($search !== '', fn ($q) => $q->where('email', 'like', "%{$search}%"))
                ->orderByDesc('created_at')->paginate(50);
        }

        return view('livewire.hq.campaigns.audiences', $data + [
            'totals' => [
                'segments' => Segment::count(),
                'subscribers' => Subscriber::whereNull('unsubscribed_at')->count(),
                'suppressed' => DB::table('marketing_suppressions')->count(),
            ],
            'languageOf' => fn ($s) => MailLocale::forContact($s->language, null, $s->country),
        ] + $this->segmentView())->title('Audiences');
    }
}
