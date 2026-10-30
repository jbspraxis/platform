{{-- The Praxis Method's four stages in order (a real sequence, so they are numbered). $steps: name, text, optional get. --}}
<ol class="method">
    @foreach ($steps as $i => $step)
        <li class="method-step">
            <span class="method-number" aria-hidden="true">{{ $i + 1 }}</span>
            <h3>{{ $step['name'] }}</h3>
            <p>{{ $step['text'] }}</p>
            @isset($step['get'])
                <p class="method-get"><strong>{{ __('pages-extra.how.you_get') }}</strong> {{ $step['get'] }}</p>
            @endisset
        </li>
    @endforeach
</ol>
