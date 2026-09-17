# Security

A working checklist, not a one-time audit — revisit this before Phase 9 sign-off and whenever a
new user-facing surface is added.

## Authentication & authorization

- No public registration route exists anywhere in the app. Admin accounts are created by an
  existing Super Admin via the admin Users screen, or seeded once via `php artisan app:make-admin`
  for the very first account (documented in `docs/DEPLOYMENT_CPANEL.md`).
- Passwords hashed via Laravel's default hasher (bcrypt/argon2, never rolled by hand).
- Session-based auth (Laravel's default guard), not token auth — this is a server-rendered Blade
  admin, not an SPA/API client.
- Login throttling via Laravel's built-in rate limiter (e.g. lock out after N failed attempts per
  email+IP combination for a cooldown window) — configured, not left at framework defaults without
  checking they're actually adequate.
- Two roles enforced server-side: `EnsureAdminRole` middleware plus Policies
  (`CertificateTemplatePolicy`, `CertificatePolicy`, `UserPolicy`) gate every admin action.
  Super-Admin-only actions (user management, settings, revoke/reissue) check the policy, not just
  route grouping — a Certificate Manager hitting a Super-Admin route directly must get a 403, not
  a hidden button that happens not to be rendered.
- Optional password reset may be added later if it doesn't add meaningful complexity; not required
  for launch.

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

## IDOR / access control

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

- Exact rate-limit numbers for login and `/verify/{codeword}` (Phase 9).
- Whether `SESSION_SECURE_COOKIE`/HTTPS is enforced from day one on staging or only once the
  production domain's SSL is confirmed (per `docs/DEPLOYMENT_CPANEL.md`).
