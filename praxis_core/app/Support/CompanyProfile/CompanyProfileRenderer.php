<?php

namespace App\Support\CompanyProfile;

/**
 * One way out of the company profile (docs/company-profile/DESIGN.md). The HQ engine holds the content and the
 * versions and knows nothing about how a version is drawn; a renderer takes the published shape the
 * contract fixes (App\Support\CompanyProfile\Profile::data()) and returns the bytes of one file.
 *
 * The PDF renderer is App\Support\CompanyProfile\PdfRenderer. A deck or a share page can implement this
 * interface later without the engine changing.
 */
interface CompanyProfileRenderer
{
    /**
     * The profile as a PDF, from the published shape in the contract.
     *
     * Two rules hold here and not only in the editor: a credential whose `held` is not true, and a fact
     * whose `enabled` is not true, never reach the file.
     *
     * @param  array<string, mixed>  $data
     */
    public function pdf(array $data): string;
}
