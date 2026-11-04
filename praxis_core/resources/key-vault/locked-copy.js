/*
 * The page inside a key vault locked copy (docs/KEY_VAULT.md section 5): it reads the envelope in this file, asks
 * for the passphrase and opens it with crypto.js, which comes just before this in the same script. No network:
 * the file's Content-Security-Policy allows no connection, image, font or form target at all.
 */
(function () {
    'use strict';
    if (typeof document === 'undefined') return;

    var SHOW_SECONDS = 60;
    var $ = function (id) { return document.getElementById(id); };

    document.addEventListener('DOMContentLoaded', function () {
        var envelope = JSON.parse($('envelope').textContent);
        var name = $('item-name').textContent;
        var form = $('unlock');
        var pass = $('passphrase');
        var button = $('open');
        var message = $('message');
        var out = $('shown');
        var text = $('secret');
        var left = $('left');
        var note = $('note');
        var timer = null;

        var say = function (words, bad) {
            message.textContent = words;
            message.className = bad ? 'msg bad' : 'msg';
        };

        var hide = function () {
            clearInterval(timer);
            text.value = '';
            out.hidden = true;
            form.hidden = false;
            note.textContent = '';
        };

        if (!window.KeyVaultCrypto || !window.KeyVaultCrypto.supported()) {
            say('This browser cannot open it. Use a recent Chrome, Edge, Firefox or Safari on a computer, and open the file from the computer itself (not from a preview in Drive or a phone’s file viewer).', true);
            button.disabled = true;
            return;
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (pass.value === '') { say('Type the passphrase.', true); return; }
            button.disabled = true;
            say('Opening… this takes a second or two.');
            window.KeyVaultCrypto.decrypt(envelope, pass.value).then(function (plain) {
                pass.value = '';
                button.disabled = false;
                say('');
                text.value = plain;
                form.hidden = true;
                out.hidden = false;
                var n = SHOW_SECONDS;
                left.textContent = String(n);
                clearInterval(timer);
                timer = setInterval(function () {
                    n -= 1;
                    left.textContent = String(n);
                    if (n <= 0) hide();
                }, 1000);
            }, function (err) {
                button.disabled = false;
                say(err && err.message === 'bad-format'
                    ? 'This file is damaged: it is not a key vault locked copy any more. Use another copy.'
                    : 'That passphrase does not open it. Check the words and their order, then try again.', true);
                pass.select();
            });
        });

        $('copy').addEventListener('click', function () {
            var done = function () { note.textContent = 'Copied. Paste it where it belongs, then copy something else so it leaves the clipboard.'; };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text.value).then(done, function () { text.select(); note.textContent = 'Selected: press Ctrl+C (or ⌘C) to copy it.'; });
            } else {
                text.select();
                note.textContent = 'Selected: press Ctrl+C (or ⌘C) to copy it.';
            }
        });

        $('save').addEventListener('click', function () {
            var slug = name.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').slice(0, 60) || 'secret';
            var url = URL.createObjectURL(new Blob([text.value], { type: 'application/octet-stream' }));
            var a = document.createElement('a');
            a.href = url;
            a.download = slug + (/^-----BEGIN /.test(text.value) ? '.pem' : '.txt');
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            note.textContent = 'Saved to your downloads as ' + a.download + '. Move it where it belongs.';
        });

        $('hide').addEventListener('click', hide);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden' && !out.hidden) hide(); });
        pass.focus();
    });
})();
