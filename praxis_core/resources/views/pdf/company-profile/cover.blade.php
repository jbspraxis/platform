@php
    use App\Support\CompanyProfile\Assets;
    use App\Support\CompanyProfile\Layout;
    use App\Support\CompanyProfile\Typography;

    $lines = Typography::headlineLines((string) ($page['headline'] ?? ''));
@endphp
{{-- The cover: the horizontal lockup, the line, the device, the descriptor, and a great deal of air. --}}
<div class="pad">
    <img src="{{ Assets::brand('horizontal-colour.jpg') }}" style="width: 132pt;">

    <div style="height: 122pt; font-size: 0; line-height: 0;"></div>

    <p class="display" style="width: {{ Layout::columnWidth(9) }}pt;">
        @foreach ($lines as $line)
            {{ $line }}@if (! $loop->last)<br>@endif
        @endforeach
    </p>

    <img src="{{ Assets::path('device', 'bar-stack.svg') }}" style="width: 268pt; margin-top: 44pt;">

    <p class="lead ink-text" style="width: {{ Layout::columnWidth(6) }}pt; margin-top: 42pt;">
        {{ $page['descriptor'] ?? '' }}
    </p>
</div>
