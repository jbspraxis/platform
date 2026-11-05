/*
 * HQ › Company profile › Client logos (docs/COMPANY_PROFILE.md section 2). Plain script, no build step: served
 * by routes/company-profile.php and loaded once with Livewire's @assets. It returns an Alpine component;
 * Livewire is reached through this.$wire.
 *
 * It makes the three renditions every use needs, here in the browser, because no PHP on this machine or on the
 * live server has gd or imagick:
 *
 *   print  the SVG as it was chosen, or a JPEG on white. What dompdf can actually draw; a PNG it cannot read.
 *   png    the same artwork on transparency, for the deck and anything that reads a PNG.
 *   web    a WebP, which is what jbspraxis.com serves.
 *
 * A raster logo is trimmed to its ink first, so every logo on the wall sits on the same baseline, then scaled
 * to one height. The server checks the bytes of all three before it keeps them.
 */
(function () {
    'use strict';

    var TARGET_HEIGHT = 160;     // what every logo is normalised to; the wall and the strip scale it down
    var MAX_WIDTH = 720;
    var JPEG_QUALITY = 0.92;
    var WEBP_QUALITY = 0.9;
    var MAX_SOURCE_BYTES = 4 * 1024 * 1024;

    function canvasOf(width, height) {
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width));
        canvas.height = Math.max(1, Math.round(height));
        return canvas;
    }

    /** The box the artwork actually fills, so a logo with air around it is not smaller than the rest */
    function inkBox(canvas) {
        var context = canvas.getContext('2d');
        var data;
        try {
            data = context.getImageData(0, 0, canvas.width, canvas.height).data;
        } catch (e) {
            return { x: 0, y: 0, width: canvas.width, height: canvas.height };
        }
        var top = canvas.height, left = canvas.width, right = -1, bottom = -1;
        for (var y = 0; y < canvas.height; y++) {
            for (var x = 0; x < canvas.width; x++) {
                var i = (y * canvas.width + x) * 4;
                var alpha = data[i + 3];
                // Transparent, or as near white as makes no difference: not ink
                var white = alpha < 12 || (data[i] > 246 && data[i + 1] > 246 && data[i + 2] > 246);
                if (white) continue;
                if (x < left) left = x;
                if (x > right) right = x;
                if (y < top) top = y;
                if (y > bottom) bottom = y;
            }
        }
        if (right < 0) return { x: 0, y: 0, width: canvas.width, height: canvas.height };
        return { x: left, y: top, width: right - left + 1, height: bottom - top + 1 };
    }

    /** The artwork trimmed and scaled onto a transparent canvas of one height */
    function normalise(image, naturalWidth, naturalHeight) {
        var first = canvasOf(naturalWidth, naturalHeight);
        first.getContext('2d').drawImage(image, 0, 0, first.width, first.height);
        var box = inkBox(first);

        var scale = TARGET_HEIGHT / box.height;
        if (box.width * scale > MAX_WIDTH) scale = MAX_WIDTH / box.width;

        var out = canvasOf(box.width * scale, box.height * scale);
        var context = out.getContext('2d');
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(first, box.x, box.y, box.width, box.height, 0, 0, out.width, out.height);
        return out;
    }

    /** The same artwork on white, which is what the PDF can carry */
    function onWhite(canvas) {
        var out = canvasOf(canvas.width, canvas.height);
        var context = out.getContext('2d');
        context.fillStyle = '#FFFFFF';
        context.fillRect(0, 0, out.width, out.height);
        context.drawImage(canvas, 0, 0);
        return out;
    }

    function load(src) {
        return new Promise(function (resolve, reject) {
            var image = new Image();
            image.onload = function () { resolve(image); };
            image.onerror = function () { reject(new Error('unreadable')); };
            image.decoding = 'sync';
            image.src = src;
        });
    }

    function readAs(file, how) {
        return new Promise(function (resolve, reject) {
            var reader = new FileReader();
            reader.onload = function () { resolve(reader.result); };
            reader.onerror = function () { reject(new Error('unreadable')); };
            how === 'text' ? reader.readAsText(file) : reader.readAsDataURL(file);
        });
    }

    function base64 (text) {
        return 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(text)));
    }

    window.logoPicker = function () {
        return {
            busy: false,
            error: '',
            note: '',
            preview: '',

            /** The file picker ends here: three renditions and the size go to Livewire in one call */
            choose: function (event) {
                var file = event.target.files && event.target.files[0];
                event.target.value = '';
                if (!file) return;

                var self = this;
                this.error = '';
                this.note = '';
                this.busy = true;

                this.renditions(file).then(function (payload) {
                    self.preview = payload.web || payload.png || payload.print;
                    self.busy = false;
                    self.$wire.setFiles(payload);
                }).catch(function (e) {
                    self.busy = false;
                    self.note = '';
                    if (e && e.message === 'too-big') {
                        self.error = 'That file is larger than 4 MB. Please choose a smaller one.';
                    } else if (e && e.message === 'svg-text') {
                        self.error = 'That SVG draws its lettering with a font, and this browser could not draw it '
                            + 'to a picture. Outline the text in your drawing program, or upload a PNG instead.';
                    } else {
                        self.error = 'That image could not be read. Choose an SVG or a PNG.';
                    }
                });
            },

            renditions: function (file) {
                if (file.size > MAX_SOURCE_BYTES) return Promise.reject(new Error('too-big'));
                var svg = file.type === 'image/svg+xml' || /\.svg$/i.test(file.name);
                return svg ? this.fromSvg(file) : this.fromRaster(file);
            },

            /**
             * An SVG is kept as it is for print, because it stays sharp at any size and php-svg-lib draws it.
             * The PNG and the WebP are drawn from it, so the deck and the website have a bitmap to show.
             */
            fromSvg: function (file) {
                var self = this;
                return readAs(file, 'text').then(function (text) {
                    var uri = base64(text);
                    // Live text in an SVG is lettering a font has to draw, and the PDF has only DejaVu and no
                    // way to fetch the real face, so the server refuses such an SVG for print. The browser
                    // has the font right here, so it draws the logo once and the JPEG goes for print instead.
                    var liveText = /<\s*(?:text|tspan)[\s>]/i.test(text);
                    return load(uri).then(function (image) {
                        var width = image.naturalWidth || image.width || TARGET_HEIGHT;
                        var height = image.naturalHeight || image.height || TARGET_HEIGHT;
                        var scale = TARGET_HEIGHT / height;
                        if (width * scale > MAX_WIDTH) scale = MAX_WIDTH / width;
                        var canvas = canvasOf(width * scale, height * scale);
                        canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
                        if (liveText) {
                            self.note = 'This SVG draws its lettering with a font, which a PDF cannot do, so the '
                                + 'picture below was drawn from it. Outline the text in your drawing program for a '
                                + 'logo that stays sharp at any size.';
                        }
                        return {
                            filename: file.name,
                            print: liveText ? onWhite(canvas).toDataURL('image/jpeg', JPEG_QUALITY) : uri,
                            png: canvas.toDataURL('image/png'),
                            web: canvas.toDataURL('image/webp', WEBP_QUALITY),
                            width: canvas.width,
                            height: canvas.height
                        };
                    }).catch(function () {
                        if (liveText) {
                            // Nothing here can draw it, and the server will refuse it: say so rather than fail later
                            throw new Error('svg-text');
                        }
                        // A browser that will not rasterise the SVG still gets a usable print rendition
                        return { filename: file.name, print: uri, png: '', web: '', width: 0, height: 0 };
                    });
                });
            },

            fromRaster: function (file) {
                return readAs(file, 'url').then(load).then(function (image) {
                    var canvas = normalise(image, image.naturalWidth || image.width, image.naturalHeight || image.height);
                    return {
                        filename: file.name,
                        print: onWhite(canvas).toDataURL('image/jpeg', JPEG_QUALITY),
                        png: canvas.toDataURL('image/png'),
                        web: canvas.toDataURL('image/webp', WEBP_QUALITY),
                        width: canvas.width,
                        height: canvas.height
                    };
                });
            }
        };
    };
})();
