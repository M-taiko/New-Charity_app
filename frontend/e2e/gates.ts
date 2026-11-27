/**
 * Visual-matrix gates: every check the matrix runs per visit, split so each
 * one can be proven to fail without running the whole matrix.
 *
 *   • `measure…` functions run IN THE PAGE via `page.evaluate()`. They are
 *     self-contained (Playwright serialises their source — no closures, no
 *     imports) and only REPORT what the DOM looks like.
 *   • `…Violations` functions run in Node, take what was measured and return
 *     human-readable violations (an empty array = the gate passes).
 *   • `auditRoute()` drives one visit of a page and returns everything that
 *     failed, so `visual-matrix.spec.ts` only has to `expect(problems)` while
 *     a diagnostic run can collect the full list in one pass.
 *
 * `e2e/gates.spec.ts` feeds each gate deliberately bad fixtures — a gate that
 * cannot fail there is not a gate (R4-08.3).
 */
import type { Page } from '@playwright/test';

export type Theme = 'light' | 'dark';

export interface Viewport {
  name: string;
  width: number;
  height: number;
}

export interface Visit {
  route: string;
  theme: Theme;
  viewport: Viewport;
}

/** what the audit needs from the repo (Node side) */
export interface AuditContext {
  /** symbol ids of the packed sprite — the source of truth for the icon gate */
  spriteIds: ReadonlySet<string>;
}

// ── colour math ─────────────────────────────────────────────────────────────

/** WCAG relative luminance of an `rgb()` / `rgba()` string; null when fully transparent. */
export function relativeLuminance(color: string): number | null {
  const parts = (color.match(/[\d.]+/g) ?? []).map(Number);
  if (parts.length < 3 || parts.slice(0, 4).some(Number.isNaN) || parts[3] === 0) {
    return null;
  }
  const [r, g, b] = parts.slice(0, 3).map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

// ── R4-01: the theme is really PAINTED ──────────────────────────────────────

/** upper bound of a "dark" page background / lower bound of a "light" one */
export const DARK_MAX_LUMINANCE = 0.2;
export const LIGHT_MIN_LUMINANCE = 0.8;

export interface ThemeSnapshot {
  /** the `data-theme` attribute on <html> */
  attr: string | null;
  /** computed background of <body> */
  bodyBg: string;
  /** the colour the page really paints behind the content (alpha composited over the canvas) */
  paintedBg: string;
}

/** runs in the page */
export function measureTheme(): ThemeSnapshot {
  const channels = (bg: string): number[] => (bg.match(/[\d.]+/g) ?? []).map(Number);
  // composite body over <html> over the canvas, bottom-up
  const layers = [document.documentElement, document.body].map((el) => getComputedStyle(el).backgroundColor);
  let [r, g, b] = [255, 255, 255]; // the default canvas
  for (const layer of layers) {
    const [lr, lg, lb, la = 1] = channels(layer);
    if (lr === undefined || lg === undefined || lb === undefined) {
      continue;
    }
    r = lr * la + r * (1 - la);
    g = lg * la + g * (1 - la);
    b = lb * la + b * (1 - la);
  }
  return {
    attr: document.documentElement.getAttribute('data-theme'),
    bodyBg: getComputedStyle(document.body).backgroundColor,
    paintedBg: `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`,
  };
}

/**
 * The gate needs the attribute AND the paint. Setting `data-theme` before
 * `goto()` used to "pass" every dark run while the page restored its saved
 * theme from localStorage and rendered light.
 */
export function themeViolations(expected: Theme, snapshot: ThemeSnapshot): string[] {
  const out: string[] = [];
  if (snapshot.attr !== expected) {
    out.push(`data-theme="${snapshot.attr ?? ''}" but "${expected}" was requested`);
  }
  const luminance = relativeLuminance(snapshot.paintedBg);
  if (luminance === null) {
    out.push(`page background "${snapshot.paintedBg}" has no measurable colour`);
  } else if (expected === 'dark' && luminance >= DARK_MAX_LUMINANCE) {
    out.push(`page is LIGHT — background ${snapshot.paintedBg}, luminance ${luminance.toFixed(3)} ≥ ${DARK_MAX_LUMINANCE} in a "dark" run`);
  } else if (expected === 'light' && luminance <= LIGHT_MIN_LUMINANCE) {
    out.push(`page is DARK — background ${snapshot.paintedBg}, luminance ${luminance.toFixed(3)} ≤ ${LIGHT_MIN_LUMINANCE} in a "light" run`);
  }
  return out;
}

// ── R4-03: no raw literal reaches a table cell ──────────────────────────────

/** what `{{ row.is_active }}` prints when nobody mapped it to a label */
const RAW_LITERAL = /^(?:true|false|null|undefined|nan|infinity|-infinity|\[object object\])$/i;

/** runs in the page — the text of every table cell, as the user reads it */
export function measureTableCells(): string[] {
  return [...document.querySelectorAll('td')].map((td) => (td.textContent ?? '').replace(/\s+/g, ' ').trim());
}

/** any cell whose whole text is a JS literal (a boolean, null, an unparsed
 *  object) — one line per literal, with how many cells print it */
export function rawLiteralViolations(cellTexts: string[]): string[] {
  const counts = new Map<string, number>();
  for (const text of cellTexts) {
    if (RAW_LITERAL.test(text)) {
      counts.set(text, (counts.get(text) ?? 0) + 1);
    }
  }
  return [...counts].map(([text, count]) => `table cell prints the raw literal "${text}"${count > 1 ? ` ×${count}` : ''}`);
}

// ── R4-04: one KPI rule for every page ──────────────────────────────────────

export interface TileBox {
  width: number;
  top: number;
  /** the tile shows its header icon */
  hasIcon: boolean;
  /** painted top-accent colour, when the tile has one */
  topAccent: string | null;
}

export interface KpiGrid {
  /** index of the `.kpi-grid` on the page */
  index: number;
  tiles: TileBox[];
}

/** tiles in one row may sit a few px apart vertically and still be one row */
const ROW_TOLERANCE_PX = 4;
/** "the same width" */
const WIDTH_TOLERANCE_PX = 2;

/** runs in the page — every `.kpi-grid` with its tiles' boxes */
export function measureKpiGrids(): KpiGrid[] {
  return [...document.querySelectorAll<HTMLElement>('.kpi-grid')].map((grid, index) => ({
    index,
    tiles: [...grid.querySelectorAll(':scope > *')]
      .map((kid) => {
        const tile = (kid.querySelector<HTMLElement>('.stat-tile, .chart-card') ?? kid) as HTMLElement;
        const rect = tile.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) {
          return null;
        }
        return {
          width: Math.round(rect.width),
          top: Math.round(rect.top),
          hasIcon: !!tile.querySelector('.stat-tile__icon, .stat-tile__circle'),
          topAccent: tile.classList.contains('stat-tile--accent-top') ? getComputedStyle(tile).borderTopColor : null,
        };
      })
      .filter((tile): tile is TileBox => tile !== null),
  }));
}

const rowsOf = (tiles: TileBox[]): TileBox[][] => {
  const rows: TileBox[][] = [];
  for (const tile of [...tiles].sort((a, b) => a.top - b.top)) {
    const row = rows.find((r) => Math.abs(r[0].top - tile.top) <= ROW_TOLERANCE_PX);
    if (row) {
      row.push(tile);
    } else {
      rows.push([tile]);
    }
  }
  return rows;
};

