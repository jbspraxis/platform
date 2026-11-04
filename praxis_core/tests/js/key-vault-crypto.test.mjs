// The key vault's browser lock (resources/key-vault/crypto.js, docs/KEY_VAULT.md section 4), on Node's WebCrypto,
// which is the same API browsers have. Run with: node --test tests/js/
// tests/Feature/KeyVault/KeyVaultCryptoTest.php runs these too, as part of php artisan test, with a locked copy it
// has just made (KEY_VAULT_LOCKED_COPY): the file's own script must open the file's own envelope.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// A classic script, as browsers and the locked copy run it: in a bare context with WebCrypto and nothing else
// (no fetch, no XMLHttpRequest, no document)
function run(code) {
    const sandbox = { crypto: globalThis.crypto, TextEncoder, TextDecoder, atob, btoa };
    sandbox.globalThis = sandbox;
    vm.runInNewContext(code, sandbox);
    return sandbox.KeyVaultCrypto;
}
const vault = run(readFileSync(new URL('../../resources/key-vault/crypto.js', import.meta.url), 'utf8'));

const SECRET = '-----BEGIN TEST KEY-----\nTHIS IS A TEST, NOT A KEY\n-----END TEST KEY-----\n';
const PASS = 'tidal-orbit-marble-quiet-lantern';
const b64 = (s) => Buffer.from(s, 'base64');

test('what it locks, it opens again with the same passphrase', async () => {
    const envelope = await vault.encrypt(SECRET, PASS);
    assert.equal(await vault.decrypt(envelope, PASS), SECRET);
});

