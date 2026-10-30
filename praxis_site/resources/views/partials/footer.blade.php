{{-- Built from GET /settings (App\Support\SiteSettings). Only registrations with a number are shown; social links are logos. --}}
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="footer-logo" href="{{ route('home') }}">
                    <img src="{{ asset('img/logo/jbs-praxis-horizontal-reversed.svg') }}" alt="{{ __('site.home_link') }}" width="141" height="28" loading="lazy">
                </a>
                <p>{{ __('site.tagline') }}</p>
                @if ($site->legalName)
                    <p class="footer-legal">{{ $site->legalName }}</p>
                @endif
            </div>

            <div>
                @if ($site->offices)
                    <h2>{{ __('site.footer.offices') }}</h2>
                    <ul>
                        @foreach ($site->offices as $office)
                            <li>{{ $office['city'] }}@if ($office['country'] !== ''), {{ $office['country'] }}@endif</li>
                        @endforeach
                    </ul>
                @endif
                @if ($site->registered())
                    <h2 class="footer-gap">{{ __('site.footer.registrations') }}</h2>
                    <ul>
                        @foreach ($site->registered() as $registration)
                            <li>
                                {{ $registration['label'] }}@if ($registration['country'] !== '') ({{ $registration['country'] }})@endif:
                                {{ $registration['number'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <h2>{{ __('site.footer.contact') }}</h2>
                <ul>
                    <li><a href="mailto:{{ $site->email }}">{{ $site->email }}</a></li>
                    @foreach ($site->phones as $phone)
                        <li>@if ($phone['label'] !== ''){{ $phone['label'] }}: @endif<a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone['number']) }}">{{ $phone['number'] }}</a></li>
                    @endforeach
                </ul>
                @if ($site->social)
                    <h2 class="footer-gap">{{ __('site.footer.follow') }}</h2>
                    <ul class="social-links">
                        @foreach ($site->social as $link)
                            <li><a class="social-link" href="{{ $link['url'] }}" rel="noopener" title="{{ $link['label'] }}"
                                   aria-label="{{ __('site.footer.social_label', ['network' => $link['label']]) }}">@include('partials.social-icon', ['network' => $link['network']])</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <h2>{{ __('site.footer.links') }}</h2>
                <ul>
                    <li><a href="{{ route('work') }}">{{ __('site.footer.work') }}</a></li>
                    <li><a href="{{ route('industries') }}">{{ __('site.footer.industries') }}</a></li>
                    <li><a href="{{ route('security') }}">{{ __('site.footer.security') }}</a></li>
                    <li><a href="{{ route('privacy') }}">{{ __('site.footer.privacy') }}</a></li>
                    <li><a href="{{ route('careers') }}">{{ __('site.footer.careers') }}</a></li>
                    <li><a href="{{ config('site.portal_url') }}/login">{{ __('site.nav.client_login') }}</a></li>
                    @if (config('site.ga4_id') !== '')
                        <li><button class="footer-button" type="button" data-consent-open>{{ __('site.footer.cookies') }}</button></li>
                    @endif
                </ul>
            </div>
        </div>

        <div class="footer-base">
            <p>{{ __('site.footer.copyright', ['year' => now()->year, 'name' => $site->companyName]) }}</p>
            @if (config('site.noindex'))
                <p>{{ __('site.footer.preview_note') }}</p>
            @endif
        </div>
    </div>
</footer>