/**
 * - every tile in a grid has the same width (±2px)
 * - a grid that wraps never leaves a single tile alone on its last row (an
 *   "orphan"). A one-column stack is not a wrap: every row is one tile there.
 * - a row never mixes tiles with and without an icon
 * - at most one top-accent colour per grid (R3-06 / R4-04)
 */
export function kpiGridViolations(grids: KpiGrid[]): string[] {
  const out: string[] = [];
  for (const grid of grids) {
    const { tiles } = grid;
    if (tiles.length === 0) {
      continue;
    }
    const where = `.kpi-grid #${grid.index}`;
    const widths = [...new Set(tiles.map((t) => t.width))].sort((a, b) => a - b);
    if (widths[widths.length - 1] - widths[0] > WIDTH_TOLERANCE_PX) {
      out.push(`${where}: tiles of different width ${widths.join('/')}`);
    }
    const rows = rowsOf(tiles);
    const columns = rows[0].length;
    if (columns > 1 && rows[rows.length - 1].length === 1) {
      out.push(`${where}: orphan tile alone on the last of ${rows.length} rows (${tiles.length} tiles in ${columns} columns)`);
    }
    const withIcon = tiles.filter((t) => t.hasIcon).length;
    if (withIcon > 0 && withIcon < tiles.length) {
      out.push(`${where}: icon on ${withIcon} of ${tiles.length} tiles — all or none`);
    }
    const accents = [...new Set(tiles.map((t) => t.topAccent).filter((a): a is string => a !== null))];
    if (accents.length > 1) {
      out.push(`${where}: ${accents.length} top-accent colours ${accents.join(', ')}`);
    }
  }
  return out;
}

// ── dark theme: no surface keeps its light paint ────────────────────────────

/** theme-following surfaces (cards, inputs, table, chrome). Filled buttons
 *  are excluded on purpose: the dark theme's primary IS a light cream. */
export const SURFACE_SELECTOR = [
  '.stat-tile',
  '.chart-card',
  '.ledger-card',
  '.p-datatable-thead > tr > th',
  '.p-datatable-tbody > tr > td',
  'app-page-header',
  '.topbar',
  '.sidebar',
  '.form-section',
  '.card-hero',
  '.bento__widget:not(.bento__widget--bare)',
  '.key-value-list',
  '.empty-state',
  '.page-skeleton',
  '.p-paginator',
  '.p-inputtext',
  '.p-textarea',
  '.p-inputwrapper',
  '.p-dialog',
  '.p-menu',
  '.p-select-overlay',
].join(', ');

export interface Surface {
  /** element tag + first two classes — groups equal findings */
  signature: string;
  background: string;
  luminance: number;
}

/**
 * runs in the page — every visible surface that paints on the WRONG side of
 * the theme: brighter than 0.3 in dark, darker than 0.2 in light. (A stray
 * `#fff` card or an un-themed input is the classic dark-mode hole.)
 */
export function measureStraySurfaces(args: { selector: string; dark: boolean }): Surface[] {
  const { selector, dark } = args;
  const channels = (bg: string): number[] => (bg.match(/[\d.]+/g) ?? []).map(Number);
  const painted = (el: Element): number[] => {
    const chain: Element[] = [];
    for (let node: Element | null = el; node; node = node.parentElement) {
      chain.push(node);
    }
    let [r, g, b] = [255, 255, 255];
    for (const node of chain.reverse()) {
      const [lr, lg, lb, la = 1] = channels(getComputedStyle(node).backgroundColor);
      if (lr === undefined || lg === undefined || lb === undefined) {
        continue;
      }
      r = lr * la + r * (1 - la);
      g = lg * la + g * (1 - la);
      b = lb * la + b * (1 - la);
    }
    return [r, g, b];
  };
  const lum = ([r, g, b]: number[]): number => {
    const [rr, gg, bb] = [r, g, b].map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * rr + 0.7152 * gg + 0.0722 * bb;
  };
  const out: Surface[] = [];
  const seen = new Set<string>();
  for (const el of document.querySelectorAll<HTMLElement>(selector)) {
    const rect = el.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) {
      continue;
    }
    const rgb = painted(el);
    const luminance = lum(rgb);
    if (dark ? luminance <= 0.3 : luminance >= 0.2) {
      continue;
    }
    const signature = `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')}`;
    const background = `rgb(${rgb.map(Math.round).join(', ')})`;
    const key = `${signature}|${background}`;
    if (!seen.has(key)) {
      seen.add(key);
      out.push({ signature, background, luminance });
    }
  }
  return out;
}

export function straySurfaceViolations(theme: Theme, surfaces: Surface[]): string[] {
  return surfaces.map((s) => `${theme === 'dark' ? 'light' : 'dark'} surface in ${theme} theme: ${s.signature} paints ${s.background}`);
}

// ── a page opens at its top ─────────────────────────────────────────────────

/**
 * A fresh navigation must land at the top of the page. PrimeNG wrote a live
 * `autofocus` on every button; on a phone the first button below the fold
 * took focus, the browser scrolled to it (523px down on /expenses) and the
 * guard then blurred it — so the focus gate passed while the page stayed
 * scrolled. Focus alone does not catch that; the scroll position does.
 */
export function scrollViolations(scrollY: number): string[] {
  return scrollY > 0 ? [`the page opened scrolled down by ${scrollY}px (something took focus or scrolled on load)`] : [];
}

// ── R5-01: header actions and the sticky action bar ──────────────────────────

/** at least this much air between two buttons of one header */
export const MIN_ACTION_GAP_PX = 8;

export interface ActionBox {
  /** what the button says (its label, else its accessible name) — for the report */
  label: string;
  left: number;
  right: number;
  top: number;
  bottom: number;
  /** painted in the brand fill: THE primary look. A split button is one unit. */
  brand: boolean;
}

export interface ActionAreaMeasure {
  /** the visible buttons of each page header on the page */
  headers: ActionBox[][];
  /** boxes that take space and show nothing: "tag.class WxH" */
  empties: string[];
}

