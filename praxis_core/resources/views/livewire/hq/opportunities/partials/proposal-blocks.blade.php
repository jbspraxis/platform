{{-- A section's text in blocks (MeetingMinutesMail::blocks): paragraphs, and lists after an optional heading line --}}
@foreach (\App\Mail\MeetingMinutesMail::blocks((string) $text) as $block)
    @if (isset($block['items']))
        @if ($block['heading'])
            <strong>{{ $block['heading'] }}</strong>
        @endif
        <ul>
            @foreach ($block['items'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    @else
        <p>{!! nl2br(e($block['text'])) !!}</p>
    @endif
@endforeach
