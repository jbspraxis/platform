{{--
    The company profile, A4 landscape, one template for English and French.

    One <div class="sheet"> per page, each a fixed height, so a section never splits across a page break
    and the French text, which runs 15 to 20 per cent longer, changes how many pages the open sets take
    and nothing else. App\Support\CompanyProfile\Layout decides what is on each page.

    The reference, the version and the page number are drawn on every page by the renderer's page script,
    not here: that is the only way to be sure they are on all of them.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $locale === 'fr' ? 'Profil d’entreprise' : 'Company profile' }} — JBS Praxis</title>
    @include('pdf.company-profile.styles')
</head>
<body>
@foreach ($pages as $index => $page)
    <div class="sheet surface-{{ $page['surface'] }}{{ $loop->last ? ' last' : '' }}">
        @include('pdf.company-profile.' . $page['view'], [
            'page' => $page['data'],
            'surface' => $page['surface'],
        ])
    </div>
@endforeach
</body>
</html>
