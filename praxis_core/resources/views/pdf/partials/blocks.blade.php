{{-- Text in blocks (MeetingMinutesMail::blocks): a paragraph, or a list after an optional heading line --}}
@foreach ($blocks as $block)
    @if (isset($block['items']))
        @if ($block['heading'])
            <h3>{{ $block['heading'] }}</h3>
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
