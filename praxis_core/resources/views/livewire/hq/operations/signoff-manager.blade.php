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
            {{-- Reading, correcting, publishing and rejecting articles moved to its own screen under Marketing --}}
            <a href="{{ route('hq.marketing.insights') }}" class="btn btn-sm btn-secondary" wire:navigate>Review Insights articles <i data-lucide="arrow-right"></i></a>
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

    @endif
</div>
