<?php

namespace App\Support\CompanyProfile;

/**
 * Turns one locale of the profile into a PDF (company-profile/CONTRACT.md).
 *
 * PR 1 ships StubProfileRenderer so the Download button and the tests work. PR 2 replaces the binding in
 * AppServiceProvider with the designed renderer. The array it is handed is exactly what
 * Profile::data($locale, $versionId) returns, with unheld credentials and disabled facts already gone.
 */
interface CompanyProfileRenderer
{
    /** The PDF itself, as bytes. */
    public function pdf(array $data): string;
}
