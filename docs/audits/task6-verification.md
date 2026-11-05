# Task6 round — deployment & manual verification notes

## PHP extensions the export system needs (T6-71.8)

The shared export service (`app/Services/ExportService.php`) uses:

- **openspout/openspout** (xlsx + csv) — requires the `zip` extension
  (and `xml`/`dom`, always present) plus `mbstring` (the Masarsoft polyfill
  covers hosts where it is missing).
- **mpdf/mpdf** (pdf) — requires `mbstring` and either `gd` or the
  `fontconfig`-based fallback for font subsetting. Arabic shaping uses the
  bundled DejaVu Sans; drop an Amiri TTF into `storage/fonts` to upgrade.

Confirm on the shared host: `php -m | grep -E "zip|mbstring|gd"`.

## Manual verification list (Task6 T6-04 + section 5 note 5)

Open old and new UI side by side and confirm parity — no code gap was
proven, these are visual checks:

- custody tracking summary cards (total amounts, partially returned,
  transfers sent/received)
- agent movements summary cards ("received custodies", "returned amounts")
- social-case list "last spend" column and search
- treasury detail income/expense totals
- reconciliation report sections (cash in, cash out, equation, result,
  mismatch warning)
- dashboard per role (approval rate, quick actions, recent alerts, my tasks,
  treasury balances, my custody summary, recent activity)
- HR KPI period start/end fields and salary calculation preview
- notification "go to transaction" link
- broadcast level "danger"
- activity log date range

`auth.register` exists in Blade without a route — no parity needed.

Before any release: run `npm run verify` (frontend) and `php artisan route:list`
(backend), then open every page in light AND dark mode and at phone width and
list anything that still looks unfinished. Items marked **(verify)** in
Task.md T6-41.5 (topbar/table-row/dialog icons in both themes) are part of
this pass.
