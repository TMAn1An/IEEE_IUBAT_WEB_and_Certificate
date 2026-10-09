# PDF Studio Integration — contract

Supersedes the manual-handoff PDF Editor Bridge (`docs/CERTIFICATE_SYSTEM.md` §PDF Editor Bridge).
That code is reused, not deleted — the reservation/finalize split and its invariants (no
`certificates` row without a stored PDF; codeword minted ahead of rendering) carry over unchanged.
This doc records the direct-integration contract so Phase 2+ have a fixed target.

**Status: implemented and verified live** (full browser run through template design, QR field
placement, participant upload, generation, QR decode + public verification, resume, and
re-download — see `docs/CHANGELOG.md`'s "PDF Studio" entry for the trace and the bugs that trace
found and fixed). Final pdfeditor commit: `067dfa7c537ae8c069a057badd9197e18c3957bd` on branch
`claude/laravel-studio-integration`.

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
