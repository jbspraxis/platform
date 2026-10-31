<?php

/*
| Passkeys and PINs (docs/SIGN_IN.md). A passkey belongs to the host it was made on: by default the relying
| party is HQ's or the portal's own host (config/app.php 'domains') and the origin is https:// on that host.
| Set these only for a local copy served on another address, for example
|   WEBAUTHN_ORIGIN_HQ=http://localhost:8000  WEBAUTHN_RP_ID_HQ=localhost
| (browsers allow passkeys on http only for localhost).
*/

return [
    'rp_id' => [
        'web' => env('WEBAUTHN_RP_ID_HQ'),
        'client' => env('WEBAUTHN_RP_ID_PORTAL'),
    ],
    'origin' => [
        'web' => env('WEBAUTHN_ORIGIN_HQ'),
        'client' => env('WEBAUTHN_ORIGIN_PORTAL'),
    ],
];
