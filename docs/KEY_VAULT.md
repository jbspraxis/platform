# Key vault

HQ › Settings and access › **Key vault** holds the owner's own secrets, the first being the server-backup private key (docs/BACKUPS.md section 3). The owner's browser locks each secret with a passphrase only the owner knows, so HQ keeps only something it cannot read. A **locked copy** of each secret, one HTML file, opens offline in any browser with the passphrase, even with HQ and its server gone. Built on 4 Nov 2026, the day the server went down.

## 1. The owner's decisions (4 Nov 2026)

1. **Never lose the backup key, even if the laptop is lost.** It is kept in three places (section 2). Bitwarden holds the main copy.
2. **Zero-knowledge.** Encryption and decryption happen only in the browser (WebCrypto): the passphrase goes through PBKDF2-HMAC-SHA-256 with 600,000 iterations, then AES-256-GCM. HQ's database stores only the ciphertext, the salt, the IV, the KDF's parameters, the item's name and its dates. Nobody with access to the server or the database can read a secret, and the passphrase never leaves the browser.
3. **Revealing takes three steps, in order:**
   1. a fresh HQ sign-in: signed in less than ten minutes ago, or the password typed again (`SignIn::isFresh`, docs/SIGN_IN.md);
   2. a one-time code emailed to the owner (`SigningCodes`: 6 digits, 10 minutes, at most 3 codes in 15 minutes, 5 wrong tries);
   3. the passphrase, typed in the browser.

   The secret then shows with Copy, and hides itself after 60 seconds, when the tab is left, or when HQ moves to another page. Every add, change, delete and reveal is written to `system_audit_logs`, and each reveal emails the owner.
4. **Owner only.** The page is visible only to the protected Owner role. It is a gate (`key-vault`, `KeyVault::allows`), not a permission in `config/access.php`, so Roles and permissions doesn't list it, no role can be given it, and no starter role's `*` reaches it. This is stricter than `'wildcard' => false`, which still lets the owner tick a module for another role. Each owner account sees only the items it locked itself.
5. **Download a locked copy**: a self-contained HTML file holding the ciphertext and a small offline page that opens it. The owner keeps it in the **jbspraxisllc@gmail.com** Google Drive, a different account from praxisjbs@gmail.com, which holds the backups.
6. **Passphrase rules:**
   - at least 4 random words or 14 characters, with a strength hint;
   - a clear warning: **if the passphrase is forgotten, the vault copy and its locked copies can't be recovered.** The Bitwarden copy is the main one.

## 2. Where the backup key lives

