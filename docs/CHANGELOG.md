# Changelog

Entries are added after each phase (or any significant change), newest first. This is a
project-behavior changelog, not a raw git log — explain what changed and why in plain English.

## Phase 0 — Repository audit and documentation (2026-09-17)

- Inspected the entire existing plain-PHP public website (`index.php`, `about.php`,
  `committee.php`, `contact.php`, `events.php`, `membership.php`, `becithcon-2026.php`,
  `hta-2026.php`, `includes/`, `partials/`, `assets/`, `.htaccess`, `robots.txt`, `sitemap.xml`,
  `site.webmanifest`). Documented full current + legacy URL map in `docs/MIGRATION_PLAN.md`.
- Located the old QR-generator prototype, which was not in the project workspace as expected — it
  was found at `~/Downloads/Compressed/IEEEQRCODEGENERATOR-main.zip`. Extracted a read-only
  reference copy into `reference/qr-generator/IEEEQRCODEGENERATOR-main/` inside this repository so
  it's inspectable and versioned going forward, without touching the original download. Fully
  read and documented its architecture (Flask + openpyxl, per-conference-per-role Excel files,
  `secrets.choice()`-based 16-char codeword, exact-match duplicate check, QR payload containing
  raw certificate data) in `docs/CERTIFICATE_SYSTEM.md` and the Phase 0 report.
- Identified a real architectural risk before committing to the PDF pipeline: FPDI's open-source
  edition only imports PDFs up to version 1.4, while Canva exports are typically newer; no
  Imagick/Ghostscript is available in the hosting's PHP module list as a rasterization fallback.
  Flagged as an open decision in `docs/CERTIFICATE_SYSTEM.md` — needs testing against a real
  Canva-exported certificate PDF before Phase 4.
- Noted a local-vs-production PHP version mismatch (local CLI 8.5.8 vs. production 8.2.31) as a
  development-environment risk; resolution approach to be confirmed with the user.
- Created the full documentation set: `CLAUDE.md`, `docs/ARCHITECTURE.md`,
  `docs/PROJECT_REQUIREMENTS.md`, `docs/DATABASE_DESIGN.md`, `docs/CERTIFICATE_SYSTEM.md`,
  `docs/TEMPLATE_EDITOR.md`, `docs/SECURITY.md`, `docs/TESTING.md`, `docs/DEPLOYMENT_CPANEL.md`,
  `docs/MIGRATION_PLAN.md`, this file.
- No Laravel code written yet. No production/live systems touched. The original site files at the
  repository root and the original QR-generator download outside the repository are untouched.

<!-- Phase 1 entry goes here once the Laravel foundation + public site migration lands. -->
