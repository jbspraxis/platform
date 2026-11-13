{{-- Writing in a project's conversation (docs/CHAT.md), shared by the Client Portal and HQ. $party ('client' | 'staff'),
     $canWrite, $chatKey, $files (uploads waiting to be sent), $quote (the message being answered, ProjectChat::quote()),
     for a client $issueMode and $tasks, for staff $internal. Enter sends, Shift + Enter makes a new line (phones: the
     send button). Files can be picked, pasted or dropped; voice notes are recorded in the browser (resources/js/chat.js). --}}
@php
    // A short, professional set; the phone's own keyboard has every other emoji
    $emoji = ['thumbs_up' => '👍', 'thanks' => '🙏', 'done' => '✅', 'ok' => '👌', 'smile' => '😊', 'slight_smile' => '🙂', 'grin' => '😀', 'relief' => '😅',
        'thinking' => '🤔', 'clap' => '👏', 'celebrate' => '🎉', 'handshake' => '🤝', 'wave' => '👋', 'raised_hands' => '🙌', 'eyes' => '👀', 'idea' => '💡',
        'pin' => '📌', 'clip' => '📎', 'calendar' => '📅', 'clock' => '⏰', 'phone' => '📞', 'speech' => '💬', 'warning' => '⚠️', 'question' => '❓'];
    $issueMode = $issueMode ?? false;
    $internal = $internal ?? false;
    $accept = '.' . str_replace(',', ',.', \App\Support\Chat\ProjectChat::FILE_MIMES);
    $fileError = $errors->first('files') ?: $errors->first('files.*') ?: $errors->first('incoming.*');
@endphp

@if (! $canWrite)
    <div class="chat-readonly" role="note"><i data-lucide="eye" aria-hidden="true"></i> {{ $party === 'client' ? __('chat.no_access') : 'Your role lets you read client messages but not reply. Ask an administrator to change your role.' }}</div>
