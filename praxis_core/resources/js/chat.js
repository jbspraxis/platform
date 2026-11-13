// The conversation between a client and its delivery team (docs/CHAT.md), in the Client Portal and HQ.
//
// chatRoom: one chat (App\Livewire\ClientOs\DeliveryChat, App\Livewire\Hq\ClientMessages\Thread). It asks the server
// for news on a timer (3 s while open and in front, 30 s otherwise), keeps the view at the newest message unless the
// person scrolled up, sends "is typing" at most every 3 s, and handles files (picked, pasted, dropped), the emoji
// panel and voice notes (MediaRecorder). The server answers a check with a browser event (chat-state: read receipts,
// typing, unread) and draws the conversation again only when it changed (chat-changed).
// chatVoice: the small player of one voice note.

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const clock = (ms) => {
    const s = Math.max(0, Math.floor((ms || 0) / 1000));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
};

function chatRoom(cfg) {
    return {
        cfg,
        isOpen: false,
        embedded: !!cfg.embedded,
        unread: 0,
        seen: 0,
        typing: [],
        atBottom: true,
        newBelow: 0,
        lastRowId: 0,
        text: '',
        sending: false,
        uploading: false,
        progress: 0,
        dragging: false,
        emojiOpen: false,
        recording: false,
        recordMs: 0,
        micError: '',
        phone: false,
        coarse: false,
        viewportStyle: '',
        busy: false,
        timer: null,
        lastTyping: 0,

        init() {
            // What the page was drawn with; afterwards the chat-state events keep these current
            this.unread = Number(this.$el.dataset.unread || 0);
            this.seen = Number(this.$el.dataset.seen || 0);
            try { this.typing = JSON.parse(this.$el.dataset.typing || '[]'); } catch (e) { this.typing = []; }
            const phone = window.matchMedia('(max-width: 900px)');
            this.phone = phone.matches;
            phone.addEventListener?.('change', (e) => { this.phone = e.matches; this.updateViewport(); });
            this.coarse = window.matchMedia('(pointer: coarse)').matches;

            // Phones: the chat follows the visible part of the screen, so the keyboard never covers the message box
            if (window.visualViewport) {
                this._vv = () => this.updateViewport();
                window.visualViewport.addEventListener('resize', this._vv);
                window.visualViewport.addEventListener('scroll', this._vv);
            }
            this._visible = () => { if (document.visibilityState === 'visible') this.schedule(50); };
            document.addEventListener('visibilitychange', this._visible);

            this.text = this.$refs.input?.value || '';
            if (this.embedded || cfg.startOpen) {
                this.$nextTick(() => this.open({ silent: this.embedded }));
            }
            this.schedule();
        },

        destroy() {
            clearTimeout(this.timer);
            clearInterval(this.recordTimer);
            this.stream?.getTracks().forEach((t) => t.stop());
            document.removeEventListener('visibilitychange', this._visible);
            if (window.visualViewport && this._vv) {
                window.visualViewport.removeEventListener('resize', this._vv);
                window.visualViewport.removeEventListener('scroll', this._vv);
            }
        },

        mine(detail) {
            return detail && detail.id === cfg.id;
        },

        // ---- Opening and closing ----------------------------------------------------------------------------

        open(detail = {}) {
            const wasOpen = this.isOpen;
            this.isOpen = true;
            this.updateViewport();
            this.$wire.opened().then(() => this.$nextTick(() => {
                if (detail.issue || detail.task !== undefined) {
                    this.$wire.startIssue(detail.task || null);
                }
                if (detail.focus) {
                    this.jump(detail.focus);
                } else if (!wasOpen) {
                    this.scrollToUnread();
                }
                if (!this.phone && !detail.silent) {
                    this.$refs.input?.focus({ preventScroll: true });
                }
            }));
            this.schedule(cfg.poll);
        },

        close() {
            if (this.embedded) {
                this.$dispatch('chat-back', { id: cfg.id });
                return;
            }
            const hadFocus = this.$refs.panel?.contains(document.activeElement);
            if (this.recording) this.stopRecording(false);
            this.emojiOpen = false;
            this.isOpen = false;
            this.updateViewport();
            if (hadFocus) this.$nextTick(() => this.$refs.fab?.focus());
            this.schedule();
        },

        updateViewport() {
            const vv = window.visualViewport;
            if (!vv || !this.phone || !(this.isOpen || this.embedded)) {
                this.viewportStyle = '';
                return;
            }
            const stick = this.atBottom;
            this.viewportStyle = `height:${Math.round(vv.height)}px;top:${Math.round(vv.offsetTop)}px;`;
            if (stick) this.$nextTick(() => this.scrollToBottom(false));
        },

        // ---- Checking for news ------------------------------------------------------------------------------

        showing() {
            return (this.isOpen || this.embedded) && document.visibilityState === 'visible';
        },

        schedule(delay) {
            clearTimeout(this.timer);
            const wait = delay ?? (this.showing() ? cfg.poll : cfg.idle);
            this.timer = setTimeout(() => this.tick(), wait);
        },

        tick() {
            if (this.busy || !this.$wire) {
                this.schedule();
                return;
            }
            this.busy = true;
            const open = this.showing();
            this.$wire.heartbeat(open, open && this.atBottom && document.hasFocus())
                .catch(() => {})
                .finally(() => {
                    this.busy = false;
                    this.schedule();
                });
        },

        onState(detail) {
            if (!this.mine(detail)) return;
            this.seen = detail.seen || 0;
            this.typing = Array.isArray(detail.typing) ? detail.typing : Object.values(detail.typing || {});
            this.unread = detail.unread || 0;
        },

        onChanged(detail) {
            if (!this.mine(detail)) return;
            const stick = this.atBottom;
            this.$nextTick(() => requestAnimationFrame(() => {
                if (stick) {
                    this.scrollToBottom(true);
                } else {
                    this.newBelow = this.rows().filter((r) => Number(r.dataset.id) > this.lastRowId && !r.classList.contains('is-mine')).length;
                }
            }));
        },

        onSent(detail) {
            if (!this.mine(detail)) return;
            this.text = '';
            this.$nextTick(() => {
                this.autosize();
                requestAnimationFrame(() => this.scrollToBottom(true));
            });
        },

        onFocusAsked(detail) {
            if (!this.mine(detail)) return;
            this.$nextTick(() => this.$refs.input?.focus({ preventScroll: true }));
        },

        onReveal(detail) {
            if (!this.mine(detail)) return;
            this.$nextTick(() => requestAnimationFrame(() => this.jump(detail.message, true)));
        },

        get typingText() {
            const names = this.typing || [];
            const s = cfg.strings;
            if (!names.length) return '';
            if (names.length === 1) return s.typingOne.replace(':name', names[0]);
            return s.typingMany.replace(':names', names.slice(0, -1).join(', ') + s.and + names[names.length - 1]);
        },

        get newBelowText() {
            return this.newBelow === 1 ? cfg.strings.newOne : cfg.strings.newMany.replace('__N__', this.newBelow);
        },

        get hasText() {
            return this.text.trim().length > 0;
        },

        // ---- Scrolling ---------------------------------------------------------------------------------------

        rows() {
            return Array.from(this.$refs.scroll?.querySelectorAll('[data-id]') || []);
        },

        onScroll() {
            const el = this.$refs.scroll;
            if (!el) return;
            const was = this.atBottom;
            this.atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 72;
            if (this.atBottom) {
                this.newBelow = 0;
                this.lastRowId = Math.max(0, ...this.rows().map((r) => Number(r.dataset.id)));
                if (!was) this.schedule(250); // reached the newest: tell the server it is read
            }
        },

        scrollToBottom(smooth) {
            const el = this.$refs.scroll;
            if (!el) return;
            el.scrollTo({ top: el.scrollHeight, behavior: smooth && !reducedMotion() ? 'smooth' : 'auto' });
            this.atBottom = true;
            this.newBelow = 0;
            this.lastRowId = Math.max(0, ...this.rows().map((r) => Number(r.dataset.id)));
        },

        scrollToUnread() {
            const line = document.getElementById(`chat-${cfg.id}-unread`);
            if (line && this.$refs.scroll) {
                this.$refs.scroll.scrollTop = Math.max(0, line.offsetTop - 24);
                this.onScroll();
            } else {
                this.scrollToBottom(false);
            }
        },

        // A quoted message or an issue: scroll to it and mark it for a moment; ask for older messages if it isn't drawn
        jump(id, asked = false) {
            const el = document.getElementById(`chat-${cfg.id}-m-${id}`);
            if (!el) {
                if (!asked) this.$wire.reveal(id);
                return;
            }
            el.scrollIntoView({ block: 'center', behavior: reducedMotion() ? 'auto' : 'smooth' });
            el.classList.remove('is-flash');
            void el.offsetWidth;
            el.classList.add('is-flash');
            setTimeout(() => el.classList.remove('is-flash'), 1800);
        },

        // ---- Writing -----------------------------------------------------------------------------------------

        autosize() {
            const t = this.$refs.input;
            if (!t) return;
            t.style.height = 'auto';
            t.style.height = `${Math.min(t.scrollHeight, 168)}px`;
        },

        onInput() {
            this.text = this.$refs.input?.value || '';
            this.autosize();
            if (this.hasText && Date.now() - this.lastTyping > 3000) {
                this.lastTyping = Date.now();
                this.$wire.typing();
            }
        },

        onEnter(e) {
            if (e.shiftKey || e.isComposing || e.keyCode === 229) return;
            // Phones: Enter makes a new line and the button sends, as in every messaging app
            if (this.coarse && !e.ctrlKey && !e.metaKey) return;
            e.preventDefault();
            this.submit();
        },

        submit() {
            if (this.sending || this.uploading) return;
            this.sending = true;
            this.$wire.send().finally(() => { this.sending = false; });
        },

        addFiles(list) {
            const files = Array.from(list || []);
            if (!files.length) return;
            this.uploading = true;
            this.progress = 0;
            this.$wire.uploadMultiple('incoming', files,
                () => { this.uploading = false; },
                () => { this.uploading = false; },
                (e) => { this.progress = e.detail.progress; });
        },

        onPaste(e) {
            const files = Array.from(e.clipboardData?.files || []);
            if (files.length) {
                e.preventDefault();
                this.addFiles(files);
            }
        },

        onDrop(e) {
            this.dragging = false;
            this.addFiles(e.dataTransfer?.files);
        },

        insertEmoji(char) {
            const t = this.$refs.input;
            if (!t) return;
            const start = t.selectionStart ?? t.value.length;
            const end = t.selectionEnd ?? start;
            t.value = t.value.slice(0, start) + char + t.value.slice(end);
            t.dispatchEvent(new Event('input', { bubbles: true }));
            this.emojiOpen = false;
            t.focus();
            t.setSelectionRange(start + char.length, start + char.length);
        },

        // ---- Voice notes -------------------------------------------------------------------------------------

        clock,

        async startRecording() {
            this.micError = '';
            if (!navigator.mediaDevices?.getUserMedia || typeof window.MediaRecorder === 'undefined') {
                this.micError = cfg.strings.micUnsupported;
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (e) {
                this.micError = cfg.strings.micDenied;
                return;
            }
            const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus']
                .find((t) => window.MediaRecorder.isTypeSupported?.(t)) || '';
            this.chunks = [];
            this.recorder = new window.MediaRecorder(this.stream, type ? { mimeType: type } : undefined);
            this.recorder.ondataavailable = (e) => { if (e.data && e.data.size) this.chunks.push(e.data); };
            this.recorder.onstop = () => this.finishRecording();
            this.keepVoice = false;
            this.recordStart = Date.now();
            this.recordMs = 0;
            this.recording = true;
            this.recorder.start(250);
            this.recordTimer = setInterval(() => {
                this.recordMs = Date.now() - this.recordStart;
                if (this.recordMs >= cfg.maxVoiceMs) this.stopRecording(true);
            }, 200);
        },

        stopRecording(send) {
            this.keepVoice = send;
            clearInterval(this.recordTimer);
            if (this.recorder && this.recorder.state !== 'inactive') {
                this.recorder.stop();
            } else {
                this.finishRecording();
            }
        },

        finishRecording() {
            this.stream?.getTracks().forEach((t) => t.stop());
            this.stream = null;
            this.recording = false;
            const ms = Math.min(Date.now() - (this.recordStart || Date.now()), cfg.maxVoiceMs);
            const chunks = this.chunks || [];
            this.chunks = [];
            if (!this.keepVoice || !chunks.length || ms < 700) return;
            const mime = (this.recorder?.mimeType || chunks[0].type || 'audio/webm').split(';')[0];
            const ext = mime.includes('mp4') ? 'm4a' : (mime.includes('ogg') ? 'ogg' : 'webm');
            const file = new File(chunks, `voice-note.${ext}`, { type: mime });
            this.uploading = true;
            this.progress = 0;
            this.$wire.upload('voice', file,
                () => { this.uploading = false; this.$wire.sendVoice(ms); },
                () => { this.uploading = false; },
                (e) => { this.progress = e.detail.progress; });
        },
    };
}

function chatVoice(durationMs) {
    return {
        playing: false,
        position: 0,
        current: 0,
        total: (durationMs || 0) / 1000,

        length() {
            const a = this.$refs.audio;
            return a && Number.isFinite(a.duration) && a.duration > 0 ? a.duration : this.total;
        },
        toggle() {
            const a = this.$refs.audio;
            if (a.paused) {
                document.querySelectorAll('.chat-voice audio').forEach((other) => { if (other !== a) other.pause(); });
                a.play().then(() => { this.playing = true; }).catch(() => { this.playing = false; });
            } else {
                a.pause();
                this.playing = false;
            }
        },
        sync() {
            const a = this.$refs.audio;
            const d = this.length();
            this.current = a.currentTime;
            this.position = d ? Math.min(1000, Math.round((a.currentTime / d) * 1000)) : 0;
            this.playing = !a.paused;
        },
        seek() {
            const a = this.$refs.audio;
            const d = this.length();
            if (d) a.currentTime = (this.position / 1000) * d;
        },
        loaded() {
            const a = this.$refs.audio;
            if (Number.isFinite(a.duration) && a.duration > 0) this.total = a.duration;
        },
        ended() {
            this.playing = false;
            this.position = 0;
            this.current = 0;
        },
        label() {
            return clock(((this.playing || this.current > 0) ? this.current : this.total) * 1000);
        },
    };
}

// HQ's floating Client messages button (App\Livewire\Hq\ClientMessages\Launcher), kept across pages: hidden on the
// Client messages page itself, which shows the same conversations
const MESSAGES_PAGE = '/operations/client-messages';

function chatLauncher() {
    return {
        here: false,
        phone: false,
        back: false,
        init() {
            this.phone = window.matchMedia('(max-width: 900px)').matches;
            this.here = location.pathname.startsWith(MESSAGES_PAGE);
            this._navigated = () => {
                this.here = location.pathname.startsWith(MESSAGES_PAGE);
                if (this.here && this.$wire.open) this.$wire.hide();
            };
            document.addEventListener('livewire:navigated', this._navigated);
        },
        destroy() {
            document.removeEventListener('livewire:navigated', this._navigated);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('chatRoom', chatRoom);
    window.Alpine.data('chatVoice', chatVoice);
    window.Alpine.data('chatLauncher', chatLauncher);
});
