# Form + Page Builder in this site

The Form Builder and Page Builder are a **reusable Laravel package**, `tman1an/formbuilder`. Its
single canonical source is **https://github.com/TMAn1An/formbuilder**. This repository contains
only the IEEE-specific integration: configuration, role mapping, the Logbook adapter, layout
adapters, navigation and integration tests. Do not copy package code into this app. Change it in
the package repository and update the dependency.

Feature documentation lives with the package:

| Package doc | Contents |
|---|---|
| `vendor/tman1an/formbuilder/docs/FORMS.md` | fields (incl. image upload), design & submit-button sizing, conditional logic, validation, submissions, Excel export, public URLs / preview / View live / copy link |
| `vendor/tman1an/formbuilder/docs/PAGES.md` | page blocks, save payload & validation invariants, form embedding, page images |
| `vendor/tman1an/formbuilder/docs/INTEGRATION.md` | generic host integration reference |

## What IEEE provides

| File | Role |
|---|---|
| `config/formbuilder.php` | all host settings (below) |
| `app/FormBuilder/IeeeAuthorizer.php` | maps IEEE roles onto the package's `Authorizer` contract |
| `app/FormBuilder/LogbookAuditLogger.php` | writes builder actions into the existing append-only Logbook (`audit_logs`) |
| `resources/views/components/formbuilder-host/admin.blade.php` | renders builder admin screens inside the IEEE admin layout |
| `resources/views/components/formbuilder-host/public.blade.php` | renders public forms/pages inside the IEEE site layout (header, IEEE meta-nav and required footer links untouched; escapes the plain-text title/description the package passes) |
| `resources/views/components/layouts/admin.blade.php` | sidebar entries: **Forms** (All Forms, Create Form, Submissions), **Pages** (All Pages, Create Page) |
| `App\Enums\DeletableRecordType::Form` / `::Page` | audit-only record types (never deletion targets) |
| `App\Enums\AuditEventType::Form*` / `::Page*` | Logbook event types (same string values as the package's `AuditEvent`) |
| `tests/Feature/Admin/FormBuilderTest.php`, `FormSubmissionExportTest.php`, `tests/Feature/FormSubmissionTest.php`, `tests/Feature/Admin/PageBuilderIntegrationTest.php` | host integration tests (IEEE roles, Logbook, layouts, embedded forms) |

## Roles

| | super_admin | certificate_manager | inactive account |
|---|---|---|---|
| Build / edit / publish / deactivate / duplicate forms and pages | ✓ | ✓ | – |
| View & export submissions, view uploaded images, upload page images | ✓ | ✓ | – |
| Archive / restore forms and pages | ✓ | – | – |
| Custom code (custom CSS, custom HTML before/after, stored-only JS) | ✓ | – | – |
| Delete forms, pages or submissions | – (no route exists) | – | – |

## URLs (unchanged from when the Form Builder lived in this app)

| URL | Route name |
|---|---|
| `/admin/forms/...` | `admin.forms.*` |
| `/admin/pages/...` | `admin.pages.*` |
| `/forms/{slug}` | `forms.show`, `forms.submit` (POST throttled 30/min/IP) |
| `/pages/{slug}` | `pages.show` |
| `/formbuilder-assets/...` | package CSS/JS and page images (`formbuilder.assets`, `formbuilder.media`) |

Admin routes use this app's `web`, `auth` and `active` middleware.

## Storage

| What | Where | Served by |
|---|---|---|
| Images uploaded with form submissions | `storage/app/private/form-builder/submissions/{form}/{submission}/` (disk `local`, private) | admin-only route, never public |
| Page images | `storage/app/public/form-builder/pages/{yyyy}/{mm}/` (disk `public`) | `/formbuilder-assets/media/{uuid}/…` |

`php artisan storage:link` is **not** required: page images are served through the package route.
Only set `FORMBUILDER_MEDIA_DELIVERY=url` if you want direct `/storage/...` URLs, which then needs
`storage:link`.

## How IEEE consumes the package

### Committed state (what production uses)

```json
"repositories": [{ "type": "vcs", "url": "https://github.com/TMAn1An/formbuilder" }],
"require": { "tman1an/formbuilder": "dev-main" }
```

`composer.lock` pins the exact package commit (`source.reference`). The server's
`composer install --no-dev` fetches exactly that commit from GitHub (public repository, no
credentials, no Packagist), so pushing to the package's `main` never changes this site until
someone runs `composer update tman1an/formbuilder` here and commits the new lock. Once the package
publishes version tags, prefer a version constraint (e.g. `"^0.1"`) over `dev-main`.

### Local development against a working copy

Clone the package next to this project (`../formbuilder`) and point Composer at it:

```bash
composer config repositories.formbuilder-dev '{"type": "path", "url": "../formbuilder", "options": {"symlink": true}}'
composer require tman1an/formbuilder:"*@dev"
```

`vendor/tman1an/formbuilder` becomes a symlink (a junction on Windows), so edits to the package
are live. **Before committing this app**, switch back to the VCS repository and a released tag
(see the package's `docs/DEVELOPMENT.md`). A `composer.lock` that points at a local path can't be
installed on the server.

> Never commit `composer.json` / `composer.lock` while the path repository is configured: the lock
> would point at `../formbuilder`, which does not exist on the server or in a fresh clone.

### Updating the package

```bash
composer update tman1an/formbuilder      # picks up the new tag/commit
php artisan migrate                      # if the release added migrations
DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit   # IEEE integration tests
git add composer.lock && git commit -m "Update tman1an/formbuilder to vX.Y.Z"
```

## Data compatibility (moving the module out of this app)

The four original migrations (`2026_10_09_100000_create_forms_table` …
`2026_10_09_100015_create_form_submission_values_table`) now ship in the package with **identical
filenames and identical schema**. Laravel records migrations by filename, so a database that
already ran them treats them as done. Their tables and every existing form, field, submission and
value are kept. Only the new package migrations run (`form_submission_files`, `pages`,
`page_blocks`, `page_media`). This was verified against the local development database: all rows
were still present before and after `php artisan migrate`.

Model classes moved from `App\Models\Form*` to `TMAn1An\FormBuilder\Models\Form*`. No class names
are stored in the database (the Logbook stores the record type `form`/`page`, mapped in
`DeletableRecordType`), so the move needs no data change.

## Deployment changes

1. `composer install --no-dev --optimize-autoloader` (now also installs `tman1an/formbuilder` from
   GitHub).
2. `php artisan migrate --force` (creates `form_submission_files`, `pages`, `page_blocks`,
   `page_media`).
3. Make sure `storage/app/private` and `storage/app/public` are writable (uploads).
4. No `storage:link`, no asset publishing, no Node. The package serves its own CSS/JS.
