// Prints the envelope crypto.js makes for KEY_VAULT_SECRET and KEY_VAULT_PASSPHRASE (and KEY_VAULT_ID, if set), so
// tests/Feature/KeyVault/KeyVaultCryptoTest.php can open the browser's lock with PHP's OpenSSL: the same recipe
// anyone could use without HQ.
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const sandbox = { crypto: globalThis.crypto, TextEncoder, TextDecoder, atob, btoa };
sandbox.globalThis = sandbox;
vm.runInNewContext(readFileSync(new URL('../../resources/key-vault/crypto.js', import.meta.url), 'utf8'), sandbox);
const vault = sandbox.KeyVaultCrypto;

const envelope = await vault.encrypt(process.env.KEY_VAULT_SECRET, process.env.KEY_VAULT_PASSPHRASE, process.env.KEY_VAULT_ID || undefined);
process.stdout.write(JSON.stringify(envelope));
