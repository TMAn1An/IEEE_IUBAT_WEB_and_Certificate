# PDF Studio Integration — contract

Superseded the manual-handoff PDF Editor Bridge (`docs/CERTIFICATE_SYSTEM.md` §PDF Editor
Bridge). The *concept* — the reservation/finalize split and its invariants (no `certificates` row
without a stored PDF; codeword minted ahead of rendering) — carried over unchanged into
`DirectBatchService`/`ReservationFinalizeService` below, but the bridge's own controller,
services and views (the manual spreadsheet/QR-zip/PDF-zip download-reupload flow) were later
removed outright in the admin workflow cleanup (see `docs/CHANGELOG.md`) — PDF Studio is now the
only way to generate certificates, not an alternative to a kept manual fallback. This doc records
the direct-integration contract, the admin-facing flow built on top of it ("PDF Certificates entry
flow" below), and what shipped in each round of work.

**Status: implemented and verified live** (full browser run through template design, QR field
placement, participant upload, generation, QR decode + public verification, resume, and
re-download; batch template immutability closed and regression-tested; standalone pdfeditor
export and standalone QR-tool generation+verification independently re-confirmed; the admin
workflow cleanup unified the entry point and removed the legacy designer/manual-bridge UI — see
`docs/CHANGELOG.md`'s "PDF Studio" and "Admin workflow cleanup" entries for the trace and the bugs
found and fixed). Final pdfeditor commit: see `docs/CHANGELOG.md`'s latest "Admin workflow
cleanup" entry for the exact SHA/branch — kept out of this contract doc so it never drifts out of
sync with what's actually deployed.

## Repos and checkpoint (verified, see audit in conversation history)

- IEEE repo `claude/happy-pascal-y6ek93` @ `59e3317` (clean) — continuing on this branch.
- pdfeditor repo, new branch `claude/laravel-studio-integration` off `claude/pdfeditor-complete` @
  `b041c7a` (clean) — a new branch because this is substantial new code, kept separate from the
  audited reference branch.
- `tman1an/formbuilder` @ `80f4b10` — untouched, out of scope.

## Why direct integration is possible with zero engine rewrite (from the audit)

- The editor's production CSP (`connect-src 'self' blob: data:`) permits same-origin `fetch()` —
  confirmed by reading `scripts/vite-plugin-pdfjs-assets.ts`. Serving the editor from the Laravel
  origin removes the "no network code" limitation without touching the CSP.
- `src/app/actions.ts`'s `loadSample()` is the exact pattern needed: `unpackBundle()` a project,
  `preparePdf()`, `ws.loadProject()`, `ws.setSheet()`, `loadImageFile()` + `ws.addImages()`. Feeding
  server data through this same path, instead of static `samples/` files, requires no change to
  `src/lib/storage`, `src/lib/render`, `src/lib/images`, or `src/lib/export`.
- `src/features/export/ExportDialog.tsx`'s `start()` is the exact per-row render loop: `planRow()` +
  `generateFilledPdf()` driven by `runBatch()`. The adapter calls the same three functions with a
  `generate` callback that additionally `fetch()`s the resulting bytes to Laravel — `runBatch` already
  takes a callback, so this needs no modification to `src/lib/export/batch.ts` either.
- `FieldSource.column` (project fieldMapping) is looked up as `row.values[source.column]` — a plain
  string key. Laravel reads the saved project's `fieldMapping` to know exactly which column names the
  template expects, and emits participant data (JSON, not CSV) keyed by those same names — removing
  any CSV quoting/type-coercion risk for values like a zero-padded student ID.

## Template/project format

- `certificate_templates` gains (migration, Phase 3): `editor_project_path` (stored `.pdftemplate`
  bundle — PDF + fonts + layout, exactly the existing bundle format, unchanged), `editor_schema`
  (JSON: derived from the bundle's `fieldMapping` + `fields`, cached so Excel-suggestion requests
  don't need to re-open the bundle every time), `editor_schema_version` (an integer bumped each save,
  used for the batch snapshot — see below).
- A **fixed image asset** (e.g. a logo placed on every certificate via `fixed-image`) is referenced
  by `imageId` inside the project JSON but its bytes live in the bundle's own ZIP only if the admin
  attached it there — the editor's bundle format has no separate fixed-image slot outside the project
  tree the way fonts do. Laravel does not need to manage these separately: they travel inside
  `editor_project_path` as part of the bundle the admin saves, unchanged.
- **Participant images** (photo fields) and **QR images** are per-batch, never part of the template:
  stored under `storage/app/private/certificate-batches/{batch}/images/` and
  `.../qr/` respectively, referenced only from `certificate_batch_reservations` rows.

## PDF Certificates entry flow

The admin-facing product built on top of everything else in this document (added in the admin
workflow cleanup — see `docs/CHANGELOG.md`). One connected flow, no legacy detour:

1. **`/admin/pdf-certificates`** (nav: "PDF Certificates" -> "Templates & Batches") —
   `PdfCertificatesController::index()`. A fresh database shows an empty state with a "New
   template" action; otherwise a "Saved templates" list (archived templates hidden, not deleted).
2. **New template** (`.../create` -> `store()`) — name + the demo certificate PDF, **in one
   form**. `StoreCertificateTemplateRequest` validates both; `TemplateBackgroundService` (reused
   from the old, removed designer flow — same storage mechanics, new caller) stores the PDF.
   `store()` redirects straight to `pdf-studio.show`, never to a template-manager page.
3. **The editor** (`PdfStudioController::show()`) — for a template with no saved project yet, the
   just-uploaded PDF is preloaded automatically as a fresh project: `show()` computes
   `initialPdfUrl` (pointing at the new `GET .../templates/{template}/source-pdf` endpoint) only
   while `editor_project_path` is still null, and the pdfeditor adapter's Design-mode auto-load
   effect (`StudioApp.tsx`) fetches it, calls the same `preparePdf()`/`newProjectFor()` pair the
   editor's own "Choose PDF…" button uses, and loads it — no re-selecting the file inside the
   editor's Welcome screen. The admin places text/image fields and the QR field, then "Save to
   server" (unchanged — see "Template/project format" above). Once a project exists,
   `initialPdfUrl` is never advertised again; the normal `fetchProject()` load takes over.
4. **Upload participants** (`.../studio/{template}/prepare`, linked from the editor's toolbar and
   from the batch-history page below) — the existing Excel-instructions/sample-download/
   preview/confirm page (`admin.pdf-studio.prepare`, unchanged by this cleanup — it already showed
   expected columns, required/optional, a downloadable sample workbook, and the "codewords/QR are
   automatic" note before this cleanup started). Confirming lands on `pdf-studio.show-batch`
   (Generate mode).
5. **Batch history** (`.../pdf-certificates/{template}/batches` ->
   `PdfCertificatesController::batches()`) — the piece that was missing before this cleanup: a
   list of a template's batches with "Resume / Review" (back into Generate mode) and "Download
   ZIP" links, so an admin can get back to an in-progress or completed batch without having
   bookmarked its URL or remembered its id. Built entirely on existing endpoints (no new
   generation/download logic) — `CertificateBatch::$batches()` relation,
   `pdf-studio.show-batch`, and the existing `batches.download` ZIP endpoint.

What this replaced: creating a template used to land on a manual name/slug/description page,
which separately linked to a manual background-upload + drag-and-drop field designer and a
manual field-CRUD screen, required a "draft -> active" activation step unrelated to PDF Studio's
own readiness check, and the only way to reach PDF Studio at all was a button back on the
template *list* page — never the template's own edit page. All of that (TemplateController,
TemplateDesignerController, TemplateFieldController, the draft/active activation gate) was
removed; see `docs/CHANGELOG.md`'s "Admin workflow cleanup" entry for the full before/after.

## Batch template immutability

A batch's remaining/resumed rows must always render from the project bundle
**pinned at confirm time**, never the template's current one — otherwise an
admin editing and re-saving a template mid-batch would silently change the
design of rows not yet finalized. This was found broken (code review, then
confirmed by a regression test reproducing the exact scenario) and fixed:

- `TemplateProjectService::save()` used to delete the template's previous
  `.pdftemplate` file on every re-save. It no longer does — every saved
  revision's file is kept on disk, since a batch may still be pinned to an
  older one.
- `certificate_batches.editor_project_path` (migration
  `2026_10_11_000002_add_editor_project_path_to_certificate_batches`) stores
  the exact storage path of the bundle the batch was confirmed against,
  captured from `$template->editor_project_path` inside
  `DirectBatchService::confirm()`. `editor_schema_version` alone (an
  integer) was not sufficient — it records *which* version a batch used but
  not a reference to that version's actual bytes once the template moves on.
- `GET /admin/api/pdf-studio/batches/{batch}/project` serves the batch's own
  pinned bundle (falling back to the template's current one only for
  legacy batches with a null `editor_project_path`, predating this column).
  The pdfeditor adapter's Generate mode (`StudioApp.tsx`'s `loadForBatch`)
  fetches this endpoint instead of the template-scoped one, so resuming an
  in-flight batch after a template edit keeps rendering the original design.
