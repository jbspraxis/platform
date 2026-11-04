@extends('layouts.site')

@section('title', __('site.verify.title'))
@section('description', e(__('site.verify.description')))
@section('noindex', '1')

@php
    /** /verify/{token} (docs/DOCUMENTS.md section 7): what the QR code on a formal document opens. */
    $found = is_array($document);
    $status = (string) ($document['status'] ?? '');
    $valid = $status === 'issued';
    $signatories = (array) ($document['signatories'] ?? []);
    $sha = (string) ($document['file_sha256'] ?? '');
    $mark = $valid ? 'is-valid' : ($status === 'replaced' ? 'is-replaced' : 'is-stopped');

    // The kind in the page's own language, by type and then by type alone; HQ's English label when neither
    // is named here. The keys hold a dot themselves, so the list is read as an array rather than by path.
    $kinds = (array) __('site.verify.kinds');
    $type = (string) ($document['type'] ?? '');
    $kind = $kinds[$type . '.' . ($document['subtype'] ?? '')] ?? $kinds[$type] ?? (string) ($document['kind'] ?? '');
@endphp

@section('content')
    <div class="stack">
        <x-page-head :eyebrow="__('site.verify.eyebrow')" :title="__('site.verify.title')" />

        @unless ($found)
            <section class="card">
                <div class="vf-head">
                    <span class="vf-mark is-stopped" aria-hidden="true"><i data-lucide="circle-help"></i></span>
                    <div>
                        <h2 class="section-title vf-verdict">{{ __('site.verify.unknown.title') }}</h2>
                        <p class="muted vf-state">{{ __('site.verify.unknown.body') }}</p>
                    </div>
                </div>
            </section>

            <section class="card" aria-labelledby="vf-help">
                <h2 class="section-title" id="vf-help">{{ __('site.verify.help.title') }}</h2>
                <p>{{ __('site.verify.help.body') }}</p>
                <p><a class="btn btn-secondary" href="mailto:{{ $site->email }}"><i data-lucide="mail"></i> {{ $site->email }}</a></p>
            </section>
        @else

        <section class="card">
            <div class="vf-head">
                <span class="vf-mark {{ $mark }}" aria-hidden="true">
                    @if ($valid)
                        <i data-lucide="circle-check"></i>
                    @elseif ($status === 'replaced')
                        <i data-lucide="refresh-cw"></i>
                    @else
                        <i data-lucide="circle-x"></i>
                    @endif
                </span>
                <div>
                    <h2 class="section-title vf-verdict">{{ __('site.verify.genuine', ['company' => $document['issued_by'] ?? 'JBS Praxis']) }}</h2>
                    <p class="muted vf-state">
                        @if ($valid)
                            {{ __('site.verify.state.valid') }}
                        @elseif ($status === 'revoked')
                            {{ __('site.verify.state.revoked', ['date' => $revokedAt ?? '—']) }}
                        @else
                            {{ __('site.verify.state.replaced', ['reference' => $document['replaced_by'] ?? '—', 'date' => $replacedAt ?? '—']) }}
                        @endif
                    </p>
                </div>
            </div>

            @if ($status === 'revoked' && ! empty($document['revoked_reason']))
                <div class="alert alert-danger vf-alert" role="status">
                    <i data-lucide="circle-alert"></i>
                    <p>{{ __('site.verify.revoked_reason', ['reason' => $document['revoked_reason']]) }}</p>
                </div>
            @elseif ($status === 'replaced')
                <div class="alert alert-warning vf-alert" role="status">
                    <i data-lucide="info"></i>
                    <p>{{ __('site.verify.replaced_note', ['reference' => $document['replaced_by'] ?? '—']) }}</p>
                </div>
            @endif
        </section>

        <section class="card" aria-labelledby="vf-details">
            <h2 class="section-title" id="vf-details">{{ __('site.verify.details') }}</h2>
            <dl class="vf-facts">
                <dt>{{ __('site.verify.fields.reference') }}</dt>
                <dd class="vf-ref">{{ $document['reference'] ?? '—' }}</dd>

                <dt>{{ __('site.verify.fields.document') }}</dt>
                <dd>{{ $document['title'] ?: '—' }}<small>{{ $kind }}</small></dd>

                <dt>{{ __('site.verify.fields.date') }}</dt>
                <dd>{{ $documentDate ?? '—' }}@if ($issuedAt && $issuedAt !== $documentDate)<small>{{ __('site.verify.fields.issued_on', ['date' => $issuedAt]) }}</small>@endif</dd>

                <dt>{{ __('site.verify.fields.issued_by') }}</dt>
                <dd>{{ $document['issued_by'] ?? '—' }}</dd>

                @if (! empty($document['addressed_to']))
                    <dt>{{ __('site.verify.fields.addressed_to') }}</dt>
                    <dd>{{ $document['addressed_to'] }}</dd>
                @endif

                <dt>{{ __('site.verify.fields.signed_by') }}</dt>
                <dd>
                    @forelse ($signatories as $person)
                        {{ $person['name'] }}<small>{{ $person['title'] }}</small>
                    @empty
                        {{ __('site.verify.fields.not_signed') }}
                    @endforelse
                </dd>

                @if (! empty($document['replaces']))
                    <dt>{{ __('site.verify.fields.replaces') }}</dt>
                    <dd class="vf-ref">{{ $document['replaces'] }}</dd>
                @endif
            </dl>

            @if (! empty($document['copy_available']))
                <p class="vf-copy">
                    <a class="btn btn-secondary" href="{{ route('verify.file', ['token' => $token]) }}" target="_blank" rel="noopener">
                        <i data-lucide="file-text"></i> {{ __('site.verify.open_copy') }}
                    </a>
                </p>
            @else
                <p class="muted small vf-copy">{{ __('site.verify.no_copy') }}</p>
            @endif
        </section>

        @if ($sha !== '')
            {{-- The file the reader drops here is hashed in their own browser and never leaves their device --}}
            <section class="card" aria-labelledby="vf-tamper" data-verify-check data-expected="{{ $sha }}">
                <h2 class="section-title" id="vf-tamper">{{ __('site.verify.tamper.title') }}</h2>
                <p class="muted">{{ __('site.verify.tamper.explain') }}</p>

                <label class="vf-drop" data-verify-drop>
                    <strong>{{ __('site.verify.tamper.drop') }}</strong>
                    <span class="muted small">{{ __('site.verify.tamper.or_choose') }}</span>
                    <input type="file" accept="application/pdf,.pdf" class="sr-only" data-verify-input>
                </label>

                <p class="muted small vf-busy" data-verify-busy hidden>{{ __('site.verify.tamper.working') }}</p>

                <div class="alert alert-success vf-alert" role="status" data-verify-result="same" hidden>
                    <i data-lucide="circle-check"></i><p>{{ __('site.verify.tamper.same') }}</p>
                </div>
                <div class="alert alert-danger vf-alert" role="status" data-verify-result="different" hidden>
                    <i data-lucide="circle-alert"></i><p>{{ __('site.verify.tamper.different') }}</p>
                </div>
                <div class="alert alert-warning vf-alert" role="status" data-verify-result="failed" hidden>
                    <i data-lucide="info"></i><p>{{ __('site.verify.tamper.failed') }}</p>
                </div>

                <p class="vf-hash">{{ __('site.verify.tamper.issued_hash') }}<br>{{ $sha }}</p>
                <p class="vf-hash" data-verify-yours hidden>{{ __('site.verify.tamper.your_hash') }}<br><span data-verify-hash></span></p>
            </section>
        @endif

        <section class="card" aria-labelledby="vf-help">
            <h2 class="section-title" id="vf-help">{{ __('site.verify.help.title') }}</h2>
            <p>{{ __('site.verify.help.body') }}</p>
            <p><a class="btn btn-secondary" href="mailto:{{ $site->email }}"><i data-lucide="mail"></i> {{ $site->email }}</a></p>
        </section>
        @endunless
    </div>
@endsection
