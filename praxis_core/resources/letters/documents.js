/*
 * HQ › Documents (docs/DOCUMENTS.md): the signature pad and the free letter's rich-text body. Plain script, no
 * build step: served by routes/letters.php and loaded once with Livewire's @assets. Each function returns an
 * Alpine component; Livewire is reached through this.$wire.
 *
 * Both the drawing and an uploaded photograph leave here as a JPEG on a white background, because dompdf can
 * put a JPEG straight into a PDF without the gd extension, which the live PHP has not got, and cannot read a
 * PNG at all. The server checks that what arrives really is a JPEG before it stores it.
 */
(function () {
    'use strict';

    var JPEG_QUALITY = 0.92;

    /** The pad: draw with a finger, a pen or a mouse, then Use this signature */
    window.signaturePad = function () {
        return {
            drawing: false,
            hasInk: false,
            last: null,

            init: function () {
                var canvas = this.$refs.pad;
                if (canvas._started) return;
                canvas._started = true;
                this.resize();
                var self = this;
                window.addEventListener('resize', function () { self.resize(); });

                var point = function (e) {
                    var rect = canvas.getBoundingClientRect();
                    var touch = e.touches && e.touches[0];
                    var x = (touch ? touch.clientX : e.clientX) - rect.left;
                    var y = (touch ? touch.clientY : e.clientY) - rect.top;
                    return { x: x * canvas.width / rect.width, y: y * canvas.height / rect.height };
                };

                var start = function (e) { e.preventDefault(); self.drawing = true; self.last = point(e); };
                var move = function (e) {
                    if (!self.drawing) return;
                    e.preventDefault();
                    var now = point(e);
                    var ctx = canvas.getContext('2d');
                    ctx.strokeStyle = '#0C142B';
                    ctx.lineWidth = Math.max(2, canvas.width / 320);
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.beginPath();
                    ctx.moveTo(self.last.x, self.last.y);
                    ctx.lineTo(now.x, now.y);
                    ctx.stroke();
                    self.last = now;
                    self.hasInk = true;
                };
                var stop = function () { self.drawing = false; self.last = null; };

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', stop);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', move, { passive: false });
                canvas.addEventListener('touchend', stop);
            },

            /* The canvas is drawn at twice its shown size, so the signature stays sharp in print */
            resize: function () {
                var canvas = this.$refs.pad;
                var width = Math.round(canvas.clientWidth * 2);
                var height = Math.round(canvas.clientHeight * 2);
                if (canvas.width === width && canvas.height === height) return;
                canvas.width = width;
                canvas.height = height;
                this.white();
                this.hasInk = false;
            },

            white: function () {
                var canvas = this.$refs.pad;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            },

            clear: function () {
                this.white();
                this.hasInk = false;
            },

            use: function () {
                if (!this.hasInk) return;
                var canvas = this.$refs.pad;
                var trimmed = trim(canvas);
                this.$wire.setImage(trimmed.canvas.toDataURL('image/jpeg', JPEG_QUALITY), 'drawn', trimmed.canvas.width, trimmed.canvas.height);
            },

            /* A photograph or a scan: drawn on white, shrunk to a sensible width, and handed over as a JPEG */
            chooseFile: function (event) {
                var file = event.target.files && event.target.files[0];
                if (!file) return;
                var self = this;
                var reader = new FileReader();
                reader.onload = function () {
                    var image = new Image();
                    image.onload = function () {
                        var max = 1200;
                        var scale = Math.min(1, max / image.width);
                        var canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.round(image.width * scale));
                        canvas.height = Math.max(1, Math.round(image.height * scale));
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
                        self.$wire.setImage(canvas.toDataURL('image/jpeg', JPEG_QUALITY), 'uploaded', canvas.width, canvas.height);
                    };
                    image.onerror = function () { window.alert('That image could not be read. Try a JPEG or a PNG.'); };
                    image.src = reader.result;
                };
                reader.readAsDataURL(file);
                event.target.value = '';
            },
        };
    };

    /** Cuts the white margin off a drawing, so the signature sits on its line rather than floating above it */
    function trim(canvas) {
        var ctx = canvas.getContext('2d');
        var data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        var top = canvas.height, left = canvas.width, right = 0, bottom = 0;
        for (var y = 0; y < canvas.height; y++) {
            for (var x = 0; x < canvas.width; x++) {
                var i = (y * canvas.width + x) * 4;
                if (data[i] < 240 || data[i + 1] < 240 || data[i + 2] < 240) {
                    if (x < left) left = x;
                    if (x > right) right = x;
                    if (y < top) top = y;
                    if (y > bottom) bottom = y;
                }
            }
        }
        if (right <= left || bottom <= top) return { canvas: canvas };

        var pad = Math.round(canvas.width / 60);
        left = Math.max(0, left - pad);
        top = Math.max(0, top - pad);
        right = Math.min(canvas.width - 1, right + pad);
        bottom = Math.min(canvas.height - 1, bottom + pad);

        var out = document.createElement('canvas');
        out.width = right - left + 1;
        out.height = bottom - top + 1;
        var outCtx = out.getContext('2d');
        outCtx.fillStyle = '#FFFFFF';
        outCtx.fillRect(0, 0, out.width, out.height);
        outCtx.drawImage(canvas, left, top, out.width, out.height, 0, 0, out.width, out.height);

        return { canvas: out };
    }

    /**
     * The free letter's body: bold, italics, lists and two heading levels. The HTML goes to the server on every
     * pause and is cleaned there (App\Support\Campaigns\RichText) before it is kept.
     */
    window.letterBody = function (opts) {
        return {
            timer: null,

            init: function () {
                var editor = this.$refs.ed;
                if (editor._started) return;
                editor._started = true;
                editor.innerHTML = opts.html || '';
                try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }
                var self = this;
                editor.addEventListener('input', function () {
                    clearTimeout(self.timer);
                    self.timer = setTimeout(function () { self.flush(); }, 600);
                });
                editor.addEventListener('blur', function () { self.flush(); });
                editor.addEventListener('paste', function (e) {
                    var data = e.clipboardData;
                    if (!data) return;
                    e.preventDefault();
                    document.execCommand('insertText', false, data.getData('text/plain'));
                });
                // The draft Claude writes replaces what is in the editor
                this.$wire.$watch('body', function (value) {
                    if (editor.innerHTML !== value) editor.innerHTML = value || '';
                });
            },

            flush: function () {
                var editor = this.$refs.ed;
                if (!editor) return;
                clearTimeout(this.timer);
                var html = editor.innerHTML;
                if (html === this._last) return;
                this._last = html;
                this.$wire.setBody(html);
            },

            cmd: function (name) {
                this.$refs.ed.focus();
                document.execCommand(name, false, null);
                this.flush();
            },

            heading: function (tag) {
                this.$refs.ed.focus();
                document.execCommand('formatBlock', false, tag);
                this.flush();
            },
        };
    };
})();