@else
    <div class="chat-composer {{ $issueMode ? 'is-issue' : '' }} {{ $internal ? 'is-internal' : '' }}" :class="{ 'is-dragging': dragging }"
         @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop($event)">
        <div class="chat-drop" x-show="dragging" style="display: none;" aria-hidden="true"><i data-lucide="upload"></i> {{ __('chat.drop') }}</div>

        @if ($issueMode)
            {{-- Reporting an issue: what it is about, a subject and a priority; the message box holds the details --}}
            <div class="chat-issue-form" role="group" aria-labelledby="chat-{{ $chatKey }}-issue-title">
                <div class="chat-issue-form-head">
                    <span id="chat-{{ $chatKey }}-issue-title" class="chat-issue-form-title"><i data-lucide="shield-alert" aria-hidden="true"></i> {{ __('chat.issue.heading') }}</span>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="cancelIssue">{{ __('chat.issue.cancel') }}</button>
                </div>
                <p class="chat-issue-form-help">{{ __('chat.issue.help') }}</p>
                <div class="chat-issue-grid">
                    <div class="field chat-issue-task">
                        <label for="chat-{{ $chatKey }}-task" class="label">{{ __('chat.issue.task') }}</label>
                        <select id="chat-{{ $chatKey }}-task" wire:model="issueTaskId" class="select">
                            <option value="">{{ __('chat.issue.no_task') }}</option>
                            @foreach ($tasks as $task)
                                <option value="{{ $task->id }}">M{{ $task->phase_number }} · {{ $task->title }}</option>
                            @endforeach
                        </select>
                        @error('issueTaskId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field chat-issue-subject">
                        <label for="chat-{{ $chatKey }}-subject" class="label">{{ __('chat.issue.subject') }}</label>
                        <input type="text" id="chat-{{ $chatKey }}-subject" wire:model="issueTitle" class="input" maxlength="150" autocomplete="off">
                        @error('issueTitle') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field chat-issue-priority">
                        <label for="chat-{{ $chatKey }}-priority" class="label">{{ __('chat.issue.priority') }}</label>
                        <select id="chat-{{ $chatKey }}-priority" wire:model="issueSeverity" class="select">
                            @foreach (['Low', 'Medium', 'Critical'] as $severity)
                                <option value="{{ $severity }}">{{ __('chat.severity.' . $severity) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        @if ($quote)
            <div class="chat-replying {{ $quote['party'] === 'staff' ? 'is-staff' : 'is-client' }}">
                <div class="min-w-0">
                    <div class="chat-replying-name">{{ __('chat.replying_to', ['name' => $quote['party'] === $party && $quote['author_id'] === $me ? __('chat.you') : $quote['name']]) }}</div>
                    <div class="chat-replying-text">{{ $quote['kind'] === 'issue' ? $quote['title'] : ($quote['text'] !== '' ? $quote['text'] : ($quote['file'] ? __('chat.summary.' . $quote['file']) : '')) }}</div>
                </div>
                <button type="button" class="chat-tool" wire:click="cancelReply" aria-label="{{ __('chat.cancel_reply') }}" title="{{ __('chat.cancel_reply') }}"><i data-lucide="x"></i></button>
            </div>
        @endif

        @if ($internal)
            <div class="chat-internal-note" role="status"><i data-lucide="lock" aria-hidden="true"></i> {{ __('chat.internal_on') }}</div>
        @endif

        @if ($files)
            <ul class="chat-pending" aria-label="{{ __('chat.attach') }}">
                @foreach ($files as $i => $file)
                    @php
                        $preview = null;
                        try {
                            $preview = method_exists($file, 'isPreviewable') && $file->isPreviewable() ? $file->temporaryUrl() : null;
                        } catch (\Throwable) {
                            $preview = null; // no preview: the name shows
                        }
                        $name = method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : __('chat.summary.file');
                    @endphp
                    <li class="chat-pending-item" wire:key="pending-{{ $i }}-{{ md5($name) }}">
                        @if ($preview)
                            <img src="{{ $preview }}" alt="" class="chat-pending-thumb">
                        @else
                            <span class="chat-pending-icon" aria-hidden="true"><i data-lucide="file"></i></span>
                        @endif
                        <span class="chat-pending-name">{{ $name }}</span>
                        <button type="button" class="chat-pending-remove" wire:click="removeFile({{ $i }})" aria-label="{{ __('chat.remove_file', ['name' => $name]) }}" title="{{ __('chat.remove_file', ['name' => $name]) }}"><i data-lucide="x"></i></button>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="chat-progress" x-show="uploading" style="display: none;" role="status">
            <span class="chat-progress-bar"><span :style="'width:' + progress + '%'"></span></span>
            <span class="chat-progress-text">{{ __('chat.uploading') }}</span>
        </div>

        @if ($errors->first('draft') || $fileError || $errors->first('voice'))
            <p class="chat-error" role="alert"><i data-lucide="circle-alert" aria-hidden="true"></i> {{ $errors->first('draft') ?: $fileError ?: $errors->first('voice') }}</p>
        @endif
        <p class="chat-error" role="alert" x-show="micError" x-text="micError" style="display: none;"></p>

        {{-- Recording a voice note --}}
        <div class="chat-recording" x-show="recording" style="display: none;" role="status" aria-live="polite">
            <button type="button" class="chat-tool" @click="stopRecording(false)" aria-label="{{ __('chat.voice_discard') }}" title="{{ __('chat.voice_discard') }}"><i data-lucide="trash-2"></i></button>
            <span class="chat-recording-dot" aria-hidden="true"></span>
            <span class="chat-recording-label">{{ __('chat.recording') }}</span>
            <span class="chat-recording-time" x-text="clock(recordMs)">0:00</span>
            <span class="chat-recording-limit">{{ __('chat.voice_limit') }}</span>
            <button type="button" class="chat-send" @click="stopRecording(true)" aria-label="{{ __('chat.voice_send') }}" title="{{ __('chat.voice_send') }}"><i data-lucide="send-horizontal"></i></button>
        </div>

        <div class="chat-compose-row" x-show="!recording">
            <div class="chat-tools">
                <label class="chat-tool" for="chat-{{ $chatKey }}-file" title="{{ __('chat.attach') }}">
                    <i data-lucide="paperclip" aria-hidden="true"></i><span class="sr-only">{{ __('chat.attach') }}</span>
                </label>
                <input type="file" id="chat-{{ $chatKey }}-file" class="chat-file-input" multiple accept="{{ $accept }},image/*" aria-describedby="chat-{{ $chatKey }}-files-help"
                       @change="addFiles($event.target.files); $event.target.value = ''">
                <span id="chat-{{ $chatKey }}-files-help" class="sr-only">{{ __('chat.files_help') }}</span>

                <div class="chat-emoji" @click.outside="emojiOpen = false">
                    <button type="button" class="chat-tool" @click="emojiOpen = !emojiOpen" :aria-expanded="emojiOpen.toString()" aria-expanded="false"
                            aria-controls="chat-{{ $chatKey }}-emoji" title="{{ __('chat.emoji') }}">
                        <i data-lucide="smile" aria-hidden="true"></i><span class="sr-only">{{ __('chat.emoji') }}</span>
                    </button>
                    <div id="chat-{{ $chatKey }}-emoji" class="chat-emoji-panel" x-show="emojiOpen" x-transition.opacity style="display: none;"
                         role="group" aria-label="{{ __('chat.emoji_title') }}" @keydown.escape.stop="emojiOpen = false; $refs.input.focus()">
                        @foreach ($emoji as $key => $char)
                            <button type="button" class="chat-emoji-btn" @click="insertEmoji(@js($char))" aria-label="{{ __('chat.emoji_names.' . $key) }}" title="{{ __('chat.emoji_names.' . $key) }}">{{ $char }}</button>
                        @endforeach
                    </div>
                </div>

                @if ($party === 'client' && ! $issueMode)
                    <button type="button" class="chat-tool" wire:click="startIssue" title="{{ __('chat.issue.report') }}">
                        <i data-lucide="shield-alert" aria-hidden="true"></i><span class="sr-only">{{ __('chat.issue.report') }}</span>
                    </button>
                @endif
                @if ($party === 'staff')
                    <button type="button" class="chat-tool {{ $internal ? 'is-on' : '' }}" wire:click="$toggle('internal')" aria-pressed="{{ $internal ? 'true' : 'false' }}" title="{{ __('chat.internal_toggle') }}: {{ __('chat.internal_help') }}">
                        <i data-lucide="lock" aria-hidden="true"></i><span class="sr-only">{{ __('chat.internal_toggle') }}</span>
                    </button>
                @endif
            </div>

            <div class="chat-input-wrap">
                <label for="chat-{{ $chatKey }}-input" class="sr-only">{{ $issueMode ? __('portal.qa.details') : __('chat.composer_label') }}</label>
                <textarea id="chat-{{ $chatKey }}-input" x-ref="input" rows="1" wire:model="draft" class="chat-input"
                          placeholder="{{ $issueMode ? __('chat.placeholder_issue') : __('chat.placeholder') }}" maxlength="{{ \App\Support\Chat\ProjectChat::BODY_MAX }}"
                          aria-describedby="chat-{{ $chatKey }}-hint" enterkeyhint="send"
                          @keydown.enter="onEnter($event)" @input="onInput()" @paste="onPaste($event)"></textarea>
            </div>

            @if ($files || $issueMode)
                <button type="button" class="chat-send" @click="submit()" :disabled="sending || uploading" aria-label="{{ $issueMode ? __('chat.issue.submit') : __('chat.send') }}" title="{{ $issueMode ? __('chat.issue.submit') : __('chat.send') }}">
                    <i data-lucide="send-horizontal" aria-hidden="true"></i>
                </button>
            @else
                <button type="button" class="chat-send" x-show="hasText" style="display: none;" @click="submit()" :disabled="sending || uploading" aria-label="{{ __('chat.send') }}" title="{{ __('chat.send') }}">
                    <i data-lucide="send-horizontal" aria-hidden="true"></i>
                </button>
                <button type="button" class="chat-send is-mic" x-show="!hasText" @click="startRecording()" aria-label="{{ __('chat.voice') }}" title="{{ __('chat.voice') }}">
                    <i data-lucide="mic" aria-hidden="true"></i>
                </button>
            @endif
        </div>
        <p id="chat-{{ $chatKey }}-hint" class="chat-hint">{{ __('chat.enter_hint') }}</p>
    </div>
@endif
