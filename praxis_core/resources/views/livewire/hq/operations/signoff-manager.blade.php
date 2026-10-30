<div>
    @php
        // Stored sign-off statuses (database values) and what people read
        $statusLabels = ['Draft' => 'Draft', 'Awaiting Signature' => 'Awaiting signature', 'Signed' => 'Signed', 'Frozen' => 'Frozen'];
        $statusPills = ['Draft' => 'pill-neutral', 'Awaiting Signature' => 'pill-warning', 'Signed' => 'pill-success', 'Frozen' => 'pill-info'];
        $isCaseStudy = $marketingTab === 'portfolio_gen';
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Sign-offs</h1>
            <p class="page-subtitle">Project handover documents for clients to sign{{ $canViewKaizen ? ', and the case studies and Insights articles drafted from them' : '' }}.</p>
        </div>
    </div>

    @if (session()->has('error'))
        <div class="alert alert-danger mb-4" role="alert"><i data-lucide="circle-alert"></i> <span>{{ session('error') }}</span></div>
    @endif
    @if (session()->has('success'))
        <div class="alert alert-success mb-4" role="status"><i data-lucide="check-circle"></i> <span>{{ session('success') }}</span></div>
    @endif

    <div class="tabs" role="tablist">
        <button type="button" role="tab" wire:click="switchMainTab('ledger')" class="tab {{ $mainTab === 'ledger' ? 'is-active' : '' }}" aria-selected="{{ $mainTab === 'ledger' ? 'true' : 'false' }}">Sign-offs</button>
        @if($canViewKaizen)
            <button type="button" role="tab" wire:click="switchMainTab('marketing')" class="tab {{ $mainTab === 'marketing' ? 'is-active' : '' }}" aria-selected="{{ $mainTab === 'marketing' ? 'true' : 'false' }}">Case studies and articles</button>
        @endif
    </div>

    <!-- Tab 1: sign-offs -->
    @if($mainTab === 'ledger')

        @if($canDraft)
            <div class="card mb-6">
                <div class="card-header">
                    <h2 class="card-title">Draft a sign-off</h2>
                </div>
                <p class="help mb-4">The JBS Praxis assistant drafts the handover summary from the project’s tasks, time and issues. You then copy the sign-off link and send it to the client’s signatory.</p>

                @if(empty($eligibleProjects))
                    <div class="alert alert-info"><i data-lucide="info"></i> <span>No projects are ready for sign-off. A project is ready when every task on it is Done and it has no sign-off yet.</span></div>
                @else
                    <form wire:submit.prevent="generateSignoffDraft" class="grid items-end gap-4 md:grid-cols-[1fr_1fr_auto]">
                        <div class="field">
                            <label for="draft-project" class="label">Project</label>
                            <select id="draft-project" wire:model="draftProjectId" class="select" required>
                                <option value="">Choose a project</option>
                                @foreach($eligibleProjects as $p)
                                    <option value="{{ $p->id }}">{{ $p->client_name }}: {{ \Illuminate\Support\Str::limit($p->title, 40) }}</option>
                                @endforeach
                            </select>
                            @error('draftProjectId') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="field">
                            <label for="draft-email" class="label">Signatory email</label>
                            <input id="draft-email" type="email" wire:model="draftSignatoryEmail" class="input" placeholder="e.g. ada.okafor@example.com" required>
                            @error('draftSignatoryEmail') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="generateSignoffDraft" class="inline-flex items-center gap-2">
                                    <i data-lucide="file-text"></i> Draft sign-off
                                </span>
                                <span wire:loading wire:target="generateSignoffDraft">Drafting…</span>
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        <div class="card p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Client and project</th>
                            <th>Status</th>
                            <th>Signatory</th>
                            <th>Signature</th>
                            <th><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($signoffs as $doc)
                            <tr>
                                <td>
                                    <div class="font-medium text-ink">{{ $doc->client_name }}</div>
                                    <div class="help">{{ \Illuminate\Support\Str::limit($doc->project_title, 35) }}</div>
                                </td>
                                <td>
                                    <span class="pill {{ $statusPills[$doc->status] ?? 'pill-neutral' }}">{{ $statusLabels[$doc->status] ?? $doc->status }}</span>
                                    <div class="help mt-1">
                                        @if($doc->status === 'Signed' || $doc->status === 'Frozen')
                                            Signed <span class="num">{{ \Carbon\Carbon::parse($doc->signed_at)->format('j M Y') }}</span>
                                        @else
                                            Created <span class="num">{{ \Carbon\Carbon::parse($doc->created_at)->format('j M Y') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div>{{ $doc->signatory_email }}</div>
                                    @if($doc->signatory_name)
                                        <div class="help">{{ $doc->signatory_name }} ({{ $doc->signatory_job_title }})</div>
                                    @endif
                                </td>
                                <td>
                                    @if($doc->client_signature_hash)
                                        <div class="inline-flex items-center gap-1 text-success" title="SHA-256: {{ $doc->client_signature_hash }}">
                                            <i data-lucide="shield-check" class="h-4 w-4"></i> Signed (SHA-256 recorded)
                                        </div>
                                        <div class="help">From IP address <span class="num">{{ $doc->client_ip_address }}</span></div>
                                    @else
                                        <span class="muted">Not signed yet</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if(($doc->status === 'Draft' || $doc->status === 'Awaiting Signature') && $doc->token)
                                            <button type="button" x-data="{ copied: false }"
                                                    @click="navigator.clipboard.writeText('{{ route('portal.signoff.gateway', ['token' => $doc->token]) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                    class="btn btn-secondary btn-sm">
                                                <i data-lucide="link"></i>
                                                <span x-text="copied ? 'Link copied' : 'Copy sign-off link'">Copy sign-off link</span>
                                            </button>
                                        @endif

                                        @if($doc->status === 'Frozen' && $canUnfreeze)
                                            <button type="button" wire:click="unfreezeSignoff('{{ $doc->id }}')" class="btn btn-secondary btn-sm" onclick="confirm('Unfreeze this sign-off? The project becomes active again and its sprint board reopens.') || event.stopImmediatePropagation()">
                                                <i data-lucide="unlock"></i> Unfreeze
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5">
                                <div class="empty-state">
                                    <strong>No sign-offs yet</strong>
                                    A sign-off is drafted here once every task on a project is Done. The client signs it from a private link.
                                </div>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @endif

    <!-- Tab 2: case studies and JBS Praxis Insights articles -->
    @if($mainTab === 'marketing' && $canViewKaizen)

        <div class="mb-6 flex flex-wrap gap-2" role="tablist" aria-label="Case studies and articles">
            @if($canPushKaizen)
                <button type="button" role="tab" wire:click="switchMarketingTab('portfolio_gen')" class="btn btn-sm {{ $marketingTab === 'portfolio_gen' ? 'btn-primary' : 'btn-secondary' }}" aria-selected="{{ $marketingTab === 'portfolio_gen' ? 'true' : 'false' }}">Draft a case study</button>
                <button type="button" role="tab" wire:click="switchMarketingTab('kaizen_gen')" class="btn btn-sm {{ $marketingTab === 'kaizen_gen' ? 'btn-primary' : 'btn-secondary' }}" aria-selected="{{ $marketingTab === 'kaizen_gen' ? 'true' : 'false' }}">Draft an Insights article</button>
            @endif
            <button type="button" role="tab" wire:click="switchMarketingTab('portfolio_ledger')" class="btn btn-sm {{ $marketingTab === 'portfolio_ledger' ? 'btn-primary' : 'btn-secondary' }}" aria-selected="{{ $marketingTab === 'portfolio_ledger' ? 'true' : 'false' }}">Case studies</button>
            <button type="button" role="tab" wire:click="switchMarketingTab('kaizen_ledger')" class="btn btn-sm {{ $marketingTab === 'kaizen_ledger' ? 'btn-primary' : 'btn-secondary' }}" aria-selected="{{ $marketingTab === 'kaizen_ledger' ? 'true' : 'false' }}">Insights articles</button>
        </div>

        <!-- Drafting -->
        @if(($marketingTab === 'portfolio_gen' || $marketingTab === 'kaizen_gen') && $canPushKaizen)
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ $isCaseStudy ? 'Draft a case study' : 'Draft a JBS Praxis Insights article' }}</h2>
                </div>
                <p class="help mb-4">Choose a signed project. The JBS Praxis assistant leaves out confidential details and drafts {{ $isCaseStudy ? 'a case study for the website' : 'an article for JBS Praxis Insights' }}. Nothing is published until someone publishes it.</p>

                <div class="mb-4 grid gap-4 md:grid-cols-2">
                    <div class="field">
                        <label for="mkt-signoff" class="label">Signed project</label>
                        <select id="mkt-signoff" wire:model="selectedSignoffForMarketing" class="select">
                            <option value="">Choose a signed project</option>
                            @foreach(collect($signoffs)->whereIn('status', ['Signed', 'Frozen'])->where('is_pushed_to_kaizen', 0) as $s)
                                <option value="{{ $s->id }}">{{ $s->client_name }}: {{ \Illuminate\Support\Str::limit($s->project_title, 40) }}</option>
                            @endforeach
                        </select>
                        @error('selectedSignoffForMarketing') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="mkt-tone" class="label">Tone</label>
                        <select id="mkt-tone" wire:model="marketingTone" class="select">
                            <option value="Clinical & Authoritative">Measured and authoritative (standard)</option>
                            <option value="Aggressive & Financial">Commercial (cost and return)</option>
                            <option value="Academic & Technical">Technical (system design)</option>
                        </select>
                    </div>
                </div>

                <div class="field mb-4">
                    <label for="mkt-rules" class="label">Confidentiality rules</label>
                    <input id="mkt-rules" type="text" wire:model="marketingSanitizationRules" class="input">
                    <span class="help">Tell the assistant what to leave out and what to keep.</span>
                </div>

                <button type="button" wire:click="generateMarketingAsset" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="generateMarketingAsset" class="inline-flex items-center gap-2">
                        <i data-lucide="sparkles"></i> {{ $isCaseStudy ? 'Draft case study' : 'Draft article' }}
                    </span>
                    <span wire:loading wire:target="generateMarketingAsset">Drafting…</span>
                </button>

                @if($generatedTitle)
                    <div class="divider my-6"></div>
                    <h3 class="section-title">Check the draft before you save it</h3>

                    <div class="flex flex-col gap-4">
                        <div class="field">
                            <label for="gen-title" class="label">Title</label>
                            <input id="gen-title" type="text" wire:model="generatedTitle" class="input font-semibold">
                        </div>

                        <div class="field">
                            <label for="gen-narrative" class="label">Text</label>
                            <textarea id="gen-narrative" wire:model="generatedNarrative" class="textarea min-h-[200px]"></textarea>
                            <span class="help">Plain text or HTML.</span>
                        </div>

                        <div class="field">
                            <label for="gen-quote" class="label">Suggested quote</label>
                            <textarea id="gen-quote" wire:model="generatedQuote" class="textarea min-h-[80px]"></textarea>
                        </div>

                        @if($isCaseStudy)
                            <div class="field">
                                <label for="gen-image" class="label">Suggested image description</label>
                                <textarea id="gen-image" wire:model="generatedDallePrompt" class="textarea min-h-[60px] text-caption" readonly></textarea>
                            </div>
                        @endif

                        <button type="button" wire:click="commitMarketingAsset" class="btn btn-primary w-full">
                            <i data-lucide="save"></i> Save as draft
                        </button>
                    </div>
                @endif
            </div>
        @endif

        <!-- Saved case studies -->
        @if($marketingTab === 'portfolio_ledger')
            <div class="card p-0">
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($portfolioLedger as $item)
                                <tr>
                                    <td>
                                        <div class="font-medium text-ink">{{ $item->title }}</div>
                                        <div class="help">{{ $item->client_pseudonym }}</div>
                                    </td>
                                    <td>
                                        @if($item->is_published)
                                            <span class="pill pill-success">Published</span>
                                        @else
                                            <span class="pill pill-neutral">Draft</span>
                                        @endif
                                    </td>
                                    <td class="num text-left">{{ \Carbon\Carbon::parse($item->created_at)->format('j M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3">
                                    <div class="empty-state">
                                        <strong>No case studies yet</strong>
                                        Drafts you save from a signed project appear here.
                                    </div>
                                </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- JBS Praxis Insights articles: read one in full, publish or unpublish it (kaizen.approve) -->
        @if($marketingTab === 'kaizen_ledger')
            @php
                $kaizenLabels = \App\Livewire\Hq\Operations\SignoffManager::KAIZEN_STATUSES;
                $kaizenChanges = \App\Livewire\Hq\Operations\SignoffManager::KAIZEN_CHANGES;
                $kaizenPills = ['Pending Moderation' => 'pill-warning', 'Draft' => 'pill-neutral', 'Published' => 'pill-success', 'Rejected' => 'pill-danger'];
                // HQ names the contributor; the website credits every article to JBS Praxis
                $byline = fn ($a) => $a->contributor_id ? (trim($a->contributor_first_name . ' ' . $a->contributor_last_name) ?: 'A contributor') : 'JBS Praxis Editorial';
                // The change an approver can make from this status, if any
                $changeFor = fn ($status) => collect($kaizenChanges)->search(fn ($rule) => in_array($status, $rule['from'], true)) ?: null;
                $day = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('j M Y') : null;
            @endphp

            <div class="kz">
                <style>
                    /* Insights articles: only what the shared component classes don't cover. Brand tokens only. */
                    .kz-toolbar { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px 24px; margin-bottom: 16px; }
                    .kz-toolbar .help { margin: 0; max-width: 60ch; }
                    .kz-filter { min-width: 240px; }
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
                    .kz-section { margin-top: 24px; max-width: 68ch; }
                    .kz-section h3 { margin: 0 0 8px; font-size: 1rem; line-height: 1.5rem; font-weight: 600; color: var(--color-ink); }
                    .kz-section p { margin: 0 0 12px; line-height: 1.625rem; overflow-wrap: anywhere; }
                    .kz-reader-foot { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--color-line); }
                    .kz-confirm-name { margin: 0 0 8px; font-weight: 600; color: var(--color-ink); overflow-wrap: anywhere; }
                    .kz-confirm-text { margin: 0; }
                    @keyframes kz-panel-in { from { transform: translateX(24px); opacity: 0; } to { transform: none; opacity: 1; } }
                    @media (prefers-reduced-motion: reduce) { .kz-reader { animation: none; } }
                    @media (max-width: 640px) {
                        .kz-filter { min-width: 0; width: 100%; }
                        .kz-reader-head { padding: 16px 8px 16px 16px; }
                        .kz-reader-body { padding: 16px; }
                        .kz-reader-foot { padding: 12px 16px; }
                    }
                </style>

                <div class="kz-toolbar">
                    <p class="help">Read an article before you publish it. A published article appears on the website within 10 minutes, credited to JBS Praxis.</p>
                    <div class="field kz-filter">
                        <label for="kz-status" class="label">Show</label>
                        <select id="kz-status" wire:model.live="kaizenStatusFilter" class="select">
                            <option value="">All articles ({{ array_sum($kaizenCounts) }})</option>
                            @foreach($kaizenLabels as $value => $label)
                                @if($value !== 'Rejected' || ! empty($kaizenCounts['Rejected']))
                                    <option value="{{ $value }}">{{ $label }} ({{ $kaizenCounts[$value] ?? 0 }})</option>
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
                                @forelse($kaizenLedger as $item)
                                    @php $change = $canPublishKaizen ? $changeFor($item->status) : null; @endphp
                                    <tr wire:key="kz-{{ $item->id }}">
                                        <td>
                                            <div class="font-medium text-ink">{{ $item->title }}</div>
                                            <div class="help">{{ \Illuminate\Support\Str::limit(strip_tags($item->executive_summary), 60) }}</div>
                                        </td>
                                        <td>
                                            <span class="pill {{ $kaizenPills[$item->status] ?? 'pill-neutral' }}">{{ $kaizenLabels[$item->status] ?? $item->status }}</span>
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
                                                @if($change === 'publish')
                                                    <button type="button" wire:click="askArticleChange('{{ $item->id }}', 'publish')" class="btn btn-primary btn-sm" aria-label="Publish ‘{{ $item->title }}’">
                                                        <i data-lucide="send"></i> Publish
                                                    </button>
                                                @elseif($change === 'unpublish')
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
                                            @if($kaizenStatusFilter !== '' && array_sum($kaizenCounts) > 0)
                                                <strong>No articles with this status</strong>
                                                None of the articles is ‘{{ $kaizenLabels[$kaizenStatusFilter] ?? $kaizenStatusFilter }}’ right now.
                                                <div class="mt-4"><button type="button" wire:click="$set('kaizenStatusFilter', '')" class="btn btn-secondary btn-sm">Show all articles</button></div>
                                            @else
                                                <strong>No Insights articles yet</strong>
                                                Drafts you save from a signed project, and articles sent through Write for Insights on the website, appear here.
                                            @endif
                                        </div>
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($readingArticle || $confirmArticle)
                    {{-- Escape closes the confirmation first, then the reader --}}
                    <div x-data x-on:keydown.escape.window="$wire.dismissArticle()">
                        @if($readingArticle)
                            @php $readingChange = $canPublishKaizen ? $changeFor($readingArticle->status) : null; @endphp
                            <div class="kz-backdrop" wire:click="closeArticle"></div>
                            <aside class="kz-reader" role="dialog" aria-modal="true" aria-labelledby="kz-reader-title" x-trap.noscroll="true">
                                <header class="kz-reader-head">
                                    <div>
                                        <p class="kz-kicker">{{ $readingArticle->category }} · <span class="num">{{ (int) $readingArticle->read_time_minutes }}</span> min read</p>
                                        <h2 id="kz-reader-title" class="kz-reader-title">{{ $readingArticle->title }}</h2>
                                    </div>
                                    <button type="button" wire:click="closeArticle" class="btn btn-ghost btn-sm" aria-label="Close the article"><i data-lucide="x"></i></button>
                                </header>

                                <div class="kz-reader-body">
                                    <dl class="kz-facts">
                                        <div>
                                            <dt>Status</dt>
                                            <dd><span class="pill {{ $kaizenPills[$readingArticle->status] ?? 'pill-neutral' }}">{{ $kaizenLabels[$readingArticle->status] ?? $readingArticle->status }}</span></dd>
                                        </div>
                                        <div>
                                            <dt>Author</dt>
                                            <dd>{{ $byline($readingArticle) }}@if($readingArticle->contributor_id && $readingArticle->contributor_job_title)<span class="help">, {{ $readingArticle->contributor_job_title }}</span>@endif</dd>
                                        </div>
                                        <div><dt>Created</dt><dd class="num">{{ $day($readingArticle->created_at) }}</dd></div>
                                        <div>
                                            <dt>{{ $readingArticle->published_at && $readingArticle->status !== 'Published' ? 'First published' : 'Published' }}</dt>
                                            <dd class="num">{{ $day($readingArticle->published_at) ?? 'Not yet' }}</dd>
                                        </div>
                                        <div><dt>Last changed</dt><dd class="num">{{ $day($readingArticle->updated_at ?? $readingArticle->created_at) }}</dd></div>
                                    </dl>
                                    @if($readingArticle->contributor_id)
                                        <p class="help kz-note">Sent through Write for Insights on the website. The website credits every article to JBS Praxis and never names contributors.</p>
                                    @endif

                                    @forelse($readingSections as $heading => $html)
                                        <section class="kz-section">
                                            <h3>{{ $heading }}</h3>
                                            {{ $html }}
                                        </section>
                                    @empty
                                        <p class="help kz-note">This article has no text yet.</p>
                                    @endforelse
                                </div>

                                @if($readingChange)
                                    <footer class="kz-reader-foot">
                                        <button type="button" wire:click="closeArticle" class="btn btn-ghost">Close</button>
                                        @if($readingChange === 'publish')
                                            <button type="button" wire:click="askArticleChange('{{ $readingArticle->id }}', 'publish')" class="btn btn-primary"><i data-lucide="send"></i> Publish</button>
                                        @else
                                            <button type="button" wire:click="askArticleChange('{{ $readingArticle->id }}', 'unpublish')" class="btn btn-secondary"><i data-lucide="eye-off"></i> Unpublish</button>
                                        @endif
                                    </footer>
                                @endif
                            </aside>
                        @endif

                        @if($confirmArticle)
                            @php $publishing = $confirmChange === 'publish'; @endphp
                            {{-- Over the reader, the reader already holds the page still: a second scroll lock would put back the
                                 wrong state when both close together after a publish, and leave the page unable to scroll --}}
                            <div class="modal-backdrop">
                                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="kz-confirm-title" aria-describedby="kz-confirm-name kz-confirm-text" x-trap{{ $readingArticle ? '' : '.noscroll' }}="true" style="max-width: 500px;">
                                    <h2 id="kz-confirm-title" class="modal-title">{{ $publishing ? 'Publish this article?' : 'Unpublish this article?' }}</h2>
                                    <p id="kz-confirm-name" class="kz-confirm-name">‘{{ $confirmArticle->title }}’</p>
                                    <p id="kz-confirm-text" class="muted kz-confirm-text">
                                        {{ $publishing
                                            ? 'It appears on JBS Praxis Insights within 10 minutes, credited to JBS Praxis.'
                                            : 'It leaves the website within 10 minutes and becomes a draft again. Its first publication date is kept.' }}
                                    </p>
                                    <div class="modal-actions">
                                        <button type="button" wire:click="cancelArticleChange" class="btn btn-ghost">{{ $publishing ? 'Leave unpublished' : 'Keep published' }}</button>
                                        <button type="button" wire:click="{{ $publishing ? 'publishArticle' : 'unpublishArticle' }}('{{ $confirmArticle->id }}')" class="btn btn-primary" wire:loading.attr="disabled">
                                            {{ $publishing ? 'Publish' : 'Unpublish' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

    @endif
</div>
