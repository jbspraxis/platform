@props(['show' => false])
@if ($show)
    <div class="alert alert-info" role="status"><i data-lucide="info"></i><p>{{ __('site.errors.stale') }}</p></div>
@endif
