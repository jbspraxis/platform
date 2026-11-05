<?php

namespace App\Exceptions\Hq;

/**
 * A tracked share link that has ended (docs/COMPANY_PROFILE.md section 14): revoked, past its expiry, or a
 * token nobody was given. HQ answers all three alike, and the message carries only which of the three it was,
 * so the page can say "this link has ended" without telling a stranger anything about the link.
 */
class HqGone extends HqApiException
{
    public function state(): string
    {
        return in_array($this->getMessage(), ['revoked', 'expired', 'not_found'], true)
            ? $this->getMessage()
            : 'not_found';
    }
}
