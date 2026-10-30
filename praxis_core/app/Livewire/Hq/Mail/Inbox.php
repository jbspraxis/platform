<?php

namespace App\Livewire\Hq\Mail;

use App\Models\Mail\MailAccount;
use App\Models\Mail\MailFolder;
use App\Models\Mail\MailMessage;
use App\Models\Mail\MailThread;
use App\Models\User;
use App\Support\Mail\Imap\ImapException;
use App\Support\Mail\MailAccess;
use App\Support\Mail\MailActions;
use App\Support\Mail\MailFetch;
use App\Support\Mail\MailNav;
use App\Support\Mail\Read\HtmlSanitizer;
use App\Support\Mail\Read\ImageTrust;
use App\Support\Mail\Read\TextFormatter;
use App\Support\Mail\Read\ThreadList;
use App\Support\Mail\Sync\MailSync;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * HQ → Mail (docs/MAIL.md, PR 2): the mailbox switcher and folders, the conversation list (badges, filters,
 * pages, search), and the reader (safe HTML, pictures held back, attachments, "Opened by"), with the toolbar
 * actions, older mail read from the server, Refresh, the phone layout and keyboard shortcuts.
 *
 * Links into Mail use ?box=&folder=&thread= (6.1). The view keeps PR 3's and PR 4's mount points (6.2) and
 * the page raises and answers the events in 6.3. Everything goes through MailAccess, MailActions and MailFetch.
 */
#[Layout('components.layouts.hq')]
#[Title('Mail')]
class Inbox extends Component
{
    public const TZ = 'Africa/Lagos';

    #[Url(as: 'box')]
    public ?string $box = null;

    /** A role (inbox, sent, drafts, archive, spam, trash) or a folder id */
    #[Url(as: 'folder')]
    public ?string $folder = null;

