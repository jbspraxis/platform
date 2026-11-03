/*
 * HQ › Campaigns › Broadcasts editor (docs/CAMPAIGNS.md 4.4): the rich-text blocks and the live preview. Plain script,
 * no build step: served by routes/campaigns.php and loaded once with Livewire's @assets. Each function returns an
 * Alpine component; Livewire is reached through this.$wire. The HTML a text block produces is cleaned on the server
 * (App\Support\Campaigns\RichText) before it is kept or sent.
 */
(function () {
    'use strict';

    /**
     * One text block: bold, italics, underline, links, lists and two heading levels. Saves to the block by its id
     * (setText), never by its position, so moving blocks around can't send the words to the wrong one.
     */
    window.campaignText = function (opts) {
        return {
            timer: null,
            linkOpen: false,
            linkUrl: '',
            range: null,

            init: function () {
                var self = this;
                var ed = this.$refs.ed;
                if (ed._started) return;
                ed._started = true;
                ed.innerHTML = opts.html || '';
                try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* older browsers */ }
                ed.addEventListener('input', function () { self.changed(); });
                ed.addEventListener('blur', function () { self.saveRange(); self.flush(); });
                ed.addEventListener('keydown', function (e) {
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                        e.preventDefault();
                        self.openLink();
                    }
                });
                // Pasted text keeps its paragraphs, lists and links but not the page's look (the server cleans the rest)
                ed.addEventListener('paste', function (e) {
                    var data = e.clipboardData;
                    if (!data) return;
                    var html = data.getData('text/html');
                    e.preventDefault();
                    if (!html) {
                        document.execCommand('insertText', false, data.getData('text/plain'));
                        return;
                    }
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    doc.querySelectorAll('script,style,meta,link,img,svg,iframe,object,table').forEach(function (n) {
                        if (n.tagName === 'TABLE') { n.replaceWith.apply(n, Array.prototype.slice.call(n.querySelectorAll('td,th')).map(function (c) { var p = doc.createElement('p'); p.textContent = c.textContent; return p; })); }
                        else n.remove();
                    });
                    doc.querySelectorAll('*').forEach(function (n) {
                        Array.prototype.slice.call(n.attributes).forEach(function (a) {
                            if (!(n.tagName === 'A' && a.name === 'href')) n.removeAttribute(a.name);
                        });
                    });
                    document.execCommand('insertHTML', false, doc.body.innerHTML);
                });
            },

            destroy: function () {
                this.flush();
            },

            changed: function () {
                var self = this;
                clearTimeout(this.timer);
                this.timer = setTimeout(function () { self.flush(); }, 500);
            },

            flush: function () {
                if (!this.$refs.ed) return;
                clearTimeout(this.timer);
                var html = this.$refs.ed.innerHTML;
                if (html === this._last) return;
                this._last = html;
                this.$wire.setText(opts.locale, opts.id, html);
            },

            cmd: function (name, value) {
                this.$refs.ed.focus();
                this.restoreRange();
                document.execCommand(name, false, value === undefined ? null : value);
                this.changed();
            },

            heading: function (tag) {
                this.$refs.ed.focus();
                this.restoreRange();
                var current = (document.queryCommandValue('formatBlock') || '').toLowerCase();
                document.execCommand('formatBlock', false, current === tag ? 'p' : tag);
                this.changed();
            },

            saveRange: function () {
                var sel = window.getSelection();
                if (sel && sel.rangeCount && this.$refs.ed.contains(sel.anchorNode)) this.range = sel.getRangeAt(0).cloneRange();
            },

            restoreRange: function () {
                if (!this.range) return;
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(this.range);
            },

            openLink: function () {
                this.saveRange();
                var a = this.range && this.range.startContainer.parentElement ? this.range.startContainer.parentElement.closest('a') : null;
                this.linkUrl = a ? a.getAttribute('href') : 'https://';
                this.linkOpen = true;
                var self = this;
                this.$nextTick(function () { self.$refs.link && self.$refs.link.focus(); });
            },

            applyLink: function () {
                var url = (this.linkUrl || '').trim();
                this.linkOpen = false;
                if (!url || url === 'https://') { this.cmd('unlink'); return; }
                if (!/^(https?:\/\/|mailto:)/i.test(url)) url = /@/.test(url) && !/\//.test(url) ? 'mailto:' + url : 'https://' + url;
                this.cmd('createLink', url);
            },

            cancelLink: function () {
                this.linkOpen = false;
                this.$refs.ed.focus();
                this.restoreRange();
            }
        };
    };

    /**
     * The live preview: the email as one person would get it, in an iframe at computer or phone width. The server
     * sends new HTML after each change (the 'broadcast-preview' event); the scroll position is kept.
     */
    window.campaignPreview = function () {
        return {
            device: 'desktop',
            scale: 1,
            height: 600,

            init: function () {
                var self = this;
                this.show(this.$el.getAttribute('data-html') || '');
                this.$el.removeAttribute('data-html');
                this.fit();
                if (window.ResizeObserver) new ResizeObserver(function () { self.fit(); }).observe(this.$refs.wrap);
                this.$watch('device', function () { self.fit(); });
            },

            /** The email is drawn at its real width (680 px on a computer, 390 on a phone) and scaled down to fit */
            width: function () {
                return this.device === 'phone' ? 390 : 680;
            },

            fit: function () {
                var wrap = this.$refs.wrap;
                if (!wrap) return;
                this.scale = Math.min(1, (wrap.clientWidth - 2) / this.width());
                this.height = Math.round(wrap.clientHeight / this.scale);
            },

            frameStyle: function () {
                return 'width: ' + this.width() + 'px; height: ' + this.height + 'px; transform: translateX(-50%) scale(' + this.scale + ')';
            },

            show: function (html) {
                var frame = this.$refs.frame;
                if (!frame) return;
                var y = 0;
                try { y = frame.contentWindow ? frame.contentWindow.scrollY : 0; } catch (e) { y = 0; }
                frame.onload = function () {
                    try {
                        frame.contentWindow.scrollTo(0, y);
                        // Links in the preview open in a new tab, never inside the frame
                        frame.contentDocument.querySelectorAll('a[href]').forEach(function (a) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener'); });
                    } catch (e) { /* sandboxed */ }
                };
                frame.srcdoc = html;
            }
        };
    };
})();
