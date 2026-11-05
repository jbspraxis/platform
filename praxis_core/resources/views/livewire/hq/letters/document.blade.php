@php
    $day = fn ($at) => $at ? \Carbon\Carbon::parse($at)->timezone('Africa/Lagos')->format('j M Y') : '—';
    $pdfUrl = route('hq.letters.pdf', ['document' => $document->id]) . '?v=' . $previewVersion;
    $formats = [
        ['bold', 'Bold', 'Ctrl+B', '<path d="M6 12h8a4 4 0 0 0 0-8H6v8Z"/><path d="M6 12h9a4 4 0 0 1 0 8H6v-8Z"/>'],
        ['italic', 'Italic', 'Ctrl+I', '<line x1="19" x2="10" y1="4" y2="4"/><line x1="14" x2="5" y1="20" y2="20"/><line x1="15" x2="9" y1="4" y2="20"/>'],
        ['insertUnorderedList', 'Bulleted list', '', '<line x1="8" x2="21" y1="6" y2="6"/><line x1="8" x2="21" y1="12" y2="12"/><line x1="8" x2="21" y1="18" y2="18"/><line x1="3" x2="3.01" y1="6" y2="6"/><line x1="3" x2="3.01" y1="12" y2="12"/><line x1="3" x2="3.01" y1="18" y2="18"/>'],
        ['insertOrderedList', 'Numbered list', '', '<line x1="10" x2="21" y1="6" y2="6"/><line x1="10" x2="21" y1="12" y2="12"/><line x1="10" x2="21" y1="18" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/>'],
        ['heading:h3', 'Small heading', '', '<path d="M4 12h8"/><path d="M4 18V6"/><path d="M12 18V6"/><path d="M17 10v8"/><path d="M21 10v8"/>'],
    ];
