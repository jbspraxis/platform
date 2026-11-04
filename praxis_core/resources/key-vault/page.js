/*
 * HQ › Settings and access › Key vault (docs/KEY_VAULT.md): the page's two Alpine components. Plain script, no
 * build step: served after crypto.js by routes/web.php and loaded once with Livewire's @assets.
 *
 * The secret and the passphrase live only in these inputs and in this script's memory. They are never bound
 * with wire:model and never passed to $wire: Livewire receives the locked envelope (keyVaultLock), and sends one
 * back only after the emailed code (keyVaultReveal). A shown secret hides itself after a minute, when the tab is
 * left or hidden, and when HQ navigates to another page.
 */
(function () {
    'use strict';

    var SHOW_SECONDS = 60;
    var HOLD_MINUTES = 5;

    function cryptoApi() {
        return window.KeyVaultCrypto;
    }

    /**
     * Resolves true once the sign-in is fresh: straight away when it is, else after the password modal
     * (Livewire's confirmIdentity dispatches key-vault-fresh; Cancel dispatches key-vault-cancel).
     */
    function fresh(wire) {
        return wire.ensureFresh().then(function (ok) {
            if (ok) return true;
            return new Promise(function (resolve) {
                var settle = function (value) {
                    return function () {
                        window.removeEventListener('key-vault-fresh', yes);
                        window.removeEventListener('key-vault-cancel', no);
                        resolve(value);
                    };
                };
                var yes = settle(true);
                var no = settle(false);
                window.addEventListener('key-vault-fresh', yes);
                window.addEventListener('key-vault-cancel', no);
            });
        });
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return Promise.reject(new Error('no-clipboard'));
    }

    function saveText(text, filename) {
        var url = URL.createObjectURL(new Blob([text], { type: 'application/octet-stream' }));
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    }

    function fileName(name, secret) {
        var slug = String(name || 'secret').toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').slice(0, 60) || 'secret';
        return slug + (/^-----BEGIN /.test(secret) ? '.pem' : '.txt');
    }

    /**
     * Adding an item, or replacing one's secret or passphrase (opts.id and opts.name).
     */
    window.keyVaultLock = function (opts) {
        opts = opts || {};
        return {
            replacing: !!opts.id,
            id: opts.id || null,
            name: opts.name || '',
            secret: '',
            passphrase: '',
            again: '',
            understood: false,
            show: false,
            busy: false,
            error: '',
            supported: true,

            init: function () {
                this.supported = !!(cryptoApi() && cryptoApi().supported());
            },

            get check() {
                return cryptoApi().checkPassphrase(this.passphrase);
            },

            get mismatch() {
                return this.again !== '' && this.again !== this.passphrase;
            },

            clear: function () {
                this.secret = '';
                this.passphrase = '';
                this.again = '';
                this.understood = false;
                this.show = false;
            },

            submit: function () {
                var self = this;
                var api = cryptoApi();
                self.error = '';
                if (!self.supported) { self.error = 'This browser cannot lock secrets. Use a recent Chrome, Edge, Firefox or Safari.'; return; }
                if (!self.replacing && self.name.trim() === '') { self.error = 'Give it a name, such as “Server backup private key”.'; return; }
                if (self.secret === '') { self.error = 'Paste the secret first.'; return; }
                if (new TextEncoder().encode(self.secret).length > api.MAX_SECRET_BYTES) { self.error = 'That secret is too long for the vault (at most 20,000 characters).'; return; }
                if (!self.check.ok) { self.error = self.check.message; return; }
                if (self.again !== self.passphrase) { self.error = 'The two passphrases are not the same.'; return; }
                if (!self.understood) { self.error = 'Tick the box to say you understand that a forgotten passphrase cannot be recovered.'; return; }

                self.busy = true;
                fresh(self.$wire).then(function (ok) {
                    if (!ok) { self.busy = false; return null; }
                    return api.encrypt(self.secret, self.passphrase, self.id).then(function (envelope) {
                        // Opened again before it leaves, so a lock that can't be opened is never kept
                        return api.decrypt(envelope, self.passphrase).then(function (back) {
                            if (back !== self.secret) throw new Error('self-check');
                            return self.replacing ? self.$wire.replace(self.id, envelope) : self.$wire.add(self.name, envelope);
                        });
                    });
                }).then(function (result) {
                    if (result === null) return;
                    self.busy = false;
                    if (result && result.ok) {
                        self.clear();
                        if (!self.replacing) self.name = '';
                    } else {
                        self.error = (result && result.error) || 'It was not saved. Reload the page and try again.';
                    }
                }).catch(function (e) {
                    self.busy = false;
                    self.error = e && e.message === 'self-check'
                        ? 'The browser could not open what it had just locked, so nothing was saved. Try another browser.'
                        : 'It was not saved. Reload the page and try again.';
                });
            },
        };
    };

    /**
     * Revealing one item: the code, then the passphrase, then the secret for a minute.
     * opts: { id, name, codeSent (a code for this item is already in the session), sentTo }
     */
    window.keyVaultReveal = function (opts) {
        return {
            id: opts.id,
            name: opts.name,
            step: opts.codeSent ? 'code' : 'idle',
            sentTo: opts.sentTo || '',
            code: '',
            passphrase: '',
            secret: '',
            envelope: null,
            left: 0,
            busy: false,
            error: '',
            note: '',
            timer: null,
            holdTimer: null,

            init: function () {
                var self = this;
                var hideIfShown = function () { if (self.step === 'shown') self.hide(); };
                document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') hideIfShown(); });
                window.addEventListener('pagehide', function () { self.hide(); });
                document.addEventListener('livewire:navigating', function () { self.hide(); });
            },

            start: function () {
                var self = this;
                self.error = '';
                self.note = '';
                self.busy = true;
                fresh(self.$wire).then(function (ok) {
                    if (!ok) return null;
                    return self.$wire.sendCode(self.id);
                }).then(function (result) {
                    self.busy = false;
                    if (result === null) return;
                    if (result && result.ok) {
                        self.step = 'code';
                        self.sentTo = result.sentTo;
                        self.code = '';
                        self.$nextTick(function () { if (self.$refs.code) self.$refs.code.focus(); });
                    } else {
                        self.error = (result && result.error) || 'The code was not sent. Try again in a minute.';
                    }
                }).catch(function () { self.busy = false; self.error = 'The code was not sent. Reload the page and try again.'; });
            },

            unlock: function () {
                var self = this;
                self.error = '';
                var code = self.code.replace(/\s+/g, '');
                if (!/^\d{6}$/.test(code)) { self.error = 'Type the 6 digits from the email.'; return; }
                self.busy = true;
                fresh(self.$wire).then(function (ok) {
                    if (!ok) return null;
                    return self.$wire.reveal(self.id, code);
                }).then(function (result) {
                    self.busy = false;
                    if (result === null) return;
                    if (result && result.envelope) {
                        self.envelope = result.envelope;
                        self.code = '';
                        self.step = 'passphrase';
                        clearTimeout(self.holdTimer);
                        self.holdTimer = setTimeout(function () { if (self.step === 'passphrase') self.hide(); }, HOLD_MINUTES * 60000);
                        self.$nextTick(function () { if (self.$refs.pass) self.$refs.pass.focus(); });
                    } else {
                        self.error = (result && result.error) || 'That code did not work.';
                        if (result && result.again) { self.step = 'idle'; self.code = ''; }
                    }
                }).catch(function () { self.busy = false; self.error = 'Something went wrong. Reload the page and try again.'; });
            },

            open: function () {
                var self = this;
                self.error = '';
                if (self.passphrase === '') { self.error = 'Type the passphrase you chose when you added it.'; return; }
                self.busy = true;
                cryptoApi().decrypt(self.envelope, self.passphrase).then(function (plain) {
                    self.busy = false;
                    self.passphrase = '';
                    self.secret = plain;
                    self.step = 'shown';
                    self.left = SHOW_SECONDS;
                    clearInterval(self.timer);
                    self.timer = setInterval(function () {
                        self.left -= 1;
                        if (self.left <= 0) self.hide();
                    }, 1000);
                }, function () {
                    self.busy = false;
                    self.error = 'That passphrase does not open it. Check the words and their order, then try again.';
                });
            },

            copy: function () {
                var self = this;
                copyText(self.secret).then(function () {
                    self.note = 'Copied. Paste it where it belongs, then copy something else so it leaves the clipboard.';
                }, function () {
                    if (self.$refs.secret) { self.$refs.secret.focus(); self.$refs.secret.select(); }
                    self.note = 'Your browser did not allow copying: it is selected, press Ctrl+C (or ⌘C).';
                });
            },

            save: function () {
                saveText(this.secret, fileName(this.name, this.secret));
                this.note = 'Saved to your downloads. Move it where it belongs, then delete the copy in Downloads.';
            },

            /** Everything forgotten: the secret, the passphrase and the envelope. Another look needs a new code. */
            hide: function () {
                clearInterval(this.timer);
                clearTimeout(this.holdTimer);
                this.secret = '';
                this.passphrase = '';
                this.envelope = null;
                this.code = '';
                this.left = 0;
                this.busy = false;
                if (this.step === 'shown' || this.step === 'passphrase') this.note = 'Hidden. To see it again, ask for a new code.';
                this.step = 'idle';
                if (this.$refs.secret) this.$refs.secret.value = '';
            },
        };
    };
})();
