# Security

A working checklist, not a one-time audit — revisit this before Phase 9 sign-off and whenever a
new user-facing surface is added.

## Authentication & authorization — implemented Phase 2

- No public registration route exists anywhere in the app (verified: `routes/admin.php` defines
  only `login`/`login.attempt`/`logout`). Admin accounts are created by an existing Super Admin via
  the admin Users screen (`Admin\UserController`), or the first one via `php artisan app:make-admin`
  (interactive password prompt, never a committed value — see `docs/DEPLOYMENT_CPANEL.md`).
- Passwords hashed via Laravel's default hasher (bcrypt, via the model's `'password' => 'hashed'`
  cast — never rolled by hand, never logged, never returned in a response: `User::$hidden` includes
  `password`, and `UserManagementTest::test_admin_user_passwords_are_never_exposed_in_responses`
  guards this).
- Session-based auth (Laravel's default `web` guard, `database` session driver — no Redis), not
  token auth — this is a server-rendered Blade admin, not an SPA/API client.
- Login throttling: `Admin\AuthController::login()` uses `RateLimiter` keyed on
  `strtolower(email).'|'.ip()`, 5 attempts per 60-second lockout, cleared on success. Deliberately
  not Laravel's `ThrottlesLogins` trait (that's part of the UI scaffolding packages this project
  isn't using) — same underlying `RateLimiter` facade, just called directly.
- Two roles enforced server-side, not by hiding a nav link: `App\Policies\UserPolicy`
  (auto-discovered for the `User` model) gates every user-management action, checked explicitly via
  `$this->authorize(...)` in `Admin\UserController` — a Certificate Manager hitting
  `/admin/users*` directly gets a 403 (covered by
  `UserManagementTest::test_certificate_manager_cannot_access_user_management`). The admin layout's
  sidebar also hides the Users link for non-Super-Admins (`@can('viewAny', User::class)`), but that
  is UX politeness on top of the server-side check, not the actual boundary.
- `App\Http\Middleware\EnsureUserIsActive` (alias `active`, applied to every authenticated admin
  route in `routes/admin.php`) logs a deactivated account out and redirects to login on their very
  next request — deactivation takes effect immediately, not just on the next login attempt.
  `Admin\AuthController::login()` separately blocks a fresh login attempt for an inactive account
  too, so both paths (existing session, new login) are covered.
- The last active Super Admin can't be deactivated or have their role changed away from
  `super_admin` (`Admin\UserController::isLastActiveSuperAdmin()`, checked before both the
  toggle-active and update actions) — prevents the admin panel from ever locking everyone out.
  Deleting a user isn't a feature at all in Version 1 (deactivate only), which sidesteps the
  "accidentally delete the last admin" failure mode entirely.
- Password reset stays out of scope for Version 1 (per the Phase 2 brief) — an inactive/locked-out
  admin is unblocked by another Super Admin via the Users screen instead.

## CSRF / XSS / injection

- CSRF middleware stays on for every state-changing route — no route added to the CSRF exception
  list without a documented, reviewed reason.
- All Eloquent queries use the query builder/Eloquent bindings; no raw SQL string interpolation of
  user input anywhere.
- Mass assignment: every model declares explicit `$fillable` (never `$guarded = []`).
- Blade's default `{{ }}` escaping is used for all dynamic output. `{!! !!}` is reserved for
  content this project itself controls (e.g. the existing site's config-sourced HTML entities like
  `&mdash;`) — **never** for anything derived from `certificates.data`, Excel-imported values, or
  any other user-supplied input.
- The public verification page in particular renders only escaped, template-approved
  (`show_on_verification = true`) fields — see `docs/CERTIFICATE_SYSTEM.md`.

### Form Builder (admin-authored HTML/CSS, public submissions)

Full detail in `docs/FORM_BUILDER.md`. Summary of the controls:

- **HTML** (HTML-block fields, custom HTML before/after) is sanitized by `FormHtmlSanitizer`
  (symfony/html-sanitizer allowlist) on save **and** on every render: no scripts, iframes, forms,
  `on*` handlers, `style` attributes, or non-http(s)/mailto/tel URLs. These are the only `{!! !!}`
  outputs in the form renderer.
- **Custom CSS** (Super Admin only) is scoped to `#ff-form-{id}` at render time by `FormCssScoper`
  (`html`/`body`/`:root` → the form wrapper; `@import`/`@font-face`/other at-rules dropped; nested
  blocks dropped; `<` escaped so `</style>` can't break out).
- **Custom JavaScript** is stored only and never rendered or executed anywhere.
- **Design values** are `#rrggbb` colors, bounded integers or enum keys mapped server-side to CSS.
  Free text never reaches a stylesheet, and values are re-normalized at render time.
- **Submissions** are validated from the stored definition only: unknown keys rejected, option values
  allow-listed, hidden-by-condition fields ignored, hidden/read-only fields server-valued.
  Submitted values are escaped everywhere in the admin, and the Excel export uses
  `ExcelFormulaGuard` + explicit string cells.
- The public layout prints its page title raw (`{!! $pageTitle !!}`), so form titles are passed through
  `e()` first. A test covers a `</title><script>` title.
- `redirect_url` accepts http/https only. The public POST is throttled to 30/min/IP.

## IDOR / access control

- Form builder: a save payload may only reference field ids of the form being saved (cross-form ids
  → 422), and form submissions are bound with `scopeBindings()` (another form's submission id → 404).

- Certificate and template IDs are not treated as secrets, but every admin route that loads a
  specific record (a certificate, a template, a batch, a user) checks ownership/permission via a
  Policy before returning data — no "just check the session is logged in and trust the ID" routes.
- Certificate PDFs and batch ZIPs are served through authorized, signed/controlled download routes
  reading from `storage/app/private/...` — never a public, guessable storage URL. Public verified
  certificate data on `/verify/{codeword}` is the one intentional exception, and it's
  field-allowlisted per `show_on_verification`, not a raw record dump.

## File upload safety

- **PDF template uploads**: validate real file content (not just the `.pdf` extension or the
  client-supplied MIME type, which are both attacker-controlled) — parse it as a PDF server-side
  (FPDI/TCPDF will simply fail on a non-PDF, which doubles as a content check) before accepting it.
  Enforce a maximum file size. Store under a server-generated filename (never the original
  filename) inside `storage/app/private/certificate-templates/{template_id}/`, never inside
  `public/`.
- **Excel imports**: only accept `.xlsx`/`.xls`/`.csv` by real content sniffing, not extension
  trust. Enforce a maximum file size and a maximum row count. Never `eval`/execute anything from
  spreadsheet cell content.
- **Formula injection**: any value that will later be written back into an exported spreadsheet
  and starts with `= + - @` gets a leading `'` (or equivalent library-level escaping) so it can
  never execute as a formula when opened in Excel/Sheets — same protection the archived prototype
  already had (`safe_excel_text()`), carried forward.
- **Path traversal**: server-generated filenames everywhere (UUID/ID-based, never derived directly
  from user input like a person's name or an uploaded filename). No path segment from user input
  is ever concatenated into a filesystem path without going through `Illuminate\Filesystem`'s safe
  path handling.
- All uploaded/generated certificate material lives outside the web root's directly-servable path
  (`storage/app/private`, not `storage/app/public`) and is only reachable through an authorized
  Laravel route.

## Verification endpoint abuse

- `GET /verify/{codeword}` is rate-limited (per IP, generous enough that a person repeatedly
  re-scanning the same physical QR on a shared/mobile network isn't blocked, but tight enough to
  meaningfully slow down codeword enumeration — exact numbers tuned in Phase 6/9 and recorded
  here once set).
- Every lookup, hit or miss, is logged to `verification_logs` with only `codeword_attempted`,
  `result`, and (on a hit) `certificate_id` — no IP storage, no device fingerprinting, per the
  privacy requirement in `docs/PROJECT_REQUIREMENTS.md`.
- A miss reveals nothing about why (no "codeword format looks valid but doesn't exist" hints).

## Secrets & configuration

- `.env` is gitignored; `.env.example` lists every required key with placeholder/safe defaults,
  never real credentials.
- No secret, password, or API key is ever written to a log line (`Log::info`, exception context,
  etc. — review anything that logs a request/model before it ships).
- `APP_DEBUG=false` and `APP_ENV=production` in production; Laravel's default error pages replace
  stack traces. Technical error detail goes to the log (`storage/logs`, not web-accessible), not
  the HTTP response.
- Database, mail, and any third-party credentials only ever come from environment variables, never
  hardcoded in source.

## Session security

- Laravel's default session security (`SESSION_ENCRYPT`, secure cookie flags on HTTPS, same-site
  cookie policy) enabled for production — `SESSION_SECURE_COOKIE=true` once HTTPS is confirmed on
  the target domain (cPanel SSL, per `docs/DEPLOYMENT_CPANEL.md`).
- Session driver: `database` (no Redis dependency, per hosting constraints).

## Dependency hygiene

- Every Composer/npm package is chosen and recorded per the package policy in `CLAUDE.md` and
  `docs/ARCHITECTURE.md` §6 — actively maintained, PHP 8.2/Laravel 12 compatible, no unreviewed
  additions.
- `composer install --no-dev --optimize-autoloader` in production — dev-only tooling (Pest,
  debugbar if ever added, etc.) never ships to production.

## Outstanding items to confirm during implementation

- Login rate limit: **decided in Phase 2** — 5 attempts / 60-second lockout, keyed on email+IP (see
  §Authentication above). `/verify/{codeword}`'s rate limit is still open (Phase 6, doesn't exist
  yet).
- Whether `SESSION_SECURE_COOKIE`/HTTPS is enforced from day one on staging or only once the
  production domain's SSL is confirmed (per `docs/DEPLOYMENT_CPANEL.md`).