/** runs in the page */
export function measureActionAreas(): ActionAreaMeasure {
  const rgbOf = (css: string): string | null => {
    const m = (css.match(/[\d.]+/g) ?? []).slice(0, 4).map(Number);
    if (m.length < 3 || m[3] === 0) {
      return null;
    }
    return m.slice(0, 3).map(Math.round).join(',');
  };
  const hexOf = (css: string): string | null => {
    const m = /^\s*#([0-9a-f]{6})\s*$/i.exec(css);
    return m ? [0, 2, 4].map((i) => parseInt(m[1].slice(i, i + 2), 16)).join(',') : rgbOf(css);
  };
  const brand = hexOf(getComputedStyle(document.documentElement).getPropertyValue('--color-primary'));
  const shown = (el: Element): boolean => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
  };
  const labelOf = (b: HTMLElement): string =>
    ((b.querySelector('.p-button-label')?.textContent ?? '') || b.getAttribute('aria-label') || b.getAttribute('title') || '(icon)').replace(/\s+/g, ' ').trim().slice(0, 24);

  const headers = [...document.querySelectorAll<HTMLElement>('app-page-header')].map((header) => {
    const units = new Map<Element, ActionBox>();
    for (const b of Array.from(header.querySelectorAll<HTMLElement>('.page-header__actions .p-button'))) {
      if (!shown(b)) {
        continue;
      }
      // a split button (label + dropdown) is ONE action: its parts touch by design
      const split = b.closest('app-split-action-button');
      const box = (split ?? b).getBoundingClientRect();
      const unit: ActionBox = units.get(split ?? b) ?? {
        label: labelOf(b),
        left: box.left,
        right: box.right,
        top: box.top,
        bottom: box.bottom,
        brand: false,
      };
      if (brand !== null && rgbOf(getComputedStyle(b).backgroundColor) === brand) {
        unit.brand = true;
      }
      units.set(split ?? b, unit);
    }
    return [...units.values()];
  });

  // "shows something" = has text, or contains (or is) a graphic. Containers
  // whose only children are graphics — a button around its icon — pass.
  const GRAPHIC = 'svg, img, canvas, input';
  const empties: string[] = [];
  for (const el of Array.from(document.querySelectorAll<HTMLElement>('.page-header__actions, .page-header__actions *, .sticky-actions, .sticky-actions *'))) {
    if (!shown(el) || el.closest(GRAPHIC) || (el.textContent ?? '').trim() !== '' || el.querySelector(GRAPHIC)) {
      continue;
    }
    const r = el.getBoundingClientRect();
    empties.push(`${el.tagName.toLowerCase()}.${[...el.classList].filter((c) => !c.startsWith('ng-')).slice(0, 2).join('.')} ${Math.round(r.width)}x${Math.round(r.height)}`);
  }
  return { headers, empties };
}

/**
 * - ONE primary per header (a second brand-filled button is a second primary)
 * - at least `minGap` px between the buttons of one row — never touching
 * - nothing in the action areas takes space without showing something
 *   (custody detail rendered a 976×25 sticky bar with no action in it)
 */
export function actionAreaViolations(measure: ActionAreaMeasure, minGap: number = MIN_ACTION_GAP_PX): string[] {
  const out: string[] = [];
  measure.headers.forEach((buttons, index) => {
    const name = measure.headers.length > 1 ? `header #${index}` : 'header';
    const primaries = buttons.filter((b) => b.brand);
    if (primaries.length > 1) {
      out.push(`${name}: ${primaries.length} primary buttons — ${primaries.map((b) => `"${b.label}"`).join(', ')} (one per header)`);
    }
    const rows: ActionBox[][] = [];
    for (const b of [...buttons].sort((x, y) => y.left - x.left)) {
      const row = rows.find((r) => r.some((o) => Math.min(o.bottom, b.bottom) - Math.max(o.top, b.top) > 4));
      if (row) {
        row.push(b);
      } else {
        rows.push([b]);
      }
    }
    for (const row of rows) {
      for (let i = 1; i < row.length; i++) {
        const gap = Math.round((row[i - 1].left - row[i].right) * 10) / 10;
        if (gap < minGap - 0.5) {
          out.push(`${name}: ${gap}px between "${row[i - 1].label}" and "${row[i].label}" (at least ${minGap}px)`);
        }
      }
    }
  });
  for (const empty of measure.empties) {
    out.push(`empty element in the action area: ${empty}`);
  }
  return out;
}

// ── R5-02: no chart without data ────────────────────────────────────────────

export interface ChartCardMeasure {
  title: string;
  /** the card says it has nothing to plot (`data-empty`) */
  empty: boolean;
  /** what its body shows: `loading` (N-09), `chart` (a canvas), `table`, or `hidden` (every series switched off) */
  view: string | null;
  /** N-09: its data is on its way (`data-loading`) — the body shows a skeleton, in the plot's full height */
  loading: boolean;
  skeleton: boolean;
  canvas: boolean;
  table: boolean;
  emptyState: boolean;
  legend: boolean;
}

/** runs in the page. Only cards rendered BY `app-chart-card`: the `.chart-card`
 *  class is also the look of ordinary titled cards on detail pages (the custody
 *  facts card, the "important information" box) — those are not charts. */
export function measureChartCards(): ChartCardMeasure[] {
  return [...document.querySelectorAll<HTMLElement>('app-chart-card .chart-card')].map((card) => ({
    title: (card.querySelector('.chart-card__title')?.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 40),
    empty: card.dataset.empty === 'true',
    view: card.dataset.view ?? null,
    loading: card.hasAttribute('data-loading'),
    skeleton: !!card.querySelector('.chart-card__loading'),
    canvas: !!card.querySelector('canvas'),
    table: !!card.querySelector('.chart-card__table'),
    emptyState: !!card.querySelector('.empty-state'),
    legend: !!card.querySelector('.chart-legend'),
  }));
}

/**
 * A chart card paints a canvas ONLY when it has something to plot. With
 * nothing to plot it shows the empty state; an all-zero series used to draw
 * a bare axis running 0…1 (R5-02).
 *
 * N-09: a card whose data is on its way says so (`data-view="loading"`) and
 * shows a skeleton — never an empty state that flips to a chart, and never a
 * canvas drawn into a box that was not reserved.
 */
export function chartCardViolations(cards: readonly ChartCardMeasure[]): string[] {
  const out: string[] = [];
  for (const card of cards) {
    const name = `chart "${card.title}"`;
    if (card.loading) {
      if (card.view !== 'loading') {
        out.push(`${name} is loading but does not say so (data-view is ${card.view ?? 'missing'})`);
      }
      if (!card.skeleton) {
        out.push(`${name} is loading and shows no skeleton`);
      }
      if (card.canvas) {
        out.push(`${name} is loading and already renders a canvas`);
      }
      if (card.emptyState) {
        out.push(`${name} is loading and already shows the empty state`);
      }
      continue;
    }
    if (card.view === 'loading') {
      out.push(`${name} says it is loading (data-view) but is not`);
    }
    if (card.empty) {
      if (card.canvas) {
        out.push(`${name} has nothing to plot but still renders a canvas`);
      }
      if (!card.emptyState) {
        out.push(`${name} has nothing to plot and shows no empty state`);
      }
      continue;
    }
    if (card.view === 'chart' && !card.canvas) {
      out.push(`${name} renders neither a canvas nor a table`);
    }
    if (card.view === 'table' && !card.table) {
      out.push(`${name} is in table view but renders no table`);
    }
    if (card.view === 'hidden' && !card.legend) {
      out.push(`${name} hides every series but offers no legend to turn them back on`);
    }
    if (card.view === null) {
      out.push(`${name} does not say what it shows (no data-view)`);
    }
  }
  return out;
}

// ── R5-03: a table that scrolls sideways ────────────────────────────────────

/** below this a table is on a "phone": key columns first, pinned column, fade, hint */
export const PHONE_MAX_WIDTH_PX = 768;
/** the pinned first column may not take more than this of a phone card */
export const PINNED_MAX_WIDTH_PX = 160;

/** an edge fade that is currently visible: which edge, and where its opaque end paints */
export interface FadePaint {
  edge: 'start' | 'end';
  opaque: 'left' | 'right' | 'none';
}

