{{-- An older message (past HQ's 12 months), read live from the mail server and not kept --}}
<div class="mr-toolbar" role="toolbar" aria-label="Message actions" wire:key="live-toolbar-{{ $item['uid'] }}">
    <button type="button" class="mr-icon-btn mr-back" wire:click="closeThread" aria-label="Back to the list" title="Back (u)">@include('livewire.hq.mail.read.icon', ['name' => 'arrow-left'])</button>
    <span class="help" style="margin: 0;">@include('livewire.hq.mail.read.icon', ['name' => 'server']) Read from the mail server: older than 12 months</span>
</div>

<div class="mr-reader-body" wire:key="live-{{ $item['uid'] }}">
    @if (! empty($item['error']))
        <div class="alert alert-warning" role="status">
            @include('livewire.hq.mail.read.icon', ['name' => 'circle-alert'])
            <span>The mail server didn't send this message. It may have been moved or deleted. <button type="button" class="btn btn-ghost btn-sm" wire:click="$refresh">Try again</button></span>
        </div>
    @else
        @php
            $from = $item['from_name'] ?: $item['from_email'] ?: 'Unknown sender';
            $body = $item['body'];
            $list = fn (array $people) => collect($people)->map(fn ($p) => ($p['name'] ?? null) ? $p['name'] . ' <' . ($p['email'] ?? '') . '>' : ($p['email'] ?? ''))->filter()->implode(', ');
        @endphp
        <h2 class="mr-subject">{{ $item['subject'] ?: '(no subject)' }}</h2>
        <article class="mr-msg is-open" aria-label="Message from {{ $from }}">
            <div class="mr-msg-head" style="cursor: default;">
                <span class="mr-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($from, 0, 1)) }}</span>
                <span class="mr-msg-from"><strong>{{ $from }}</strong>@if ($item['from_email'] && $item['from_email'] !== $from) <span>&lt;{{ $item['from_email'] }}&gt;</span>@endif</span>
                <span class="mr-msg-date">{{ $item['date_full'] }}</span>
                <span class="mr-msg-sub">to {{ \App\Livewire\Hq\Mail\Inbox::people($item['to'], '', 4) ?: 'undisclosed recipients' }}</span>
            </div>
            <div class="mr-msg-info">
                @if (count($item['to']))<span><b>To</b> {{ $list($item['to']) }}</span>@endif
                @if (count($item['cc']))<span><b>Cc</b> {{ $list($item['cc']) }}</span>@endif
                <span><b>In</b> {{ $item['folder'] }}</span>
            </div>
            <div class="mr-msg-body">
                @if (($body['blocked'] ?? 0) > 0 && ! $item['remote'])
                    <div class="mr-images">
                        @include('livewire.hq.mail.read.icon', ['name' => 'image'])
                        <span>Pictures from this sender are hidden to protect your privacy.</span>
                        <span class="mr-images-actions">
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="loadImages('live:{{ $item['uid'] }}')">Load pictures</button>
                            @if ($item['from_email'])
                                <button type="button" class="btn btn-ghost btn-sm" wire:click="trustSender('live:{{ $item['uid'] }}')">Always for this sender</button>
                            @endif
                        </span>
                    </div>
                @endif

                @if (($body['kind'] ?? '') === 'html')
                    <iframe class="mr-frame" title="Message from {{ $from }}" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                            referrerpolicy="no-referrer" x-data="mailFrame" wire:key="live-frame-{{ $item['uid'] }}-{{ $item['remote'] ? 1 : 0 }}"
                            srcdoc="{{ $body['doc'] }}"></iframe>
                @elseif (($body['kind'] ?? '') === 'text')
                    {!! $body['html'] !!}
                @else
                    <p class="help" style="margin: 0;">This message has no text.</p>
                @endif

                @if (count($item['attachments']))
                    <div class="mr-files" aria-label="Attachments">
                        @foreach ($item['attachments'] as $a)
                            <span class="mr-file" wire:key="la-{{ $a['section'] }}">
                                @include('livewire.hq.mail.read.icon', ['name' => 'paperclip'])
                                <span class="mr-file-name" title="{{ $a['filename'] }}">{{ $a['filename'] }}</span>
                                <span class="mr-file-size">{{ \App\Livewire\Hq\Mail\Inbox::size((int) $a['size']) }}</span>
                                @if (in_array(strtolower($a['mime']), ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'], true))
                                    <a class="mr-icon-btn" href="{{ $a['url'] }}?view=1" target="_blank" rel="noopener" aria-label="Open {{ $a['filename'] }}" title="Open {{ $a['filename'] }}">@include('livewire.hq.mail.read.icon', ['name' => 'eye'])</a>
                                @endif
                                <a class="mr-icon-btn" href="{{ $a['url'] }}" download aria-label="Download {{ $a['filename'] }}" title="Download {{ $a['filename'] }}">@include('livewire.hq.mail.read.icon', ['name' => 'download'])</a>
                            </span>
                        @endforeach
                    </div>
                @endif
                <p class="help" style="margin: 16px 0 0;">Replying to mail older than 12 months isn't available in HQ yet.</p>
            </div>
        </article>
    @endif
</div>