test('the envelope is the format HQ keeps, and holds nothing readable', async () => {
    const envelope = await vault.encrypt(SECRET, PASS);
    assert.equal(envelope.format, 'jbspraxis-key-vault');
    assert.equal(envelope.v, 1);
    assert.match(envelope.id, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    assert.deepEqual(Object.keys(envelope).sort(), ['cipher', 'ciphertext', 'format', 'id', 'kdf', 'v']);
    assert.deepEqual({ ...envelope.kdf, salt: undefined }, { name: 'PBKDF2', hash: 'SHA-256', iterations: 600000, salt: undefined });
    assert.equal(b64(envelope.kdf.salt).length, 16);
    assert.deepEqual({ ...envelope.cipher, iv: undefined }, { name: 'AES-GCM', length: 256, tagLength: 128, iv: undefined });
    assert.equal(b64(envelope.cipher.iv).length, 12);
    assert.equal(b64(envelope.ciphertext).length, Buffer.byteLength(SECRET) + 16, 'the secret, then the 16-byte tag');

    const json = JSON.stringify(envelope);
    for (const piece of ['TEST KEY', 'NOT A KEY', PASS, 'tidal', Buffer.from(SECRET).toString('base64').slice(0, 12)]) {
        assert.ok(!json.includes(piece), `the envelope does not contain "${piece}"`);
    }
});

test('two locks of the same secret share nothing: new salt, new IV, new id', async () => {
    const a = await vault.encrypt(SECRET, PASS);
    const b = await vault.encrypt(SECRET, PASS);
    assert.notEqual(a.id, b.id);
    assert.notEqual(a.kdf.salt, b.kdf.salt);
    assert.notEqual(a.cipher.iv, b.cipher.iv);
    assert.notEqual(a.ciphertext, b.ciphertext);
});

test('replacing keeps the item id the ciphertext is bound to', async () => {
    const id = '0b6f3c1e-8d2a-4f5b-9c7d-2e1f0a9b8c7d';
    const envelope = await vault.encrypt('new secret', PASS, id);
    assert.equal(envelope.id, id);
    assert.equal(await vault.decrypt(envelope, PASS), 'new secret');
});

test('a wrong passphrase, a changed byte or another item id does not open it', async () => {
    const envelope = await vault.encrypt(SECRET, PASS);
    await assert.rejects(vault.decrypt(envelope, PASS + ' '), /wrong-passphrase/);
    await assert.rejects(vault.decrypt(envelope, 'Tidal-orbit-marble-quiet-lantern'), /wrong-passphrase/);

    const bytes = b64(envelope.ciphertext);
    bytes[3] ^= 1;
    await assert.rejects(vault.decrypt({ ...envelope, ciphertext: bytes.toString('base64') }, PASS), /wrong-passphrase/);

    // The ciphertext is bound to its item (AES-GCM additional data): moved onto another row, it does not open
    await assert.rejects(vault.decrypt({ ...envelope, id: '11111111-2222-4333-8444-555555555555' }, PASS), /wrong-passphrase/);
});

test('an envelope with too few iterations or another cipher is refused before any guessing', async () => {
    const envelope = await vault.encrypt(SECRET, PASS);
    await assert.rejects(vault.decrypt({ ...envelope, kdf: { ...envelope.kdf, iterations: 100000 } }, PASS), /bad-format/);
    await assert.rejects(vault.decrypt({ ...envelope, cipher: { ...envelope.cipher, name: 'AES-CBC' } }, PASS), /bad-format/);
    await assert.rejects(vault.decrypt({ ...envelope, format: 'something-else' }, PASS), /bad-format/);
    await assert.rejects(vault.decrypt(null, PASS), /bad-format/);
});

test('the passphrase is read in Unicode NFC, so an accent typed either way opens it', async () => {
    const composed = 'café lumière ombre rivière';
    const decomposed = composed.normalize('NFD');
    assert.notEqual(composed, decomposed);
    const envelope = await vault.encrypt(SECRET, composed);
    assert.equal(await vault.decrypt(envelope, decomposed), SECRET);
});

test('empty and oversized secrets are refused', async () => {
    await assert.rejects(vault.encrypt('', PASS), /empty/);
    await assert.rejects(vault.encrypt('x'.repeat(vault.MAX_SECRET_BYTES + 1), PASS), /too-long/);
    const longest = await vault.encrypt('x'.repeat(vault.MAX_SECRET_BYTES), PASS);
    assert.equal(b64(longest.ciphertext).length, vault.MAX_SECRET_BYTES + 16);
});

test('it opens what PHP and OpenSSL locked the same way (the recipe in docs/KEY_VAULT.md)', async () => {
    // Made with hash_pbkdf2('sha256', …, 600000) and openssl_encrypt('aes-256-gcm') with the same additional data
    const envelope = {
        format: 'jbspraxis-key-vault', v: 1, id: '6f1c2a9e-3b4d-4c5e-8f70-1a2b3c4d5e6f',
        kdf: { name: 'PBKDF2', hash: 'SHA-256', iterations: 600000, salt: 'AAECAwQFBgcICQoLDA0ODw==' },
        cipher: { name: 'AES-GCM', length: 256, tagLength: 128, iv: 'oKGio6Slpqeoqaqr' },
        ciphertext: '/dCczhmj6lbN4kMicTMA4xTBqwe9WogyeZDTj7S4s48bAwdalir1jIt7OuWy1fiXB9ZZyXe7JhlmB5/P8/l99qiaOSl3xZ9dIKhGYbAniYmDtIbsuUO9MkXjkNN/AFAhKQ==',
    };
    assert.equal(await vault.decrypt(envelope, PASS), '-----BEGIN TEST KEY-----\nTHIS IS A TEST VECTOR, NOT A KEY\n-----END TEST KEY-----\n');
});

test('the passphrase rule: 4 random words or 14 characters, never something common', () => {
    const rule = (p) => vault.checkPassphrase(p);
    assert.equal(rule('').ok, false);
    assert.equal(rule('short pass').ok, false);
    assert.equal(rule('Tr0ub4dor&3').ok, false, '11 characters');
    assert.equal(rule('tidal orbit marble quiet').ok, true, '4 words');
    assert.equal(rule('tidal-orbit-marble-quiet-lantern').strength, 'strong', '5 words');
    assert.equal(rule('k7#Qp!2vRx9@Lm').ok, true, '14 characters');
    assert.equal(rule('aaaaaaaaaaaaaaaaaaaa').ok, false, 'one character repeated');
    assert.equal(rule('abab abab abab abab').ok, false, 'the same word four times');
    assert.equal(rule('password password password password').ok, false, 'common');
    assert.equal(rule('JBS Praxis backup key 2026').ok, false, 'about the company');
    assert.equal(rule('98765432109876').ok, false, '14 digits: too easy to guess');
    assert.equal(rule('tidal orbit marble quiet').strength, 'fair', '4 words is the floor, about 52 bits');
    assert.equal(rule('tidal orbit marble quiet').bits, 52);
    assert.ok(rule('k7#Qp!2vRx9@Lm').bits >= 80);
});

test('a locked copy HQ made opens with its own script, offline (KEY_VAULT_LOCKED_COPY)', { skip: !process.env.KEY_VAULT_LOCKED_COPY }, async () => {
    const html = readFileSync(process.env.KEY_VAULT_LOCKED_COPY, 'utf8');
    const envelope = JSON.parse(html.match(/<script type="application\/json" id="envelope">([\s\S]*?)<\/script>/)[1]);
    const scripts = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
    assert.equal(scripts.length, 1, 'one script, inline');

    const own = run(scripts[0]);
    assert.equal(await own.decrypt(envelope, process.env.KEY_VAULT_PASSPHRASE), process.env.KEY_VAULT_SECRET);
    await assert.rejects(own.decrypt(envelope, 'not the passphrase'), /wrong-passphrase/);
});