export interface PhoneTableMeasure {
  /** the table is wider than its card */
  scrolls: boolean;
  /** column fields the page declared for phones (`data-phone-columns`) */
  declared: string[] | null;
  /** those fields were applied to the rendered order (`data-phone-first`) */
  applied: boolean;
  /** the rendered header order (`data-columns`) */
  rendered: string[];
  /** the first RENDERED header cell is pinned to the start edge */
  pinned: boolean;
  /** the first rendered cell of the first body row is pinned like its header; null when there is no body row */
  bodyPinned: boolean | null;
  firstWidth: number;
  /** fade elements that are currently visible */
  fades: number;
  hintVisible: boolean;
  /** the direction the table reads in */
  dir: 'rtl' | 'ltr';
  /** each visible fade and the side its opaque end really paints on */
  fadePaint: FadePaint[];
  /** the side of the pinned column its divider is painted on */
  divider: 'left' | 'right' | 'none';
  /** the last body row paints its bottom line; null when there is no body row */
  lastRowLine: boolean | null;
}

/** runs in the page */
export function measurePhoneTables(): PhoneTableMeasure[] {
  /**
   * which side of an edge fade its OPAQUE end paints on, read from the
   * gradient as the browser computed it. A gradient starts with its first
   * colour on the side the direction points AWAY from — `to left, surface,
   * transparent` is surface on the right — and it knows nothing about the
   * direction of the page. (Nested: this function is sent to the page alone.)
   */
  const fadeOpaqueSide = (fade: HTMLElement): 'left' | 'right' | 'none' => {
    const image = getComputedStyle(fade).backgroundImage;
    const direction = image.match(/^linear-gradient\(\s*(to (left|right)|-?[\d.]+deg)/);
    if (!direction) {
      return 'none';
    }
    const towardLeft = direction[1] === 'to left' || Math.abs(parseFloat(direction[1]) - 270) < 0.5;
    const colours = image.match(/rgba?\([^)]*\)/g) ?? [];
    const alpha = (colour: string): number => parseFloat(colour.match(/,\s*([\d.]+)\s*\)$/)?.[1] ?? '1');
    const firstSide = towardLeft ? 'right' : 'left';
    if (alpha(colours[0] ?? '') > 0.99 && alpha(colours[1] ?? '') < 0.01) {
      return firstSide;
    }
    if (alpha(colours[0] ?? '') < 0.01 && alpha(colours[1] ?? '') > 0.99) {
      return firstSide === 'right' ? 'left' : 'right';
    }
    return 'none';
  };
  /**
   * The first cell that is RENDERED. A hidden cell is not a column: on a
   * selectable table the selection cell comes first in every row and is
   * hidden on a phone — read in its place, it answered for the pinned column
   * with `position: sticky` and a width of 0. (Nested, like the one above.)
   */
  const firstRendered = (cells: NodeListOf<HTMLElement>): HTMLElement | undefined =>
    [...cells].find((cell) => getComputedStyle(cell).display !== 'none');
  const isPinned = (cell: HTMLElement): boolean => {
    const style = getComputedStyle(cell);
    return style.position === 'sticky' && style.insetInlineStart === '0px';
  };
  return [...document.querySelectorAll<HTMLElement>('.ledger-table')].map((card) => {
    const box = card.querySelector<HTMLElement>('.p-datatable-table-container');
    const first = firstRendered(card.querySelectorAll<HTMLElement>('.p-datatable-table thead th'));
    const firstBody = firstRendered(card.querySelectorAll<HTMLElement>('.p-datatable-table tbody tr:first-child > td'));
    const cs = first ? getComputedStyle(first) : null;
    const hint = card.querySelector<HTMLElement>('.ledger-table__scroll-hint');
    const visible = [...card.querySelectorAll<HTMLElement>('.ledger-table__fade')].filter((fade) => getComputedStyle(fade).opacity !== '0');
    // "rgb(…) 1px 0 0 0 inset": a positive x offset paints the line on the LEFT inner edge
    const shadow = cs?.boxShadow?.match(/rgba?\([^)]*\)\s+(-?[\d.]+)px\s+-?[\d.]+px[^,]*inset/);
    const offset = shadow ? parseFloat(shadow[1]) : 0;
    const lastRow = card.querySelector<HTMLElement>('.p-datatable-table tbody tr:last-child > td');
    const lastRowStyle = lastRow ? getComputedStyle(lastRow) : null;
    return {
      scrolls: !!box && box.scrollWidth - box.clientWidth > 1,
      declared: card.dataset.phoneColumns ? card.dataset.phoneColumns.split(',') : null,
      applied: card.dataset.phoneFirst === 'true',
      rendered: (card.dataset.columns ?? '').split(',').filter(Boolean),
      pinned: !!first && isPinned(first),
      bodyPinned: firstBody === undefined ? null : isPinned(firstBody),
      firstWidth: first ? Math.round(first.getBoundingClientRect().width) : 0,
      fades: visible.length,
      hintVisible: !!hint && getComputedStyle(hint).display !== 'none',
      dir: getComputedStyle(card).direction === 'ltr' ? 'ltr' : 'rtl',
      fadePaint: visible.map((fade) => ({ edge: fade.classList.contains('ledger-table__fade--start') ? 'start' : 'end', opaque: fadeOpaqueSide(fade) })),
      divider: offset > 0 ? 'left' : offset < 0 ? 'right' : 'none',
      lastRowLine: lastRowStyle ? parseFloat(lastRowStyle.borderBlockEndWidth) > 0 && !/,\s*0\)$/.test(lastRowStyle.borderBlockEndColor) : null,
    };
  });
}

/**
 * On a phone a table that scrolls must say so and keep its title column:
 * key columns first, a pinned (and not too wide) first column, an edge fade
 * and a hint. On a wider screen none of that may appear — desktop tables
 * keep exactly the order and look they were verified with.
 *
 * The fade and the divider are checked for WHERE THEY PAINT, not only that
 * they exist. Round 5 shipped both fades mirrored and the divider on the far
 * side of the pinned column: the structural half of this gate was green the
 * whole time, because the elements were all there.
 */
export function phoneTableViolations(tables: readonly PhoneTableMeasure[], phone: boolean): string[] {
  const out: string[] = [];
  for (const [index, table] of tables.entries()) {
    const head = table.rendered.slice(0, 3).join(', ') + (table.rendered.length > 3 ? '…' : '');
    const name = `table #${index} [${head}]`;
    if (phone) {
      if (table.scrolls) {
        if (!table.pinned) {
          out.push(`${name} scrolls but its first column is not pinned`);
        } else if (table.bodyPinned === false) {
          // the header alone is not the column: its cells scroll away under it
          out.push(`${name} scrolls but the cells of its first column are not pinned (the header alone is)`);
        }
        if (table.fades === 0) {
          out.push(`${name} scrolls but shows no edge fade`);
        }
        if (!table.hintVisible) {
          out.push(`${name} scrolls but shows no hint`);
        }
        // in RTL the start edge is the right, so the start fade touches the
        // pinned column with its right side and the end fade sits on the left
        const rtl = table.dir === 'rtl';
        for (const fade of table.fadePaint) {
          const against = fade.edge === 'start' ? (rtl ? 'right' : 'left') : (rtl ? 'left' : 'right');
          if (fade.opaque !== against) {
            const what = fade.edge === 'start' ? 'against the pinned column' : 'on the card edge';
            out.push(`${name} ${fade.edge} fade paints its opaque end ${fade.opaque === 'none' ? 'nowhere' : `on the ${fade.opaque}`} — it must be ${what} (${against})`);
          }
        }
        if (table.pinned && table.divider !== (rtl ? 'left' : 'right')) {
          out.push(`${name} pinned column's divider is ${table.divider === 'none' ? 'missing' : `on its ${table.divider} edge`} — it belongs on the exposed ${rtl ? 'left' : 'right'} edge`);
        }
        // the last row's line is also what separates the body from a totals row
        if (table.lastRowLine === false) {
          out.push(`${name} loses the line under its last row`);
        }
      } else if (table.hintVisible) {
        out.push(`${name} fits its card but shows the scroll hint`);
      }
      // every table states which of its columns lead on a phone, fitting or
      // not: one that fits today is the next narrow phone's scrolling table
      if (!table.declared) {
        out.push(`${name} does not say which columns lead on a phone`);
      } else {
        const lead = table.declared.filter((field) => table.rendered.includes(field));
        const start = table.rendered.slice(0, lead.length);
        if (!table.applied || lead.join(',') !== start.join(',')) {
          out.push(`${name} starts with "${start.join(', ')}" on a phone — declared "${lead.join(', ')}"`);
        }
      }
      if (table.pinned && table.firstWidth > PINNED_MAX_WIDTH_PX + 1) {
        out.push(`${name} pinned column is ${table.firstWidth}px wide (at most ${PINNED_MAX_WIDTH_PX})`);
      }
      continue;
    }
    if (table.applied) {
      out.push(`${name} applied its phone column order on a wider screen`);
    }
    if (table.fades > 0) {
      out.push(`${name} shows an edge fade on a wider screen`);
    }
    if (table.hintVisible) {
      out.push(`${name} shows the phone hint on a wider screen`);
    }
    if (table.pinned) {
      out.push(`${name} pins its first column on a wider screen`);
    }
  }
  return out;
}

