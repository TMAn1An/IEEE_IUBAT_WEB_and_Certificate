@props([
    'pageTitle',
    'pageDesc',
    'pageUrl',
    'pageImage' => null,
    'pageImageW' => null,
    'pageImageH' => null,
    'pageJsonLd' => null,
    'current' => '',
    'bodyClass' => '',
    'voxelQr' => false,
])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{!! $pageTitle !!}</title>
<meta name="description" content="{!! $pageDesc !!}">
<meta name="theme-color" content="{{ config('site.site.theme_color') }}">
<link rel="icon" href="{{ config('site.site.favicon') }}" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
@if ($voxelQr)
<link rel="stylesheet" href="/assets/css/voxel-qr.css">
@endif
<link rel="canonical" href="{{ $pageUrl }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('site.site.name') }}">
<meta property="og:title" content="{!! $pageTitle !!}">
<meta property="og:description" content="{!! $pageDesc !!}">
<meta property="og:image" content="{{ $pageImage ?? config('site.site.og_image') }}">
<meta property="og:image:width" content="{{ $pageImageW ?? config('site.site.og_image_w') }}">
<meta property="og:image:height" content="{{ $pageImageH ?? config('site.site.og_image_h') }}">
<meta property="og:url" content="{{ $pageUrl }}">
<meta name="twitter:card" content="summary_large_image">
@if ($pageJsonLd)
<script type="application/ld+json">
{!! $pageJsonLd !!}
</script>
@endif
</head>
<body class="{{ $bodyClass }}">
<x-site.header :current="$current" />

{{ $slot }}

<x-site.footer>
    {{ $scripts ?? '' }}
</x-site.footer>
</body>
</html>
