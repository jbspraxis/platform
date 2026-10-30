{{-- Praxis AI, the website assistant, on every page: a <details> panel and a plain form (POST /chat, CSRF,
     FormGuard). resources/js/app.js sends it with fetch and adds the reply in place. The last 20 turns live in
     the session; a reply's links (same-site paths only, App\Support\ChatLinks) are shown as buttons. --}}
@php
    $chatTurns = (array) session(\App\Http\Controllers\ChatController::SESSION_KEY, []);
    $chatErrors = $errors->getBag('chat');
    $chatNeedsCheck = ! \App\Http\Middleware\FormGuard::chatCleared(request());
@endphp
<details class="chat" id="chat" @if (session('chat_open') || $chatErrors->any()) open @endif>
    <summary><i data-lucide="message-circle"></i><span>{{ __('site.chat.open') }}</span></summary>
    <div class="chat-panel">
        <div class="chat-head">
            <h2>{{ __('site.chat.title') }}</h2>
            <p>{{ __('site.chat.intro') }}</p>
        </div>
        <ol class="chat-log" data-chat-log aria-live="polite">@foreach ($chatTurns as $turn)<li class="chat-turn {{ ($turn['role'] ?? '') === 'user' ? 'is-user' : 'is-assistant' }}"><span class="who">{{ ($turn['role'] ?? '') === 'user' ? __('site.chat.you') : __('site.chat.assistant') }}</span>@if (($turn['role'] ?? '') === 'user'){{ $turn['text'] ?? '' }}@else{!! \App\Support\ChatText::html((string) ($turn['text'] ?? '')) !!}@php $turnLinks = \App\Support\ChatLinks::clean($turn['links'] ?? []); @endphp @if ($turnLinks)<span class="chat-links" role="group" aria-label="{{ __('site.chat.links') }}">@foreach ($turnLinks as $link)<a class="btn btn-secondary btn-sm" href="{{ url($link['path']) }}">{{ $link['label'] }}</a>@endforeach</span>@endif @endif</li>@endforeach</ol>
        <form class="chat-form" method="POST" action="{{ route('chat') }}" data-chat-form
              data-you="{{ __('site.chat.you') }}" data-assistant="{{ __('site.chat.assistant') }}"
              data-links="{{ __('site.chat.links') }}"
              data-sending="{{ __('site.chat.sending') }}" data-failed="{{ __('site.chat.unavailable', ['email' => $site->email]) }}"
              data-expired="{{ __('site.errors.expired_body') }}">
            @csrf
            <x-form-guard form="chat" />
            <x-textarea name="chat_message" id="chat-message" :label="__('site.chat.message')" bag="chat" maxlength="1500" rows="3" />
            <p class="chat-status" data-chat-status role="status"></p>
            @if ($chatNeedsCheck)
                <x-turnstile />
            @endif
            <div class="actions">
                <button class="btn btn-primary btn-sm" type="submit"><i data-lucide="send"></i>{{ __('site.chat.send') }}</button>
            </div>
        </form>
    </div>
</details>
