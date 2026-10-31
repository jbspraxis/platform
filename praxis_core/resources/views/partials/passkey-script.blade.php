{{-- window.praxisPasskey: the browser side of passkeys on HQ and the Client Portal (docs/SIGN_IN.md section 3).
     It turns the server's options (base64url strings) into what navigator.credentials needs, and the answer back
     into plain strings for Livewire. In the <head> so it is there before any component asks for it. --}}
<script>
window.praxisPasskey = window.praxisPasskey || (function () {
    var enc = function (buf) {
        var bytes = new Uint8Array(buf), s = '';
        for (var i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };
    var dec = function (text) {
        var b = atob(String(text).replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((String(text).length + 3) % 4));
        var out = new Uint8Array(b.length);
        for (var i = 0; i < b.length; i++) out[i] = b.charCodeAt(i);
        return out.buffer;
    };
    var withIds = function (list) {
        return (list || []).map(function (c) { return Object.assign({}, c, { id: dec(c.id) }); });
    };
    var supported = function () {
        return !!(window.isSecureContext && window.PublicKeyCredential && navigator.credentials && navigator.credentials.create);
    };
    return {
        supported: supported,
        // A fingerprint, face or screen lock built into this device
        platform: function () {
            if (!supported() || !PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable) return Promise.resolve(false);
            return PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function () { return false; });
        },
        create: async function (o) {
            var c = await navigator.credentials.create({ publicKey: Object.assign({}, o, {
                challenge: dec(o.challenge),
                user: Object.assign({}, o.user, { id: dec(o.user.id) }),
                excludeCredentials: withIds(o.excludeCredentials),
            }) });
            return { id: c.id, rawId: enc(c.rawId), type: c.type, response: {
                clientDataJSON: enc(c.response.clientDataJSON),
                attestationObject: enc(c.response.attestationObject),
                transports: c.response.getTransports ? c.response.getTransports() : [],
            } };
        },
        get: async function (o) {
            var c = await navigator.credentials.get({ publicKey: Object.assign({}, o, {
                challenge: dec(o.challenge),
                allowCredentials: withIds(o.allowCredentials),
            }) });
            return { id: c.id, rawId: enc(c.rawId), type: c.type, response: {
                clientDataJSON: enc(c.response.clientDataJSON),
                authenticatorData: enc(c.response.authenticatorData),
                signature: enc(c.response.signature),
                userHandle: c.response.userHandle ? enc(c.response.userHandle) : null,
            } };
        },
        // Why the browser gave no answer, as a sign_in.passkey.* key
        problem: function (err) {
            var name = err && err.name;
            if (name === 'InvalidStateError') return 'exists';
            if (name === 'NotSupportedError' || name === 'SecurityError' || !supported()) return 'unsupported';
            return 'cancelled';
        },
    };
})();
</script>
