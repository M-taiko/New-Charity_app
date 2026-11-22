# Design system — "مؤسسة نجيب سليم الخيرية"

## The Stitch redesign (RD phases — light + dark)

The app runs on the **Naguib Selim Foundation** Stitch design system in BOTH
themes. Every color lives in `_tokens.scss` once per theme; components and
blocks only reference `--color-*` / `--status-*` / `--chart-*` tokens —
`npm run check:palette` rejects any hex outside the whitelist in built CSS.

### Colors (RD-1.1)

| Role | Light | Dark |
|---|---|---|
| primary / on-primary | `#5C4033` / white | `#F5EFE8` / `#3A261D` (cream primary) |
| primary-container | `#EFE8E1` | `--dark-3` |
| secondary | `#9A7B5C` | `#B99B7C` |
| surface (cards) | `#FFFFFF` on `#F4F3F1` bg | `#201E1B` on `#12100E` bg |
| hairline / outline | `#E2DCD4` | `#3A3835` / `#4A4845` |
| text / muted | `#1F1B18` / `#6E655E` | `#F5EFE8` / `#BEAFA7` |
| success / danger / warning / info | `#10B981` `#E11D48` `#D97706` `#3B82F6` | `#34D399` `#FB7185` `#FBBF24` `#60A5FA` |

Status tints (`--status-active/info/warning/danger/neutral` bg+fg pairs) are
solid hexes in light, channel-alpha rgb() in dark (Chrome 109-safe — never
`color-mix()`). Chart palette `--chart-1..8` + `--chart-muted`/`--chart-track`
per theme; tinted mini-card borders `--tint-blue/rose/green/amber-border`.

### Typography & shape (RD-1.4/1.5)

- **Cairo** 600/700 (self-hosted subset, preloaded) is the display face for
  titles/KPI figures; **IBM Plex Sans Arabic** 400/600 is body/UI.
- Type roles: page title = 24/32 w700 (`--text-2xl`), card title 18 w600,
  labels 12 w500/600, body 14; `tabular-nums` everywhere numbers compare.
- Radii: `--radius-s 6 / m 8 / l 12 / xl 16 / full`. Motion fast = 150ms
  ease-out. Shell: 64px topbar, 256px sidebar (64 collapsed), 1280px content.

### Key patterns added

- **Soft buttons** (RD-3.1): action-kinds with `variant: 'soft'` render tint
  bg + semantic fg (`.p-button-soft`); in-table "view" row action rests as
  the soft blue pill (`severity: 'info'`).
- **Stat tiles** (RD-3.2): `tone` adds the top-accent card (4px status bar +
  colored value), `circle` the circular icon chip, `tinted` the tint-bg
  mini-cards (custody detail); `kpi-grid--2/4/5/6` are the Stitch grids.
- **Status pills** (RD-3.3): stamps are rounded-full tint+fg pills; money
  color code `.money--pos/neg/link` (green/rose/blue).
- **Spending ratio** (RD-3.3): `app-progress-cell` — slim bar + tabular %.
- **Card-hero** (RD-4.10): detail facts card with the primary header band.
- **Count chips** (RD-4.3): `.filter-chips` — status filter chips with live
  counts (primary-container active chip).
- **Journal ledger** (RD-4.10/5.3): `journalStampClass`/`journalMoneyClass`
  map transaction types to pills + signed money colors.
- **UX layer (Phase 6)**: 2px top progress line under the topbar while API
  calls fly (`pendingRequests`), sticky table headers, bottom-sheet dialogs
  on phones, money-formatted chart tooltips, offline auto-dismiss, and the
  `unsavedChangesGuard` on form routes (components opt in via
  `hasUnsavedChanges()`; the guard's single native `confirm` is the one
  documented exception to the no-alert ban — it must block navigation
  synchronously inside the router guard).
- Print ALWAYS forces the light palette (`print.scss` `:root[data-theme]`
  overrides win in both themes).

---


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
- **`app-data-table`** is the ONLY table (T6-20): `mode="static"` covers
  detail pages, reports and secondary lists (client sort/pagination/search/
  filters), totals ride the `[footer]` slot, and `app-ledger-table` is
  DELETED. The `filter-bar` markup survives ONLY as report parameters
  (date range / direction pickers that change the data set) — never for
  row filtering.
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


## Task6 — buttons, colors, tables, filters, print, motion (T6-10…T6-80)

### Buttons — the action-kind map (T6-13)

A button never picks its own severity. `ACTION_KINDS`
(`shared/ui/actions/action-kind.ts`) maps every action kind to
severity + variant + icon; pages use `app-action-button [kind]="…"`
(or the same map for row actions and dialog confirms):