// ── Round 6: a page header never holds the page ─────────────────────────────

/**
 * The blocks a page is made of. None of them belongs inside its header. Three
 * pages shipped with `<app-page-header>` never closed, so the tab bar, the KPI
 * tiles and the table were projected into the header's flex row: the table
 * came out 0px wide and the page looked empty — through 288 green visits,
 * because `<main>` still had its text.
 */
export const PAGE_BLOCK_SELECTOR = [
  'app-data-table',
  'app-async-page',
  'app-section-nav',
  'form',
  'app-form-section',
  '.kpi-grid',
  'app-stat-tile',
  'app-chart-card',
].join(', ');

/**
 * Runs in the page — sent there alone, so the list above is written out here
 * too rather than read from the module.
 */
export function measureContentInPageHeader(): string[] {
  const selector = [
    'app-data-table',
    'app-async-page',
    'app-section-nav',
    'form',
    'app-form-section',
    '.kpi-grid',
    'app-stat-tile',
    'app-chart-card',
  ].join(', ');
  const inside: string[] = [];
  for (const block of Array.from(document.querySelectorAll<HTMLElement>(selector))) {
    if (block.closest('app-page-header') !== null) {
      const name = block.tagName.toLowerCase() + (block.className && typeof block.className === 'string' ? `.${block.className.trim().split(/\s+/)[0]}` : '');
      inside.push(name);
    }
  }
  return inside;
}

export function contentInPageHeaderViolations(inside: readonly string[]): string[] {
  return inside.map((name) => `<${name}> is inside the page header — the page header is never closed (it is closed, or ends with "/>")`);
}

// ── Round 6: the table header is one row ────────────────────────────────────

export interface TableHeaderMeasure {
  /** the column, as the table names it */
  field: string;
  /** the name runs on this many lines; null when the cell has no name */
  lines: number | null;
  /** where the sort arrow sits: on the name's line(s), on a line of its own under them, or there is none */
  arrow: 'beside' | 'below' | 'none';
  /** the cell clips what it holds */
  clipped: boolean;
}

/**
 * Runs in the page. In every table header the name is on one line with its
 * sort arrow beside it, and nothing is clipped: a column is never narrower
 * than its own header. Measured on every visit, at both widths.
 */
export function measureTableHeaders(): TableHeaderMeasure[] {
  const out: TableHeaderMeasure[] = [];
  for (const cell of Array.from(document.querySelectorAll<HTMLElement>('.ledger-table .p-datatable-table thead th'))) {
    const text = cell.querySelector<HTMLElement>('.ledger-table__header-text');
    const field = cell.getAttribute('data-field') ?? '';
    const clipped = cell.scrollWidth > cell.clientWidth + 1;
    if (text === null) {
      out.push({ field, lines: null, arrow: 'none', clipped });
      continue;
    }
    const textBox = text.getBoundingClientRect();
    const lineHeight = parseFloat(getComputedStyle(text).lineHeight) || 0;
    let arrow: TableHeaderMeasure['arrow'] = 'none';
    const icon = cell.querySelector('p-sorticon');
    if (icon !== null) {
      const iconBox = icon.getBoundingClientRect();
      // below = on a line of its own, under the name. Beside the last line of a name that
      // runs on several lines is "beside" — the wrapped name is said by `lines`
      arrow = iconBox.top >= textBox.bottom - 2 ? 'below' : 'beside';
    }
    out.push({
      field,
      lines: textBox.height === 0 || lineHeight === 0 ? null : Math.round(textBox.height / lineHeight),
      arrow,
      clipped,
    });
  }
  return out;
}

export function tableHeaderViolations(headers: readonly TableHeaderMeasure[]): string[] {
  const out: string[] = [];
  for (const header of headers) {
    const name = header.field === '' ? '(a header cell)' : `"${header.field}"`;
    if (header.arrow === 'below') {
      out.push(`the sort arrow of the column ${name} sits under its name — it belongs beside it, on one line`);
    }
    if (header.lines !== null && header.lines > 1) {
      out.push(`the name of the column ${name} runs on ${header.lines} lines — a column is as wide as its header`);
    }
    if (header.clipped) {
      out.push(`the header of the column ${name} is clipped`);
    }
  }
  return out;
}

// ── Reported, not fixed: form fields wider than their grid (round 5) ────────

export interface FormOverflowMeasure {
  /** form grids on the page, and the fields directly in them */
  grids: number;
  fields: number;
  /** fields that are wider than their grid */
  wide: number;
  /** by how many pixels, at the most */
  worst: number;
}

/** runs in the page */
export function measureFormOverflow(): FormOverflowMeasure {
  const out: FormOverflowMeasure = { grids: 0, fields: 0, wide: 0, worst: 0 };
  for (const grid of Array.from(document.querySelectorAll<HTMLElement>('.form-section__grid'))) {
    const box = grid.getBoundingClientRect();
    if (box.width === 0) {
      continue;
    }
    out.grids++;
    for (const field of Array.from(grid.children)) {
      const f = field.getBoundingClientRect();
      if (f.width === 0) {
        continue;
      }
      out.fields++;
      // how far the field leaves its grid, on either side (left in RTL, right in LTR)
      const by = Math.max(box.left - f.left, f.right - box.right);
      if (by > 1) {
        out.wide++;
        out.worst = Math.max(out.worst, Math.round(by));
      }
    }
  }
  return out;
}

/**
 * Round 5 found, and did not fix, that a form field never gets narrower than
 * 320px — `.form-section__grid` is `repeat(auto-fit, minmax(320px, 1fr))` —
 * while the grid follows its card: on a phone under 394px wide every field
 * runs past the card. Forms were marked "do not touch" in that review, so
 * this is a NOTE on every run, counted on its own line: a defect that is
 * known is stated, not hidden among the excused, nor silently green.
 * `minmax(min(320px, 100%), 1fr)` is the whole fix once it is approved.
 */