| Copy | Where | Opens with | Survives |
|---|---|---|---|
| **Main** | Bitwarden (the owner's vault), as an attachment or as the pasted text | The Bitwarden master password | The laptop and the server being lost |
| **Vault** | HQ › Settings and access › Key vault | HQ password, the emailed code, the passphrase | The laptop being lost. **Not** the server being lost: HQ runs on the server the backups protect |
| **Locked copy** | The jbspraxisllc@gmail.com Google Drive, one `.html` file | The passphrase, in any browser, offline | The laptop, the server and Bitwarden being lost (while the passphrase is remembered) |

The laptop also holds the original file, `Documents\JBS Praxis backup key\backup-private-key.pem`.

Keep the passphrase somewhere other than these three: learn it, and write it on paper kept at home. If it lives only in Bitwarden, losing Bitwarden also loses the two locked copies. Bitwarden's passphrase generator (4 or 5 words) makes a good one.

## 3. Using the vault

- **Add a secret.** Type a name (the only part kept in plain text), paste the secret, type the passphrase twice, tick the box that says a forgotten passphrase can't be recovered, then click **Lock and save**. The browser locks the secret, opens it again to check, and only then sends the locked envelope. HQ refuses anything that isn't the vault's format, including fewer than 600,000 iterations. Right after saving, **Download a locked copy** appears under the item.
- **Reveal.** Click **Reveal**, type your password if asked, and enter the 6-digit code from the email. The code works once, for that item only, in that browser session. Then type the passphrase and click **Show the secret**, which shows it for 60 seconds with **Copy**, **Save as a file** and **Hide it**. A wrong passphrase only gets a message; the browser checks it, so HQ never learns about wrong guesses. Afterwards an email says which item was opened, when, from which device and IP address.
- **Download a locked copy** stays open for 5 minutes after a right code (or after adding), in the same browser session. Each download is audited.
- **Rename** changes only the name. Locked copies keep the name they were made with.
- **Replace** locks a new secret, or the same one with a new passphrase, under the same item. Download a new locked copy afterwards. An old locked copy still opens with the old passphrase, to the old secret. Each version has a **fingerprint** (`3f9a 0c41 7be2 d5aa`), shown on the page and inside the locked copy, so you can tell whether a copy in Drive is current.
- **Delete** removes HQ's copy only. It doesn't touch Bitwarden or any locked copy.

Emails: `App\Mail\KeyVaultMail`, English only. The code is in the body only, never in the subject. Neither email carries any part of a secret.

Audit actions, all with `target_table = key_vault_items`:

| Action | Written when |
|---|---|
| `key_vault.added` | A secret was added |
| `key_vault.renamed` | An item was renamed. `old_values` and `new_values` hold the names |
| `key_vault.replaced` | A secret or passphrase was replaced. The values hold the two fingerprints |
| `key_vault.deleted` | An item was deleted. `old_values` holds its name |
| `key_vault.code_sent` | A code was emailed |
| `key_vault.code_wrong` | A wrong, expired, used or other item's code was typed |
| `key_vault.revealed` | A code was accepted and the envelope went to the browser |
| `key_vault.locked_copy` | A locked copy was downloaded |

They show in Overview › Audit log. The audit row is written before anything leaves: if it can't be written, nothing is revealed.

## 4. The lock

`praxis_core/resources/key-vault/crypto.js` runs on the HQ page and inside every locked copy, so the two always lock and open the same way:

```
key        = PBKDF2-HMAC-SHA-256(passphrase in Unicode NFC, salt = 16 random bytes, 600,000 iterations) → 32 bytes
ciphertext = AES-256-GCM(key, iv = 12 random bytes, additional data = "JBS Praxis key vault v1|<item id>") → bytes + 16-byte tag
```

The envelope (JSON, base64 for the bytes) is the only form in which HQ ever holds a secret:

```json
{ "format": "jbspraxis-key-vault", "v": 1, "id": "<uuid made by the browser>",
  "kdf": { "name": "PBKDF2", "hash": "SHA-256", "iterations": 600000, "salt": "…" },
  "cipher": { "name": "AES-GCM", "length": 256, "tagLength": 128, "iv": "…" },
  "aad": "JBS Praxis key vault v1|<uuid>", "ciphertext": "…" }
```

- The item's id is part of the additional data, so a ciphertext moved onto another row doesn't open.
- PBKDF2 was chosen over Argon2 because no browser has Argon2 built in, and a locked copy must open offline in any browser without a script from anywhere.
- Even at 600,000 iterations, a modern GPU can still try thousands of passphrases a second. That is why the passphrase must be long (section 1), and why the envelope leaves HQ only after the code.
- The secret and the passphrase never leave the page's inputs and the script's memory. They are bound with Alpine (`x-model`), never with Livewire, and spell check is off on those inputs, because browsers' enhanced spell checkers send text away.

`key_vault_items` holds `id`, `user_id`, `name`, `format_version`, `kdf`, `kdf_iterations`, `kdf_salt`, `cipher`, `iv`, `ciphertext`, `last_revealed_at`, `created_at` and `updated_at`, and nothing else (`KeyVaultTest` pins this list).

## 5. The locked copy: opening it offline

The file is named like `key-vault-server-backup-private-key-locked-copy-2026-11-05.html`. It holds the envelope, `crypto.js` and a small page. Its Content-Security-Policy allows only its own script and style, by hash, and no connection of any kind (`connect-src 'none'`, `default-src 'none'`), so nothing typed into it can leave the computer.

**Keep it:** upload it to the **jbspraxisllc@gmail.com** Google Drive, in a folder such as `JBS Praxis key vault`. After a Replace, upload the new file and delete the old one.

**Open it:**

1. On a computer, download the file from Drive. Drive's own preview doesn't run it, and a phone's file viewer usually can't either.
2. Double-click it to open it in Chrome, Edge, Firefox or Safari. No internet is needed.
3. Type the passphrase and click **Open it**.
4. Click **Copy**, or **Save as a file**. For the backup key, the file is saved as `server-backup-private-key.pem` (from the item's name). Rename it to `backup-private-key.pem` and put it in `Documents\JBS Praxis backup key\`, where the restore tool looks (docs/BACKUPS.md section 6).

**If the page won't open it**, any program that does PBKDF2-HMAC-SHA-256 and then AES-256-GCM can. First copy the JSON block under "If this page will not open it" in the file into `envelope.json`. Then use either recipe below.

With Python 3, after `pip install cryptography`. The passphrase is asked for and not shown:

```python
import base64, getpass, hashlib, json, unicodedata
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
e = json.load(open("envelope.json", encoding="utf-8"))
b = base64.b64decode
p = unicodedata.normalize("NFC", getpass.getpass("Passphrase: ")).encode()
key = hashlib.pbkdf2_hmac("sha256", p, b(e["kdf"]["salt"]), e["kdf"]["iterations"], 32)
print(AESGCM(key).decrypt(b(e["cipher"]["iv"]), b(e["ciphertext"]), e["aad"].encode()).decode())
```

With Node.js 18 or later, nothing to install. Save the code below as `open-locked-copy.mjs` and run `node open-locked-copy.mjs envelope.json > backup-private-key.pem`. The passphrase shows as you type it:

```js
import { readFileSync } from 'node:fs';
import { pbkdf2Sync, createDecipheriv } from 'node:crypto';
import { createInterface } from 'node:readline/promises';
const e = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const rl = createInterface({ input: process.stdin, output: process.stderr });
const pass = await rl.question('Passphrase: '); rl.close();
const b = (s) => Buffer.from(s, 'base64');
const key = pbkdf2Sync(Buffer.from(pass.normalize('NFC')), b(e.kdf.salt), e.kdf.iterations, 32, 'sha256');
const data = b(e.ciphertext), d = createDecipheriv('aes-256-gcm', key, b(e.cipher.iv));
d.setAAD(Buffer.from(e.aad)); d.setAuthTag(data.subarray(-16));
process.stdout.write(Buffer.concat([d.update(data.subarray(0, -16)), d.final()]));
```

## 6. If the laptop is lost

Nothing is lost: the backup key is in Bitwarden, in the vault and in the jbspraxisllc Drive.

1. On any computer, sign in to **Bitwarden** and get `backup-private-key.pem` from the item where it was saved. If Bitwarden can't be reached, use the **HQ vault** (section 3, Reveal, then Save as a file). If HQ is down too, use the **locked copy** from the jbspraxisllc Drive (section 5).
2. Save it as `Documents\JBS Praxis backup key\backup-private-key.pem` on the new PC, and copy `deploy/backups/pc/` from the repository next to it to get the restore tool (docs/BACKUPS.md section 6). Check the fingerprint the restore tool prints against the one in System health's README.
3. The laptop held the key unencrypted (the owner chose no passphrase on it, docs/BACKUPS.md section 1). If it may be in someone else's hands, treat the key as exposed:
   1. make a new key (docs/BACKUPS.md section 3) on the new PC;
   2. put its public half in the repository;
   3. save the new private key in Bitwarden, then **Replace** the vault item with it and download a new locked copy;
   4. keep the old key everywhere it is for as long as old backups made with it are kept (Drive keeps 12 months).
4. Change the HQ password and the Bitwarden master password if either was saved on the laptop, and sign the laptop out of Google (myaccount.google.com › Security › Your devices) for both praxisjbs@ and jbspraxisllc@.

## 7. Other things that can go wrong

- **The passphrase is forgotten.** The vault copy and its locked copies are lost for good: nobody can open them, by design. Use Bitwarden. Then delete the vault item, add it again with a new passphrase, and replace the locked copy in Drive.
- **An email says an item was opened and it wasn't you.** Someone has the HQ password *and* the owner's email. Change both passwords now and sign out other sessions. They still need the passphrase: a strong one holds for years even against someone with the envelope. Change the secret itself all the same: for the backup key, make a new key (section 6, step 3).
- **Bitwarden is lost.** Open the locked copy with the passphrase, put the key back in a new password manager, and carry on.
- **The server is lost.** HQ's copy goes with it until the database is restored from a backup, which needs the key from Bitwarden or the locked copy. This is why HQ is never the only copy.

## 8. The first item, after the merge (the owner, not Claude)

1. Open HQ › Settings and access › **Key vault** (Owner only).
2. Name it `Server backup private key`.
3. Open `Documents\JBS Praxis backup key\backup-private-key.pem` with Notepad, select all of it, copy it, and paste it into **Secret**.
4. Choose a passphrase of 4 or 5 random words. Type it twice, tick the box, and click **Lock and save**.
5. Click **Download a locked copy** and upload the file to the jbspraxisllc@gmail.com Drive.
6. Test it once: open the downloaded file with the Wi-Fi off, type the passphrase, and check that the text starts with `-----BEGIN` and ends with `-----END …-----`. Then click **Reveal** in HQ once to see the code email and the notice arrive.

Claude never opens, reads or pastes the key file, the passphrase or any password.

## 9. Testing and local runs

- `tests/Feature/KeyVault/KeyVaultTest.php` (14 tests, on the test MySQL like every HQ screen test) covers:
  - owner only: Partner (`*`), a role with every box ticked and an inactive owner are refused, and the rail;
  - only the envelope is stored: the table's columns, and the secret and passphrase found nowhere in the row, the audit log, the session or the emails;
  - the page has no property for either;
  - fresh sign-in, then the code, then a single release of the envelope;
  - 3 codes in 15 minutes, 5 wrong tries, 10-minute expiry, one item per code, and 5 password retries;
  - rename, replace and delete, each with its audit row;
  - bad envelopes refused;
  - one owner never reaching another's items;
  - the download window.
- `tests/Feature/KeyVault/LockedCopyTest.php` checks the locked copy's markup: nothing loaded or reachable, exactly two scripts, the CSP hashes, the envelope and the recipe, and a hostile item name.
- `tests/Feature/KeyVault/KeyVaultCryptoTest.php` runs `tests/js/key-vault-crypto.test.mjs` on Node (`node --test`; CI has Node for the asset build), with a locked copy it has just made: that file's own script must open it. It also has OpenSSL open what `crypto.js` locked, and checks the fixed vector both sides use.
- Run locally (Laragon):

  ```bash
  DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=praxis_test_vault php vendor/bin/phpunit tests/Feature/KeyVault
  ```

  The JS tests alone: `node --test tests/js/key-vault-crypto.test.mjs`.
- The page's script is plain JavaScript served by `GET /system/key-vault/vault.js`: `crypto.js` then `page.js`, no build step.

## 10. Files

- **Code:** `app/Support/KeyVault/` (KeyVault, Envelope, LockedCopy), `app/Livewire/Hq/System/KeyVaultPage.php`, `app/Http/Controllers/KeyVaultController.php`, `app/Models/KeyVaultItem.php`, `app/Mail/KeyVaultMail.php`.
- **Scripts and views:** `resources/key-vault/` (crypto.js, page.js, locked-copy.js, locked-copy.css), `resources/views/livewire/hq/system/key-vault.blade.php` and `partials/key-vault-lock-fields.blade.php`, `resources/views/key-vault/locked-copy.blade.php`, `resources/views/emails/key-vault.blade.php`, `lang/en/emails/key_vault.php`.
- **Migration:** `2026_11_04_970000_create_key_vault_items.php`.
- **Shared files touched:**
  - `routes/web.php`: three routes in the system group;
  - `AppServiceProvider`: the `key-vault` gate;
  - `components/layouts/hq.blade.php`: the rail link under Settings and access;
  - `docs/BACKUPS.md`: the key section points here.