- Verified by `tests/Feature/Admin/PdfStudioTest.php`'s
  `test_batch_template_immutability_old_batch_keeps_old_design_new_batch_gets_new_design`:
  starts a 3-row batch, finalizes one row, edits+saves the template with a
  different design, resumes the old batch (asserts its served bundle is
  still the original one, by reading back `project.json`'s `name`), and
  confirms a new batch created after the edit gets the edited design.
- `template_snapshot`/`layout_snapshot`-style JSON columns remain
  unnecessary here *because* this path-pinning is an equivalent (and
  simpler) immutable record — the bundle file itself never changes once
  written, and the batch holds its own pointer to the specific file.

## Concurrency — what's a DB guarantee vs. what's not

- **Codeword uniqueness across `certificates`, `qr_certificates`, and
  `certificate_batch_reservations`**: each of these three tables has its
  own DB-level `UNIQUE` column constraint on `codeword`, so no two rows
  *within the same table* can ever collide, full stop, enforced by MySQL
  regardless of application races. There is, however, no single DB
  constraint spanning all three tables — `uniqueCodeword()` (in both
  `DirectBatchService` and the older bridge) only *checks* all three tables
  before inserting, which is itself a check-then-insert race in the
  cross-table sense. In practice this is not a realistic exposure: codewords
  are `random_bytes(32)` (256 bits of CSPRNG entropy, 64 hex chars), so the
  probability of two concurrent requests independently generating the same
  value is cryptographically negligible — far below the probability of
  e.g. a hash-function collision anyone would otherwise rely on daily. This
  is a documented, accepted design tradeoff (consistent with CLAUDE.md:
  "Uniqueness is a DB unique constraint; on collision, regenerate and retry
  inside the transaction" — regeneration already happens up to 5 times if a
  *pre-insert* check finds a match; an extremely unlikely insert-time
  collision instead fails that one row inside its own `DB::transaction()`,
  caught and recorded as a per-row failure, never a fatal batch error).
- **Concurrent/repeated batch confirmation (same idempotency key)**:
  `certificate_batches.idempotency_key` has a DB-level `UNIQUE` constraint.
  `DirectBatchService::confirm()` originally only did an application-level
  "check then insert" (`where('idempotency_key', ...)->first()` then
  `create()`), which is a genuine race: two concurrent requests with the
  same key can both see null and both attempt to create, and the loser's
  insert would violate the unique constraint. Fixed: the create is now
  wrapped in a `try`/`catch` for
  `Illuminate\Database\UniqueConstraintViolationException`, and the loser
  returns the winner's row instead of a 500. The DB constraint is the real
  guarantee; the catch only makes losing the race a clean no-op instead of
  an error. Verified deterministically (not via real concurrency — see
  below) by
  `test_confirm_survives_a_lost_race_on_the_same_idempotency_key`, which
  inserts the "winning" row directly between the check and the create to
  force the exact race window.
- **Concurrent finalization of the same reservation**:
  `ReservationFinalizeService::finalize()` wraps the whole operation in
  `DB::transaction()` and takes `CertificateBatchReservation::query()->where('id', ...)->lockForUpdate()->first()`
  — a `SELECT ... FOR UPDATE` row lock. Under MySQL/InnoDB this is a real
  guarantee: a second concurrent transaction finalizing the same
  reservation blocks until the first commits, then sees the
  now-`Finalized` status and returns `already_finalized`/`conflict` instead
  of double-creating a `Certificate` row. This is the correct pattern and
  was verified by *code review*, not by an executed concurrency test — see
  the limitation below.
- **What is and isn't actually tested here**: this sandbox has no MySQL or
  Docker available (`docker ps` fails — no daemon), so all of the
  automated tests in this repo (including the ones added for this review)
  run against SQLite, and SQLite does not provide MySQL/InnoDB's row-level
  locking — `lockForUpdate()` under SQLite only ever contends at the
  database-file level, never per-row. **SQLite passing these tests proves
  the application-level logic (idempotency catch, lock-then-check-status
  flow) is wired up correctly; it does NOT prove MySQL's row lock actually
  serializes two truly concurrent requests** — that can only be
  demonstrated against a real MySQL server with two genuinely concurrent
  connections. See `docs/CERTIFICATE_SYSTEM.md`'s "Verifying concurrency
  under MySQL/Sail" for the exact commands to run this for real on the
  existing Windows Sail environment.

## Row identity and batch lifecycle

- A row's durable identity is its `certificate_batch_reservations.id` (already the case in the
  existing bridge) — **never a filename**. The finalize endpoint is now
  `POST /admin/api/pdf-studio/reservations/{reservation}/finalize`, authorized against the
  reservation's batch, not a filename match.
