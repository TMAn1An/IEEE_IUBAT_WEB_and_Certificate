<?php

/**
 * Pins a specific commit of the separate pdfeditor repository
 * (https://github.com/TMAn1An/pdfeditor) into this app's public assets, so a
 * fresh checkout of THIS repo can reproduce exactly what's deployed — never
 * an untracked, silently-diverging copy. See docs/PDF_STUDIO_INTEGRATION.md.
 *
 * Run by a developer (or a deploy script), never at request time or inside
 * `composer install` — this app has no Node runtime dependency; the output
 * is plain static files, the same arrangement the public site's own Vite
 * build already uses.
 *
 * Usage:
 *   php scripts/sync-pdf-editor-assets.php /path/to/pdfeditor/dist <commit-sha>
 *
 * Build the source first in the pdfeditor checkout: `npm run build`.
 */

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php scripts/sync-pdf-editor-assets.php <path-to-pdfeditor-dist> <commit-sha>\n");
    exit(1);
}

[, $distPath, $sha] = $argv;

if (! is_dir($distPath)) {
    fwrite(STDERR, "Not a directory: {$distPath}\n");
    exit(1);
}
if (! preg_match('/^[0-9a-f]{7,40}$/', $sha)) {
    fwrite(STDERR, "Second argument must look like a git commit SHA (got: {$sha})\n");
    exit(1);
}
if (! is_file($distPath.'/studio.html')) {
    fwrite(STDERR, "{$distPath}/studio.html not found — did you run `npm run build` with the studio.html entry present?\n");
    exit(1);
}

$shortSha = substr($sha, 0, 12);
$target = __DIR__."/../public/vendor/pdf-editor/{$shortSha}";

if (is_dir($target)) {
    fwrite(STDOUT, "{$target} already exists — removing before re-sync.\n");
    exec('rm -rf '.escapeshellarg($target));
}

mkdir($target, 0755, true);
exec('cp -R '.escapeshellarg(rtrim($distPath, '/').'/.').' '.escapeshellarg($target), $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "Copy failed.\n");
    exit(1);
}

$manifestPath = __DIR__.'/../public/vendor/pdf-editor/manifest.json';
file_put_contents($manifestPath, json_encode([
    'commit' => $sha,
    'short_commit' => $shortSha,
    'synced_at' => date('c'),
    'path' => "vendor/pdf-editor/{$shortSha}",
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

fwrite(STDOUT, "Synced pdfeditor dist ({$sha}) into public/vendor/pdf-editor/{$shortSha}\n");
fwrite(STDOUT, "Update config/pdf-studio.php's 'asset_path' if the short SHA changed, then commit both the new\n");
fwrite(STDOUT, "public/vendor/pdf-editor/{$shortSha}/ directory and manifest.json.\n");
