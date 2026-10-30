{{-- The success state a form's page shows after Post/Redirect/Get --}}
@props(['title', 'again' => null, 'againLabel' => null])
<div class="card success-panel" role="status">
    <i data-lucide="circle-check" class="icon-ok"></i>
    <h2>{{ $title }}</h2>
    {{ $slot }}
    @if ($again)
        <div class="actions">
            <a class="btn btn-secondary" href="{{ $again }}">{{ $againLabel }}</a>
            <a class="btn btn-ghost" href="{{ route('home') }}">{{ __('site.errors.go_home') }}</a>
        </div>
    @endif
</div>