- `certificate_batches` gains `idempotency_key` (nullable, unique) so a duplicate "Generate" click
  (same key) returns the existing batch instead of reserving twice.
- Finalize is idempotent per reservation: re-posting the same bytes (or any bytes) to an already
  `Finalized` reservation returns the existing stored result (200, unchanged) rather than creating a
  second `certificates` row or overwriting the stored PDF — a genuinely *different* PDF for an
  already-finalized row is rejected (409) rather than silently replacing history, consistent with
  CLAUDE.md's "certificates are never destructively overwritten."
- `certificate_batches.editor_schema_version` is copied onto the batch at reserve time (alongside the
  existing per-certificate snapshot columns) so a later template edit can never change what an
  in-flight or completed batch rendered.

## Endpoint contract (all under `admin` middleware group: `auth`+`active`, CSRF, Certificate policy)

| Method | Path | Purpose |
|---|---|---|
| GET | `/admin/certificates/studio/{template}` | Mounts the embedded editor for this template (Blade shell + config script tag) |
| GET | `/admin/api/pdf-studio/templates/{template}/project` | Returns the saved `.pdftemplate` bundle bytes (or 404 if none saved yet) |
| PUT | `/admin/api/pdf-studio/templates/{template}/project` | Saves a new bundle + derived schema (multipart: `project` file) |
| GET | `/admin/api/pdf-studio/templates/{template}/schema` | Derived field schema (keys/types/required) for Excel suggestions |
| GET | `/admin/api/pdf-studio/templates/{template}/sample.xlsx` | Downloadable example workbook from the schema |
| POST | `/admin/api/pdf-studio/templates/{template}/batches` | Upload participant Excel (+ optional photo files) → validate/preview |
| POST | `/admin/api/pdf-studio/templates/{template}/batches/confirm` | Confirm a validated preview → idempotent batch+reservations create |
| GET | `/admin/api/pdf-studio/batches/{batch}/project` | Returns the bundle PINNED to this batch at confirm time (immutability — see below), not the template's current one |
| GET | `/admin/api/pdf-studio/batches/{batch}/manifest` | Rows + QR asset URLs + project bundle URL, for the adapter to consume |
| POST | `/admin/api/pdf-studio/reservations/{reservation}/finalize` | One PDF per reservation, idempotent, identity = reservation id |
| GET | `/admin/api/pdf-studio/batches/{batch}/status` | Progress counts |
| GET | `/admin/api/pdf-studio/batches/{batch}/download.zip` | Stored PDFs, stable numbered filenames + original order |

## Build/deployment arrangement

- pdfeditor remains its own repo/build (`npm run build` → `dist/`). A new Laravel-side script
  (`scripts/sync-pdf-editor-assets.php`, run manually by a developer, never at request time or in
  CI-less production) copies a specific commit's `dist/` into
  `public/vendor/pdf-editor/{short-sha}/` and writes `public/vendor/pdf-editor/manifest.json`
  recording the exact source commit SHA — reproducible from a fresh checkout, no untracked/silently
  diverging copy. Laravel references the pinned path from `config/pdf-studio.php`.
- No Node process runs in production — identical in spirit to the existing Vite-build-then-ship
  pattern already used for the public site's own assets.
