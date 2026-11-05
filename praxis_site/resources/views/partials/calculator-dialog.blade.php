{{-- The cost of poor quality calculator as a conversation. The button in partials/calculator-band opens it;
     without JavaScript that button stays what it is, a link to /cost-calculator, and this dialog is never used.

     It *is* the calculator's form: the same action, the same CSRF token, honeypot, signed page-load time and
     Turnstile as the page form, with the work email asked for in the ordinary way and every other answer written
     into a hidden input by resources/js/calculator. The last step submits it normally, so nothing on the server
     changes (App\Http\Controllers\CalculatorController).

     $calcLists comes from the view composer in App\Providers\AppServiceProvider: HQ's lists, cached, with the
     last good copy served while HQ is down. With no lists at all the opener is left alone and the link stands. --}}
@php
    $chat = (array) __('site.calculator.chat');
@endphp
<dialog class="agent" data-calc-dialog aria-labelledby="agent-title"
        data-locale="{{ app()->getLocale() }}"
        data-strings="{{ json_encode($chat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
        data-lists="{{ json_encode($calcLists ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <form class="agent-shell" method="POST" action="{{ route('calculator.store') }}" data-calc-form novalidate>
        @csrf
        {{-- "calculator-agent" only keeps the honeypot's id unique where this sits beside the page form;
             the middleware's own form name comes from the route (guard:calculator), not from here --}}
        <x-form-guard form="calculator-agent" />
        {{-- Every answer after the email, as the inputs the controller already expects --}}
        <div data-calc-hidden hidden></div>

        <header class="agent-head">
            <div class="agent-head-row">
                <p class="agent-mark" id="agent-title"><i data-lucide="calculator"></i><span>{{ $chat['title'] }}</span></p>
                <button class="agent-close" type="button" data-calc-close aria-label="{{ $chat['close'] }}" title="{{ $chat['close'] }}"><i data-lucide="x"></i></button>
            </div>

            {{-- Where they are: six steps, each filling as its fields are answered --}}
            <ol class="agent-rail" data-calc-rail hidden>
                @foreach ((array) $chat['steps'] as $key => $label)
                    <li class="agent-rail-step" data-step="{{ $key }}"><span class="agent-rail-name">{{ $label }}</span></li>
                @endforeach
            </ol>

            {{-- What they have said so far: every one of them a button back to that question --}}
            <div class="agent-chips" data-calc-chips hidden role="group" aria-label="{{ $chat['confirm']['heading'] }}"></div>
        </header>

        <div class="agent-body" data-calc-body data-lenis-prevent>
            {{-- The one field asked the ordinary way, before any of the talking --}}
            <section class="agent-gate" data-calc-gate>
                <h2 class="agent-gate-title">{{ $chat['gate']['heading'] }}</h2>
                <p class="agent-gate-lead">{{ $chat['gate']['lead'] }}</p>
                <div class="field agent-gate-field">
                    <label class="label" for="agent-email">{{ __('site.calculator.email') }}</label>
                    <p class="help" id="agent-email-hint">{{ $chat['gate']['hint'] }}</p>
                    <input class="input" type="email" id="agent-email" name="email" inputmode="email"
                           autocomplete="email" maxlength="150" aria-required="true"
                           aria-describedby="agent-email-hint agent-email-error">
                    <p class="field-error" id="agent-email-error" data-calc-gate-error role="alert"></p>
                </div>
                <button class="btn btn-primary btn-lg agent-start" type="button" data-calc-start>
                    <span>{{ $chat['gate']['start'] }}</span><i data-lucide="arrow-right"></i>
                </button>
            </section>

            <ol class="agent-log" data-calc-log aria-live="polite"></ol>
        </div>

        <footer class="agent-foot" data-calc-foot hidden>
            <div class="agent-choices" data-calc-choices role="group" aria-label="{{ $chat['say'] }}" hidden></div>
            <div class="agent-composer">
                <label class="sr-only" for="agent-say">{{ $chat['say'] }}</label>
                <textarea class="agent-input" id="agent-say" data-calc-input rows="1" maxlength="400"
                          autocomplete="off" placeholder="{{ $chat['placeholder'] }}"></textarea>
                <button class="agent-send" type="button" data-calc-send aria-label="{{ $chat['send'] }}" title="{{ $chat['send'] }}"><i data-lucide="arrow-up"></i></button>
            </div>
            <p class="agent-status" data-calc-status role="status"></p>
            {{-- Rendered from the start so Cloudflare's script finds it, shown only at the last step --}}
            <div class="agent-guard" data-calc-guard><x-turnstile /></div>
        </footer>
    </form>
</dialog>
