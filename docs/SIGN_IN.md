# Passkeys and the sign-in PIN (HQ and Client Portal)

Agreed with the owner on 31 October 2026: staff (HQ, `hq.jbspraxis.com`) and clients (Client Portal, `portal.jbspraxis.com`) can sign in with a **passkey** (their device's fingerprint, face or screen lock) or a **4-digit PIN** as well as their password, the way MyCity App does. The task force tracker is not touched.

## 1. Owner decisions

| # | Question | Decision |
|---|---|---|
| 1 | Which sign-ins | **HQ and the Client Portal.** Not the task force tracker. |
| 2 | The PIN | **4 digits, like MyCity**: email address + PIN on the sign-in page. Common shapes refused (1234, 0000, 1212, 1155, the most common codes). The first 5 wrong in a row cost nothing, then each waits twice as long (2 s, 4 s ... up to 15 minutes). 10 wrong in a row, or 20 in any 30 days, switch the PIN off; the password still works and the person sets a new PIN. Never a lockout, never an administrator. |
| 3 | Where they are set up | **HQ: My profile, Sign-in section. Portal: Sign-in settings** (in the rail, opens a drawer). Plus a **card after a password sign-in** for someone with no passkey: "Sign in faster next time" / "Add a passkey" / "Not now" (hides it on that device for 14 days, like the install card). |

## 2. What people see

- **Sign-in page** (both): under the usual form, "or", then **Sign in with a passkey** (only in a browser that can use passkeys) and **Sign in with a PIN** (switches the password field to a PIN field; **Use your password** switches back). A passkey needs nothing typed: the browser offers the person's passkeys for that site.
- **The card** appears at the top of HQ (any screen) or the portal dashboard right after a password sign-in, only on a device with a fingerprint, face or screen lock built in, and only for the first 10 minutes.
- **Settings**: the person's passkeys (device and browser, "Synced" when a password manager keeps it, added and last used dates, **Remove**), **Add a passkey**, and the PIN (**Set a PIN** / **Change PIN** / **Remove PIN**). Adding a passkey or setting a PIN within 10 minutes of signing in needs nothing more; after that the password is asked again first. Removing needs nothing: it only takes a way in away.
- Every word is in `lang/en/sign_in.php` and `lang/fr/sign_in.php` (the portal shows French to French readers; HQ is English).

## 3. Passkeys: how they are checked

The server has no Composer (docs/PWA.md), so WebAuthn is written in PHP with OpenSSL: `App\Support\SignIn\WebAuthn` (with `Cbor` for the authenticator's binary answers). The decisions a reviewer should check:

1. **The fingerprint, face or screen lock is required** at creation and at every use (`userVerification: required`, refused without the UV flag). A passkey replaces the password, so it is two factors on its own: the device and the person unlocking it.
2. **The relying party is the host itself** (`config/app.php` `domains`): an HQ passkey works only on HQ, a portal passkey only on the portal. The origin is checked against configuration, never taken from the request. Passkeys are also stored per guard (`web`, `client`), so even the same credential could never cross over.
3. **Discoverable passkeys** (`residentKey: required`): signing in needs nothing typed, and the options for signing in name no credentials, so they say nothing about whether an email address has a passkey.
4. **One per device**: the person's passkeys are excluded when adding one. At most 10 per account.
5. **The signature counter must go up** when the authenticator keeps one (a counter that goes back is a cloned key). Synced passkeys report 0 every time, which is allowed.
6. **No attestation** is asked for: the make of authenticator isn't checked. ES256 (P-256) and RS256 (Windows Hello) keys are accepted.
7. **Challenges** live in the session, are good for one answer and two minutes, and belong to one purpose (adding or signing in) and one guard.

The browser side is `window.praxisPasskey` (`resources/views/partials/passkey-script.blade.php`, in the `<head>` of both apps): it converts the options and answers between base64url and the bytes `navigator.credentials` needs. `SecurityHeaders` allows `publickey-credentials-create` and `-get` for the site itself.

## 4. The PIN

`App\Support\SignIn\Pins`: bcrypt hash, wrong tries counted per account in `sign_in_pins` (the row is locked while a try is checked, so two at once are both counted). On top of that, 30 wrong PINs from one connection in 15 minutes stop that connection trying PINs for 15 minutes, so one machine can't try a PIN across many accounts. An email address with no account or no PIN gets the same answer, after the same work, as a wrong PIN; the remaining tries are only said once 5 or fewer are left.

## 5. Data

Migration `2026_10_31_700000_create_sign_in_methods.php`:

- `passkeys`: `guard` (`web` = users, `client` = client_users), `owner_id`, `credential_id` (unique), `public_key` (PEM), `algorithm`, `sign_count`, `transports`, `backed_up`, `name` (device and browser), `last_used_at`.
- `sign_in_pins`: one row per account: `pin_hash` (null once switched off), `failed_count`, `last_failed_at`, `recent_failures` (the last 30 days), `switched_off_at`.

No foreign keys (one table serves both account tables); `SignIn::forget()` deletes an account's rows if accounts are ever deleted for good. Suspended accounts can't sign in with either (only Active accounts are looked up; clients also need a password set).

Audit log (`system_audit_logs`): `AUTH_LOGIN` says which way someone signed in; `PASSKEY_ADDED`, `PASSKEY_REMOVED`, `PIN_SET`, `PIN_CHANGED`, `PIN_REMOVED`, `PIN_SWITCHED_OFF`. Client rows carry `target_table = client_users`.

## 6. Files

| Part | Files |
|---|---|
| Rules | `app/Support/SignIn/{SignIn,WebAuthn,Cbor,Passkeys,PasskeyRefused,Pins,DeviceName}.php`, `config/sign_in.php` |
| Sign-in pages | `app/Livewire/Concerns/SignsInWithPasskeyOrPin.php`, `app/Livewire/Hq/Auth/LoginTerminal.php`, `app/Livewire/ClientOs/LoginMatrix.php`, their views, `resources/views/livewire/sign-in/alternatives.blade.php` |
| Settings and card | `app/Livewire/Account/{SignInMethods,PasskeyOffer}.php` and their views; My profile (`livewire/hq/account/my-profile.blade.php`); the portal drawer (`livewire/client-os/dashboard.blade.php`); the card's place in `components/layouts/hq.blade.php` |
| Browser | `resources/views/partials/passkey-script.blade.php` (included by `partials/app-head.blade.php`) |
| Words | `lang/en/sign_in.php`, `lang/fr/sign_in.php` |
| Tests | `tests/Unit/SignIn/*`, `tests/Feature/SignInMethodsTest.php` (MySQL), `tests/Support/SoftAuthenticator.php` (a software authenticator with real keys) |

## 7. Running it locally

Browsers allow passkeys on `http` only for `localhost` and its subdomains. Serve HQ as `hq.localhost` and the portal as `portal.localhost` and tell the app where:

```
HQ_DOMAIN=hq.localhost
PORTAL_DOMAIN=portal.localhost
WEBAUTHN_ORIGIN_HQ=http://hq.localhost:8000
WEBAUTHN_ORIGIN_PORTAL=http://portal.localhost:8000
```

Live needs none of these: the defaults are the real hosts on https.
