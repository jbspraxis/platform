<div>
    @php
        $labels = \App\Livewire\Hq\Marketing\InsightsArticles::STATUSES;
        $changes = \App\Livewire\Hq\Marketing\InsightsArticles::CHANGES;
        $editable = \App\Livewire\Hq\Marketing\InsightsArticles::EDITABLE;
        $pills = ['Pending Moderation' => 'pill-warning', 'Draft' => 'pill-neutral', 'Published' => 'pill-success', 'Rejected' => 'pill-danger'];
        // HQ names the contributor; the website credits every article to JBS Praxis
        $byline = fn ($a) => $a->contributor_id ? (trim($a->contributor_first_name . ' ' . $a->contributor_last_name) ?: 'A contributor') : 'JBS Praxis Editorial';
        // What an approver can do from this status: publish or unpublish, and turn down what is waiting for review
        $actionsFor = fn ($status) => $canReview ? collect($changes)->filter(fn ($rule) => in_array($status, $rule['from'], true))->keys()->all() : [];
        $day = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('j M Y') : null;
        $waiting = (int) ($counts['Pending Moderation'] ?? 0);
        $invalid = fn (string $key) => $errors->has($key) ? 'aria-invalid=true aria-describedby=' . str_replace('.', '-', $key) . '-error' : '';
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Insights articles</h1>
            <p class="page-subtitle">Articles sent through Write for Insights on the website, and drafts saved from signed projects. Read one, correct it, then publish it or send it back.</p>
        </div>
    </div>

    <div class="kz">
        <style>
            /* Insights articles: only what the shared component classes don't cover. Brand tokens only. */
            .kz-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 24px; margin-bottom: 16px; }
            .kz-toolbar .help { margin: 0; max-width: 60ch; }
            .kz-filter { min-width: 240px; }
            .kz-waiting { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; margin-bottom: 16px; padding: 12px 16px; border: 1px solid var(--color-line); border-left: 3px solid var(--color-warning, var(--color-line)); border-radius: var(--radius-card); color: var(--color-ink); }
            .kz-backdrop { position: fixed; inset: 0; z-index: 48; background: rgba(12, 20, 43, 0.4); }
            .kz-reader { position: fixed; top: 0; right: 0; bottom: 0; z-index: 49; display: flex; flex-direction: column; width: 640px; max-width: 100%; background: var(--color-paper); border-left: 1px solid var(--color-line); box-shadow: var(--shadow-float); color: var(--color-slate); animation: kz-panel-in var(--motion-standard) var(--ease-enter); }
            .kz-reader-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 20px 16px 20px 24px; border-bottom: 1px solid var(--color-line); }
            .kz-kicker { margin: 0 0 4px; font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
            .kz-reader-title { margin: 0; font-size: 1.25rem; line-height: 1.75rem; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
            .kz-reader-body { flex: 1; overflow-y: auto; overscroll-behavior: contain; padding: 24px; }
            .kz-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(170px, 100%), 1fr)); gap: 12px 24px; margin: 0; padding: 16px; border: 1px solid var(--color-line); border-radius: var(--radius-card); }
            .kz-facts dt { font-size: 0.8125rem; line-height: 1.125rem; color: var(--color-muted); }
            .kz-facts dd { margin: 2px 0 0; color: var(--color-ink); overflow-wrap: anywhere; }
            .kz-note { margin: 12px 0 0; }
            .kz-feedback { margin: 16px 0 0; padding: 4px 0 4px 16px; border-left: 2px solid var(--color-line); }
            .kz-feedback h3 { margin: 0 0 4px; font-size: 0.875rem; line-height: 1.25rem; font-weight: 600; color: var(--color-ink); }
            .kz-feedback p { margin: 0; white-space: pre-line; overflow-wrap: anywhere; }
            .kz-section { margin-top: 24px; max-width: 68ch; }
            .kz-section h3 { margin: 0 0 8px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
            .kz-section p { margin: 0 0 12px; line-height: 1.625rem; overflow-wrap: anywhere; }
            .kz-form { display: grid; gap: 16px; max-width: 68ch; }
            .kz-reader-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--color-line); }
            .kz-confirm-name { margin: 0 0 8px; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
            .kz-confirm-text { margin: 0 0 16px; }
            @keyframes kz-panel-in { from { transform: translateX(24px); opacity: 0; } to { transform: none; opacity: 1; } }
            @media (prefers-reduced-motion: reduce) { .kz-reader { animation: none; } }
            @media (max-width: 640px) {
                .kz-filter { min-width: 0; width: 100%; }
                .kz-reader-head { padding: 16px 8px 16px 16px; }
                .kz-reader-body { padding: 16px; }
                .kz-reader-foot { padding: 12px 16px; }
            }
        </style>

        @if($waiting > 0 && $kaizenStatusFilter !== 'Pending Moderation')
            <div class="kz-waiting" role="status">
                <span><strong class="num">{{ $waiting }}</strong> {{ $waiting === 1 ? 'article is' : 'articles are' }} waiting for your review.</span>
                <button type="button" wire:click="$set('kaizenStatusFilter', 'Pending Moderation')" class="btn btn-secondary btn-sm">Show {{ $waiting === 1 ? 'it' : 'them' }}</button>
            </div>
        @endif

        <div class="kz-toolbar">
            <p class="help">A published article appears on the website within 10 minutes, credited to JBS Praxis. When you publish or reject a contributor’s article, they are emailed.</p>
            <div class="field kz-filter">
                <label for="kz-status" class="label">Show</label>
                <select id="kz-status" wire:model.live="kaizenStatusFilter" class="select">
                    <option value="">All articles ({{ array_sum($counts) }})</option>
                    @foreach($labels as $value => $label)
                        @if($value !== 'Rejected' || ! empty($counts['Rejected']))
                            <option value="{{ $value }}">{{ $label }} ({{ $counts[$value] ?? 0 }})</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Author</th>
                            <th>Created</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledger as $item)
                            @php $rowActions = $actionsFor($item->status); @endphp
                            <tr wire:key="kz-{{ $item->id }}">
                                <td>
                                    <div class="font-medium text-ink">{{ $item->title }}</div>
                                    <div class="help">{{ \Illuminate\Support\Str::limit(strip_tags($item->executive_summary), 60) }}</div>
                                </td>
                                <td>
                                    <span class="pill {{ $pills[$item->status] ?? 'pill-neutral' }}">{{ $labels[$item->status] ?? $item->status }}</span>
                                    @if($item->published_at)
                                        <div class="help mt-1">{{ $item->status === 'Published' ? 'Published' : 'First published' }} <span class="num">{{ $day($item->published_at) }}</span></div>
                                    @endif
                                </td>
                                <td>{{ $byline($item) }}</td>
                                <td class="num text-left">{{ $day($item->created_at) }}</td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="readArticle('{{ $item->id }}')" class="btn btn-secondary btn-sm" aria-label="Read ‘{{ $item->title }}’">
                                            <i data-lucide="book-open"></i> Read
                                        </button>
                                        @if(in_array('publish', $rowActions, true))
                                            <button type="button" wire:click="askArticleChange('{{ $item->id }}', 'publish')" class="btn btn-primary btn-sm" aria-label="Publish ‘{{ $item->title }}’">
                                                <i data-lucide="send"></i> Publish
                                            </button>
                                        @endif
                                        @if(in_array('reject', $rowActions, true))
                                            <button type="button" wire:click="askArticleChange('{{ $item->id }}', 'reject')" class="btn btn-secondary btn-sm" aria-label="Reject ‘{{ $item->title }}’">
                                                <i data-lucide="undo-2"></i> Reject
                                            </button>
                                        @endif
                                        @if(in_array('unpublish', $rowActions, true))
                                            <button type="button" wire:click="askArticleChange('{{ $item->id }}', 'unpublish')" class="btn btn-secondary btn-sm" aria-label="Unpublish ‘{{ $item->title }}’">
                                                <i data-lucide="eye-off"></i> Unpublish
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    @if($kaizenStatusFilter !== '' && array_sum($counts) > 0)
                                        <strong>No articles with this status</strong>
                                        None of the articles is ‘{{ $labels[$kaizenStatusFilter] ?? $kaizenStatusFilter }}’ right now.
                                        <div class="mt-4"><button type="button" wire:click="$set('kaizenStatusFilter', '')" class="btn btn-secondary btn-sm">Show all articles</button></div>
                                    @else
                                        <strong>No Insights articles yet</strong>
                                        Articles sent through Write for Insights on the website, and drafts you save from a signed project, appear here.
                                    @endif
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($reading || $confirm)
            {{-- Escape closes the confirmation first, then the editor, then the reader --}}
            <div x-data x-on:keydown.escape.window="$wire.dismissArticle()">
                @if($reading)
                    @php
                        $readingActions = $actionsFor($reading->status);
                        $canEditThis = $canEdit && in_array($reading->status, $editable, true);
                    @endphp
                    <div class="kz-backdrop" wire:click="closeArticle"></div>
                    <aside class="kz-reader" role="dialog" aria-modal="true" aria-labelledby="kz-reader-title" x-trap.noscroll="true">
                        <header class="kz-reader-head">
                            <div>
                                <p class="kz-kicker">{{ $reading->category }} · <span class="num">{{ (int) $reading->read_time_minutes }}</span> min read</p>
                                <h2 id="kz-reader-title" class="kz-reader-title">{{ $editing ? 'Edit ‘' . $reading->title . '’' : $reading->title }}</h2>
                            </div>
                            <button type="button" wire:click="closeArticle" class="btn btn-ghost btn-sm" aria-label="Close the article"><i data-lucide="x"></i></button>
                        </header>

                        <div class="kz-reader-body">
                            <dl class="kz-facts">
                                <div>
                                    <dt>Status</dt>
                                    <dd><span class="pill {{ $pills[$reading->status] ?? 'pill-neutral' }}">{{ $labels[$reading->status] ?? $reading->status }}</span></dd>
                                </div>
                                <div>
                                    <dt>Author</dt>
                                    <dd>{{ $byline($reading) }}@if($reading->contributor_id && $reading->contributor_job_title)<span class="help">, {{ $reading->contributor_job_title }}</span>@endif</dd>
                                </div>
                                <div><dt>Created</dt><dd class="num">{{ $day($reading->created_at) }}</dd></div>
                                <div>
                                    <dt>{{ $reading->published_at && $reading->status !== 'Published' ? 'First published' : 'Published' }}</dt>
                                    <dd class="num">{{ $day($reading->published_at) ?? 'Not yet' }}</dd>
                                </div>
                                <div><dt>Last changed</dt><dd class="num">{{ $day($reading->updated_at ?? $reading->created_at) }}</dd></div>
                            </dl>
                            @if($reading->contributor_id)
                                <p class="help kz-note">Sent through Write for Insights on the website. The website credits every article to JBS Praxis and never names contributors.</p>
                            @endif
                            @if($reading->status === 'Rejected' && filled($reading->rejected_reason))
                                <div class="kz-feedback">
                                    <h3>Your note to the contributor{{ $reading->rejected_at ? ', ' . $day($reading->rejected_at) : '' }}</h3>
                                    <p>{{ $reading->rejected_reason }}</p>
                                </div>
                            @endif

                            @if($editing)
                                <form id="kz-edit-form" wire:submit="saveEdit" class="kz-form mt-6" novalidate>
                                    <div class="field">
                                        <label for="kz-f-title" class="label">Title</label>
                                        <input id="kz-f-title" type="text" class="input" maxlength="255" wire:model.live.blur="form.title" {!! $invalid('form.title') !!}>
                                        @error('form.title') <span class="field-error" id="form-title-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="kz-f-category" class="label">Category</label>
                                        <select id="kz-f-category" class="select" wire:model.live.blur="form.category" {!! $invalid('form.category') !!}>
                                            @foreach($categories as $category)
                                                <option value="{{ $category }}">{{ $category }}</option>
                                            @endforeach
                                        </select>
                                        @error('form.category') <span class="field-error" id="form-category-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="kz-f-summary" class="label">Summary</label>
                                        <textarea id="kz-f-summary" class="textarea" rows="3" wire:model.live.blur="form.executive_summary" {!! $invalid('form.executive_summary') !!}></textarea>
                                        <span class="help">Up to 50 words; <span class="num">{{ str_word_count((string) ($form['executive_summary'] ?? '')) }}</span> so far. It’s the first thing readers see.</span>
                                        @error('form.executive_summary') <span class="field-error" id="form-executive_summary-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="kz-f-problem" class="label">The problem</label>
                                        <textarea id="kz-f-problem" class="textarea" rows="7" wire:model.live.blur="form.problem_statement" {!! $invalid('form.problem_statement') !!}></textarea>
                                        <span class="help">Separate paragraphs with a blank line. Wrap a phrase in **two asterisks** to make it bold.</span>
                                        @error('form.problem_statement') <span class="field-error" id="form-problem_statement-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="kz-f-solution" class="label">What was done</label>
                                        <textarea id="kz-f-solution" class="textarea" rows="7" wire:model.live.blur="form.engineered_solution" {!! $invalid('form.engineered_solution') !!}></textarea>
                                        @error('form.engineered_solution') <span class="field-error" id="form-engineered_solution-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div class="field">
                                        <label for="kz-f-result" class="label">The result</label>
                                        <textarea id="kz-f-result" class="textarea" rows="4" wire:model.live.blur="form.final_roi" {!! $invalid('form.final_roi') !!}></textarea>
                                        @error('form.final_roi') <span class="field-error" id="form-final_roi-error">{{ $message }}</span> @enderror
                                    </div>
                                </form>
                            @else
                                @forelse($readingSections as $heading => $html)
                                    <section class="kz-section">
                                        <h3>{{ $heading }}</h3>
                                        {{ $html }}
                                    </section>
                                @empty
                                    <p class="help kz-note">This article has no text yet.</p>
                                @endforelse
                            @endif
                        </div>

                        @if($editing)
                            <footer class="kz-reader-foot">
                                <button type="button" wire:click="cancelEdit" class="btn btn-ghost">Discard changes</button>
                                <button type="submit" form="kz-edit-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveEdit"><i data-lucide="check"></i> Save changes</button>
                            </footer>
                        @elseif($canEditThis || $readingActions)
                            <footer class="kz-reader-foot">
                                <button type="button" wire:click="closeArticle" class="btn btn-ghost">Close</button>
                                @if($canEditThis)
                                    <button type="button" wire:click="startEdit('{{ $reading->id }}')" class="btn btn-secondary"><i data-lucide="pencil"></i> Edit</button>
                                @endif
                                @if(in_array('reject', $readingActions, true))
                                    <button type="button" wire:click="askArticleChange('{{ $reading->id }}', 'reject')" class="btn btn-secondary"><i data-lucide="undo-2"></i> Reject</button>
                                @endif
                                @if(in_array('publish', $readingActions, true))
                                    <button type="button" wire:click="askArticleChange('{{ $reading->id }}', 'publish')" class="btn btn-primary"><i data-lucide="send"></i> Publish</button>
                                @endif
                                @if(in_array('unpublish', $readingActions, true))
                                    <button type="button" wire:click="askArticleChange('{{ $reading->id }}', 'unpublish')" class="btn btn-secondary"><i data-lucide="eye-off"></i> Unpublish</button>
                                @endif
                            </footer>
                        @endif
                    </aside>
                @endif

                @if($confirm)
                    @php
                        $rejecting = $confirmChange === 'reject';
                        $publishing = $confirmChange === 'publish';
                        $emailsContributor = $confirm->status === 'Pending Moderation' && $confirm->contributor_id;
                    @endphp
                    {{-- Over the reader, the reader already holds the page still: a second scroll lock would put back the
                         wrong state when both close together after a publish, and leave the page unable to scroll --}}
                    <div class="modal-backdrop">
                        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="kz-confirm-title" aria-describedby="kz-confirm-name kz-confirm-text" x-trap{{ $reading ? '' : '.noscroll' }}="true" style="max-width: 500px;">
                            <h2 id="kz-confirm-title" class="modal-title">{{ $rejecting ? 'Reject this article?' : ($publishing ? 'Publish this article?' : 'Unpublish this article?') }}</h2>
                            <p id="kz-confirm-name" class="kz-confirm-name">‘{{ $confirm->title }}’</p>
                            <p id="kz-confirm-text" class="muted kz-confirm-text">
                                @if($rejecting)
                                    It stays off the website.{{ $emailsContributor ? ' The contributor is emailed the note below, with an invitation to send a revised article.' : ' Keep the note below for whoever reads this article next.' }}
                                @elseif($publishing)
                                    It appears on JBS Praxis Insights within 10 minutes, credited to JBS Praxis.{{ $emailsContributor ? ' The contributor is emailed the link.' : '' }}
                                @else
                                    It leaves the website within 10 minutes and becomes a draft again. Its first publication date is kept.
                                @endif
                            </p>
                            @if($rejecting)
                                <div class="field">
                                    <label for="kz-reject-reason" class="label">Note to the contributor</label>
                                    <textarea id="kz-reject-reason" class="textarea" rows="5" maxlength="2000" wire:model="rejectReason" x-init="$nextTick(() => $el.focus())" {!! $invalid('rejectReason') !!}></textarea>
                                    <span class="help">{{ $emailsContributor ? 'They read this as you write it. ' : '' }}Say what would make the article ready to publish.</span>
                                    @error('rejectReason') <span class="field-error" id="rejectReason-error">{{ $message }}</span> @enderror
                                </div>
                            @endif
                            <div class="modal-actions">
                                <button type="button" wire:click="cancelArticleChange" class="btn btn-ghost">{{ $rejecting ? 'Keep waiting' : ($publishing ? 'Leave unpublished' : 'Keep published') }}</button>
                                <button type="button" wire:click="{{ $rejecting ? 'rejectArticle' : ($publishing ? 'publishArticle' : 'unpublishArticle') }}('{{ $confirm->id }}')" class="btn btn-primary" wire:loading.attr="disabled">
                                    {{ $rejecting ? ($emailsContributor ? 'Reject and email' : 'Reject') : ($publishing ? 'Publish' : 'Unpublish') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
