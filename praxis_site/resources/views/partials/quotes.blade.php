{{-- Client quotes (redesign PR 3): only those the person approved in writing (config site.quotes_approved). None
     approved: nothing is rendered. A quote names the person only when it is allowed to (name not empty). --}}
@php($approved = array_values(array_filter((array) config('site.quotes_approved', []), fn ($key) => is_array(__('site.proof.quotes.' . $key)))))
@if ($approved)
    <section class="band band-paper" aria-labelledby="quotes-title">
        <div class="container">
            <x-section-head id="quotes-title" :title="__('site.proof.quotes_title')" rule />
            <ul class="quotes">
                @foreach ($approved as $key)
                    @php($quote = __('site.proof.quotes.' . $key))
                    <li class="quote">
                        <figure>
                            <blockquote><p>{{ $quote['text'] }}</p></blockquote>
                            <figcaption>
                                @if ($quote['name'] !== '')<span class="quote-name">{{ $quote['name'] }}</span>@endif
                                <span class="quote-role">{{ $quote['role'] }}, {{ $quote['org'] }}</span>
                            </figcaption>
                        </figure>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
