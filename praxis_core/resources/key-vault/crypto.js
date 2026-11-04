/*
 * JBS Praxis key vault: the lock (docs/KEY_VAULT.md section 4). Runs only in the owner's browser, on HQ's Key vault
 * page and inside every locked copy, so the two can never disagree. HQ's server never sees the passphrase or the
 * secret: it keeps what encrypt() returns, and gives it back only after the owner's emailed code.
 *
 *   key        = PBKDF2-HMAC-SHA-256(passphrase in Unicode NFC, 16 random bytes of salt, 600,000 iterations), 32 bytes
 *   ciphertext = AES-256-GCM(key, 12 random bytes of IV, additional data "JBS Praxis key vault v1|<id>"), tag at the end
 *
 * WebCrypto only: no library, no network. Argon2 would be stronger against guessing, but no browser has it built
 * in, and a locked copy must open offline in any browser without a script from anywhere.
 */
(function (root) {
    'use strict';

    var FORMAT = 'jbspraxis-key-vault';
    var VERSION = 1;
    var AAD_PREFIX = 'JBS Praxis key vault v1|';
    var ITERATIONS = 600000;
    var MIN_ITERATIONS = 600000;
    var MAX_ITERATIONS = 10000000;
    var MAX_SECRET_BYTES = 20000;

    function subtle() {
        var c = root.crypto;
        if (!c || !c.subtle || typeof c.getRandomValues !== 'function') {
            throw new Error('no-webcrypto');
        }
        return c.subtle;
    }

    function utf8(text) {
        return new TextEncoder().encode(text);
    }

    function toBase64(bytes) {
        var binary = '';
        for (var i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
        return root.btoa(binary);
    }

    function fromBase64(text) {
        var binary = root.atob(String(text));
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes;
    }

    function random(length) {
        var bytes = new Uint8Array(length);
        root.crypto.getRandomValues(bytes);
        return bytes;
    }

    /** A version 4 UUID: the item's id, made here because the ciphertext is bound to it */
    function newId() {
        if (typeof root.crypto.randomUUID === 'function') return root.crypto.randomUUID();
        var b = random(16);
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        var hex = Array.prototype.map.call(b, function (x) { return (x + 0x100).toString(16).slice(1); }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function deriveKey(passphrase, salt, iterations) {
        var s = subtle();
        return s.importKey('raw', utf8(String(passphrase).normalize('NFC')), 'PBKDF2', false, ['deriveKey']).then(function (base) {
            return s.deriveKey({ name: 'PBKDF2', hash: 'SHA-256', salt: salt, iterations: iterations }, base,
                { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
        });
    }

    /**
     * Locks a secret with a passphrase. id: the item's id when replacing one, else a new one is made.
     * Resolves to the envelope HQ keeps: { format, v, id, kdf: {...}, cipher: {...}, ciphertext }.
     */
    function encrypt(secret, passphrase, id) {
        var plain = utf8(String(secret));
        if (plain.length === 0) return Promise.reject(new Error('empty'));
        if (plain.length > MAX_SECRET_BYTES) return Promise.reject(new Error('too-long'));
        var itemId = id || newId();
        var salt = random(16);
        var iv = random(12);
        return deriveKey(passphrase, salt, ITERATIONS).then(function (key) {
            return subtle().encrypt({ name: 'AES-GCM', iv: iv, additionalData: utf8(AAD_PREFIX + itemId), tagLength: 128 }, key, plain);
        }).then(function (sealed) {
            return {
                format: FORMAT,
                v: VERSION,
                id: itemId,
                kdf: { name: 'PBKDF2', hash: 'SHA-256', iterations: ITERATIONS, salt: toBase64(salt) },
                cipher: { name: 'AES-GCM', length: 256, tagLength: 128, iv: toBase64(iv) },
                ciphertext: toBase64(new Uint8Array(sealed)),
            };
        });
    }

    /** Opens an envelope. Rejects with 'wrong-passphrase' when the passphrase (or the file) doesn't match. */
    function decrypt(envelope, passphrase) {
        var e = envelope || {};
        var kdf = e.kdf || {};
        var cipher = e.cipher || {};
        if (e.format !== FORMAT || e.v !== VERSION || kdf.name !== 'PBKDF2' || kdf.hash !== 'SHA-256'
            || cipher.name !== 'AES-GCM' || cipher.length !== 256 || typeof e.id !== 'string'
            || !(kdf.iterations >= MIN_ITERATIONS && kdf.iterations <= MAX_ITERATIONS)) {
            return Promise.reject(new Error('bad-format'));
        }
        var salt, iv, sealed;
        try {
            salt = fromBase64(kdf.salt);
            iv = fromBase64(cipher.iv);
            sealed = fromBase64(e.ciphertext);
        } catch (err) {
            return Promise.reject(new Error('bad-format'));
        }
        return deriveKey(passphrase, salt, kdf.iterations).then(function (key) {
            return subtle().decrypt({ name: 'AES-GCM', iv: iv, additionalData: utf8(AAD_PREFIX + e.id), tagLength: 128 }, key, sealed);
        }).then(function (plain) {
            return new TextDecoder('utf-8', { fatal: true }).decode(new Uint8Array(plain));
        }, function () {
            throw new Error('wrong-passphrase');
        });
    }

    /**
     * The passphrase rule (owner, 4 Nov 2026): at least 4 words or at least 14 characters, and a hint of how
     * hard it is to guess. The bits assume words picked at random (dice, or Bitwarden's passphrase generator).
     * Returns { ok, strength: 'weak'|'fair'|'strong', bits, words, length, message }.
     */
    function checkPassphrase(passphrase) {
        var p = String(passphrase || '');
        var length = Array.from(p).length;
        var words = p.trim().split(/[\s\-_.,;:+]+/).filter(function (w) { return /\p{L}{3,}/u.test(w); });
        var distinct = new Set(words.map(function (w) { return w.toLowerCase(); })).size;
        var uniqueChars = new Set(Array.from(p)).size;

        var pool = 0;
        if (/[a-z]/.test(p)) pool += 26;
        if (/[A-Z]/.test(p)) pool += 26;
        if (/[0-9]/.test(p)) pool += 10;
        if (/[^A-Za-z0-9]/.test(p)) pool += 33;
        var charBits = length * Math.log2(Math.max(pool, 1));
        if (uniqueChars < length / 2) charBits = charBits / 2;
        // Mostly letters in words: a guesser tries words, not characters, so count about 12.9 bits a word (a
        // 7,776-word list, as dice and Bitwarden use), plus a little for any digits or symbols added
        var letters = (p.match(/\p{L}/gu) || []).length;
        var extras = p.replace(/[\p{L}\s\-_.,;:+]/gu, '').length;
        var wordish = distinct >= 2 && letters >= length * 0.7;
        var bits = Math.round(wordish ? distinct * 12.9 + extras * 3.3 : charBits);

        var common = /^(password|passphrase|jbs ?praxis|qwerty|azerty|123456|letmein)/i.test(p.replace(/\s+/g, ' ').trim());
        var longEnough = distinct >= 4 || length >= 14;
        var ok = longEnough && uniqueChars >= 6 && !common && bits >= 50;
        var strength = !ok ? 'weak' : (bits < 60 ? 'fair' : 'strong');
        var message;
        if (length === 0) message = 'Use at least 4 random words, or 14 characters or more.';
        else if (common) message = 'Too easy to guess: pick words nobody would connect with you or JBS Praxis.';
        else if (!longEnough) message = 'Too short: use at least 4 random words, or 14 characters or more.';
        else if (!ok) message = 'Too easy to guess: mix letters, digits and symbols, or use 4 random words.';
        else if (strength === 'fair') message = 'Good enough. One more random word makes it much harder to guess.';
        else message = 'Strong.';

        return { ok: ok, strength: strength, bits: bits, words: distinct, length: length, message: message };
    }

    var api = {
        FORMAT: FORMAT,
        VERSION: VERSION,
        AAD_PREFIX: AAD_PREFIX,
        ITERATIONS: ITERATIONS,
        MAX_SECRET_BYTES: MAX_SECRET_BYTES,
        encrypt: encrypt,
        decrypt: decrypt,
        checkPassphrase: checkPassphrase,
        newId: newId,
        supported: function () {
            try { subtle(); return true; } catch (e) { return false; }
        },
    };

    root.KeyVaultCrypto = api;
})(typeof globalThis !== 'undefined' ? globalThis : this);
