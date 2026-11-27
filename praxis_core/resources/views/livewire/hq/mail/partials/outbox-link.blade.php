{{-- Folder list: Outbox, below Drafts. Not an IMAP folder: a link to Scheduled and unsent (HQ's own outbox). --}}
<li wire:key="folder-outbox">
    <a href="{{ route('hq.mail.compose.outbox') }}" class="mr-nav-btn" wire:navigate>
        @include('livewire.hq.mail.read.icon', ['name' => 'clock'])
        <span class="mr-nav-name">Outbox</span>
        @if ($outboxCount > 0)<span class="mr-nav-count" aria-label="{{ $outboxCount }} waiting">{{ $outboxCount }}</span>@endif
    </a>
</li>