@endphp
<div class="doc">
    @assets
        <script src="{{ route('hq.letters.script', ['v' => filemtime(resource_path('letters/documents.js'))]) }}"></script>
    @endassets

    <style>
        /* HQ › Documents › one document (docs/DOCUMENTS.md): only what the shared component classes don't cover. */
        .doc-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; align-items: start; }
        .doc-preview { position: sticky; top: 16px; }
        .doc-frame { width: 100%; aspect-ratio: 1 / 1.414; border: 1px solid var(--color-line); border-radius: 8px; background: var(--color-mist); }
        .doc-two { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        .doc-people { display: grid; grid-template-columns: 1fr 1fr auto; gap: 8px; align-items: end; }
        .doc-people + .doc-people { margin-top: 8px; }
        .doc-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 0.875rem; color: var(--color-muted); margin: 0 0 4px; }
        .doc-meta strong { color: var(--color-ink); font-variant-numeric: tabular-nums; }
        .doc-state { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 0.75rem; font-weight: 600; }
        .doc-state.is-draft { background: var(--color-mist); color: var(--color-muted); }
        .doc-state.is-issued { background: #E7F4EC; color: #1A6E47; }
        .doc-state.is-revoked { background: #FBE9E7; color: #B3261E; }
        .doc-state.is-replaced { background: #FBF1DF; color: #8A5700; }
        .doc-state.is-sent_for_signature { background: #E8EEFB; color: #2A4B9B; }
        .doc-state.is-fully_signed { background: #E7F4EC; color: #14603C; }
        .doc-party { font-size: 0.875rem; line-height: 1.4; color: var(--color-slate); }
        .doc-party strong { display: block; color: var(--color-ink); }
        .doc-link { font-size: 0.75rem; word-break: break-all; color: var(--color-muted); }
        .doc-toolbar { display: flex; gap: 2px; padding: 4px; border: 1px solid var(--color-line-strong); border-bottom: 0; border-radius: 8px 8px 0 0; background: var(--color-mist); }
        .doc-icon { display: inline-flex; width: 30px; height: 30px; align-items: center; justify-content: center; border: 0; border-radius: 6px; background: transparent; color: var(--color-slate, #4A5468); cursor: pointer; }
        .doc-icon:hover { background: var(--color-paper); }
        .doc-icon svg { width: 16px; height: 16px; }
        .doc-editor { min-height: 220px; padding: 12px 14px; border: 1px solid var(--color-line-strong); border-radius: 0 0 8px 8px; background: var(--color-paper); font-size: 0.9375rem; line-height: 1.6; }
        .doc-editor:focus { outline: 2px solid var(--color-deep-teal); outline-offset: -2px; }
        .doc-editor p { margin: 0 0 10px; }
        .doc-sign { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .doc-sign img { max-height: 48px; background: #FFF; }
        .doc-sign-name { font-weight: 600; color: var(--color-ink); }
        .doc-sign-name small { display: block; font-weight: 400; color: var(--color-muted); }
        .doc-check { display: flex; align-items: flex-start; gap: 8px; }
        .doc-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        @media (max-width: 1024px) {
            .doc-grid { grid-template-columns: 1fr; }
            .doc-preview { position: static; }
        }
        @media (max-width: 600px) {
            .doc-two, .doc-people { grid-template-columns: 1fr; }
            .doc .modal-actions { flex-direction: column-reverse; }
            .doc .modal-actions .btn { width: 100%; }
            .doc-actions .btn { width: 100%; }
        }
    </style>

    <div class="page-header">
        <div>
            <p class="doc-meta">
                <span><strong>{{ $document->reference }}</strong></span>
                <span class="doc-state is-{{ $document->status }}">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
                <span>{{ $kind }}</span>
                <span>{{ $details['name'] }}</span>
            </p>
            <h1 class="page-title">{{ $document->title ?: 'Untitled document' }}</h1>
            <p class="page-subtitle">
                @if ($document->status === 'draft')
                    A draft. Nothing is fixed until you issue it; after that the PDF and its fingerprint never change.
                @elseif ($document->status === 'issued')
                    Issued {{ $day($document->issued_at) }}. This is the file that went out.
                @elseif ($document->status === 'sent_for_signature')
                    Issued {{ $day($document->issued_at) }} and sent to {{ $party?->name }} to countersign.
                @elseif ($document->status === 'fully_signed')
                    Signed by every party on {{ $day($document->fully_signed_at) }}. The signed PDF is the one to rely on.
                @elseif ($document->status === 'revoked')
                    Revoked {{ $day($document->revoked_at) }}: {{ $document->revoked_reason }}
                @else
                    Replaced by {{ $document->replacedBy?->reference }}. Anyone who checks it is told so.
                @endif
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ route('hq.letters.register') }}" wire:navigate><i data-lucide="arrow-left"></i> Register</a>
    </div>

    <x-flash class="mb-6" />
    @if ($flash)<div class="alert alert-success mb-6">{{ $flash }}</div>@endif

    <div class="doc-grid">
        <div>
            @if ($canEdit)
                <div class="card mb-6">
                    <h2 class="card-title">The document</h2>

                    <div class="field">
                        <label class="label" for="doc-title">Title</label>
                        <input id="doc-title" type="text" class="input" wire:model="title" maxlength="200">
                        @error('title')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="doc-two">
                        <div class="field">
                            <label class="label" for="doc-date">Date on the document</label>
                            <input id="doc-date" type="date" class="input" wire:model="documentDate">
                            <p class="help">Today in Lagos by default.</p>
                            @error('documentDate')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label class="label" for="doc-to">{{ $common['addressed_to']['label'] }}</label>
                            <input id="doc-to" type="text" class="input" wire:model="addressedTo" maxlength="200">
                            <p class="help">{{ $common['addressed_to']['help'] }}</p>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="doc-address">{{ $common['addressed_to_address']['label'] }}</label>
                        <textarea id="doc-address" class="input" rows="3" wire:model="addressedToAddress" maxlength="500"></textarea>
                    </div>

                    @foreach ($definitions as $key => $definition)
                        @php $id = 'f-' . $key; @endphp
                        @if (($definition['type'] ?? 'text') === 'people')
                            <div class="field" wire:key="field-{{ $key }}">
                                <span class="label">{{ $definition['label'] }}</span>
                                @if (! empty($definition['help']))<p class="help">{{ $definition['help'] }}</p>@endif
                                @foreach ($fields[$key] ?? [] as $index => $person)
                                    <div class="doc-people" wire:key="p-{{ $key }}-{{ $index }}">
                                        <div>
                                            <label class="sr-only" for="{{ $id }}-n-{{ $index }}">Name</label>
                                            <input id="{{ $id }}-n-{{ $index }}" type="text" class="input" placeholder="Name" wire:model="fields.{{ $key }}.{{ $index }}.name" maxlength="150">
                                        </div>
                                        <div>
                                            <label class="sr-only" for="{{ $id }}-t-{{ $index }}">Role</label>
                                            <input id="{{ $id }}-t-{{ $index }}" type="text" class="input" placeholder="Role" wire:model="fields.{{ $key }}.{{ $index }}.title" maxlength="150">
                                        </div>
                                        <button type="button" class="btn btn-secondary btn-sm" wire:click="removePerson('{{ $key }}', {{ $index }})" aria-label="Remove this person"><i data-lucide="trash-2"></i></button>
                                    </div>
                                @endforeach
                                <button type="button" class="btn btn-secondary btn-sm mt-3" wire:click="addPerson('{{ $key }}')"><i data-lucide="plus"></i> Add a signatory</button>
                            </div>
                        @elseif (($definition['type'] ?? 'text') === 'select')
                            <div class="field" wire:key="field-{{ $key }}">
                                <label class="label" for="{{ $id }}">{{ $definition['label'] }}</label>
                                <select id="{{ $id }}" class="input" wire:model.live="fields.{{ $key }}">
                                    @foreach ($definition['options'] as $value => $option)
                                        <option value="{{ $value }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                                @if (! empty($definition['help']))<p class="help">{{ $definition['help'] }}</p>@endif
                            </div>
                        @elseif (($definition['type'] ?? 'text') === 'textarea')
                            <div class="field" wire:key="field-{{ $key }}">
                                <label class="label" for="{{ $id }}">{{ $definition['label'] }}</label>
                                <textarea id="{{ $id }}" class="input" rows="{{ $definition['rows'] ?? 3 }}" wire:model="fields.{{ $key }}"></textarea>
                                @if (! empty($definition['help']))<p class="help">{{ $definition['help'] }}</p>@endif
                            </div>
                        @else
                            <div class="field" wire:key="field-{{ $key }}">
                                <label class="label" for="{{ $id }}">{{ $definition['label'] }}</label>
                                <input id="{{ $id }}" type="{{ ($definition['type'] ?? 'text') === 'date' ? 'date' : 'text' }}" class="input"
                                       wire:model="fields.{{ $key }}" placeholder="{{ $definition['placeholder'] ?? '' }}" maxlength="200">
                                @if (! empty($definition['help']))<p class="help">{{ $definition['help'] }}</p>@endif
                            </div>
                        @endif
                    @endforeach

                    <div class="doc-two">
                        <div class="field">
                            <label class="label" for="doc-salutation">{{ $common['salutation']['label'] }}</label>
                            <input id="doc-salutation" type="text" class="input" wire:model="fields.salutation" placeholder="{{ $common['salutation']['placeholder'] }}" maxlength="200">
                        </div>
                        <div class="field">
                            <label class="label" for="doc-closing">{{ $common['closing']['label'] }}</label>
                            <input id="doc-closing" type="text" class="input" wire:model="fields.closing" placeholder="{{ $common['closing']['placeholder'] }}" maxlength="200">
                        </div>
                    </div>

                    @if ($isAgreement)
                        {{-- The other party: who they are in print, and where their private signing link is sent --}}
                        <div class="field">
                            <span class="label">The other party</span>
                            <p class="help">They sign online, from a private link emailed to the address below. Their name and role are printed under their signature.</p>
                        </div>
                        <div class="doc-two">
                            @foreach (['name', 'role_title', 'email', 'company', 'registration'] as $key)
                                <div class="field" wire:key="cp-{{ $key }}">
                                    <label class="label" for="cp-{{ $key }}">{{ $counterpartyDefs[$key]['label'] }}</label>
                                    <input id="cp-{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" class="input"
                                           wire:model="counterparty.{{ $key }}" placeholder="{{ $counterpartyDefs[$key]['placeholder'] ?? '' }}" maxlength="200">
                                    @if (! empty($counterpartyDefs[$key]['help']))<p class="help">{{ $counterpartyDefs[$key]['help'] }}</p>@endif
                                    @error('counterparty.' . $key)<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        <div class="field">
                            <label class="label" for="cp-address">{{ $counterpartyDefs['address']['label'] }}</label>
                            <textarea id="cp-address" class="input" rows="3" wire:model="counterparty.address" maxlength="500"></textarea>
                            @error('counterparty.address')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    @if ($freeBody)
                        <div class="field">
                            <label class="label" for="doc-brief">Ask Claude for a draft</label>
                            <div class="doc-two" style="grid-template-columns: 1fr auto; align-items: start;">
                                <input id="doc-brief" type="text" class="input" wire:model="brief" maxlength="500"
                                       placeholder="One sentence: what is this letter for?">
                                <button type="button" class="btn btn-secondary" wire:click="draft" wire:loading.attr="disabled" wire:target="draft">
                                    <span wire:loading.remove wire:target="draft"><i data-lucide="sparkles"></i> Draft it</span>
                                    <span wire:loading wire:target="draft">Writing…</span>
                                </button>
                            </div>
                            <p class="help">Claude writes the body only. Read it, change what you want, then save.</p>
                            @error('brief')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="field" wire:ignore x-data="letterBody({ html: @js($body) })">
                            <span class="label">The letter</span>
                            <div class="doc-toolbar" role="toolbar" aria-label="Text format">
                                @foreach ($formats as [$command, $label, $keys, $paths])
                                    @php $click = str_starts_with($command, 'heading:') ? "heading('" . substr($command, 8) . "')" : "cmd('{$command}')"; @endphp
                                    <button type="button" class="doc-icon" @mousedown.prevent @click="{{ $click }}" aria-label="{{ $label }}" title="{{ $label }}{{ $keys ? " ({$keys})" : '' }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
                                    </button>
                                @endforeach
                            </div>
                            <div class="doc-editor" x-ref="ed" contenteditable="true" role="textbox" aria-multiline="true" aria-label="The letter"></div>
                        </div>
                    @endif

                    <div class="field">
                        <label class="doc-check">
                            <input type="checkbox" class="checkbox" wire:model="showQr">
                            <span>Add a QR code anyone can scan to check this document</span>
                        </label>
                        <p class="help">Off by default. The code carries a long random address, never the reference, and opens a page on jbspraxis.com.</p>
                    </div>

                    @if ($canManage)
                        <div class="field">
                            <label class="doc-check">
                                <input type="checkbox" class="checkbox" wire:model="copyPublic">
                                <span>Let the check page show a copy of this document</span>
                            </label>
                            <p class="help">Leave it off for anything confidential: the page then only confirms the reference, the date and who signed it.</p>
                        </div>
                    @endif

                    <div class="doc-actions">
                        <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save the draft</button>
                    </div>
                </div>
            @endif

            <div class="card mb-6">
                <h2 class="card-title">Signatures</h2>
                @if ($document->signatures->isEmpty())
                    <p class="help">Nobody has signed it yet. Without a signature the document prints a signature line for a pen.</p>
                @else
                    @foreach ($document->signatures as $signature)
                        <div class="doc-sign mb-3" wire:key="s-{{ $signature->id }}">
                            <img src="{{ $signature->dataUri() }}" alt="">
                            <span class="doc-sign-name">{{ $signature->signatory_name }}<small>{{ $signature->signatory_title }} · {{ $day($signature->signed_at) }}</small></span>
                        </div>
                    @endforeach
                @endif

                @if ($canSign)
                    <div class="doc-actions mt-3">
                        @if ($signedByMe)
                            <button type="button" class="btn btn-secondary" wire:click="removeSignature">Take my signature off</button>
                        @elseif ($mySignature)
                            <button type="button" class="btn btn-primary" wire:click="sign">Sign as {{ $mySignature->signatory_name }}, {{ $mySignature->signatory_title }}</button>
                        @else
                            <a class="btn btn-secondary" href="{{ route('hq.letters.signature') }}" wire:navigate>Save your signature first</a>
                        @endif
                    </div>
                    @error('signature')<p class="field-error">{{ $message }}</p>@enderror
                @endif
            </div>

            @if ($isAgreement)
                <div class="card mb-6">
                    <h2 class="card-title">The other party’s signature</h2>
                    @if (! $party || ! $party->name)
                        <p class="help">Fill the other party in above and save, then issue the agreement and send it to them.</p>
                    @else
                        <p class="doc-party mb-3">
                            <strong>{{ $party->name }}</strong>
                            {{ $party->role_title }}@if ($party->company) · {{ $party->company }}@endif<br>
                            {{ $party->email }}
                        </p>

                        @if ($party->hasSigned())
                            <div class="doc-sign mb-3">
                                @if ($party->dataUri())<img src="{{ $party->dataUri() }}" alt="">@endif
                                <span class="doc-sign-name">{{ $party->signed_name }}<small>{{ $party->role_title }} · {{ $day($party->signed_at) }}@if ($party->ip_address) · from {{ $party->ip_address }}@endif</small></span>
                            </div>
                            <p class="help">The signed PDF keeps a signature record page: who signed, when, from where, and the fingerprint of the agreement as it was issued.</p>
                        @elseif ($party->linkIsLive())
                            <p class="help">
                                Sent {{ $day($party->sent_at) }} ({{ $party->sent_count }} {{ $party->sent_count === 1 ? 'time' : 'times' }}).
                                The link works until {{ $day($party->token_expires_at) }}, then it has to be sent again.
                            </p>
                            @if ($signingUrl)<p class="doc-link mt-3">{{ $signingUrl }}</p>@endif
                        @else
                            <p class="help">They have not been sent anything yet.</p>
                        @endif

                        @if ($canSend && ! $party->hasSigned())
                            <div class="doc-actions mt-3">
                                @if ($cannotSend === null)
                                    <button type="button" class="btn btn-primary" wire:click="sendForSignature" wire:loading.attr="disabled" wire:target="sendForSignature">
                                        <i data-lucide="send"></i> {{ $party->sent_count > 0 ? 'Send the link again' : 'Send for signature' }}
                                    </button>
                                @endif
                                @if ($party->linkIsLive())
                                    <button type="button" class="btn btn-secondary" wire:click="cancelSigning">Take the link back</button>
                                @endif
                            </div>
                            @if ($cannotSend !== null)<p class="help mt-3">{{ $cannotSend }}</p>@endif
                            @error('signing')<p class="field-error">{{ $message }}</p>@enderror
                        @endif
                    @endif
                </div>
            @endif

            @if ($canManage)
                <div class="card mb-6">
                    <h2 class="card-title">Issuing</h2>
                    @if ($document->isDraft())
                        <p class="help">Issuing renders the PDF once and keeps it, with the fingerprint the check page compares a reader's copy against. After that the words cannot change: a mistake is put right by replacing it.</p>
                        <div class="doc-actions mt-3">
                            <button type="button" class="btn btn-primary" wire:click="issue" wire:loading.attr="disabled">Issue {{ $document->reference }}</button>
                        </div>
                    @else
                        <p class="doc-meta">
                            <span>Issued <strong>{{ $day($document->issued_at) }}</strong></span>
                            @if ($document->file_sha256)<span>SHA-256 <strong>{{ substr($document->file_sha256, 0, 16) }}…</strong></span>@endif
                            @if ($document->signed_file_sha256)<span>Signed SHA-256 <strong>{{ substr($document->signed_file_sha256, 0, 16) }}…</strong></span>@endif
                            @if ($document->replaces)<span>Replaces <strong>{{ $document->replaces->reference }}</strong></span>@endif
                        </p>
                        <div class="doc-actions mt-3">
                            <a class="btn btn-secondary" href="{{ route('hq.letters.pdf', ['document' => $document->id, 'download' => 1]) }}"><i data-lucide="download"></i> Download the {{ $hasSignedFile ? 'signed PDF' : 'PDF' }}</a>
                            @if ($hasSignedFile)
                                <a class="btn btn-secondary" href="{{ route('hq.letters.pdf', ['document' => $document->id, 'download' => 1, 'issued' => 1]) }}"><i data-lucide="file-clock"></i> The PDF as issued</a>
                            @endif
                            @if (in_array($document->status, ['issued', 'sent_for_signature', 'fully_signed'], true))
                                <button type="button" class="btn btn-secondary" wire:click="$set('revoking', true)">Revoke it</button>
                            @endif
                            @if (in_array($document->status, ['issued', 'sent_for_signature', 'fully_signed', 'revoked'], true))
                                <button type="button" class="btn btn-secondary" wire:click="replace">Replace it with a new version</button>
                            @endif
                            @if ($canFile && ! $document->isFiled())
                                <button type="button" class="btn btn-secondary" wire:click="fileAway" wire:loading.attr="disabled"><i data-lucide="folder-plus"></i> File in Company documents</button>
                            @endif
                        </div>
                        @if ($document->isFiled())
                            <p class="help mt-3">Filed in <a href="{{ route('hq.mdm.documents.show', ['document' => $document->company_document_id]) }}" wire:navigate>Company documents</a>, with the company's other papers.</p>
                        @endif
                        @error('filing')<p class="field-error">{{ $message }}</p>@enderror
                    @endif
                </div>
            @endif
        </div>

        <div class="doc-preview">
            <div class="card">
                <h2 class="card-title">Preview</h2>
                <p class="help">The real PDF, on the letterhead built from Master data › Legal entities.</p>
                <iframe class="doc-frame mt-3" src="{{ $pdfUrl }}" title="{{ $document->reference }}" wire:key="preview-{{ $previewVersion }}"></iframe>
                <div class="doc-actions mt-3">
                    <a class="btn btn-secondary btn-sm" href="{{ $pdfUrl }}" target="_blank" rel="noopener"><i data-lucide="external-link"></i> Open the PDF</a>
                </div>
            </div>
        </div>
    </div>

    @if ($needsPassword)
        <div class="modal-backdrop" wire:key="confirm">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="doc-confirm-title">
                <h2 class="modal-title" id="doc-confirm-title">Type your password again</h2>
                <p class="help">You signed in more than ten minutes ago. Applying a signature asks for your password, so a session left open cannot sign for you.</p>
                <div class="field">
                    <label class="label" for="doc-password">Password</label>
                    <input id="doc-password" type="password" class="input" wire:model="confirmPassword" wire:keydown.enter="confirmIdentity" autocomplete="current-password">
                    @error('confirmPassword')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="cancelConfirm">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmIdentity">Confirm and sign</button>
                </div>
            </div>
        </div>
    @endif

    @if ($revoking)
        <div class="modal-backdrop" wire:key="revoke">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="doc-revoke-title">
                <h2 class="modal-title" id="doc-revoke-title">Revoke {{ $document->reference }}</h2>
                <p class="help">The PDF already sent stays as it is, but anyone who scans or checks it will be told it was revoked, and on what date.</p>
                <div class="field">
                    <label class="label" for="doc-reason">Why</label>
                    <input id="doc-reason" type="text" class="input" wire:model="revokeReason" maxlength="490">
                    @error('revokeReason')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" wire:click="$set('revoking', false)">Cancel</button>
                    <button type="button" class="btn btn-primary" wire:click="revoke">Revoke it</button>
                </div>
            </div>
        </div>
    @endif
</div>
