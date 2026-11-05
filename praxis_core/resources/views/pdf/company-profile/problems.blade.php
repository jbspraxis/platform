@php
    use App\Support\CompanyProfile\Layout;

    $problemWidth = Layout::columnWidth(Layout::PROBLEM_COLUMNS);
    $answerWidth = Layout::answerWidth();
@endphp
{{--
    The heart of the book, and the one page where the device does the work rather than decorating it: the
    rule is Ink across the problem and turns Teal exactly where the answer starts, on the column the
    answers are set in. Everything below it is hairlines, so the rule is still said once.

    An open set: the domains are listed, never counted, and the list carries on over as many pages as the
    content needs.
--}}
<div class="pad">
    @if ($page['first'] ?? false)
        <div class="cols">
            <div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
                <p class="h1">{{ $page['title'] ?? '' }}</p>
            </div><div class="gap"></div><div class="col" style="width: {{ Layout::columnWidth(6) }}pt;">
                <p class="lead">{{ $page['lead'] ?? '' }}</p>
            </div>
        </div>
        <div style="height: 30pt; font-size: 0; line-height: 0;"></div>
    @else
        <p class="meta">{{ $page['title'] ?? '' }} &nbsp;·&nbsp; {{ $words['continued'] }}</p>
        <div style="height: 16pt; font-size: 0; line-height: 0;"></div>
    @endif

    @include('pdf.company-profile.rule', [
        'left' => $problemWidth + Layout::GUTTER,
        'right' => $answerWidth,
    ])

    @foreach ($page['groups'] ?? [] as $group)
        <p class="h3" style="margin-top: {{ $loop->first ? 22 : 26 }}pt;">
            {{ $group['domain'] ?? '' }}@if ($group['continued'] ?? false)<span class="meta"> &nbsp;{{ $words['continued'] }}</span>@endif
        </p>

        @foreach ($group['items'] ?? [] as $item)
            @unless ($loop->first)
                <div style="height: 9pt; font-size: 0; line-height: 0;"></div>
                <div class="hair"></div>
                <div style="height: 10pt; font-size: 0; line-height: 0;"></div>
            @else
                <div style="height: 11pt; font-size: 0; line-height: 0;"></div>
            @endunless
            <div class="cols">
                <div class="col" style="width: {{ $problemWidth }}pt;">
                    <p class="serif">{{ $item['problem'] ?? '' }}</p>
                </div><div class="gap"></div><div class="col" style="width: {{ $answerWidth }}pt;">
                    <p class="small">{{ $item['answer'] ?? '' }}</p>
                </div>
            </div>
        @endforeach
    @endforeach

    @if (($page['closing'] ?? '') !== '')
        <div style="height: 26pt; font-size: 0; line-height: 0;"></div>
        <div class="hair"></div>
        <p class="serif" style="width: {{ Layout::columnWidth(8) }}pt; margin-top: 16pt;">{{ $page['closing'] }}</p>
    @endif
</div>
