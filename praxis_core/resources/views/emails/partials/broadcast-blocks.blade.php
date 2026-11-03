{{-- The blocks of a broadcast version (App\Support\Campaigns\Blocks), drawn with the email components. The person's details
     are already in the words; text blocks are sanitised HTML with the email's inline styles (RichText::forEmail). --}}
@foreach ($blocks as $block)
    @switch($block['type'])
        @case('heading')
            @if ($block['text'] !== '')<x-mail.h2>{{ $block['text'] }}</x-mail.h2>@endif
            @break
        @case('text')
            {!! $block['html'] !!}
            @break
        @case('image')
            @if ($block['src'] !== '')<x-mail.image :src="$block['src']" :alt="$block['alt']" :link="$block['link'] ?: null" />@endif
            @break
        @case('button')
            @if ($block['label'] !== '' && $block['url'] !== '')<x-mail.button :url="$block['url']" :label="$block['label']" />@endif
            @break
        @case('quote')
            @if ($block['text'] !== '')
                <x-mail.quote>{{ $block['text'] }}</x-mail.quote>
                @if ($block['source'] !== '')
                    <p style="margin: -12px 0 20px 18px; font-family: 'Plus Jakarta Sans', Arial, sans-serif; font-size: 14px; line-height: 22px; color: #5E6679;">{{ $block['source'] }}</p>
                @endif
            @endif
            @break
        @case('figures')
            <x-mail.figures :items="$block['items']" />
            @break
        @case('details')
            @php($rows = collect($block['rows'])->filter(fn ($r) => $r['value'] !== '')->mapWithKeys(fn ($r) => [$r['label'] !== '' ? $r['label'] : '·' => $r['value']])->all())
            @if ($rows)<x-mail.facts :rows="$rows" />@endif
            @break
        @case('divider')
            <x-mail.divider />
            @break
    @endswitch
@endforeach
