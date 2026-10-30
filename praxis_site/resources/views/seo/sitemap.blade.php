{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@isset($url['en'])
        <xhtml:link rel="alternate" hreflang="en" href="{{ $url['en'] }}"/>
        <xhtml:link rel="alternate" hreflang="fr" href="{{ $url['fr'] }}"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['en'] }}"/>
@endisset
    </url>
@endforeach
</urlset>
