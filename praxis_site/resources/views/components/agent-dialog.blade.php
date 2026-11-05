{{-- A page form, answered as a conversation. The opener that carries data-agent-open="{{ $form }}" opens it;
     without JavaScript that opener stays what it is and the page form below is the way in.

     It *is* the page's form: the same action, CSRF token, honeypot, signed page-load time and Turnstile, with
     one field asked the ordinary way and every other answer written into a hidden input by resources/js/agent.
     The last step submits it normally, so nothing on the server changes.

     This is a separate component from partials/calculator-dialog on purpose. The calculator is live and keeps
     its own dialog and its own engine, so nothing here can change how it behaves.

     $strings is the form's lang block (site.agent.{form}); $lists is value/label pairs per select. --}}
@props([
    'form',            // the flow's name in resources/js/agent/forms
    'action',          // where the page form posts
    'guard' => null,   // the form-guard name; defaults to "{form}-agent" so ids stay unique beside the page form
    'strings' => [],
    'lists' => [],
    'gate',            // ['name' => …, 'kind' => 'text'|'email'|'longtext', 'label' => …, 'hint' => …]
    'enctype' => null,
    'files' => [],     // real file inputs this form needs: ['cv' => ['accept' => '.pdf,.doc', 'label' => …]]
])
@php
    // Lists are given the way the page already has them (value => label, from lang or from HQ); the engine
    // wants them as value/label pairs so a tapped pill can carry the stored value, not the visible words.
    $agentLists = [];
    foreach ((array) $lists as $listName => $rows) {
        $agentLists[$listName] = [];
        foreach ((array) $rows as $value => $label) {
            // Already a row (countries have to be: several share a dialling code, and a map keyed by the
            // code would keep only the last of each)
            if (is_array($label)) {
                $agentLists[$listName][] = ['value' => (string) ($label['value'] ?? ''), 'label' => (string) ($label['label'] ?? '')];
                continue;
            }
            $agentLists[$listName][] = ['value' => (string) $value, 'label' => (string) $label];
        }
    }
@endphp
<dialog class="agent" data-agent-dialog aria-labelledby="agent-{{ $form }}-title"
        data-form="{{ $form }}"
        data-locale="{{ app()->getLocale() }}"
        data-strings="{{ json_encode($strings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        data-lists="{{ json_encode($agentLists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <form class="agent-shell" method="POST" action="{{ $action }}" data-agent-form novalidate
          @if ($enctype) enctype="{{ $enctype }}" @endif>
        @csrf
        {{-- The guard's name only keeps the honeypot's id unique beside the page form; the middleware's own
             form name comes from the route, not from here --}}
        <x-form-guard :form="$guard ?? $form . '-agent'" />
        <div data-agent-hidden hidden></div>

        {{-- A File cannot be written into a hidden input from script, so the real one lives here and the
             visitor picks on it; the browser then posts it from where they chose it. It is placed off
             screen rather than display:none, so that clicking it from the conversation works everywhere. --}}
        @foreach ((array) $files as $fileName => $file)
            <label class="agent-file" for="agent-{{ $form }}-{{ $fileName }}">
                <span>{{ $file['label'] ?? $fileName }}</span>
                <input type="file" id="agent-{{ $form }}-{{ $fileName }}" name="{{ $fileName }}"
                       accept="{{ $file['accept'] ?? '' }}" tabindex="-1">
            </label>
        @endforeach

        <header class="agent-head">
            <div class="agent-head-row">
                <p class="agent-mark" id="agent-{{ $form }}-title">
                    <i data-lucide="{{ $strings['icon'] ?? 'message-square-text' }}"></i><span>{{ $strings['title'] }}</span>
                </p>
                <button class="agent-close" type="button" data-agent-close aria-label="{{ $strings['close'] }}" title="{{ $strings['close'] }}"><i data-lucide="x"></i></button>
            </div>

            {{-- Where they are: one segment per step, each filling as its fields are answered --}}
            <ol class="agent-rail" data-agent-rail hidden>
                @foreach ((array) ($strings['steps'] ?? []) as $key => $label)
                    <li class="agent-rail-step" data-step="{{ $key }}"><span class="agent-rail-name">{{ $label }}</span></li>
                @endforeach
            </ol>

            {{-- What they have said so far: every one of them a button back to that question --}}
            <div class="agent-chips" data-agent-chips hidden role="group" aria-label="{{ $strings['confirm']['heading'] }}"></div>
        </header>

        <div class="agent-body" data-agent-body data-lenis-prevent>
            {{-- The one field asked the ordinary way, before any of the talking --}}
            <section class="agent-gate" data-agent-gate>
                <h2 class="agent-gate-title">{{ $strings['gate']['heading'] }}</h2>
                <p class="agent-gate-lead">{{ $strings['gate']['lead'] }}</p>
                <div class="field agent-gate-field">
                    <label class="label" for="agent-{{ $form }}-gate">{{ $gate['label'] }}</label>
                    @if (! empty($gate['hint']))
                        <p class="help" id="agent-{{ $form }}-gate-hint">{{ $gate['hint'] }}</p>
                    @endif
                    @if (($gate['kind'] ?? 'text') === 'longtext')
                        <textarea class="textarea" id="agent-{{ $form }}-gate" name="{{ $gate['name'] }}" rows="4"
                                  maxlength="{{ $gate['max'] ?? 5000 }}" aria-required="true" data-agent-gate-input
                                  aria-describedby="agent-{{ $form }}-gate-hint agent-{{ $form }}-gate-error"></textarea>
                    @else
                        <input class="input" id="agent-{{ $form }}-gate" name="{{ $gate['name'] }}"
                               type="{{ ($gate['kind'] ?? 'text') === 'email' ? 'email' : 'text' }}"
                               @if (($gate['kind'] ?? 'text') === 'email') inputmode="email" autocomplete="email" @endif
                               maxlength="{{ $gate['max'] ?? 255 }}" aria-required="true" data-agent-gate-input
                               aria-describedby="agent-{{ $form }}-gate-hint agent-{{ $form }}-gate-error">
                    @endif
                    <p class="field-error" id="agent-{{ $form }}-gate-error" data-agent-gate-error role="alert"></p>
                </div>
                <button class="btn btn-primary btn-lg agent-start" type="button" data-agent-start>
                    <span>{{ $strings['gate']['start'] }}</span><i data-lucide="arrow-right"></i>
                </button>
            </section>

            <ol class="agent-log" data-agent-log aria-live="polite"></ol>
        </div>

        <footer class="agent-foot" data-agent-foot hidden>
            <div class="agent-choices" data-agent-choices role="group" aria-label="{{ $strings['say'] }}" hidden></div>
            <div class="agent-composer">
                <label class="sr-only" for="agent-{{ $form }}-say">{{ $strings['say'] }}</label>
                <textarea class="agent-input" id="agent-{{ $form }}-say" data-agent-input rows="1" maxlength="5000"
                          autocomplete="off" placeholder="{{ $strings['placeholder'] }}"></textarea>
                <button class="agent-send" type="button" data-agent-send aria-label="{{ $strings['send'] }}" title="{{ $strings['send'] }}"><i data-lucide="arrow-up"></i></button>
            </div>
            <p class="agent-status" data-agent-status role="status"></p>
            {{-- Rendered from the start so Cloudflare's script finds it, shown only at the last step --}}
            <div class="agent-guard" data-agent-guard><x-turnstile /></div>
        </footer>
    </form>
</dialog>
