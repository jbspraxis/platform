{{-- The secret and its passphrase (inside an x-data="keyVaultLock()"): Alpine only, never wire:model, so they stay in this browser --}}
<div class="field">
    <label class="label" for="{{ $prefix }}-secret">Secret</label>
    <textarea id="{{ $prefix }}-secret" class="input textarea kv-secret" x-model="secret" rows="8"
              autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-gramm="false" data-enable-grammarly="false"></textarea>
    <p class="help">Paste all of it: for a key file, from <code>-----BEGIN</code> to the last <code>-----</code>. It is locked in this browser before anything is sent.</p>
</div>
<div class="kv-two">
    <div class="field">
        <label class="label" for="{{ $prefix }}-pass">Passphrase</label>
        <div class="kv-pass">
            <input id="{{ $prefix }}-pass" :type="show ? 'text' : 'password'" type="password" class="input" x-model="passphrase"
                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore
                   aria-describedby="{{ $prefix }}-strength">
            <button type="button" class="btn btn-ghost btn-sm kv-toggle" @click="show = !show" :aria-pressed="show.toString()" x-text="show ? 'Hide' : 'Show'">Show</button>
        </div>
        <div class="kv-meter" :data-strength="passphrase === '' ? '' : check.strength" aria-hidden="true"><span></span><span></span><span></span></div>
        <p class="help" id="{{ $prefix }}-strength" aria-live="polite" x-text="check.message + (passphrase !== '' && check.ok ? ' About ' + check.bits + ' bits, if the words or characters were picked at random.' : '')">Use at least 4 random words, or 14 characters or more.</p>
    </div>
    <div class="field">
        <label class="label" for="{{ $prefix }}-again">Passphrase again</label>
        <input id="{{ $prefix }}-again" :type="show ? 'text' : 'password'" type="password" class="input" x-model="again"
               autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" data-1p-ignore data-lpignore="true" data-bwignore>
        <p class="field-error" x-show="mismatch" x-cloak>The two passphrases are not the same.</p>
    </div>
</div>
<div class="alert alert-warning" role="note">
    <span><strong>If you forget this passphrase, nothing can open this copy or its locked copies:</strong> not HQ, not JBS Praxis, not Claude. Bitwarden holds the main copy. Learn the passphrase, and write it down somewhere safe that is not this computer and not the same Google account as the locked copy. Bitwarden’s passphrase generator (4 or 5 words) makes a good one.</span>
</div>
<label class="kv-check">
    <input type="checkbox" class="checkbox" x-model="understood">
    <span>I understand: if I forget the passphrase, this vault copy cannot be recovered, and I keep the main copy in Bitwarden.</span>
</label>
