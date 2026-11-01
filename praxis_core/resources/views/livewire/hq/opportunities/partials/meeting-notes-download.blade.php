{{-- Download a meeting's notes as a Markdown file (MeetingNotesController): with the meeting's details, or with the full
     transcript for those who may read it. $t transcript, $m its meeting, $canReadTranscripts, $where tells the Meetings tab and
     the list of every transcript apart in element ids. --}}
@php($menuId = 'notes-download-' . $where . '-' . $t->id)
<div class="mtg-download" x-data="{ open: false }" @keydown.escape="if (open) { open = false; $refs.toggle.focus() }" @click.outside="open = false">
    <button type="button" class="btn btn-ghost btn-sm" x-ref="toggle" @click="open = ! open" aria-expanded="false" :aria-expanded="open.toString()" aria-controls="{{ $menuId }}"
            aria-label="{{ __('meetings.notes.download_label', ['title' => $m->title]) }}">
        <i data-lucide="download"></i> {{ __('meetings.notes.download') }} <i data-lucide="chevron-down"></i>
    </button>
    <ul class="mtg-download-menu" id="{{ $menuId }}" x-show="open" x-cloak style="display: none">
        <li>
            <a href="{{ route('hq.crm.meeting-notes', ['opportunity' => $m->opportunity_id, 'transcript' => $t->id, 'what' => 'notes']) }}" download @click="open = false">
                <i data-lucide="file-text"></i> {{ __('meetings.notes.download_notes') }}
            </a>
        </li>
        @if ($canReadTranscripts)
            <li>
                <a href="{{ route('hq.crm.meeting-notes', ['opportunity' => $m->opportunity_id, 'transcript' => $t->id, 'what' => 'transcript']) }}" download @click="open = false">
                    <i data-lucide="scroll-text"></i> {{ __('meetings.notes.download_transcript') }}
                </a>
            </li>
        @endif
    </ul>
</div>
