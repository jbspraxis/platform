@php
    /** One message in the open conversation ($m from Inbox::readerMessages()) */
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', '', (string) $m['from']))))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '@';
    $list = fn (array $people) => collect($people)->map(fn ($p) => ($p['name'] ?? null) ? $p['name'] . ' <' . ($p['email'] ?? '') . '>' : ($p['email'] ?? ''))->filter()->implode(', ');
    $body = $m['body'];
@endphp
<article class="mr-msg {{ $m['open'] ? 'is-open' : '' }} {{ $m['model']->is_seen ? '' : 'is-unread' }}" wire:key="m-{{ $m['id'] }}" aria-label="Message from {{ $m['from'] }}">
    <button type="button" class="mr-msg-head" wire:click="toggleMessage({{ $m['id'] }})" aria-expanded="{{ $m['open'] ? 'true' : 'false' }}">
        @if (! empty($m['sender']['avatar_path']))
            {{-- A colleague with a photo (HQ only) --}}
            <x-hq.avatar :user="$m['sender']" class="mr-avatar" />
        @else
            <span class="mr-avatar" aria-hidden="true">{{ $initials }}</span>
        @endif
        <span class="mr-msg-from"><strong>{{ $m['from'] }}</strong>@if ($m['open'] && $m['from_email'] && $m['from_email'] !== $m['from']) <span>&lt;{{ $m['from_email'] }}&gt;</span>@endif</span>
        <span class="mr-msg-date" title="{{ $m['date_full'] }}">
            @if ($m['model']->is_flagged)@include('livewire.hq.mail.read.icon', ['name' => 'flag'])<span class="sr-only">Flagged</span>@endif
            @if ($m['attachments']->isNotEmpty() && ! $m['open'])@include('livewire.hq.mail.read.icon', ['name' => 'paperclip'])@endif
            {{ $m['date'] }}
        </span>
        <span class="mr-msg-sub">
            @if ($m['open'])
                to {{ \App\Livewire\Hq\Mail\Inbox::people($m['to'], '', 4) ?: 'undisclosed recipients' }}@if (count($m['cc'])), cc {{ \App\Livewire\Hq\Mail\Inbox::people($m['cc'], '', 3) }}@endif
            @else
                {{ $m['model']->snippet }}
            @endif
        </span>
    </button>

    @if ($m['open'])
        <div class="mr-msg-info">
            <span><b>Date</b> {{ $m['date_full'] }}</span>
            @if (count($m['to']))<span><b>To</b> {{ $list($m['to']) }}</span>@endif
            @if (count($m['cc']))<span><b>Cc</b> {{ $list($m['cc']) }}</span>@endif
            @if ($m['folder'])<span><b>In</b> {{ $m['folder'] }}</span>@endif
            @if ($m['sent_by'])<span><b>Sent from HQ by</b> {{ $m['sent_by'] }}</span>@endif
            @if ($m['opened_by'])<span><b>Opened by</b> {{ $m['opened_by'] }}</span>@endif
        </div>

        <div class="mr-msg-body">
            @if (($body['blocked'] ?? 0) > 0 && ! $m['remote'])
                <div class="mr-images" wire:key="img-{{ $m['id'] }}">
                    @include('livewire.hq.mail.read.icon', ['name' => 'image'])
                    <span>Pictures from this sender are hidden to protect your privacy.</span>
                    <span class="mr-images-actions">
                        <button type="button" class="btn btn-ghost btn-sm" wire:click="loadImages({{ $m['id'] }})">Load pictures</button>
                        @if ($m['from_email'])
                            <button type="button" class="btn btn-ghost btn-sm" wire:click="trustSender({{ $m['id'] }})">Always for this sender</button>
                        @endif
                    </span>
                </div>
            @endif

            @switch($body['kind'] ?? 'empty')
                @case('html')
                    <iframe class="mr-frame" title="Message from {{ $m['from'] }}" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                            referrerpolicy="no-referrer" x-data="mailFrame" wire:key="frame-{{ $m['id'] }}-{{ $m['remote'] ? 1 : 0 }}"
                            srcdoc="{{ $body['doc'] }}"></iframe>
                    @break
                @case('text')
                    {!! $body['html'] !!}
                    @break
                @case('error')
                    <div class="alert alert-warning" role="status">
                        @include('livewire.hq.mail.read.icon', ['name' => 'circle-alert'])
                        <span>The mail server didn't send this message's text. <button type="button" class="btn btn-ghost btn-sm" wire:click="$refresh">Try again</button></span>
                    </div>
                    @break
                @default
                    <p class="help" style="margin: 0;">This message has no text.</p>
            @endswitch

            @if ($m['attachments']->isNotEmpty())
                <div class="mr-files" aria-label="Attachments">
                    @foreach ($m['attachments'] as $a)
                        @php $viewable = in_array(strtolower($a->mime), ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'application/pdf', 'text/plain'], true); @endphp
                        <span class="mr-file" wire:key="a-{{ $a->id }}">
                            @include('livewire.hq.mail.read.icon', ['name' => 'paperclip'])
                            <span class="mr-file-name" title="{{ $a->filename }}">{{ $a->filename }}</span>
                            <span class="mr-file-size">{{ \App\Livewire\Hq\Mail\Inbox::size((int) $a->size) }}</span>
                            @if ($viewable)
                                <a class="mr-icon-btn" href="{{ route('hq.mail.read.attachment', ['attachment' => $a->id, 'view' => 1]) }}" target="_blank" rel="noopener" title="Open {{ $a->filename }}" aria-label="Open {{ $a->filename }}">@include('livewire.hq.mail.read.icon', ['name' => 'eye'])</a>
                            @endif
                            <a class="mr-icon-btn" href="{{ route('hq.mail.read.attachment', ['attachment' => $a->id]) }}" download title="Download {{ $a->filename }}" aria-label="Download {{ $a->filename }}">@include('livewire.hq.mail.read.icon', ['name' => 'download'])</a>
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($canSend)
                <div class="mr-msg-actions">
                    @if ($m['is_draft'])
                        <button type="button" class="btn btn-secondary btn-sm" @click="compose('draft', {{ $m['id'] }})">@include('livewire.hq.mail.read.icon', ['name' => 'pen']) Edit draft</button>
                    @else
                        <button type="button" class="btn btn-secondary btn-sm" @click="compose('reply', {{ $m['id'] }})">@include('livewire.hq.mail.read.icon', ['name' => 'reply']) Reply</button>
                        <button type="button" class="btn btn-secondary btn-sm" @click="compose('reply_all', {{ $m['id'] }})">@include('livewire.hq.mail.read.icon', ['name' => 'reply-all']) Reply all</button>
                        <button type="button" class="btn btn-secondary btn-sm" @click="compose('forward', {{ $m['id'] }})">@include('livewire.hq.mail.read.icon', ['name' => 'forward']) Forward</button>
                    @endif
                </div>
            @endif
        </div>
    @endif
</article>
