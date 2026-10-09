<?php

use App\FormBuilder\IeeeAuthorizer;
use App\FormBuilder\LogbookAuditLogger;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Form + Page Builder (tman1an/formbuilder) -- IEEE host configuration
|--------------------------------------------------------------------------
| The builder itself lives in the reusable package (vendor/tman1an/formbuilder,
| canonical source: https://github.com/TMAn1An/formbuilder). Everything
| IEEE-specific is wired here: roles, the Logbook, the admin/site layouts and
| URLs. See docs/FORM_BUILDER.md for how IEEE consumes the package.
|
| Route names/URLs are configured so the module keeps the exact URLs and
| route names it had when it lived inside this app:
|   admin  /admin/forms/...  -> admin.forms.*   (+ /admin/pages/... -> admin.pages.*)
|   public /forms/{slug}     -> forms.show / forms.submit
|          /pages/{slug}     -> pages.show
*/

return [
    'user_model' => User::class,
    'users_table' => 'users',

    // super_admin: everything; certificate_manager: build/publish/export but
    // no custom code and no archive/restore. Inactive accounts: nothing.
    'authorizer' => IeeeAuthorizer::class,

    // Builder actions go into the existing append-only Logbook (audit_logs).
    'audit_logger' => LogbookAuditLogger::class,

    'admin' => [
        'enabled' => true,
        'prefix' => 'admin',
        'name' => 'admin.',
        'middleware' => ['web', 'auth', 'active'],
        'layout' => 'formbuilder-host.admin',
    ],

    'public' => [
        'enabled' => true,
        'forms_prefix' => 'forms',
        'pages_prefix' => 'pages',
        'name' => '',
        'middleware' => ['web'],
        'layout' => 'formbuilder-host.public',
        'submit_throttle' => '30,1',
    ],

    'assets' => [
        'prefix' => 'formbuilder-assets',
    ],

    'storage' => [
        // Submission uploads: private (storage/app/private), admin-only route.
        'submissions_disk' => env('FORMBUILDER_SUBMISSIONS_DISK', 'local'),
        'submissions_path' => 'form-builder/submissions',
        // Page images: public content, served through the package media route,
        // so no `php artisan storage:link` is required on cPanel.
        'media_disk' => env('FORMBUILDER_MEDIA_DISK', 'public'),
        'media_path' => 'form-builder/pages',
        'media_delivery' => env('FORMBUILDER_MEDIA_DELIVERY', 'route'),
    ],

    'uploads' => [
        'max_kb' => 10240,
        'default_field_max_kb' => 4096,
    ],
];
