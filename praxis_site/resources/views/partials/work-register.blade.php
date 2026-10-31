{{-- Work as a register (App\Support\Work::CASES rows, text in lang work.cases): the name links to the case study and its
     link covers the row; then what we built, then sector, country and stage. $cases: slug => row. --}}
<ul class="register">
    @foreach ($cases as $slug => $case)
        <li class="register-row">
            <h3 class="register-name"><a href="{{ route('work.show', ['slug' => $slug]) }}">{{ __('work.cases.' . $slug . '.name') }}</a></h3>
            <p class="register-summary">{{ __('work.cases.' . $slug . '.summary') }}</p>
            <dl class="register-meta">
                <div><dt>{{ __('work.labels.sector') }}</dt><dd>{{ __('work.sectors.' . $case['sector'] . '.name') }}</dd></div>
                <div><dt>{{ __('work.labels.country') }}</dt><dd>{{ __('work.cases.' . $slug . '.country') }}</dd></div>
                <div><dt>{{ __('work.labels.stage') }}</dt><dd>{{ __('work.stages.' . $case['stage']) }}</dd></div>
            </dl>
        </li>
    @endforeach
</ul>
