<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile\ProfileVersion;
use App\Support\CompanyProfile\CompanyProfileRenderer;
use App\Support\CompanyProfile\Deck\DeckRenderer;
use App\Support\CompanyProfile\MissingProfile;
use App\Support\CompanyProfile\Profile;
use App\Support\CompanyProfile\Sections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company profile as a file, for sales to send and for the owner to check a draft before approving it:
 * the PDF (docs/COMPANY_PROFILE.md section 8) and the animated deck (docs/company-profile/DECK.md).
 *
 * The routes are named `company-profile.download` and `company-profile.deck` — the names the company-profile
 * PRs agreed on, so each of them can link here without knowing where it is mounted. Both read the published
 * version by default, or a given one for someone who may edit or approve, and both carry the same filename
 * but their own extension, so a client's folder holds one profile in two forms rather than two unrelated
 * things.
 */
class CompanyProfileController extends Controller
{
    public function download(Request $request, CompanyProfileRenderer $renderer): Response
    {
        [$data, $version] = $this->profile($request);

        $pdf = $renderer->pdf($data);

        Profile::audit(
            'COMPANY_PROFILE_DOWNLOADED',
            'Downloaded the company profile '.($data['meta']['version'] ?? '').' in '.strtoupper((string) ($data['meta']['locale'] ?? '')),
            $version,
        );

        return $this->file($pdf, 'application/pdf', $this->filename($data, 'pdf'));
    }

    /** The same profile as the twenty-four slide deck, in the same language and from the same version. */
    public function deck(Request $request, DeckRenderer $renderer): Response
    {
        [$data, $version] = $this->profile($request);

        $pptx = $renderer->pptx($data);

        Profile::audit(
            'COMPANY_PROFILE_DECK_DOWNLOADED',
            'Downloaded the company profile deck '.($data['meta']['version'] ?? '').' in '.strtoupper((string) ($data['meta']['locale'] ?? '')),
            $version,
        );

        return $this->file(
            $pptx,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            $this->filename($data, 'pptx'),
        );
    }

    /**
     * The profile a request asks for: the published version, or a given one for someone who may edit or
     * approve it. Both downloads share it, so a draft cannot leak through one of them and not the other.
     *
     * @return array{0: array<string, mixed>, 1: ProfileVersion|null}
     */
    private function profile(Request $request): array
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

        return [$data, $version];
    }

    private function file(string $bytes, string $type, string $name): Response
    {
        return response($bytes, 200, [
            'Content-Type' => $type,
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function filename(array $data, string $extension): string
    {
        $reference = str_replace(['/', '\\', '"'], '-', (string) ($data['meta']['reference'] ?? 'company-profile'));
        $locale = strtoupper((string) ($data['meta']['locale'] ?? 'en'));

        return 'JBS Praxis company profile '.$reference.' '.$locale.'.'.$extension;
    }
}
