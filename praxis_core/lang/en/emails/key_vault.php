<?php

// KeyVaultMail (docs/KEY_VAULT.md section 3): the owner's key vault code and the notice of a reveal. Internal, English only.
return [
    'code_subject' => 'Your HQ key vault code (valid for :minutes minutes)',
    'code_headline' => 'Your key vault code',
    'code_preheader' => 'It opens “:item” in HQ’s key vault, once.',
    'code_intro' => 'Use this code to open “:item” in HQ’s key vault. It works once, in the browser that asked for it; you will then type your passphrase there.',
    'code_not_you' => 'If you did not ask for it, someone has signed in to HQ as you: change your HQ password now (My profile). Without this code and your passphrase they still cannot read the secret.',

    'revealed_subject' => '“:item” was opened in HQ’s key vault',
    'revealed_headline' => 'A key vault item was opened',
    'revealed_preheader' => 'Your emailed code was used just now.',
    'revealed_intro' => 'Your emailed code was used to open this item in HQ’s key vault. The secret is unlocked only in that browser, with your passphrase.',
    'revealed_not_you' => 'If this was not you: change your HQ password now, then change the secret itself (for the backup key, make a new key) and replace it in Bitwarden and the vault. Overview › Audit log lists each reveal, with its time and IP address.',
    'fact_item' => 'Item',
    'fact_when' => 'When',
    'fact_device' => 'Device',
    'fact_ip' => 'IP address',
    'button' => 'Open the key vault',
];