export function formOverflowNote(measure: FormOverflowMeasure): string | null {
  if (measure.wide === 0) {
    return null;
  }
  return `form fields wider than their grid: ${measure.wide} of ${measure.fields}, by up to ${measure.worst}px (reported in round 5, not fixed)`;
}

/**
 * One line for everything the matrix saw, reported and did not fix. The
 * notes are `${route} [${theme}/${viewport}] ${message}`; what is summed is
 * the LIGHT visits only — a dark visit shows the same fields, and counting
 * both would double every number.
 */
export function reportedSummary(notes: readonly string[]): string {
  const routes = new Set<string>();
  const wheres = new Set<string>();
  let wide = 0;
  let fields = 0;
  let worst = 0;
  let message = '';
  for (const note of notes) {
    const at = note.indexOf(' [');
    routes.add(note.slice(0, at));
    const where = note.slice(at + 2, note.indexOf('] ', at));
    wheres.add(where);
    message = note.slice(note.indexOf('] ', at) + 2);
    const counted = /grid: (\d+) of (\d+), by up to (\d+)px/.exec(note);
    if (counted && where.startsWith('light/')) {
      wide += Number(counted[1]);
      fields += Number(counted[2]);
      worst = Math.max(worst, Number(counted[3]));
    }
  }
  const totals = fields > 0 ? `: ${wide} of ${fields} fields in light, by up to ${worst}px` : '';
  const plural = (n: number, word: string): string => `${n} ${word}${n === 1 ? '' : 's'}`;
  return `${plural(notes.length, 'visit')} on ${plural(routes.size, 'route')} (${[...wheres].sort().join(', ')})${totals} — ${message}`;
}

/**
 * A whole-page screenshot (R4-08.1). Not Playwright's `fullPage: true`:
 * that path lets Chromium capture beyond the viewport, which relayouts the
 * page for a moment and produced frames with the sidebar pushed out of frame
 * on some routes (the live DOM was correct the whole time). The REAL viewport
 * is made as tall as the document instead, then restored — what is captured
 * is a layout the browser actually showed.
 */
export async function screenshotWholePage(page: Page, path: string, viewport: Viewport): Promise<void> {
  await page.evaluate(() => document.fonts.ready);
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  await page.setViewportSize({ width: viewport.width, height: Math.max(viewport.height, height) });
  await page.waitForTimeout(400);
  await page.screenshot({ path, fullPage: false });
  await page.setViewportSize({ width: viewport.width, height: viewport.height });
}

// ── shared by the older gates ───────────────────────────────────────────────

/** dotted Latin identifiers that are never legitimate UI text (i18n keys) */
export const RAW_I18N_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;

/** settles when the page shows real content (header, a state, a first card) */
export const CONTENT_SELECTOR =
  'app-page-header, .error-state, .empty-state, .stat-tile, .form-section, .chat, .ledger-table, .chart-card, .card-hero, .key-value-list';

/** console noise that is the environment, not the app */
const CONSOLE_NOISE = ['net::ERR_ABORTED', 'net::ERR_NO_BUFFER_SPACE'];

/** `Failed to fetch dynamically imported module: <url>` → that module's URL */
const DROPPED_MODULE = /Failed to fetch dynamically imported module:\s*(\S+)/i;

export interface AuditResult {
  /** every gate violation of the visit, already prefixed with route/theme/viewport */
  problems: string[];
  /** what was seen and deliberately not counted as a failure, with the reason */
  notes: string[];
}

/**
 * Console and page errors → failures. One kind needs a second look.
 *
 * "Failed to fetch dynamically imported module" is what the page prints when
 * the connection carrying a lazy-route chunk is dropped. The server this
 * matrix runs against is PHP's single-threaded one: after a few hundred
 * visits in a row it drops a connection (the same Windows socket exhaustion
 * that surfaces as `net::ERR_NO_BUFFER_SPACE`), at a different route every
 * run. The same sentence is also what a chunk missing from the bundle
 * prints — so the module is asked for again, from outside the browser:
 * served (200) means the connection was dropped and the visit is noted, not
 * failed; anything else fails the gate with the status it answered.
 */
export async function consoleErrorViolations(
  errors: readonly string[],
  statusOf: (url: string) => Promise<number>,
): Promise<Pick<AuditResult, 'problems' | 'notes'>> {
  const problems: string[] = [];
  const notes: string[] = [];
  for (const error of errors) {
    if (CONSOLE_NOISE.some((noise) => error.includes(noise))) {
      continue;
    }
    const dropped = DROPPED_MODULE.exec(error);
    if (!dropped) {
      problems.push(error);
      continue;
    }
    const status = await statusOf(dropped[1]);
    if (status === 200) {
      notes.push(`the server dropped one chunk fetch; the module is served (HTTP 200): ${dropped[1].split('/').pop()}`);
    } else {
      problems.push(`${error} — that module answers HTTP ${status}`);
    }
  }
  return { problems: problems.map((e) => 'console errors: ' + e), notes };
}

