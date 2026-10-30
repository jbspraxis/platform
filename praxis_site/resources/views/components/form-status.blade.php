{{-- Above every form: HQ unavailable or rate-limited (values kept), or the summary of a 422 --}}
@if (session('hq_error'))
    <div class="alert alert-warning" role="alert"><i data-lucide="triangle-alert"></i><p>{{ session('hq_error') }}</p></div>
@elseif ($errors->any())
    <div class="alert alert-danger" role="alert"><i data-lucide="circle-alert"></i><p>{{ $errors->first('form') ?: (session('form_summary') ?: __('site.form.summary')) }}</p></div>
@endif
