{{-- The Praxis Method guide by email (POST /services → HQ /whitepaper). Used on Services and How we work; the
     confirmation shows on the page the visitor came from (ServicesController). --}}
<h2 class="band-title" id="guide-title">{{ $title }}</h2>
@if (session('sent') === 'services')
    <x-success :title="__('site.services.sent_title')" :again="url()->current() . '#guide'" :again-label="__('site.services.send_another')">
        <p>{{ __('site.services.sent_body') }}</p>
    </x-success>
@else
    @if ($text ?? null)
        <p class="band-text">{{ $text }}</p>
    @endif
    <form class="card form" method="POST" action="{{ route('services.store') }}" novalidate>
        @csrf
        <x-form-guard form="services" />
        <x-form-status />
        <input type="hidden" name="back" value="{{ \Illuminate\Support\Str::after((string) request()->route()?->getName(), 'fr.') === 'how-we-work' ? 'how-we-work' : 'services' }}">
        <x-field name="email" type="email" :label="__('site.services.email')" autocomplete="email" maxlength="100" />
        <x-turnstile />
        <div class="actions">
            <button class="btn btn-primary" type="submit"><i data-lucide="file-text"></i>{{ $send ?? __('site.services.send') }}</button>
        </div>
    </form>
@endif