| Meaning | Severity | Variant | Examples |
|---|---|---|---|
| Destructive | `danger` (red) | filled in confirm dialogs, outlined/text elsewhere | delete, reject, cancel, cancel transfer |
| Approve/success | `success` (green) | filled for the decisive action | approve, accept, receive, mark reviewed |
| Caution | `warn` (amber) | outlined | request return, freeze, reopen |
| Informational | `info` (blue-teal) | outlined/text | transfer, view movements |
| Primary | brand brown | filled — ONE per page header or dialog | save, create, apply |
| Secondary | `secondary` | outlined | back, print, export, dialog cancel |
| Row actions | — | icon-only ghost | view, edit, more |

The semantic scales (green/red/amber/blue-teal) are defined ONCE in
`core/theme/brand-preset.ts` (`primitive` section) so button, tag, message,
toast and badge severities can never fall back to PrimeNG defaults.
`check-palette` allows exactly: the 8 brand colors + the semantic scales +
the module accent set + the chart palette. `check-actions` fails any
severity outside `{secondary, danger, success, warn, info}` and any
warn-filled button.

### Module accents (T6-40)

Eight accent tokens (`--accent-custodies|expenses|treasury|cases|hr|tasks|
procurement|reports`, light + dark values in `_tokens.scss`) appear ONLY as
tinted icon chips (`<app-icon chip="custodies">`), status stamps, charts and
notifications — brand brown stays the chrome. Status stamps use the semantic
colors: pending amber, approved green, rejected red, info blue, closed gray.

### Page anatomy (T6-10/T6-11)

- **`app-section-nav [module]`** — the module sub-nav under every page header
  (custodies, expenses, treasury, hr, procurement, cases). Cross-page links
  live here, never as header buttons.
- **Header rule** — max 3 visible buttons: 1 primary (+ creation variants in a
  split button) and up to 2 secondary; everything else in a `p-menu` More menu.
- **Detail pages** — status decisions in ONE `sticky-actions` bar, primary
  decision first, destructive last.
- **`app-tabs`** — the only tabs pattern (accessible tablist, count badges,
  `?tab=` sync, arrow keys). No page stacks two tables; multi-section pages
  tab them (custody/case detail, tracking, my custodies).

### Tables (T6-20…T6-22, T6-21)

`app-data-table` is the ONLY table component. `app-ledger-table` is retired.

- **`mode="static"`** — client-side sort, pagination, search and filters for
  detail/report tables; server mode stays for paginated lists.
- **Sorting contract (T6-21)** — the server returns `meta.sortable` (accepted
  keys; unknown sort keys are 422 `sort.invalid`, never silently ignored).
  Columns carry `sortKey` when the server key differs; arrows are disabled on
  columns the server cannot sort (dev-mode warns).
- **Columns** — cells render from the ordered column list via
  `<ng-template appCell="field" let-row>` so header, data and totals always
  move together; `pinned` columns (actions) stay last, not draggable or
  resizable; min width 80px; widths/order/sort persist per user.
- **Filters (T6-30)** — the table owns filtering: `filters` (JSON defs) or the
  API's `meta.filters`, or `autoFilters` derivation. One "Filters" control
  with chips + active count + `?filter[…]=` URL mirroring everywhere. The
  backend allowlist (`ApiList::paginate(filterable:)`) rejects unknown filter
  keys with 422 `filter.invalid`. Custom renderers (the expense direction
  cascade) ride `appFilterCustom`.
- **Export/Print (T6-71/T6-70)** — every table toolbar has Export ▾
  (xlsx/pdf/csv via the authenticated blob client; the backend runs the SAME
  query, capped at 2000 rows, audit-logged) and a Print menu (portrait/
  landscape A4 via CSS named pages, repeated headers, actions column hidden,
  static tables can print all rows). Charts flatten to images on beforeprint.

### Motion levels (T6-50)

`MotionService` is the single source: `data-motion="off|lite|full"` +
`--motion-scale` 0/1/2. **off** = completely static (no CSS
animations/transitions, no count-up, Chart.js `animation:false`, ripple off,
no shimmer, no smooth scroll). **lite** = the old full (count-up, 350ms chart
entrance, standard transitions). **full** = lite + page-enter transitions
(View Transitions API when available), card reveals (`appReveal`,
`appCardReveal`), overlay/dialog entrance animations, micro-interactions
(button press, hover lift, stamp pop), staggered chart entrances (800ms),
list FLIP (`appFlip` via @formkit/auto-animate). prefers-reduced-motion
defaults new users to off; a saved choice always wins.

### Transfers (T6-80)

A pending transfer can be cancelled by its sender or a manager
(`POST custody-transfers/{id}/cancel`): releases the frozen amount, notifies
receiver + accountant + managers, writes the activity log; decided transfers
answer 409 `transfer.not_pending`. The API exposes `can_cancel`; a danger
"Cancel transfer" confirm exists on my custodies, the transfer detail page
and the transfers list.

### Print + verify (T6-70, section 5 note 5)

Before any release: `npm run verify`, then open the pages in light AND dark
mode at phone width and list anything unfinished (manual pass, not
automated). Printable pages default portrait; tables with 7+ columns
(custody movements, governorates, items, researchers, salaries) print
landscape via `app-print-sheet orientation="landscape"`.
