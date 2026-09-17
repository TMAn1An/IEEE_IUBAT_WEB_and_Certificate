# Template Editor

Version 1 scope only — see `docs/PROJECT_REQUIREMENTS.md` §Explicit non-goals for what's
deliberately excluded.

## Workflow

1. Admin uploads a PDF (Canva export). Server validates real content (not extension/MIME header
   alone — see `docs/SECURITY.md`), reads page count (first page only is used), and reads page
   width/height in points via FPDI/TCPDF. These get stored on `certificate_templates`
   (`page_width_pt`, `page_height_pt`, `page_orientation`).
2. Browser renders the PDF's first page to a `<canvas>` using **PDF.js** (vendored static JS
   asset, BSD-licensed, no server rasterization needed and no Imagick/Ghostscript dependency —
   see the PDF-pipeline note in `docs/CERTIFICATE_SYSTEM.md`, which is about the *output* PDF
   writer, not this *preview*, so it's unaffected by that risk).
3. Admin clicks "Add field," picks a type, drags it into position on the canvas, resizes if
   needed, sets label/key/style/required/`show_on_verification` in a side panel.
4. On save, the editor converts every field's canvas pixel position/size into PDF points (see
   §Coordinate conversion) and posts the full field list to the server, which persists
   `template_fields` rows.

## Field types (v1)

| Type | Notes |
|---|---|
| `text` | single-line string |
| `long_text` | multi-line, wraps within its box |
| `number` | validated numeric |
| `date` | validated date, formatted consistently on render |
| `dropdown` | fixed `options` list, configured per field |
| `certificate_number` | system-managed value, not user-entered on the form — just positioned |
| `qr_code` | system-managed, positioned as a square box; the QR itself is generated at certificate-generation time, not at template-save time |

Each field additionally carries: `label`, `field_key` (unique per template, snake_case),
`is_required`, `font_family`, `font_size`, `font_weight` (bold on/off), `text_align`, `text_color`,
`show_on_verification`, `verification_label`.

Deferred to a later version if needed: `image`, `signature` field types.

## Coordinate conversion — the part that must not drift

The editor works in **browser canvas pixels** (whatever size PDF.js renders the preview at, which
may itself be scaled for the admin's screen). The PDF renderer works in **PDF points** (1/72
inch), with the coordinate origin at the page's **bottom-left**, while a browser canvas's origin
is **top-left**. Getting this wrong is the single most likely source of "fields render in the
wrong place" bugs, so the conversion is centralized in one place
(`App\Services\Templates\PdfCoordinateService`) and every write path uses it — never re-derive the
math inline in a controller.

Conversion, given:
- `previewScale` = the ratio between the canvas's rendered pixel size and the PDF's actual point
  size at the render scale PDF.js was asked to use (PDF.js's `viewport.scale` makes this
  explicit — capture and send it back with the save request rather than re-deriving it from
  on-screen measurements, which are one more place to introduce drift).
- `page_width_pt` / `page_height_pt` — stored on the template at upload time.

```
x_pt      = canvas_x / previewScale
width_pt  = canvas_width / previewScale
height_pt = canvas_height / previewScale
y_pt      = page_height_pt - (canvas_y / previewScale) - height_pt   # flip top-left -> bottom-left
```

Store `x_pt`, `y_pt`, `width_pt`, `height_pt` on `template_fields` — points, not pixels, not
percentages. At render time (`CertificatePdfService`), these are used directly against the
imported page (which is always drawn at 1:1 scale in points, so no further conversion is needed
there — the only conversion boundary in the whole system is editor-save time).

If the template PDF is re-uploaded/replaced with a different page size, existing field positions
would no longer be valid against the new dimensions — v1 treats "replace the PDF" as effectively a
new template (or requires re-positioning fields); this is a deliberate simplification, documented
here so it isn't rediscovered as a surprise later.

## Fonts

Server-side embedded fonts only, from an approved list configured in the app (not arbitrary
uploaded fonts in v1). Must include at least one Unicode font capable of rendering Bangla names
correctly (see `docs/CERTIFICATE_SYSTEM.md` and `docs/SECURITY.md`/`docs/TESTING.md` for the
Unicode testing requirement) — planned: Noto Sans Bengali (SIL Open Font License, redistribution
permitted), embedded via TCPDF's TTF font conversion, alongside a standard Latin sans-serif for
English-only certificates. The approved font list is a small, explicit array server-side; the
editor's font picker only offers what's in that list, so there is never a "field references a font
that doesn't exist at render time" failure mode.

## What v1 does NOT need

- No Canva-style free-form design canvas — the background is fixed, only field placement is
  editable.
- No per-field custom font upload.
- No multi-page templates (first page only).
- No real-time collaborative editing.

## Validation on save

- `field_key` unique per template, restricted to snake_case identifiers (used as the `data` JSON
  key and the Excel column key — must round-trip safely through both).
- At least one `qr_code` field recommended (not hard-required at the DB level, but the UI should
  warn if a template has no QR placement, since that breaks the verification flow's primary
  intended path — an admin could still choose to omit it deliberately for a non-QR use case).
- Field boxes must fall within `[0, page_width_pt] x [0, page_height_pt]` — reject/clamp anything
  placed off-page.
