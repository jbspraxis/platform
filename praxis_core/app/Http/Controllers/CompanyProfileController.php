<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\MissingProfile;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Sections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company profile as a PDF (docs/COMPANY_PROFILE.md section 8), for sales to send and for the owner to
 * check a draft before approving it.
 *
 * The route is named `company-profile.download` — the name the three company-profile PRs agreed on, so PR 2
 * and PR 3 can link to it without knowing where it is mounted. PR 2 binds the designed renderer over the
 * stub; nothing here changes.
 */
class CompanyProfileController extends Controller
{
    public function download(Request $request, CompanyProfileRenderer $renderer): Response
    {
        $locale = (string) $request->query('locale', 'en');
        abort_unless(Sections::isLocale($locale), 404);

        $version = null;
        if ($number = $request->query('version')) {
            $version = ProfileVersion::where('number', (int) $number)->firstOrFail();

            // A version that is not the published one is a draft: only someone who may edit or approve sees it.
            abort_unless(
                $version->status === ProfileVersion::PUBLISHED
                    || Auth::user()?->can('company_profile.edit')
                    || Auth::user()?->can('company_profile.approve'),
                403,
            );
        }

        try {
            $data = $version
                ? Profile::dataFor($version, $locale)
                : Profile::data($locale);
        } catch (MissingProfile $e) {
            abort(404, $e->getMessage());
        }

        $pdf = $renderer->pdf($data);

        Profile::audit(
            'COMPANY_PROFILE_DOWNLOADED',
            'Downloaded the company profile '.($data['meta']['version'] ?? '').' in '.strtoupper($locale),
            $version,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($data, $locale).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function filename(array $data, string $locale): string
    {
        $reference = str_replace(['/', '\\', '"'], '-', (string) ($data['meta']['reference'] ?? 'company-profile'));

        return 'JBS Praxis company profile '.$reference.' '.strtoupper($locale).'.pdf';
    }
}
