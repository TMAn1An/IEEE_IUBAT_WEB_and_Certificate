<?php

/**
 * The pinned pdfeditor build this app serves under the admin PDF Studio —
 * see docs/PDF_STUDIO_INTEGRATION.md and scripts/sync-pdf-editor-assets.php.
 * Updated only by re-running that script; never edited to point at an
 * un-synced path.
 */
return [
    // Relative to public/ — e.g. "vendor/pdf-editor/b5495579ebe8".
    'asset_path' => env('PDF_STUDIO_ASSET_PATH', 'vendor/pdf-editor/067dfa7c537a'),

    // The pdfeditor commit this asset_path was built from (informational,
    // surfaced in the admin UI / docs so "which build is live" is never a
    // guess). Kept in sync with public/vendor/pdf-editor/manifest.json by
    // the sync script.
    'source_commit' => env('PDF_STUDIO_SOURCE_COMMIT', '067dfa7c537ae8c069a057badd9197e18c3957bd'),

    // Maximum participant Excel rows accepted by a single "reserve" request.
    'max_batch_rows' => (int) env('PDF_STUDIO_MAX_BATCH_ROWS', 500),

    // Maximum bytes for one participant photo upload.
    'max_photo_bytes' => (int) env('PDF_STUDIO_MAX_PHOTO_BYTES', 8 * 1024 * 1024),
];
