<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Primary QR category
    |--------------------------------------------------------------------------
    | The old tool (IEEEQRCODEGENERATOR-main) has exactly one form/category.
    | This Laravel version's main tool page (/admin/qr-tool/generate) stays
    | just as single-purpose -- it doesn't ask the admin to pick a category
    | first, it's always bound to this one. See
    | docs/CERTIFICATE_SYSTEM.md §Simple QR tool: old-tool-parity rebuild.
    | Full multi-category management still exists at /admin/qr-tool/categories
    | for anyone who needs it later; this setting only controls which one
    | category the old-tool-parity page itself targets.
    */
    'primary_category_slug' => env('QR_TOOL_PRIMARY_CATEGORY', 'becithcon-2026'),

];
