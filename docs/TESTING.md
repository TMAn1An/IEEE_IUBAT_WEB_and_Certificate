# Testing

Framework: **Pest** (Laravel 12's default recommendation), running on an in-memory/dedicated test
SQLite or MySQL database — decide and document once Phase 1 sets up the environment (MySQL is
preferred so behavior matches production exactly, e.g. JSON column handling).

## Ground rules

- Every significant feature ships with automated tests in the same phase that implements it — a
  phase isn't done while its tests are red.
- Assertions check database state and rendered content (`assertSee`, model attribute checks), not
  just HTTP status codes. `assertStatus(200)` alone is not a passing test for anything that
  produces or changes data.
- Use factories/seeders for test data — never real participant/personal information (see
  `docs/DEPLOYMENT_CPANEL.md` re: what never gets committed).
- Run the full suite after each phase in `docs/MIGRATION_PLAN.md` before starting the next.

## Required coverage by area

### Public site (`tests/Feature/PublicSite`)
- Every current clean URL (`/`, `/about`, `/committee`, `/membership`, `/events`, `/contact`,
  `/event/becithcon-2026`, `/event/hta-2026`) returns 200 and contains expected distinguishing
  content (not just a 200 — assert on page-specific text/heading).
- Every legacy URL from `docs/MIGRATION_PLAN.md`'s redirect table 301s to its correct canonical
  target.
- Nav highlighting (`$current` equivalent) renders the right "is-current" state per page.
- IEEE brand-lock elements present on every page: meta-nav links (exact set/order), Master Brand
  image with `alt="IEEE"`, required footer policy links.

### Auth (`tests/Feature/Auth`)
- Login succeeds with correct credentials, fails with incorrect ones.
- No registration route exists (assert 404/405 on any guessed registration URL).
- Login throttling kicks in after repeated failures.
- Logout invalidates the session (subsequent admin request redirects to login).

### Authorization
- A Certificate Manager hitting a Super-Admin-only route (user management, settings,
  revoke/reissue) gets 403, not 200 with a hidden button.
- An unauthenticated request to any `/admin/*` route redirects to login, never renders admin
  content.
- IDOR check: a Certificate Manager cannot fetch/download a certificate PDF via a guessed/adjacent
  ID without the proper authorization check passing.

### Templates (`tests/Feature/Templates`)
- Creating a template with a valid PDF succeeds; page width/height/orientation are correctly
  read and stored.
- Uploading a non-PDF (even one renamed to `.pdf`) is rejected.
- Oversized file rejected.
- Adding `template_fields` (each type) persists correctly; `field_key` uniqueness per template is
  enforced (duplicate key on the same template fails validation).
- A `draft` template cannot be selected for certificate generation; an `active` one can.

### Single certificate creation (`tests/Feature/Certificates`)
- The dynamic form renders exactly the fields defined by the chosen template (test with two
  templates that have different field sets and assert both form surfaces differ accordingly — this
  is the concrete test of the "template-driven, not hardcoded" requirement).
- Submitting valid data creates a `certificates` row with the correct `data` JSON, `status=active`,
  a non-null `certificate_number`, and a non-null `codeword`.
- Submitting invalid data (missing required field, invalid dropdown value) is rejected with
  field-level errors, no record created.
- **Unique code generation**: generating many certificates never collides on `codeword` or
  `certificate_number` (test forces/simulates a collision and asserts a retry produces a different
  value rather than a DB error bubbling up).
- The generated PDF file exists on the private disk after generation (structural check — see
  §PDF/QR below for what automated tests can and can't verify).

### Excel (`tests/Feature/Excel`)
- Downloaded template's header row exactly matches the chosen template's `template_fields`
  (label, order) — test with two different templates to confirm headers differ accordingly.
- Import validation, with row-numbered errors, for: missing required header, extra/unexpected
  header, blank rows, invalid dropdown value, invalid date format, duplicate rows within the file,
  100+ row file (performance/memory sanity), special characters (including Bangla text), long
  names (near/over any length limit), unsupported file type rejected outright.
- Nothing is generated until validation passes and the admin confirms.

### Bulk generation (`tests/Feature/Certificates` or a dedicated `BulkGeneration` group)
- A valid batch of N rows produces N `certificates` rows, each with a unique codeword, all linked
  to the same `batch_id`.
- A batch with some invalid rows finishes `partial`, with failed rows recorded and identifiable;
  valid rows still succeed.
- Batch status transitions correctly (`pending` -> `processing` -> `completed`/`partial`/`failed`).
- ZIP download contains exactly the successfully generated PDFs.

### Verification (`tests/Feature/Verification`)
- Valid, active codeword -> 200, shows only `show_on_verification=true` fields for that
  certificate's template, plus certificate number and issue date.
- Unknown codeword -> "Certificate Not Verified" state, no data leakage, no hint about validity of
  format.
- Revoked certificate's codeword -> "no longer valid" state, no further personal data shown.
- Reissued (superseded) original codeword behaves per the decision recorded in
  `docs/CERTIFICATE_SYSTEM.md` §Reissue — test locks in whatever that decision ends up being.
- Two certificates from templates with different `show_on_verification` field sets render
  different information (again, the concrete "one engine, dynamic fields" proof for verification).
- A `verification_logs` row is written on both hit and miss, with no IP/fingerprint data stored.
- Rate limiting triggers after the configured threshold.

### Dynamic-field cross-cutting test (explicitly required by the project brief)

One dedicated test (or small suite) that:
1. Creates Template A with fields `name`, `role`, `institution`.
2. Creates Template B with fields `name`, `paper_id`, `track`.
3. Generates one certificate from each.
4. Asserts both went through the same `CertificateGenerationService`/`CertificatePdfService`
   without any template-specific branch, and that each certificate's verification page shows only
   its own template's fields.

This test is the automated proof of the project's core acceptance criterion and should not be
allowed to regress silently.

## PDF/QR — automated + manual

Automated tests cannot fully verify visual PDF output or that a QR physically scans. Two layers:

**Automated structural checks**:
- Generated PDF file exists, non-zero size, valid PDF (openable by a PDF-parsing library, e.g.
  re-import via FPDI in the test itself as a sanity check).
- Page count and page dimensions match the source template.
- QR payload string is exactly `{APP_URL}/verify/{codeword}` (test the `QrCodeService`'s output
  directly at the unit level, independent of the PDF).

**Manual QA checklist** (run at least once per phase touching PDF/QR, and before any production
deploy):
- [ ] PDF opens cleanly in Adobe Reader and a browser PDF viewer.
- [ ] Background (Canva design) preserved, no visible quality loss vs. the uploaded original.
- [ ] Every field lands in the correct position (spot-check against the template editor's preview).
- [ ] A long name doesn't overflow its box unreadably.
- [ ] QR actually scans with a phone camera and opens the correct `/verify/{codeword}` URL.
- [ ] Scanning opens the correct certificate record (not another one).
- [ ] Special characters (apostrophes, accents) render correctly.
- [ ] Bangla/Unicode names render correctly with the embedded font (not boxes/tofu characters).
- [ ] Landscape and portrait source PDFs both behave correctly.
- [ ] At least one differently-sized page (e.g. A4 vs. Letter) behaves correctly.

## Form Builder

Automated (`tests/Feature/Admin/FormBuilderTest.php`, `tests/Feature/FormSubmissionTest.php`,
`tests/Feature/Admin/FormSubmissionExportTest.php`, `tests/Unit/Forms/*`): creation, persistence of
fields/order/options/widths/design/colors/custom CSS/conditions after reload, order regression,
unique/valid keys, cross-form field ids, optimistic locking (409), HTML sanitization (save + render),
CSS scoping, custom-code restricted to super_admin, conditional visibility (server resolver and
required-only-when-visible), section cascading, public submission + snapshots, inactive/draft/private
forms, unknown keys, invalid choices, manipulated hidden/read-only values, limits/schedule,
rename/option-edit/archive history, key locking, Excel columns + formula guard, duplication without
submissions, lifecycle + Logbook events, authorization and escaping. Tests build forms through the
real HTTP endpoints (`tests/Concerns/BuildsForms.php`).

**Manual QA checklist** (run before shipping builder/renderer changes):
- [ ] Create a form; add 8+ field types; change widths; change colors, border and radius.
- [ ] Add dropdown options, an HTML block (try a `<script>`: it must disappear), custom CSS.
- [ ] Configure a conditional field; confirm the canvas shows "Hidden by condition".
- [ ] Save, reload: everything is still present (fields, order, widths, colors, options, rules, CSS, HTML).
- [ ] Publish; submit the public form (the conditional field appears only when its condition matches).
- [ ] View the submission; export Excel (values readable, `=…` values prefixed with `'`).
- [ ] Rename a field; the old submission still shows the original label plus "Now labelled …".
- [ ] Check the public form at phone width (fields stack to full width).

## Development data

Seeders/factories provide: a Super Admin and a Certificate Manager account (fake credentials only,
never committed real ones — see `docs/DEPLOYMENT_CPANEL.md` for how the first real admin is
created), 2+ sample templates with different field sets, sample `template_fields`, a handful of
sample certificates (active + revoked + reissued), and one sample batch — all fake data, no real
participant information.