/** one full visit: navigate, settle, run every gate, report what failed and what was excused */
export async function auditRoute(page: Page, visit: Visit, ctx: AuditContext): Promise<AuditResult> {
  const { route, theme, viewport } = visit;
  const at = (message: string): string => `${route} [${theme}/${viewport.name}] ${message}`;
  const problems: string[] = [];
  const notes: string[] = [];
  const check = (violations: readonly string[]): void => {
    problems.push(...violations.map(at));
  };

  const errors: string[] = [];
  const onConsole = (m: { type(): string; text(): string }): void => {
    if (m.type() === 'error') {
      errors.push('console: ' + m.text().slice(0, 200));
    }
  };
  const onPageError = (e: Error): void => {
    errors.push('pageerror: ' + String(e).slice(0, 200));
  };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);

  try {
    await page.setViewportSize({ width: viewport.width, height: viewport.height });
    // the theme is seeded through storage, never the DOM: an attribute set here
    // is discarded by the navigation and the app restores its saved theme
    // (the key is inlined: a page function cannot see this module's constants)
    await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);

    // The dev server this runs against is PHP's single-threaded one: while
    // another client holds a request, a page can sit without content past
    // any timeout. One quiet second attempt absorbs that stall; a page that
    // shows nothing twice still fails.
    let settled = false;
    for (let attempt = 1; attempt <= 2 && !settled; attempt++) {
      await page.goto('/app/' + route, { waitUntil: 'domcontentloaded' });

      // 0 — WHILE LOADING: the page must never scroll sideways, not even for a
      //     frame (the top loading bar once stuck out past the viewport and
      //     widened the page in RTL). Sample the first ~0.9s after navigation.
      let loadOverflow = 0;
      for (let i = 0; i < 6; i++) {
        const o = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth).catch(() => 0);
        loadOverflow = Math.max(loadOverflow, o);
        await page.waitForTimeout(150);
      }
      if (loadOverflow > 1) {
        problems.push(at(`page scrolled sideways while loading (+${loadOverflow}px)`));
      }

      // settle: header or error state visible, then one paint for skeletons→content
      try {
        await page.locator(CONTENT_SELECTOR).first().waitFor({ state: 'visible', timeout: 25_000 });
        settled = true;
      } catch {
        if (attempt === 2) {
          problems.push(at('the page showed no content within 25s, twice'));
        } else {
          await page.waitForTimeout(3_000);
        }
      }
    }
    await page.waitForTimeout(1_200);

    // the page must have landed at its top (a focused control below the
    // fold scrolls the whole page before anything blurs it)
    check(scrollViolations(await page.evaluate(() => Math.round(window.scrollY))));

    // R5-01 — one primary per header, air between the buttons, no empty bar
    check(actionAreaViolations(await page.evaluate(measureActionAreas)));

    // R5-02 — no chart paints an axis without data
    check(chartCardViolations(await page.evaluate(measureChartCards)));

    // R5-03 — on a phone a scrolling table leads with its key columns, pins
    //         its first column and says that it scrolls; wider screens are
    //         left exactly as they were
    check(phoneTableViolations(await page.evaluate(measurePhoneTables), viewport.width < PHONE_MAX_WIDTH_PX));

    // round 6 — a page header never holds the page (three pages shipped with it
    //           never closed: the table was 0px wide and the page looked empty)
    check(contentInPageHeaderViolations(await page.evaluate(measureContentInPageHeader)));

    // round 6 — in every table header the name is on one line, with its sort
    //           arrow beside it and nothing clipped — at both widths
    check(tableHeaderViolations(await page.evaluate(measureTableHeaders)));

    // reported in round 5 and not fixed (forms: "do not touch") — counted on
    // every run, never among the excused
    const formNote = formOverflowNote(await page.evaluate(measureFormOverflow));
    if (formNote) {
      notes.push(at(formNote));
    }

    // R4-01 — the theme is really painted (dark runs used to render light)
    check(themeViolations(theme, await page.evaluate(measureTheme)));

    // 1 — no horizontal page scroll
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 1) {
      problems.push(at(`horizontal scroll +${overflow}px`));
    }

    // 3 — no literal i18n key rendered (emails stripped first — user addresses
    //     like adam.yahia.543@org.com are not translation keys)
    const mainText = (await page.locator('main, .empty-state--page').first().innerText().catch(() => '')) || '';
    const rawKey = RAW_I18N_KEY.exec(mainText.replace(/[\w.+-]+@[\w-]+\.[\w.]+/g, ''));
    if (rawKey) {
      problems.push(at(`literal i18n key "${rawKey[0]}"`));
    }

    // 4 — never an empty <main>
    if (mainText.trim().length === 0) {
      problems.push(at('empty <main>'));
    }

    // R4-03 — no table cell prints a raw JS literal
    check(rawLiteralViolations(await page.evaluate(measureTableCells)));

    // R4-04 — KPI rows: same width, no orphan, all-or-none icons, one accent
    check(kpiGridViolations(await page.evaluate(measureKpiGrids)));

    // dark/light surfaces
    check(straySurfaceViolations(theme, await page.evaluate(measureStraySurfaces, { selector: SURFACE_SELECTOR, dark: theme === 'dark' })));

    // 2 — console/page errors (dead API calls, template errors). A dropped
    //     chunk fetch is checked against the server before it is excused.
    const consoleResult = await consoleErrorViolations(errors, async (url) => {
      try {
        return (await page.request.get(url)).status();
      } catch {
        return 0;
      }
    });
    check(consoleResult.problems);
    notes.push(...consoleResult.notes.map(at));

    // 5 — B-06: nothing steals focus on load (body or the skip-link target)
    const activeDesc = await page.evaluate(() => {
      const el = document.activeElement;
      return el === null || el === document.body ? 'body' : `${el.tagName.toLowerCase()}.${String(el.className).slice(0, 40)}`;
    });
    if (activeDesc !== 'body' && activeDesc !== 'main.app-shell__content') {
      problems.push(at(`focus stolen on load by ${activeDesc}`));
    }

    // 5b — R2-02: KPI tile edges align (dashboard)
    if (route === 'dashboard') {
      check(
        await page.evaluate(() => {
          const bad: string[] = [];
          for (const tile of document.querySelectorAll<HTMLElement>('.stat-tile')) {
            const label = tile.querySelector<HTMLElement>('.stat-tile__head .stat-tile__label, .stat-tile__label');
            const value = tile.querySelector<HTMLElement>('.stat-tile__value');
            const caption = tile.querySelector<HTMLElement>('.stat-tile__caption, .stat-tile__delta');
            if (!label || !value) continue;
            const glyph = document.createRange();
            glyph.selectNodeContents(value);
            const valueRect = glyph.getBoundingClientRect();
            const lR = Math.round(label.getBoundingClientRect().right);
            const vR = Math.round(valueRect.right);
            if (Math.abs(lR - vR) > 1.5) {
              bad.push(`label.r@${lR} value.r@${vR} text=${(value.textContent ?? '').trim().slice(0, 12)}`);
            }
            if (caption) {
              const cR = Math.round(caption.getBoundingClientRect().right);
              if (Math.abs(lR - cR) > 1.5) {
                bad.push(`label.r@${lR} caption.r@${cR}`);
              }
            }
          }
          return bad.map((b) => `dashboard tiles misaligned: ${b}`);
        }),
      );
    }

    // 6 — B-01: every sprite <use> href resolves to a real symbol
    const usedIconIds = await page.evaluate(() =>
      [...document.querySelectorAll('use')]
        .map((u) => u.getAttribute('href') ?? u.getAttribute('xlink:href') ?? '')
        .filter((ref) => ref.includes('icons/sprite.svg#'))
        .map((ref) => ref.split('#')[1] ?? '')
        .filter(Boolean),
    );
    const missingIcons = [...new Set(usedIconIds)].filter((id) => !ctx.spriteIds.has(id));
    if (missingIcons.length > 0) {
      problems.push(at(`unresolved sprite refs: ${missingIcons.join(', ')}`));
    }

    // 7 — B-04: no date/amount text is ellipsized
    check(
      await page.evaluate(() => {
        const clipped: string[] = [];
        for (const el of document.querySelectorAll('td span, td strong, td a')) {
          const style = getComputedStyle(el);
          if (style.textOverflow !== 'ellipsis') continue;
          const text = (el.textContent ?? '').trim();
          if (/^[\d.,:/\s]+(%|)$/.test(text) && text !== '') {
            clipped.push(`ellipsized number/date "${text.slice(0, 30)}"`);
          }
        }
        return clipped;
      }),
    );

    // 8 — B-10: exactly one highlighted sidebar item (shell pages only)
    const sidebarActive = await page.evaluate(() => document.querySelectorAll('.sidebar__link--active').length);
    if (sidebarActive > 1) {
      problems.push(at(`sidebar active items = ${sidebarActive}`));
    }

    // 9 — R3-02: sidebar pitch — consecutive links WITHIN a group run ≤ 48px
    if ((await page.locator('.sidebar nav').count()) > 0) {
      const pitch = await page.evaluate(() => {
        const nav = document.querySelector('.sidebar nav');
        if (!nav) return { max: 0, count: 0 };
        const tops: number[] = [];
        for (const child of nav.children) {
          if (child.classList.contains('sidebar__group-label') || child.classList.contains('sidebar__branch')) {
            tops.push(NaN);
            continue;
          }
          const el = child as HTMLElement;
          if (el.offsetParent === null) continue;
          tops.push(Math.round(el.getBoundingClientRect().top));
        }
        let max = 0;
        for (let i = 1; i < tops.length; i++) {
          if (Number.isNaN(tops[i]) || Number.isNaN(tops[i - 1])) continue;
          max = Math.max(max, tops[i] - tops[i - 1]);
        }
        return { max, count: tops.filter((t) => !Number.isNaN(t)).length };
      });
      if (pitch.count >= 2 && pitch.max > 48) {
        problems.push(at(`sidebar pitch ${pitch.max}px > 48px`));
      }
    }

    // 10 — R3-04/R3-05 (1280 only): no clipped table headers, no overflowing actions cells
    if (viewport.name === '1280') {
      check(
        await page.evaluate(() => {
          const bad: string[] = [];
          for (const th of document.querySelectorAll<HTMLElement>('.ledger-table th')) {
            if (th.scrollWidth > th.clientWidth + 1) {
              bad.push(`clipped th "${(th.textContent ?? '').trim().slice(0, 20)}" ${th.scrollWidth}>${th.clientWidth}`);
            }
          }
          for (const td of document.querySelectorAll<HTMLElement>('.ledger-table td.ledger-table__cell-actions')) {
            if (td.scrollWidth > td.clientWidth + 1) {
              bad.push(`overflowing actions cell ${td.scrollWidth}>${td.clientWidth}`);
            }
          }
          return bad;
        }),
      );
    }

    // 11 — R3-01 (dashboard): KPI tiles ≤ 220px tall, no two direct .bento children overlap
    if (route === 'dashboard') {
      check(
        await page.evaluate(() => {
          const bad: string[] = [];
          for (const tile of document.querySelectorAll<HTMLElement>('.stat-tile')) {
            const h = Math.round(tile.getBoundingClientRect().height);
            if (h > 220) bad.push(`KPI tile ${h}px tall (> 220)`);
          }
          const kids = [...document.querySelectorAll<HTMLElement>('.bento > *')];
          for (let i = 0; i < kids.length; i++) {
            for (let j = i + 1; j < kids.length; j++) {
              const a = kids[i].getBoundingClientRect();
              const b = kids[j].getBoundingClientRect();
              const x = Math.min(a.right, b.right) - Math.max(a.left, b.left);
              const y = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
              if (x > 4 && y > 4) bad.push(`bento children overlap: #${i}×#${j}`);
            }
          }
          return bad;
        }),
      );
    }

    // 12 — R3-03 (dashboard doughnut): centre text fits inside the hole
    if (route === 'dashboard') {
      const donutFit = await page.evaluate(() => {
        const plot = document.querySelector<HTMLElement>('.chart-card__plot canvas')?.parentElement;
        const total = document.querySelector<HTMLElement>('.chart-card__total');
        if (!plot || !total) return null;
        const range = document.createRange();
        range.selectNodeContents(total);
        return { textW: Math.round(range.getBoundingClientRect().width), hole: Math.round(plot.clientWidth * 0.68) };
      });
      if (donutFit && donutFit.textW > donutFit.hole) {
        problems.push(at(`donut centre ${donutFit.textW}px > hole ${donutFit.hole}px`));
      }
    }

    // 13 — equal-height cards (GLOBAL): side-by-side visible cards in one row
    //      (.bento / .kpi-grid) share one height (±2px), and a row keeps ≥ 16px
    //      before the block that follows it
    const cardRows = await page.evaluate(() => {
      const unequal: string[] = [];
      let tightGap: string | null = null;
      for (const rowSel of ['.bento', '.kpi-grid']) {
        for (const row of Array.from(document.querySelectorAll<HTMLElement>(rowSel))) {
          const groups = new Map<number, number[]>();
          for (const kid of Array.from(row.children)) {
            const card = kid.querySelector<HTMLElement>('.chart-card, .stat-tile') ?? kid;
            const r = card.getBoundingClientRect();
            if (r.width === 0 || r.height === 0) continue;
            const key = Math.round(r.top / 6);
            groups.set(key, [...(groups.get(key) ?? []), Math.round(r.height)]);
          }
          for (const heights of groups.values()) {
            if (heights.length < 2) continue;
            const diff = Math.max(...heights) - Math.min(...heights);
            if (diff > 2) {
              unequal.push(`${rowSel} [${heights.join(',')}] Δ${diff}px`);
            }
          }
          const next = row.nextElementSibling;
          if (next) {
            const nr = next.getBoundingClientRect();
            const rr = row.getBoundingClientRect();
            if (nr.width > 0 && nr.height > 0 && nr.top >= rr.bottom - 1) {
              const gap = Math.round(nr.top - rr.bottom);
              if (gap < 16) {
                tightGap = `${rowSel}→${next.tagName.toLowerCase()} ${gap}px`;
              }
            }
          }
        }
      }
      return { unequal, tightGap };
    });
    if (cardRows.unequal.length > 0) {
      problems.push(at(`unequal card heights: ${cardRows.unequal.join(' | ')}`));
    }
    if (cardRows.tightGap) {
      problems.push(at(`row touches the next block: ${cardRows.tightGap}`));
    }

    // 14 — actions-cell text buttons (≥1280): one width, the same edges
    if (viewport.name === '1280') {
      const boxes = await page.evaluate(() =>
        Array.from(document.querySelectorAll<HTMLElement>('.ledger-table__cell-actions .p-button'))
          .filter((b) => b.querySelector('.p-button-label') && b.getBoundingClientRect().width > 0)
          .map((b) => {
            const r = b.getBoundingClientRect();
            return { w: Math.round(r.width), l: Math.round(r.left), r: Math.round(r.right) };
          }),
      );
      if (boxes.length > 1) {
        const widths = [...new Set(boxes.map((b) => b.w))];
        const edges = [...new Set(boxes.map((b) => `${b.l}-${b.r}`))];
        if (widths.length !== 1 || edges.length !== 1) {
          problems.push(at(`actions buttons differ — widths ${widths.join(', ')}, edges ${edges.join(', ')}`));
        }
      }
    }

    // 15 — one table for every screen: no mobile-card variant anywhere, and a
    //      table wider than its card must be the visible scroll area
    const tableShape = await page.evaluate(() => {
      const card = document.querySelector('.ledger-card, .ledger-table__cards');
      const table = document.querySelector<HTMLElement>('.ledger-table table.p-datatable-table');
      const visible = !!table && table.getBoundingClientRect().height > 40 && getComputedStyle(table).display !== 'none';
      const container = document.querySelector<HTMLElement>('.ledger-table .p-datatable-table-container');
      const hiddenColumns = container ? Math.max(0, container.scrollWidth - container.clientWidth) : 0;
      return { cards: !!card, tableVisible: visible, hiddenColumns };
    });
    if (tableShape.cards) {
      problems.push(at('mobile cards are rendered'));
    } else if (tableShape.hiddenColumns > 0 && !tableShape.tableVisible) {
      problems.push(at('table hidden while wider than its card'));
    }
  } finally {
    page.removeListener('console', onConsole);
    page.removeListener('pageerror', onPageError);
  }

  return { problems, notes };
}

/**
 * ONE init script for the whole run: before the app's own boot script reads
 * it, every document writes the requested theme (left in sessionStorage by
 * `auditRoute`) into the storage the app restores from (`ThemeService` /
 * index.html). Registered once per page.
 */
export function seedThemeInitScript(): void {
  const theme = sessionStorage.getItem('visual-matrix.theme');
  if (theme === 'light' || theme === 'dark') {
    localStorage.setItem('naguib-selim.theme', theme);
  }
}
