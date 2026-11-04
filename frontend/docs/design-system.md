# Design system — "مؤسسة نجيب سليم الخيرية"

الثمانية ألوان فقط (10.1). الطوابع بالشكل/الأيقونة لا باللون (10.1.3). الخطوط المسطرة والهامش المزدوج، الأرقام بوجه Amiri، الرسم اللوني بترتيب ثابت brand/light/ink/taupe/mid/sand مع separator (10.1.4). BEM بخصائص منطقية فقط، الطبقات base<primeng<blocks<utilities.

## Shared page patterns (Task4 — T4-20…T4-26)

Every page composes the same skeleton, in this order:

1. `app-page-header` — always OUTSIDE any async wrapper, so the title never
   disappears while loading or on error. Back link = `common.back_to_list`
   in the `actions` slot.
2. optional status stamp / KPI row (`app-status-stamp`, `app-stat-tile`)
3. filters (`.filter-bar` with `.field` controls)
4. content

- **`app-async-page`** is the page state machine: error → skeleton → empty →
  content. Inputs: the `resource()`, a `variant` (`list` / `detail` /
  `report` / `form`), an `empty` flag, optional `backLink`. It renders
  `app-error-state` (API message + Retry wired to `resource.reload()`,
  403/404 mapped to the standard copy) and `app-page-skeleton`. No page may
  render blank — a failed load always shows the error state.
- **`app-data-table`** stays the one table for interactive lists (pagination,
  column drag, saved state).
- **`app-ledger-table`** is the one static table for detail pages, reports
  and secondary lists: projected rows via `<ng-template appLedgerBody>`,
  cells tagged `<td appLedgerCol="…">`, built-in empty row, horizontal
  scroll, optional sortable columns and a `<tfoot footer>` totals slot.
- **`app-field`** wraps every form control: label + required marker + hint +
  error with `for`/`id` wiring; server 422 messages land under their field.
  Field groups sit in **`app-form-section`** cards.
- **`app-row-actions`** renders per-row actions: icon buttons with
  aria-label/tooltip, destructive actions last and distinct, collapsing to a
  labelled "⋯" menu under 640 px.
- Dialogs are fixed: reject/cancel/resolve → `app-reason-dialog`; delete →
  `app-confirm-delete`; any other modal form → `app-form-dialog`. No page
  hand-rolls a reason/confirm form, and **native browser dialogs
  (`alert()` / `confirm()` / `prompt()`, including `window.*` forms) are
  banned** — enforced by the `no-alert` ESLint error in `npm run lint`.
- Money/dates/numbers only through the shared pipes; status only through
  `app-status-stamp`. No inline `style="…"`, no raw `<table`, no Arabic
  literal outside `ar.json` — `npm run check:i18n` enforces all three.
