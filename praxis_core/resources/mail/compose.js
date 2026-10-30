/*
 * HQ Mail composer (docs/MAIL.md, PR 3): the rich-text editor, the address chips with suggestions, and the
 * Undo send countdown. Plain script, no build step: served by routes/mail/compose.php and loaded once with
 * Livewire's @assets. Each function returns an Alpine component; Livewire is reached through this.$wire.
 * The HTML the editor produces is cleaned on the server (HtmlCleaner) before it is kept or sent.
 */
(function () {
    'use strict';

    var EMAIL = /^[^\s@<>(),;:"]+@[^\s@<>(),;:"]+\.[^\s@<>(),;:"]{2,}$/;

    /** "Ada Client <ada@client.com>", "ada@client.com" → {name, email} or null */
    function parseAddress(text) {
        text = String(text || '').trim().replace(/^[,;]+|[,;]+$/g, '').trim();
        if (!text) return null;
        var m = text.match(/^\s*"?([^"<]*?)"?\s*<([^<>]+)>\s*$/);
        var name = m ? m[1].trim() : null;
        var email = (m ? m[2] : text).trim().toLowerCase();
        return EMAIL.test(email) ? { name: name || null, email: email } : null;
    }

    window.hqMailEditor = function (opts) {
        return {
            timer: null,
            range: null,
            linkOpen: false,
            linkUrl: '',
            confirmDiscard: false,
            dragging: false,

            init: function () {
                var self = this;
                var ed = this.$root.querySelector(".mc-editor");
                this._ed = ed;
                ed.innerHTML = opts.html || '';
                this._onLoad = function (e) { ed.innerHTML = (e.detail && e.detail.html) || ''; };
                window.addEventListener('mail-editor-load', this._onLoad);
                ed.addEventListener('input', function () { self.changed(); });
                ed.addEventListener('keydown', function (e) {
                    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                        e.preventDefault();
                        self.send();
                    }
                    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                        e.preventDefault();
                        self.openLink();
                    }
                });
                // Pasted text keeps its paragraphs and links but not the page's look (the server cleans the rest)
                ed.addEventListener('paste', function (e) {
                    var data = e.clipboardData;
                    if (!data) return;
                    var html = data.getData('text/html');
                    if (!html) return;
                    e.preventDefault();
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    doc.querySelectorAll('script,style,meta,link,img,svg,iframe,object').forEach(function (n) { n.remove(); });
                    doc.querySelectorAll('*').forEach(function (n) {
                        Array.prototype.slice.call(n.attributes).forEach(function (a) {
                            if (!(n.tagName === 'A' && a.name === 'href')) n.removeAttribute(a.name);
                        });
                    });
                    document.execCommand('insertHTML', false, doc.body.innerHTML);
                });
                ed.addEventListener('blur', function () { self.saveRange(); });
                this.$nextTick(function () {
                    var first = self.$root.querySelector('[data-autofocus]');
                    (first || ed).focus();
                });
            },

            destroy: function () {
                window.removeEventListener('mail-editor-load', this._onLoad);
                clearTimeout(this.timer);
            },

            html: function () { var ed = this._ed || this.$root.querySelector(".mc-editor"); return ed ? ed.innerHTML : null; },

            changed: function () {
                var self = this;
                clearTimeout(this.timer);
                this.timer = setTimeout(function () { self.$wire.autosave(self.html()); }, opts.autosaveMs || 1500);
            },

            send: function () {
                clearTimeout(this.timer);
                this.$wire.send(this.html());
            },

            cmd: function (name, value) {
                (this._ed || this.$root.querySelector(".mc-editor")).focus();
                this.restoreRange();
                document.execCommand(name, false, value === undefined ? null : value);
                this.changed();
            },

            saveRange: function () {
                var sel = window.getSelection();
                if (sel && sel.rangeCount && this._ed && this._ed.contains(sel.anchorNode)) {
                    this.range = sel.getRangeAt(0).cloneRange();
                }
            },

            restoreRange: function () {
                if (!this.range) return;
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(this.range);
            },

            openLink: function () {
                this.saveRange();
                this.linkUrl = '';
                this.linkOpen = true;
                var self = this;
                this.$nextTick(function () { self.$refs.linkInput && self.$refs.linkInput.focus(); });
            },

            applyLink: function () {
                var url = this.linkUrl.trim();
                this.linkOpen = false;
                if (!url) return;
                if (!/^(https?:|mailto:|tel:)/i.test(url)) url = (EMAIL.test(url) ? 'mailto:' : 'https://') + url;
                var sel = this.range;
                if (sel && sel.collapsed) {
                    this.cmd('insertHTML', '<a href="' + url.replace(/"/g, '&quot;') + '">' + url.replace(/^mailto:/i, '').replace(/</g, '&lt;') + '</a>');
                } else {
                    this.cmd('createLink', url);
                }
            },

            dropFiles: function (e) {
                if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                    this.$wire.uploadMultiple('uploads', e.dataTransfer.files);
                }
            },
        };
    };

    window.hqMailChips = function (field) {
        return {
            field: field,
            list: [],
            query: '',
            items: [],
            active: -1,
            bad: '',
            timer: null,

            init: function () {
                var self = this;
                this.list = (this.$wire[field] || []).slice();
                this.$wire.$watch(field, function (v) { self.list = (v || []).slice(); });
            },

            commit: function () {
                this.$wire.setRecipients(this.field, this.list);
            },

            add: function (text) {
                var parts = String(text || '').split(/[,;\n]+(?=(?:[^"]*"[^"]*")*[^"]*$)/);
                var added = false;
                var left = [];
                for (var i = 0; i < parts.length; i++) {
                    if (!parts[i].trim()) continue;
                    var a = parseAddress(parts[i]);
                    if (!a) { left.push(parts[i].trim()); continue; }
                    if (!this.list.some(function (x) { return x.email === a.email; })) this.list.push(a);
                    added = true;
                }
                this.query = left.join(', ');
                this.bad = left.length ? '“' + left[0] + '” isn’t an email address. Check it and try again.' : '';
                this.items = [];
                this.active = -1;
                if (added) this.commit();
                return added;
            },

            pick: function (item) {
                if (!this.list.some(function (x) { return x.email === item.email; })) this.list.push({ name: item.name, email: item.email });
                this.query = '';
                this.items = [];
                this.active = -1;
                this.bad = '';
                this.commit();
                this.$refs.input.focus();
            },

            remove: function (i) {
                this.list.splice(i, 1);
                this.commit();
            },

            typed: function () {
                var self = this;
                this.bad = '';
                if (/[,;]\s*$/.test(this.query)) { this.add(this.query); return; }
                clearTimeout(this.timer);
                var q = this.query.trim();
                if (q.length < 2) { this.items = []; return; }
                this.timer = setTimeout(function () {
                    self.$wire.suggest(q).then(function (r) {
                        if (self.query.trim() === q) { self.items = r || []; self.active = self.items.length ? 0 : -1; }
                    });
                }, 200);
            },

            key: function (e) {
                if (e.key === 'ArrowDown' && this.items.length) { e.preventDefault(); this.active = (this.active + 1) % this.items.length; }
                else if (e.key === 'ArrowUp' && this.items.length) { e.preventDefault(); this.active = (this.active - 1 + this.items.length) % this.items.length; }
                else if (e.key === 'Enter' || (e.key === 'Tab' && this.query.trim())) {
                    if (this.active >= 0 && this.items[this.active]) { e.preventDefault(); this.pick(this.items[this.active]); }
                    else if (this.query.trim()) { e.preventDefault(); this.add(this.query); }
                }
                else if (e.key === 'Escape' && this.items.length) { e.stopPropagation(); this.items = []; }
                else if (e.key === 'Backspace' && !this.query && this.list.length) { this.remove(this.list.length - 1); }
            },

            blurred: function () {
                var self = this;
                setTimeout(function () {
                    if (self.query.trim()) self.add(self.query);
                    self.items = [];
                }, 150);
            },

            pasted: function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text');
                if (/[,;\n]/.test(text)) { e.preventDefault(); this.add(this.query + text); }
            },

            label: function (a) { return a.name || a.email; },
        };
    };

    window.hqMailToast = function (toast) {
        return {
            left: toast.seconds || 0,
            t: null,
            init: function () {
                var self = this;
                if (toast.type === 'undo') {
                    var end = Date.now() + this.left * 1000;
                    this.t = setInterval(function () {
                        self.left = Math.max(0, Math.ceil((end - Date.now()) / 1000));
                        if (self.left === 0) {
                            clearInterval(self.t);
                            self.$wire.deliver(toast.outboxId);
                        }
                    }, 250);
                } else if (toast.type === 'sent' || toast.type === 'info') {
                    this.t = setTimeout(function () { self.$wire.dismissToast(); }, 6000);
                }
            },
            destroy: function () {
                clearInterval(this.t);
                clearTimeout(this.t);
            },
        };
    };
})();
