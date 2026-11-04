{{-- The cost of poor quality calculator as a step in the journey (redesign PR 3): what it counts and its three real
     steps (pages/calculator), then one button. On the homepage and every case study, before the final call to action. --}}
<section class="band band-paper" aria-labelledby="calculator-title">
    <div class="container calculator-band">
        <div class="calculator-band-text">
            <h2 class="band-title" id="calculator-title">{{ __('pages.home.calculator_title') }}</h2>
            <p class="band-text">{{ __('pages.home.calculator_text') }}</p>
            <p class="calculator-counts">{{ __('pages.home.calculator_counts') }}</p>
            <a class="btn btn-secondary btn-lg" href="{{ route('calculator') }}"><i data-lucide="calculator"></i>{{ __('pages.home.calculator_cta') }}</a>
        </div>
        <ol class="calculator-steps">
            @foreach (__('pages.home.calculator_steps') as $step)
                <li>
                    <h3>{{ $step['title'] }}</h3>
                    <p>{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
