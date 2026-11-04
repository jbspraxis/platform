<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Content-Security-Policy" content="{{ $csp }}">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex, nofollow">
<title>Locked copy: {{ $name }}</title>
<style>{!! $style !!}</style>
</head>
<body>
<main>
    <p class="brand">JBS Praxis · Key vault · Locked copy</p>
    <h1 id="item-name">{{ $name }}</h1>
    <p>This file holds one secret from the JBS Praxis HQ key vault, locked with the owner’s passphrase. It opens here, in this browser, without the internet and without HQ: nothing you type is sent anywhere.</p>

    <div class="card">
        <form id="unlock" autocomplete="off">
            <label for="passphrase">Passphrase</label>
            <input id="passphrase" type="password" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore>
            <div class="row"><button id="open" type="submit">Open it</button></div>
            <p id="message" class="msg" role="status" aria-live="polite"></p>
            <noscript><p class="msg bad">This page needs JavaScript to open it. Open the file in a recent browser with JavaScript on, or use the recipe at the end.</p></noscript>
        </form>

        <div id="shown" hidden>
            <label for="secret">The secret (hides itself in <span id="left">60</span> seconds)</label>
            <textarea id="secret" readonly autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-gramm="false"></textarea>
            <div class="row">
                <button id="copy" type="button">Copy</button>
                <button id="save" type="button" class="secondary">Save as a file</button>
                <button id="hide" type="button" class="secondary">Hide it</button>
            </div>
            <p id="note" class="msg" role="status" aria-live="polite"></p>
        </div>
    </div>

    <dl class="facts">
        <dt>Locked in HQ</dt><dd>{{ $lockedAt }}</dd>
        <dt>This file made</dt><dd>{{ $madeAt }}</dd>
        <dt>Fingerprint</dt><dd>{{ $fingerprint }} (the same as on HQ’s Key vault page while this copy is current)</dd>
        <dt>Lock</dt><dd>PBKDF2-HMAC-SHA-256, {{ $iterations }} iterations, then AES-256-GCM</dd>
    </dl>

    <h2>If you forget the passphrase</h2>
    <p class="muted">Nobody can open this file without it: not JBS Praxis, not the server, not Claude. Use the main copy in Bitwarden instead.</p>

    <details>
        <summary>If this page will not open it</summary>
        <p class="muted">Open the file on a computer, from the computer itself (a preview in Google Drive or a phone’s file viewer does not run it), in a recent Chrome, Edge, Firefox or Safari. Any program that does PBKDF2-HMAC-SHA-256 then AES-256-GCM can open it too, with the values below and the passphrase. Save the block below as <code>envelope.json</code>, then run this Python 3 (after <code>pip install cryptography</code>); the passphrase is asked for and not shown. docs/KEY_VAULT.md in the jbspraxis/platform repository has the same recipe for Node.js.</p>
<pre>import base64, getpass, hashlib, json, unicodedata
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
e = json.load(open("envelope.json", encoding="utf-8"))
b = base64.b64decode
p = unicodedata.normalize("NFC", getpass.getpass("Passphrase: ")).encode()
key = hashlib.pbkdf2_hmac("sha256", p, b(e["kdf"]["salt"]), e["kdf"]["iterations"], 32)
print(AESGCM(key).decrypt(b(e["cipher"]["iv"]), b(e["ciphertext"]), e["aad"].encode()).decode())</pre>
<pre>{{ $envelopePretty }}</pre>
    </details>
</main>
<script type="application/json" id="envelope">{!! $envelopeJson !!}</script>
<script>{!! $script !!}</script>
</body>
</html>
