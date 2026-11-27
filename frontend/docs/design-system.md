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
- **KPI rows & stat tiles** (RD-3.2, reshaped by R4-04) — ONE rule for every
  page. A `.kpi-grid` is a CSS grid whose column count follows from two things
  only: how wide the row is (1 column below 376px, 2 / 3 / 4 / 5 from
  376 / 572 / 768 / 964px — a 180px tile plus 16px gaps) and how many tiles it
  holds (5 → 5, 6 → 3+3, 7 → 4+3, 8 → 4+4, 11 → 4+4+3; the last row is never a
  lone tile). At a 1280px viewport the row is 976px and five tiles sit five-up
  at 182px; a tile narrower than 200px tightens its padding and figure. There
  are no `kpi-grid--N` modifiers any more. Every tile carries its icon (a 20px
  glyph in a 36px soft circle, in the row's module accent) — a row never mixes
  tiles with and without one. `tone` colours the VALUE only, where colour
  carries meaning (incoming green, outgoing red) and never on a zero figure;
  the 4px top-accent bar is retired. `tinted` keeps the custody-detail
  mini-cards.
- **Avatars** (R4-07): `app-avatar` shows the photo when there is a usable one
  — present, loaded, at least half the size it is shown at — otherwise the
  initials on the primary container. A stored picture can be a 1×1 pixel or a
  dead link; both fall back to initials and never flash.
- **LTR values in RTL cells** (R4-03): `app-ltr-cell` for an e-mail address or
  a Latin identifier. Left as bare cell text the browser ellipsized such a
  value at its START; isolated and laid out LTR it truncates at its end, stays
  aligned under its header and carries its full text in a `title`.
- **Role pills** (R4-03): `.role-pills` / `.role-pill` — small NEUTRAL chips, a
  role is a label, not a status (stays `app-status-stamp`); two at most, the
  rest folded into `+N`.
- **Notifications timeline** (R4-06): `.notices` — rows grouped under
  اليوم / أمس / أقدم; an unread row has a brand dot, a bold title and a
  visually-hidden "غير مقروء"; the message wraps in full; the whole row is a
  link to the related record (a button when there is none) that also marks it
  read. The page's one header action is "mark all read".
- **Chart numbers** (R4-02): every number Chart.js paints goes through
  `shared/lib/chart-format.ts` — Western digits, compact ticks, the sign in
  front, and each value pinned between LEFT-TO-RIGHT MARKs. A canvas that
  inherits the page's RTL direction paints `-400K` as `400K-`; a real minus on
  its own does not fix that, the LRM pins do. Chart.js has no root
  `rtl`/`textDirection` switch for axis ticks (only its legend and tooltip
  plugins read one), so Arabic category labels keep the page's direction.
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
- **No focus, no scroll on load** (B-06, root-caused in R4): PrimeNG's
  `AutoFocus` directive writes a live `autofocus` attribute on every
  `<p-button>` (it receives `undefined` where it expects `false`).
  Chromium focuses such a button and scrolls it into view — on a phone the
  first button below the fold made the page open halfway down
  (/expenses 523px, /dashboard 1,106px at 360px). `core/primeng/
  autofocus-default.ts` stops the attribute before it reaches the DOM; the app
  never uses native autofocus.
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
  `app-page-actions` enforces it (R5-01): the first action of a primary kind is
  THE filled button, the other visible ones render secondary, and a fourth
  action moves into "⋯" — as does any action marked `menu: true` (the custody
  detail's Print). The buttons sit `--space-2` (8px) apart and are one size.
  `app-action-button` renders an anchor for a route and a real `<button>` for
  a handler; PrimeNG's `.p-button-sm` lives in a cascade layer and loses to the
  unlayered `button { font: inherit }` reset, so the bar restates the small
  size for the real button (30px, 12px label). Outside the bar a small
  `<p-button>` still renders at the default size — known, measured, reported
  in the round-5 changelog.
- **Detail pages** — status decisions in ONE `sticky-actions` bar, primary
  decision first, destructive last. The page renders the bar only when it has
  a decision to offer (`@if (hasStickyAction())`); an empty 25px strip under
  the header is worse than no bar. `.sticky-actions:empty { display: none }`
  is the net.
- **Dark theme and text buttons** — the dark general `.p-button` rule paints
  the cream fill; its hover/active ink is scoped to filled buttons
  (`:not(.p-button-text, .p-button-outlined, .p-button-soft)`). Applied to
  every button it turned "تعديل" and each "⋯" trigger from 17:1 to 1.17:1 the
  moment the pointer arrived. `e2e/button-states.spec.ts` measures every
  button kind at rest and under the pointer in both themes.
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
- **Columns fit their content (R4)** — the table uses automatic layout:
  every column is as wide as what it holds (numbers, dates and short labels
  on one line; a long text column wraps only past 28rem). The table fills its
  card when there is room; when the columns need more than the card is wide
  the table gets wider and **scrolls left/right inside its card** — on any
  screen. There is NO mobile-card variant: phones and desktops see the same
  table. Actions columns get an explicit width
  (`max(160, minWidth)`) because fixed widths are what `table-layout`
  honours; the labelled workflow button in an actions cell fills the width
  left beside the icon slots.
- **Filters (T6-30)** — the table owns filtering: `filters` (JSON defs) or the
  API's `meta.filters`, or `autoFilters` derivation. One "Filters" control
  with chips + active count + `?filter[…]=` URL mirroring everywhere. The
  backend allowlist (`ApiList::paginate(filterable:)`) rejects unknown filter
  keys with 422 `filter.invalid`. Custom renderers (the expense direction
  cascade) ride `appFilterCustom`.
- **Export/Print (T6-71/T6-70, R4)** — the table's "⋯" menu has exactly
  **Excel (xlsx)**, **PDF** and one **Print** action (always portrait). CSV
  was removed (a duplicate of Excel). Export downloads through the
  authenticated blob client as `<table>-<date>.<ext>`; the file replays
  EXACTLY the params the list was fetched with (`tableExportQuery()` on the
  page → `[exportQuery]`, plus the table's live sort/search) so it always
  matches what is on screen; the backend caps at 2000 rows and audit-logs.
  `tests/Feature/ExportRoutesTest.php` asserts every export route returns a
  real file for both formats (the `…/{id}` show-routes once swallowed the
  `…/export` URLs → 404; ids are now `whereNumber`).
- **Phones (R5-03)** — below 768px a table keeps its key columns in reach. On
  a wider screen nothing in this bullet applies.
  - **Every table declares its phone order**: `[phoneFirst]="['title',
    'status', 'amount']"` — those columns lead, the rest keep their order,
    actions stay last (all 49 tables do; the matrix fails one that does not).
  - **The first column is pinned** to the start edge (`position: sticky`),
    at most 160px wide, with a hairline divider on its exposed edge.
    **It is the first cell that RENDERS, not the first in the document.**
    A selectable table puts its selection cell first in every row and hides
    it on a phone; rules written as `th:first-child` pinned that hidden cell
    and the real first column scrolled away, on two lists, while the gate —
    reading the same cell — stayed green. The pinning, the start fade's
    offset, the row's focus ring and the selection tint all follow the first
    rendered cell (`:where([data-selectable='true']) … :nth-child(2)`, which
    adds the condition without adding specificity).
  - **A table wider than its card says so**: an edge fade on each side that
    still has columns behind it (`appScrollEdges` → `data-scroll-start/end`
    on the card) and one line above the table, "اسحب لرؤية بقية الأعمدة".
    A fade is opaque AT ITS EDGE and clears inward. A gradient's first colour
    sits opposite to its direction (`to right, surface, transparent` is
    surface on the LEFT) and gradients do not follow `dir`, so each edge
    states its paint for LTR and for RTL — round 5 shipped both mirrored, and
    the structural check was green the whole time because the elements existed.
    The start fade begins after the pinned column — at the width of the first
    cell that renders.
  - **A scrolling table switches to `border-collapse: separate`**, phones
    only: a sticky cell cannot carry a collapsed border. Nothing else changes
    — every row keeps its line, the last one included, whose line is also what
    separates the body from a totals row (footer cells draw no top border).
- **Row selection and bulk actions (N-04)** — `selectable` adds a checkbox
  column, select-all-on-page and a bar fixed to the bottom of the viewport
  while at least one row is selected (`bulkActions`, each behind a confirm
  with the count and the exact total). A filter, a search or a sort change
  clears the selection. On a phone the column is hidden and the boxes sit in
  the first, pinned cell. The server answers per id — never all-or-nothing.
- **Saved views (N-02)** — `savedViews` on the table adds "العروض المحفوظة":
  filters, search, sort and hidden columns under a name, at most ten, per
  table and per user (`shared/lib/saved-views.ts`). The filters panel and the
  views panel are never open together.
- **Quick view (N-03)** — `rowActivatable` makes a row focusable: a click on
  it, or Enter / Space, opens `app-quick-view` (a drawer on the start side,
  420px, the whole screen on a phone). A click on a link or a button inside
  the row is that control's. The address, the filters and the scroll do not
  change; the focus goes back to the row.
- **Two empty states, never the same words (N-08)** — "no data at all" is the
  page's onboarding (`app-empty-state` with a title, a hint and a call to
  action the role may do); "no results for this filter" keeps the toolbar and
  offers "مسح الفلاتر". **"No results" is read only from rows that answer the
  search on screen**: from a change in the search box or a filter until a
  request that began after it has ended, the table shows its loading body.
  The table narrows the rows it holds by the search text as the user types —
  rows that are the previous answer must not be called empty.
- **The header is one unbreakable row (round 6)** — the select-all box, the
  column's name and its sort arrow are one inline box, as wide as what it
  holds on one line. A column is never narrower than its own header: the name
  never breaks, and the arrow always sits beside it, never under it. The cost
  is measured and accepted: wide tables get wider (the matrix reports an arrow
  under its name, a name on more than one line, or a clipped header).
- **The filter panel fits its card (round 6)** — `app-table-filters`' panel is
  as wide as its fields need on one line and never wider than the table card
  (the card is a size container; `100cqw` is its inner width); its fields wrap;
  a range field holds two controls with room for both; and it opens flush with
  the card's start edge. Options are never raw values: a status filter carries
  the names the stamps use, and a yes/no column is a "نعم / لا" choice.

### Charts — `app-chart-card` (R5-02)

A chart with nothing to plot does not draw an empty axis. When every value of
every series is 0, missing or non-finite — or there is no series at all —
(`chartIsEmpty(series, compare)`, `shared/lib/chart-series.ts`) the card shows
the shared `app-empty-state` instead of the canvas: module accent, icon, and
either the page's own `emptyLabel` ("لا توجد مصروفات خلال آخر 7 أيام" on the
dashboard) or `chart.empty`. The card's "view all" link stays. A chart that
has data but whose every series is switched off in the legend keeps its legend
and shows `chart.all_hidden`, because the legend is the way back.

### Motion levels (T6-50)

`MotionService` is the single source: `data-motion="off|lite|full"` +
`--motion-scale` 0/1/2. **off** = completely static (no CSS
animations/transitions, no count-up, Chart.js `animation:false`, ripple off,
no shimmer, no smooth scroll). **lite** = count-up, 350ms chart
entrance, standard transitions. **full** = lite + the router's own
cross-fade on route change (`withViewTransitions`: old page → new page,
never through a blank), card reveals on scroll (`appCardReveal`),
overlay/dialog entrance animations, micro-interactions (button press,
hover lift, stamp pop), staggered chart entrances (800ms), list FLIP
(`appFlip` via @formkit/auto-animate). **Content is never hidden to be
animated**: the page-enter fade (`appReveal`) was retired because it
rendered every page at opacity 0 on navigation — the "blank then show"
flash. Route chunks are preloaded (`PreloadAllModules`). prefers-reduced-motion
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

### The visual matrix and its gates (`e2e/gates.ts`)

`npm run e2e -- visual-matrix.spec.ts` visits every route × light/dark ×
360/1280 against the PHP server and the packed `public/app`, runs every gate
per visit and saves a whole-page screenshot per visit under
`e2e/screenshots/<route>__<theme>__<viewport>.png`.

Each gate is a pair: a `measure…` function that runs in the page and a
`…Violations` function that decides. `e2e/gates.spec.ts` feeds every gate the
defect it was written for and asserts it is reported — **a gate that cannot
fail there is not a gate** (`npm run e2e -- gates.spec.ts`, 60 tests).

| Gate | Fails when |
|---|---|
| theme painted | `data-theme` is not the requested one, or the page background's luminance is ≥ 0.2 in a dark run / ≤ 0.8 in a light run. (R4-01: for three rounds every "dark" screenshot was light — the theme was set on the document before `goto()`, the navigation discarded it and the app restored its saved theme. The theme is now seeded through storage.) |
| no raw literal in a cell | a table cell's whole text is `true` / `false` / `null` / `undefined` / `NaN` / `[object Object]` (R4-03) |
| KPI row | tiles of different width in one grid, a lone tile on the last wrapped row, an icon on some tiles but not all, more than one top-accent colour (R4-04) |
| action area (R5-01) | a page header carries more than one filled primary button (a split button counts as one), two of its buttons are closer than 8px on the same row, or the action area holds an element with a size and nothing visible in it (the empty 25px sticky bar) |
| chart card (R5-02) | a card rendered by `app-chart-card` has nothing to plot and still draws a canvas, or shows no empty state; one with data renders neither a canvas nor a table; every series is hidden and there is no legend to turn them back on |
| phone table (R5-03) | below 768px: a table does not say which columns lead (`[phoneFirst]`), does not start with them, scrolls without a pinned first column / edge fade / hint line, pins one wider than 160px, paints a fade's opaque end away from its edge, has its divider on the far side of the pinned column, or loses the line under its last row. At 768px and above: any of that appears on a desktop table. **The first column is the first cell that renders — a hidden cell is not a column** (round 6 pinned the hidden selection cell of a selectable table and this gate, reading that cell, stayed green): it is read in the header and in the first body row, and a pinned header alone is reported. |
| stray surface | a card, input, table cell or chrome element paints in the other theme's colour (light in dark, dark in light) |
| page header holds the page (round 6) | a table, the state wrapper, the tab bar, a form, a KPI grid, a tile or a chart card is inside `app-page-header`. Three pages shipped with the header never closed — the table came out 0px wide and the page looked empty, through 288 green visits, because `<main>` still had its text. `npm run check:headers` catches it at the source first. |
| table header is one row (round 6) | a sort arrow sits on a line of its own under its name, a name runs on more than one line, or a header is clipped — at both widths |
| page at its top | a fresh navigation does not land at scrollY 0 |
| the older gates | sideways scroll (also while loading), console/page errors, raw i18n keys, empty `<main>`, focus stolen on load, unresolved sprite refs, ellipsized numbers/dates, more than one active sidebar item, sidebar pitch > 48px, clipped header/action cells (1280), dashboard tile geometry and donut centre, unequal card heights and a row touching the next block, action buttons of different width (1280), a mobile-card variant |

Two things the matrix cannot see have a spec of their own, because a picture
never shows them:

- `e2e/button-states.spec.ts` — hover. For every kind of button on ten pages,
  in both themes: the label's contrast against its real painted background
  (alpha tints composited) at rest and with the pointer on it, ≥ 4.5:1.
  `KNOWN_FAILURES` lists the ones reported and not fixed; an entry that stops
  failing fails the run, so the list cannot go stale.
- `e2e/notifications-unread.spec.ts` — a state the seeded data does not have.
  The account has no unread notification, so every matrix picture shows read
  rows; this spec answers the list request itself (nothing is written to the
  database) and asserts the unread marker.

How the matrix seeds the theme and takes its screenshots matters as much as
the gates: the theme goes through `localStorage` (what the app restores), the
fonts are awaited, and a whole-page screenshot is taken with the real viewport
made as tall as the document — Playwright's `fullPage: true` lets Chromium
relayout the page for a moment and produced frames with the sidebar pushed
out of frame.