    #[Url(as: 'thread')]
    public ?int $thread = null;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'show', except: 'all')]
    public string $filter = 'all';

    public int $page = 1;

    /** Older mail or a server search, read live and not stored: mode (older|server), folder, query, items, next */
    #[Locked]
    public array $live = [];

    /** The live message open in the reader (its UID in $live['folder']) */
    #[Locked]
    public ?int $liveUid = null;

    /** Messages open (not folded) in the reader */
    #[Locked]
    public array $expanded = [];

    /** Messages whose remote pictures the person chose to load */
    #[Locked]
    public array $images = [];

    /** The last move, for Undo: ids and the folder they came from */
    #[Locked]
    public ?array $undo = null;

    #[Locked]
    public ?string $flash = null;

    #[Locked]
    public string $flashKind = 'success';

    /** Which confirmation is open: empty (Trash or Spam), delete (for good) */
    #[Locked]
    public ?string $confirm = null;

    public function mount(): void
    {
        abort_unless(MailAccess::canUse($this->user()), 403);
        $this->filter = in_array($this->filter, ThreadList::FILTERS, true) ? $this->filter : 'all';

        // A link to a conversation opens its mailbox, whatever box= says
        if ($this->thread) {
            $thread = MailThread::find($this->thread);
            if ($thread && MailAccess::canThread($this->user(), $thread, 'read')) {
                $this->box = $thread->account_id;
            } else {
                $this->thread = null;
                $this->notice('That conversation is no longer in a mailbox you can open.', 'error');
            }
        }

        if ($this->box && ! $this->accounts->firstWhere('id', $this->box)) {
            $this->flash ??= 'You no longer have access to that mailbox.';
            $this->flashKind = 'error';
            $this->box = null;
        }
        $this->box ??= $this->accounts->first()?->id;

        if ($this->thread) {
            $this->showThread($this->thread);
        }
    }

    // ------------------------------------------------------------------ navigation

    public function selectBox(string $id): void
    {
        abort_unless($this->accounts->firstWhere('id', $id), 403);
        $this->box = $id;
        $this->folder = null;
        $this->search = '';
        $this->filter = 'all';
        $this->resetView();
        unset($this->account, $this->currentFolder, $this->folders);
    }

    public function selectFolder(string $folder): void
    {
        $this->folder = $folder === 'inbox' ? null : $folder;
        $this->search = '';
        $this->resetView();
        unset($this->currentFolder);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ThreadList::FILTERS, true) ? $filter : 'all';
        $this->page = 1;
    }

    public function updatedSearch(): void
    {
        $this->search = mb_substr(trim($this->search), 0, 200);
        $this->page = 1;
        $this->live = [];
        $this->liveUid = null;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->updatedSearch();
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function openThread(int $id): void
    {
        $this->liveUid = null;
        $this->showThread($id);
    }

    public function closeThread(): void
    {
        $this->thread = null;
        $this->liveUid = null;
        $this->expanded = [];
    }

    /** Fold or unfold one message; unfolding an unread one opens it (read for everyone, owner decision 6) */
    public function toggleMessage(int $id): void
    {
        $message = $this->threadMessages()->firstWhere('id', $id);
        abort_unless($message, 404);
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }
        $this->expanded[] = $id;
        $this->markOpened(collect([$message]));
    }

    public function expandAll(): void
    {
        $messages = $this->threadMessages();
        $this->expanded = $messages->pluck('id')->all();
        $this->markOpened($messages);
    }

    // ------------------------------------------------------------------ pictures

    /** Load this message's remote pictures (a stored message by id; an older one as live:<uid>) */
    public function loadImages(int|string $key): void
    {
        abort_unless($this->imageSender($key) !== false, 404);
        $this->images[] = (string) $key;
    }

    public function trustSender(int|string $key): void
    {
        $sender = $this->imageSender($key);
        abort_unless(is_string($sender) && $sender !== '', 404);
        ImageTrust::trust($this->user(), $sender);
        $this->images[] = (string) $key;
        $this->notice('Pictures from ' . $sender . ' will always load for you.');
    }

    /** @return string|false|null the sender of the message behind an image key, false when it isn't on screen */
    private function imageSender(int|string $key): string|false|null
    {
        if (is_string($key) && str_starts_with($key, 'live:')) {
            $item = (int) substr($key, 5) === $this->liveUid ? collect($this->live['items'] ?? [])->firstWhere('uid', $this->liveUid) : null;

            return $item ? ($item['from_email'] ?? null) : false;
        }
        $message = $this->threadMessages()->firstWhere('id', (int) $key);

        return $message ? $message->from_email : false;
    }

    // ------------------------------------------------------------------ actions

    /**
     * Toolbar and keyboard actions on the open conversation: archive, spam, trash, inbox (restore / not spam),
     * read, unread, flag, unflag, delete (for good, Trash and Spam only; confirmed first), move:<folder id>.
     */
    public function act(string $action): void
    {
        $thread = $this->openThreadModel();
        if (! $thread) {
            return;
        }
        $user = $this->user();
        $scope = $this->scopeMessages($thread);
        $actions = MailActions::make();
        $from = $scope->first()?->folder_id;

        $latest = collect([$scope->sortBy('received_at')->last()]);
        $done = $this->attempt(fn () => match (true) {
            in_array($action, ['archive', 'spam', 'trash', 'inbox'], true) => $actions->moveToRole($user, $scope, $action),
            $action === 'read' => $actions->markRead($user, $scope, true),
            $action === 'unread' => $actions->markRead($user, $latest, false),
            $action === 'flag' => $actions->flag($user, $latest, true),
            $action === 'unflag' => $actions->flag($user, $thread->messages()->where('is_flagged', true)->get(), false),
            $action === 'delete' => $actions->deleteForever($user, $scope),
            str_starts_with($action, 'move:') => $actions->move($user, $scope,
                MailFolder::where('account_id', $this->box)->where('selectable', true)->findOrFail((int) substr($action, 5))),
            default => abort(422),
        });
        $this->confirm = null;
        if (! $done) {
            return;
        }

        MailNav::forget($user->id);
        $this->dispatch('mail-thread-changed', threadId: $thread->id);

        $moved = in_array($action, ['archive', 'spam', 'trash', 'inbox'], true) || str_starts_with($action, 'move:');
        $this->undo = $moved && $from ? ['ids' => $scope->pluck('id')->all(), 'folder' => $from] : null;
        $this->notice(match (true) {
            $action === 'archive' => 'Archived.',
            $action === 'spam' => 'Moved to Spam.',
            $action === 'trash' => 'Moved to Trash.',
            $action === 'inbox' => 'Moved to Inbox.',
            $action === 'read' => 'Marked as read.',
            $action === 'unread' => 'Marked as unread.',
            $action === 'flag' => 'Flagged.',
            $action === 'unflag' => 'Flag removed.',
            $action === 'delete' => 'Deleted for good.',
            default => 'Moved.',
        });

        if ($moved || $action === 'delete' || $action === 'unread') {
            // It has left this folder (or the person wants to come back to it later): back to the list
            $this->closeThread();
        }
    }

    public function undoMove(): void
    {
        $undo = $this->undo;
        $this->undo = null;
        if (! $undo) {
            return;
        }
        $folder = MailFolder::where('account_id', $this->box)->find($undo['folder']);
        $messages = MailMessage::whereIn('id', $undo['ids'])->where('account_id', $this->box)->get();
        if (! $folder || $messages->isEmpty()) {
            $this->notice('That can no longer be undone.', 'error');

            return;
        }
        if ($this->attempt(fn () => MailActions::make()->move($this->user(), $messages, $folder))) {
            MailNav::forget($this->user()->id);
            $this->notice('Moved back to ' . $folder->label() . '.');
        }
    }

    public function askConfirm(string $what): void
    {
        $this->confirm = in_array($what, ['empty', 'delete'], true) ? $what : null;
    }

    public function emptyFolder(): void
    {
        $folder = $this->currentFolder;
        $this->confirm = null;
        if (! $folder || ! in_array($folder->role, ['trash', 'spam'], true)) {
            return;
        }
        if ($this->attempt(fn () => MailActions::make()->emptyFolder($this->user(), $folder))) {
            MailNav::forget($this->user()->id);
            $this->closeThread();
            $this->notice($folder->label() . ' is empty.');
        }
    }

    /** Refresh: ask the mail server for new mail now (the scheduler does it every minute anyway) */
    public function refreshMailbox(): void
    {
        $account = $this->account;
        if (! $account) {
            return;
        }
        // A fresh row: the listed one carries access_level, which isn't a column (sync saves the account)
        $result = MailSync::make()->account(MailAccount::findOrFail($account->id), 20);
        MailNav::forget($this->user()->id);
        match ($result['status']) {
            'busy' => $this->notice('This mailbox is updating already. New mail appears in a moment.'),
            'skipped' => $this->notice('This mailbox needs its password again. A manager can enter it on Mailboxes.', 'error'),
            'error' => $this->notice("The mail server didn't answer. Try again in a minute.", 'error'),
            default => $this->notice($result['added'] ? "Up to date: {$result['added']} new." : 'Up to date.'),
        };
    }

    /** wire:poll: counts and the list follow the scheduler's copy */
    public function tick(): void
    {
    }

    public function dismissFlash(): void
    {
        $this->flash = null;
        $this->undo = null;
    }

    // ------------------------------------------------------------------ older mail and server search

    /** Mail older than HQ's copy (12 months), a page at a time from the server */
    public function loadOlder(): void
    {
        $folder = $this->currentFolder;
        if (! $folder) {
            return;
        }
        $continue = ($this->live['mode'] ?? null) === 'older' && ($this->live['folder'] ?? null) === $folder->id;
        $before = $continue ? ($this->live['next'] ?? null) : null;
        if ($continue && $before === null) {
            return;
        }
        $page = $this->attempt(fn () => MailFetch::make()->older($this->user(), $folder, $before, 30));
        if ($page === false) {
            return;
        }
        $this->live = [
            'mode' => 'older', 'folder' => $folder->id, 'query' => null,
            'items' => array_merge($continue ? $this->live['items'] : [], array_map([$this, 'liveRow'], $page['items'])),
            'next' => $page['next'],
        ];
    }

    /** Search the whole folder on the mail server (all dates) */
    public function searchServer(): void
    {
        $folder = $this->currentFolder;
        if (! $folder || $this->search === '') {
            return;
        }
        $items = $this->attempt(fn () => MailFetch::make()->searchServer($this->user(), $folder, $this->search, 50));
        if ($items === false) {
            return;
        }
        $this->live = ['mode' => 'server', 'folder' => $folder->id, 'query' => $this->search, 'items' => array_map([$this, 'liveRow'], $items), 'next' => null];
    }

    public function openLive(int $uid): void
    {
        abort_unless(collect($this->live['items'] ?? [])->firstWhere('uid', $uid), 404);
        $this->thread = null;
        $this->expanded = [];
        $this->liveUid = $uid;
    }

    public function closeLive(): void
    {
        $this->live = [];
        $this->liveUid = null;
    }

    // ------------------------------------------------------------------ events from the other parts (6.3)

    #[On('mail-sent')]
    public function onSent(?string $accountId = null, ?int $threadId = null, ?string $outboxId = null, mixed ...$more): void
    {
        if ($threadId && $threadId === $this->thread) {
            $latest = $this->threadMessages()->last();
            if ($latest && ! in_array($latest->id, $this->expanded, true)) {
                $this->expanded[] = $latest->id;
            }
        }
    }

    #[On('mail-thread-changed')]
    public function onThreadChanged(?int $threadId = null, mixed ...$more): void
    {
        // re-rendered with the new state; a conversation that no longer exists closes
        if ($this->thread && ! MailThread::whereKey($this->thread)->exists()) {
            $this->closeThread();
        }
    }

    #[On('mail-refresh')]
    public function onRefresh(?string $accountId = null, mixed ...$more): void
    {
    }

    // ------------------------------------------------------------------ state

    #[Computed]
    public function accounts(): Collection
    {
        return MailAccess::accounts($this->user());
    }

    #[Computed]
    public function account(): ?MailAccount
    {
        return $this->accounts->firstWhere('id', $this->box);
    }

    /** @return Collection<int, MailFolder> selectable folders, standard ones first */
    #[Computed]
    public function folders(): Collection
    {
        if (! $this->account) {
            return collect();
        }

        return MailFolder::where('account_id', $this->account->id)->where('selectable', true)->get()
            ->sortBy(fn (MailFolder $f) => sprintf('%d-%s', MailFolder::ROLE_ORDER[$f->role] ?? 9, mb_strtolower($f->path)))
            ->values();
    }

    #[Computed]
    public function currentFolder(): ?MailFolder
    {
        $folders = $this->folders;
        $key = $this->folder ?: 'inbox';

        return ctype_digit($key)
            ? $folders->firstWhere('id', (int) $key) ?? $folders->firstWhere('role', 'inbox')
            : $folders->firstWhere('role', $key) ?? $folders->firstWhere('role', 'inbox') ?? $folders->first();
    }

    public function render()
    {
        // Counts may have changed during this request (opening marks read, actions move mail)
        unset($this->folders, $this->currentFolder);
        $user = $this->user();
        $account = $this->account;
        $folder = $this->currentFolder;
        $list = ['rows' => collect(), 'total' => 0, 'page' => 1];

        if ($account && $folder) {
            $list = $this->search !== ''
                ? ThreadList::search($user, $account, $this->search, $folder, $this->filter, $this->page)
                : ThreadList::folder($user, $folder, $this->filter, $this->page);
            $this->page = $list['page'];
        }

        $thread = $this->openThreadModel();
        if ($this->thread && ! $thread) {
            $this->thread = null;
        }

        return view('livewire.hq.mail.inbox', [
            'user' => $user,
            'accounts' => $this->accounts,
            'account' => $account,
            'folders' => $this->folders,
            'currentFolder' => $folder,
            'boxUnread' => $this->boxUnread(),
            'rows' => $list['rows'],
            'total' => $list['total'],
            'perPage' => ThreadList::PER_PAGE,
            'folderNames' => $this->folders->mapWithKeys(fn (MailFolder $f) => [$f->id => $f->label()])->all(),
            'openThread' => $thread,
            'messages' => $thread ? $this->readerMessages($thread) : [],
            'liveMessage' => $this->liveMessage(),
            'canOrganise' => $account && MailAccess::can($user, $account, 'organise'),
            'canSend' => $account && MailAccess::can($user, $account, 'send'),
            'level' => $account?->access_level,
        ]);
    }

    // ------------------------------------------------------------------ helpers

    private function user(): User
    {
        return Auth::user();
    }

    private function resetView(): void
    {
        $this->page = 1;
        $this->thread = null;
        $this->live = [];
        $this->liveUid = null;
        $this->expanded = [];
        $this->confirm = null;
    }

    private function notice(string $text, string $kind = 'success'): void
    {
        $this->flash = $text;
        $this->flashKind = $kind;
        if ($kind === 'error') {
            $this->undo = null;
        }
    }

    /** Run a server call; on a refusal, say so calmly. @return mixed its result, or false when it failed */
    private function attempt(callable $work): mixed
    {
        try {
            return $work() ?? true;
        } catch (ImapException) {
            $this->notice("The mail server didn't accept that. Try again in a minute.", 'error');
        } catch (ValidationException $e) {
            $this->notice(collect($e->errors())->flatten()->first() ?? 'That didn\'t work.', 'error');
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 403) {
                throw $e;
            }
            $this->notice($e->getMessage() !== '' ? $e->getMessage() : "That can't be done here.", 'error');
        }

        return false;
    }

    private function openThreadModel(): ?MailThread
    {
        if (! $this->thread || ! $this->account) {
            return null;
        }
        $thread = MailThread::where('account_id', $this->account->id)->find($this->thread);

        return $thread && MailAccess::canThread($this->user(), $thread, 'read') ? $thread : null;
    }

    private function showThread(int $id): void
    {
        $thread = MailThread::find($id);
        abort_unless($thread && $thread->account_id === $this->box && MailAccess::canThread($this->user(), $thread, 'read'), 404);

        $this->thread = $id;
        $messages = $this->threadMessages();
        // The unread ones and the latest are open; the rest fold
        $this->expanded = $messages->filter(fn (MailMessage $m) => ! $m->is_seen)->pluck('id')
            ->push($messages->last()?->id)->filter()->unique()->values()->all();
        $this->markOpened($messages->whereIn('id', $this->expanded));
        $this->dispatch('mail-thread-opened', accountId: $thread->account_id, threadId: $thread->id);
    }

    /** Opening marks read for everyone and records who opened it first (MailActions::opened) */
    private function markOpened(Collection $messages): void
    {
        $user = $this->user();
        $changed = false;
        foreach ($messages as $message) {
            if ($message->is_seen && $message->seen_by) {
                continue;
            }
            try {
                MailActions::make()->opened($user, $message);
                $changed = true;
            } catch (ImapException) {
                // Shown as unread until the server takes it; reading goes on
                $changed = true;
            }
        }
        if ($changed) {
            MailNav::forget($user->id);
        }
    }

    /**
     * The conversation's messages, one per message (a copy in two folders shows once: the one in this folder,
     * else one outside Spam and Trash), oldest first.
     *
     * @return Collection<int, MailMessage>
     */
    private function threadMessages(): Collection
    {
        if (! $this->thread) {
            return collect();
        }
        $current = $this->currentFolder?->id;
        $roles = $this->folders->pluck('role', 'id');

        return MailMessage::with(['attachments', 'folder:id,role,name,path'])
            ->where('thread_id', $this->thread)->where('account_id', $this->box)
            ->orderBy('received_at')->orderBy('id')->get()
            ->groupBy(fn (MailMessage $m) => $m->message_id_hash ?? 'row:' . $m->id)
            ->map(fn (Collection $copies) => $copies->sortBy(fn (MailMessage $m) => match (true) {
                $m->folder_id === $current => 0,
                ! in_array($roles[$m->folder_id] ?? null, ['spam', 'trash'], true) => 1,
                default => 2,
            })->first())
            ->sortBy(fn (MailMessage $m) => sprintf('%015d-%012d', $m->received_at?->timestamp ?? 0, $m->id))
            ->values();
    }

    /** What toolbar actions work on: the conversation's messages in this folder (in search, outside Sent and Drafts) */
    private function scopeMessages(MailThread $thread): Collection
    {
        $all = MailMessage::with('account')->where('thread_id', $thread->id)->where('account_id', $this->box)->get();
        $folder = $this->currentFolder;
        $roles = $this->folders->pluck('role', 'id');
        $scope = $this->search === '' && $folder
            ? $all->where('folder_id', $folder->id)
            : $all->filter(fn (MailMessage $m) => ! in_array($roles[$m->folder_id] ?? null, ['sent', 'drafts'], true));

        return ($scope->isNotEmpty() ? $scope : $all)->values();
    }

    /** The open conversation as the reader shows it */
    private function readerMessages(MailThread $thread): array
    {
        $user = $this->user();
        $messages = $this->threadMessages();
        $people = User::whereIn('id', $messages->pluck('seen_by')->merge($messages->pluck('sent_by'))->filter()->unique())
            ->get(['id', 'first_name', 'last_name'])->keyBy('id');
        $trusted = ImageTrust::trusted($user, $messages->pluck('from_email')->all());
        $current = $this->currentFolder?->id;

        return $messages->map(function (MailMessage $m) use ($people, $trusted, $current) {
            $open = in_array($m->id, $this->expanded, true);
            $remote = in_array((string) $m->id, $this->images, true) || in_array(strtolower((string) $m->from_email), $trusted, true);

            return [
                'id' => $m->id,
                'model' => $m,
                'open' => $open,
                'from' => $m->from_name ?: $m->from_email ?: 'Unknown sender',
                'from_email' => $m->from_email,
                'to' => $m->to ?? [],
                'cc' => $m->cc ?? [],
                'date' => self::when($m->received_at, true),
                'date_full' => $m->received_at?->timezone(self::TZ)->format('j M Y, H:i'),
                'folder' => $m->folder_id !== $current ? $m->folder?->label() : null,
                'opened_by' => $m->seen_by ? trim(($people->get($m->seen_by)?->first_name ?: 'Someone') . ', ' . self::when($m->seen_at, true), ', ') : null,
                'sent_by' => $m->sent_by ? $people->get($m->sent_by)?->name : null,
                'attachments' => $m->attachments->filter(fn ($a) => ! $a->is_inline || ! $a->content_id)->values(),
                'is_draft' => $m->is_draft || $m->folder?->role === 'drafts',
                'remote' => $remote,
                'body' => $open ? $this->body($m, $remote) : null,
            ];
        })->all();
    }

    /** @return array{kind: string, html?: string, doc?: string, blocked?: int} */
    private function body(MailMessage $m, bool $remote): array
    {
        try {
            $body = $m->body_state === 'cached'
                ? ['text' => (string) $m->body_text, 'html' => $m->body_html]
                : Cache::remember('mail-read-body:' . $m->id, 600, fn () => MailFetch::make()->body($this->user(), $m));
        } catch (ImapException) {
            return ['kind' => 'error'];
        }

        $cid = fn (string $id) => $m->attachments->firstWhere('content_id', trim($id, '<> '))
            ? route('hq.mail.read.inline', ['message' => $m->id, 'cid' => trim($id, '<> ')], false) : null;

        return self::present($body['text'] ?? '', $body['html'] ?? null, $remote, $cid);
    }

    /** A body as the reader shows it: HTML in a sandboxed frame, text inline */
    public static function present(string $text, ?string $html, bool $remote, callable $cid): array
    {
        if ($html !== null && trim($html) !== '') {
            $clean = HtmlSanitizer::clean($html, $remote, $cid);
            if (trim(strip_tags($clean['html'], '<img>')) !== '') {
                return ['kind' => 'html', 'doc' => self::document($clean['html'], $clean['styles'], $remote), 'blocked' => $clean['blocked']];
            }
        }
        if (trim($text) === '') {
            return ['kind' => 'empty'];
        }

        return ['kind' => 'text', 'html' => TextFormatter::toHtml($text), 'blocked' => 0];
    }

    /**
     * The document inside the reader's frame. The frame runs no scripts (sandbox) and the policy lets pictures
     * load only from HQ (cid:) and data:, or from anywhere once the person allowed them.
     */
    public static function document(string $html, string $styles, bool $remote): string
    {
        $self = request()->getSchemeAndHttpHost();
        $img = "'self' {$self} data:" . ($remote ? ' https: http:' : '');
        $policy = "default-src 'none'; img-src {$img}; style-src 'unsafe-inline'; font-src data:" . ($remote ? ' https:' : '') . "; base-uri 'none'; form-action 'none'";
        // Brand tokens from resources/css/app.css (the frame can't read the page's variables)
        $base = 'html,body{margin:0;padding:0;background:#FFFFFF}'
            . 'body{font-family:"Plus Jakarta Sans","Segoe UI",Arial,sans-serif;font-size:14px;line-height:1.55;color:#0C142B;overflow-wrap:anywhere;word-break:break-word}'
            . 'img{max-width:100%;height:auto}table{max-width:100%}pre{white-space:pre-wrap}a{color:#08707C}'
            . '.hq-mail-body{overflow-x:auto}'
            . '.hq-quote{margin-top:12px}.hq-quote>summary{cursor:pointer;display:inline-block;padding:2px 10px;border-radius:999px;background:#F2F5F7;color:#5E6679;font-size:12px;list-style:none}'
            . '.hq-quote>summary::-webkit-details-marker{display:none}.hq-quote[open]>summary{margin-bottom:8px}'
            . 'blockquote{margin:0 0 0 4px;padding-left:12px;border-left:2px solid #D8DEE6;color:#4A5468}';

        return '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta http-equiv="Content-Security-Policy" content="' . e($policy) . '">'
            . '<base target="_blank"><style>' . $base . '</style>'
            . ($styles !== '' ? '<style>' . $styles . '</style>' : '')
            . '</head><body>' . $html . '</body></html>';
    }

    /** The live (older) message open in the reader */
    private function liveMessage(): ?array
    {
        if (! $this->liveUid || empty($this->live['folder'])) {
            return null;
        }
        $folder = MailFolder::where('account_id', $this->box)->find($this->live['folder']);
        if (! $folder) {
            return null;
        }
        try {
            $item = Cache::remember('mail-read-live:' . $this->user()->id . ':' . $folder->id . ':' . $this->liveUid, 600,
                fn () => MailFetch::make()->message($this->user(), $folder, $this->liveUid));
        } catch (ImapException) {
            return ['error' => true, 'uid' => $this->liveUid];
        }
        if (! $item) {
            return ['error' => true, 'uid' => $this->liveUid];
        }

        $sections = collect($item['attachments'] ?? []);
        $link = fn (array $a) => route('hq.mail.read.live', ['folder' => $folder->id, 'uid' => $this->liveUid, 'section' => $a['section']], false);
        $cid = function (string $id) use ($sections, $link) {
            $part = $sections->firstWhere('content_id', trim($id, '<> '));

            return $part ? $link($part) . '?view=1' : null;
        };
        $remote = in_array('live:' . $this->liveUid, $this->images, true)
            || in_array(strtolower((string) ($item['from_email'] ?? '')), ImageTrust::trusted($this->user(), [$item['from_email'] ?? '']), true);

        return $this->liveRow($item) + [
            'to' => $item['to'] ?? [],
            'cc' => $item['cc'] ?? [],
            'date_full' => isset($item['received_at']) ? Carbon::parse($item['received_at'])->timezone(self::TZ)->format('j M Y, H:i') : null,
            'attachments' => $sections->filter(fn ($a) => ! $a['is_inline'] || ! $a['content_id'])
                ->map(fn ($a) => $a + ['url' => $link($a)])->values()->all(),
            'remote' => $remote,
            'body' => self::present((string) ($item['text'] ?? ''), $item['html'] ?? null, $remote, $cid),
            'folder' => $folder->label(),
        ];
    }

    /** A live item with plain values only (Livewire keeps it between requests) */
    private function liveRow(array $item): array
    {
        $at = $item['received_at'] ?? $item['sent_at'] ?? null;

        return [
            'uid' => (int) $item['uid'],
            'subject' => (string) ($item['subject'] ?? ''),
            'from_name' => $item['from_name'] ?? null,
            'from_email' => $item['from_email'] ?? null,
            'received_at' => $at instanceof CarbonInterface ? $at->toIso8601String() : $at,
            'is_seen' => (bool) ($item['is_seen'] ?? true),
            'is_flagged' => (bool) ($item['is_flagged'] ?? false),
            'has_attachments' => (bool) ($item['has_attachments'] ?? false),
        ];
    }

    /** Unread Inbox mail per mailbox, for the switcher */
    private function boxUnread(): array
    {
        $ids = $this->accounts->pluck('id')->all();

        return $ids ? MailFolder::whereIn('account_id', $ids)->where('role', 'inbox')->pluck('unread_count', 'account_id')->map(fn ($n) => (int) $n)->all() : [];
    }

    /** 840 KB, 2.4 MB */
    public static function size(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1) . ' MB',
            $bytes >= 1024 => max(1, (int) round($bytes / 1024)) . ' KB',
            default => $bytes . ' bytes',
        };
    }

    /** Names for a list row: the people other than the mailbox itself */
    public static function people(?array $participants, string $mailbox, int $max = 3): string
    {
        $others = collect($participants ?? [])->reject(fn ($p) => strtolower((string) ($p['email'] ?? '')) === strtolower($mailbox));

        return $others->take($max)->map(fn ($p) => ($p['name'] ?? null) ?: ($p['email'] ?? ''))->filter()->implode(', ');
    }

    /** 10:42 today, 3 Nov this year, 3 Nov 2025 before; $time adds the time to dates */
    public static function when(?CarbonInterface $at, bool $time = false): string
    {
        if (! $at) {
            return '';
        }
        $local = $at->copy()->timezone(self::TZ);
        $now = now()->timezone(self::TZ);

        return match (true) {
            $local->isSameDay($now) => $local->format('H:i'),
            $local->year === $now->year => $local->format('j M') . ($time ? ', ' . $local->format('H:i') : ''),
            default => $local->format('j M Y'),
        };
    }
}
