{{-- Every form (App\Http\Middleware\FormGuard): a honeypot that people never see, and the signed page-load time --}}
@props(['form'])
<div class="hp" aria-hidden="true">
    <label for="hp-{{ $form }}">{{ __('site.form.honeypot') }}</label>
    <input type="text" id="hp-{{ $form }}" name="{{ \App\Http\Middleware\FormGuard::HONEYPOT }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Support\FormStamp::FIELD }}" value="{{ \App\Support\FormStamp::make() }}">
