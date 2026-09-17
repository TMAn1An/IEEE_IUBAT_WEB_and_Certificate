<?php
/* Shared <head>. Every page sets these variables before including this file:
   - $pageTitle  (echoed raw — pass already-escaped HTML, e.g. use &mdash;)
   - $pageDesc   (echoed raw — pass already-escaped HTML)
   - $pageUrl    (absolute URL of this page)
   Optional:
   - $pageImage  (absolute URL of the Open Graph image; default from
                  includes/config.php, the event poster) plus matching
                  $pageImageW / $pageImageH ("1200")
   - $pageJsonLd (raw JSON-LD markup, printed before </head>)
   - $voxelQr    (bool — loads voxel-qr.css for the QR widget page)
   Values are raw-echoed intentionally; do not run them through
   htmlspecialchars(). Data helpers (config(), svg(), ...) live in
   includes/components.php.
*/
require_once __DIR__ . '/../includes/components.php';

/* Don't announce the backend language/version in the response headers —
   works regardless of how the host runs PHP (mod_php, FPM, CGI), unlike an
   .htaccess php_flag/Header directive, which depends on a specific handler
   and can 500 the whole site if that module isn't loaded. */
header_remove('X-Powered-By');

$pageTitle = $pageTitle ?? '';
$pageDesc  = $pageDesc ?? '';
$pageUrl   = $pageUrl ?? '';
$pageImage = $pageImage ?? config()['site']['og_image'];
$pageImageW = $pageImageW ?? config()['site']['og_image_w'];
$pageImageH = $pageImageH ?? config()['site']['og_image_h'];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?></title>
<meta name="description" content="<?= $pageDesc ?>">
<meta name="theme-color" content="<?= config()['site']['theme_color'] ?>">
<link rel="icon" href="<?= config()['site']['favicon'] ?>" type="image/png">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<?php if (!empty($voxelQr)): ?>
<link rel="stylesheet" href="/assets/css/voxel-qr.css">
<?php endif; ?>
<link rel="canonical" href="<?= $pageUrl ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= config()['site']['name'] ?>">
<meta property="og:title" content="<?= $pageTitle ?>">
<meta property="og:description" content="<?= $pageDesc ?>">
<meta property="og:image" content="<?= $pageImage ?>">
<meta property="og:image:width" content="<?= $pageImageW ?>">
<meta property="og:image:height" content="<?= $pageImageH ?>">
<meta property="og:url" content="<?= $pageUrl ?>">
<meta name="twitter:card" content="summary_large_image">
<?php if (!empty($pageJsonLd)): ?>
<script type="application/ld+json">
<?= $pageJsonLd ?>

</script>

<?php endif; ?>
</head>
