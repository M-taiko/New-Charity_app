import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { expect, test } from '@playwright/test';
import { formatChartAmount, formatChartTick, MINUS } from '../src/app/shared/lib/chart-format';
import { chartIsEmpty } from '../src/app/shared/lib/chart-series';
import {
  DARK_MAX_LUMINANCE,
  MIN_ACTION_GAP_PX,
  PINNED_MAX_WIDTH_PX,
  actionAreaViolations,
  chartCardViolations,
  contentInPageHeaderViolations,
  consoleErrorViolations,
  formOverflowNote,
  kpiGridViolations,
  measureActionAreas,
  measureChartCards,
  measureContentInPageHeader,
  measureFormOverflow,
  measureKpiGrids,
  measurePhoneTables,
  measureStraySurfaces,
  measureTableCells,
  measureTableHeaders,
  measureTheme,
  phoneTableViolations,
  rawLiteralViolations,
  relativeLuminance,
  reportedSummary,
  scrollViolations,
  seedThemeInitScript,
  straySurfaceViolations,
  tableHeaderViolations,
  themeViolations,
  type ActionAreaMeasure,
  type ChartCardMeasure,
  type KpiGrid,
  type PhoneTableMeasure,
  type TableHeaderMeasure,
  type ThemeSnapshot,
  type TileBox,
} from './gates';

/**
 * Proof that the visual-matrix gates are gates (R4-08.3: "do not mark a gate
 * OK unless it can fail"). Each one is fed the exact defect it was written
 * for and must report it - then fed a correct page and stay quiet. The
 * fixtures are deliberately ugly (inline styles, raw literals); they exist
 * only here.
 *
 * The first real dark run of the matrix is what makes this file necessary:
 * for three rounds the "dark" half could not fail, because nothing measured
 * the paint. Round 5 adds the header-action gate (R5-01), the chart gate
 * (R5-02) and the phone-table gate (R5-03).
 */

const LRM = '\u200E';
/** a person's name and a role label, in the app's language */
const PERSON = '\u0645\u062D\u0645\u062F \u0637\u0627\u0631\u0642';
const ROLE = '\u0645\u062F\u064A\u0631';
const ACTIVE = '\u0646\u0634\u0637';

// -- R4-02: the tick formatter ------------------------------------------------

test.describe('R4-02 chart tick formatter', () => {
  test('a negative tick is a real minus IN FRONT, pinned between LRM marks', () => {
    const tick = formatChartTick(-50_000);
    expect(tick).toBe(`${LRM}${MINUS}50K${LRM}`);
    // the sign precedes the first digit (what the RTL canvas used to reverse)
    expect(tick.indexOf(MINUS)).toBeLessThan(tick.search(/\d/));
  });

  test('without the LRM pins the output is the obvious fix - which the canvas test below proves insufficient', () => {
    expect(formatChartTick(-50_000).replaceAll(LRM, '')).toBe(`${MINUS}50K`);
  });

  test('positive and zero values carry no sign - never a minus on zero', () => {
    expect(formatChartTick(50_000)).toBe(`${LRM}50K${LRM}`);
    expect(formatChartTick(400)).toBe(`${LRM}400${LRM}`);
    expect(formatChartTick(1_163_753)).toBe(`${LRM}1.2M${LRM}`);
    for (const zero of [0, -0, -0.004]) {
      expect(formatChartTick(zero)).not.toContain(MINUS);
      expect(formatChartAmount(zero)).not.toContain(MINUS);
    }
  });

  test('tooltip amounts keep two decimals and a thousands separator', () => {
    expect(formatChartAmount(-1_234.5)).toBe(`${LRM}${MINUS}1,234.50${LRM}`);
    expect(formatChartAmount(70_532)).toBe(`${LRM}70,532.00${LRM}`);
  });

  test('digits are Western at every magnitude', () => {
    for (const value of [-400_000, -1, 0, 7, 999, 70_000, 1_200_000, 12_345_678]) {
      const text = formatChartTick(value) + formatChartAmount(value);
      // ASCII digits and separators only, plus the two marks defined above
      expect(text).toMatch(/^[\x20-\x7E\u200E\u2212]+$/u);
      expect(text).not.toMatch(/[\u0660-\u0669]/u); // Arabic-Indic digits
    }
  });

  test('on an RTL canvas the formatted tick paints minus-first - and the raw string does not', async ({ page }) => {
    await page.setContent('<html lang="ar" dir="rtl"><body></body></html>');
    // three paintings of the same glyphs: the formatted tick as the chart
    // paints it (the canvas inherits the page's RTL direction), the intended
    // reading order (LTR canvas, bare text), and the old behaviour (RTL
    // canvas, bare text). Equal pixels = equal visual order.
    const images = await page.evaluate(
      ({ formatted, bare }: { formatted: string; bare: string }) => {
        const paint = (text: string, direction: 'ltr' | 'inherit'): string => {
          const canvas = document.createElement('canvas');
          canvas.width = 220;
          canvas.height = 70;
          // the canvas must be IN the RTL document: a detached one inherits
          // nothing and paints LTR, which would make the control below pass
          // without ever reproducing the bug (a chart canvas is attached)
          document.body.appendChild(canvas);
          const ctx = canvas.getContext('2d')!;
          ctx.direction = direction;
          // "start" would mean the RIGHT edge on the RTL canvas - pin the
          // anchor so only the glyph order can differ
          ctx.textAlign = 'left';
          ctx.font = '30px Arial';
          ctx.fillStyle = '#111';
          ctx.fillText(text, 12, 40);
          return canvas.toDataURL();
        };
        return { subject: paint(formatted, 'inherit'), reference: paint(bare, 'ltr'), control: paint(bare, 'inherit') };
      },
      { formatted: formatChartTick(-50_000), bare: `${MINUS}50K` },
    );
    // the control proves the comparison can tell the two orders apart
    expect(images.control).not.toBe(images.reference);
    expect(images.subject).toBe(images.reference);
  });
});

// -- R4-01: the theme is painted ----------------------------------------------

test.describe('R4-01 theme gate', () => {
  const lightPage: ThemeSnapshot = { attr: 'light', bodyBg: 'rgb(244, 243, 241)', paintedBg: 'rgb(244, 243, 241)' };
  const darkPage: ThemeSnapshot = { attr: 'dark', bodyBg: 'rgb(18, 16, 14)', paintedBg: 'rgb(18, 16, 14)' };

  test('relative luminance of the two page backgrounds', () => {
    expect(relativeLuminance('rgb(244, 243, 241)')!).toBeCloseTo(0.897, 2);
    expect(relativeLuminance('rgb(18, 16, 14)')!).toBeCloseTo(0.005, 2);
    expect(relativeLuminance('rgba(0, 0, 0, 0)')).toBeNull();
    expect(relativeLuminance('not a colour')).toBeNull();
  });

  test('the old matrix: a "dark" run that rendered light fails on both counts', () => {
    const violations = themeViolations('dark', lightPage);
    expect(violations).toHaveLength(2);
    expect(violations[0]).toContain('data-theme="light"');
    expect(violations[1]).toContain('page is LIGHT');
    // and the mirror image: a light run that rendered dark
    expect(themeViolations('light', darkPage)).toContainEqual(expect.stringContaining('page is DARK'));
  });

  test('the attribute alone is not enough - the paint is what is measured', () => {
    expect(themeViolations('dark', { ...darkPage, paintedBg: lightPage.paintedBg })).toContainEqual(
      expect.stringContaining('page is LIGHT'),
    );
    expect(themeViolations('dark', { ...lightPage, attr: 'dark' })).toContainEqual(expect.stringContaining('page is LIGHT'));
    // and a correctly painted page with the wrong attribute is still wrong
    expect(themeViolations('dark', { ...darkPage, attr: 'light' })).toContainEqual(expect.stringContaining('data-theme="light"'));
    expect(themeViolations('dark', darkPage)).toEqual([]);
    expect(themeViolations('light', lightPage)).toEqual([]);
  });

  test('an unthemed page is light, so it fails a dark run instead of passing silently', () => {
    const canvas: ThemeSnapshot = { attr: 'dark', bodyBg: 'rgba(0, 0, 0, 0)', paintedBg: 'rgb(255, 255, 255)' };
    expect(themeViolations('dark', canvas)).toContainEqual(expect.stringContaining('page is LIGHT'));
    // a background nobody can read
    expect(themeViolations('dark', { ...darkPage, paintedBg: 'n/a' })).toContainEqual(expect.stringContaining('no measurable colour'));
  });

  test('in a real page the gate reads what is painted, through transparent layers', async ({ page }) => {
    await page.setContent('<html data-theme="dark"><body style="margin:0"><div style="height:100px"></div></body></html>');
    const unthemed = await page.evaluate(measureTheme);
    expect(unthemed.paintedBg).toBe('rgb(255, 255, 255)');
    expect(themeViolations('dark', unthemed)).not.toEqual([]);

    await page.setContent('<html data-theme="dark" style="background:#12100e"><body style="background:rgba(0,0,0,0)"></body></html>');
    const themed = await page.evaluate(measureTheme);
    expect(themed.paintedBg).toBe('rgb(18, 16, 14)');
    expect(themeViolations('dark', themed)).toEqual([]);
  });

  test('on the app: seeding the attribute the old way fails the gate, seeding the storage passes it', async ({ page }) => {
    // OLD: data-theme set on the document, then a navigation that discards it
    await page.goto('/app/login', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
    await page.goto('/app/login', { waitUntil: 'networkidle' });
    const oldWay = themeViolations('dark', await page.evaluate(measureTheme));
    expect(oldWay).toContainEqual(expect.stringContaining('page is LIGHT'));

    // NEW: the theme is written into the storage the app restores from.
    // (the first load only creates the origin sessionStorage lives in)
    const seeded = await page.context().newPage();
    await seeded.addInitScript(seedThemeInitScript);
    await seeded.goto('/app/login', { waitUntil: 'domcontentloaded' });
    await seeded.evaluate(() => sessionStorage.setItem('visual-matrix.theme', 'dark'));
    await seeded.goto('/app/login', { waitUntil: 'networkidle' });
    const snapshot = await seeded.evaluate(measureTheme);
    expect(snapshot.attr).toBe('dark');
    expect(relativeLuminance(snapshot.paintedBg)!).toBeLessThan(DARK_MAX_LUMINANCE);
    expect(themeViolations('dark', snapshot)).toEqual([]);
  });
});

// -- R4-03: no raw literal in a table cell ------------------------------------

test.describe('R4-03 raw literal gate', () => {
  test('a cell that prints a JS literal is reported; real text is not', () => {
    const cells = [PERSON, 'true', 'donia.a5ra2019@gmail.com', 'false', 'null', 'undefined', '1', '0', 'True', '[object Object]', 'NaN', ''];
    expect(rawLiteralViolations(cells)).toEqual([
      'table cell prints the raw literal "true"',
      'table cell prints the raw literal "false"',
      'table cell prints the raw literal "null"',
      'table cell prints the raw literal "undefined"',
      'table cell prints the raw literal "True"',
      'table cell prints the raw literal "[object Object]"',
      'table cell prints the raw literal "NaN"',
    ]);
    // the same literal in every row is one line with the count
    expect(rawLiteralViolations(Array(16).fill('true'))).toEqual(['table cell prints the raw literal "true" \u00D716']);
    expect(rawLiteralViolations([ACTIVE, '01099446903', ROLE, '\u2014'])).toEqual([]);
  });

  test('the same cells read from a real table', async ({ page }) => {
    await page.setContent(`<table><tr><td>true</td><td>${PERSON}</td><td> false </td></tr></table>`);
    expect(rawLiteralViolations(await page.evaluate(measureTableCells))).toHaveLength(2);
    await page.setContent(`<table><tr><td>${ACTIVE}</td><td>${PERSON}</td></tr></table>`);
    expect(rawLiteralViolations(await page.evaluate(measureTableCells))).toEqual([]);
  });
});

// -- R4-04: one KPI rule ------------------------------------------------------

test.describe('R4-04 KPI row gate', () => {
  const box = (width: number, top: number, hasIcon = true): TileBox => ({ width, top, hasIcon, topAccent: null });
  const grid = (index: number, ...tiles: TileBox[]): KpiGrid => ({ index, tiles });

  test('a lone tile on the last row is an orphan (the expenses row before the fix)', () => {
    const violations = kpiGridViolations([grid(0, box(232, 200), box(232, 200), box(232, 200), box(232, 200), box(232, 316))]);
    expect(violations).toEqual([expect.stringContaining('orphan tile alone on the last of 2 rows (5 tiles in 4 columns)')]);
    // ...and the fix - five in a row, or three plus two - is not
    expect(kpiGridViolations([grid(0, box(182, 200), box(182, 200), box(182, 200), box(182, 200), box(182, 200))])).toEqual([]);
    expect(kpiGridViolations([grid(0, box(315, 200), box(315, 200), box(315, 200), box(315, 331), box(315, 331))])).toEqual([]);
  });

  test('a one-column stack is a layout, not five orphans', () => {
    const stacked = grid(0, box(328, 200), box(328, 310), box(328, 420), box(328, 530), box(328, 640));
    expect(kpiGridViolations([stacked])).toEqual([]);
    expect(kpiGridViolations([grid(0, box(976, 200))])).toEqual([]);
  });

  test('tiles of different width in one grid are reported', () => {
    // HR before the fix: three 315px tiles, then two 480px ones
    const violations = kpiGridViolations([grid(1, box(315, 200), box(315, 200), box(315, 200), box(480, 316), box(480, 316))]);
    expect(violations).toEqual(['.kpi-grid #1: tiles of different width 315/480']);
  });

  test('an icon on some tiles of a row is reported; on all or on none is fine', () => {
    const mixed = grid(0, box(315, 200, true), box(315, 200, false), box(315, 200, false));
    expect(kpiGridViolations([mixed])).toEqual(['.kpi-grid #0: icon on 1 of 3 tiles \u2014 all or none']);
    expect(kpiGridViolations([grid(0, box(315, 200, false), box(315, 200, false))])).toEqual([]);
    expect(kpiGridViolations([grid(0, box(315, 200), box(315, 200))])).toEqual([]);
  });

  test('more than one top-accent colour in a grid is reported; one is allowed', () => {
    const barred = grid(0, { ...box(232, 200), topAccent: 'rgb(16, 185, 129)' }, { ...box(232, 200), topAccent: 'rgb(244, 63, 94)' });
    expect(kpiGridViolations([barred])).toEqual(['.kpi-grid #0: 2 top-accent colours rgb(16, 185, 129), rgb(244, 63, 94)']);
    const uniform = grid(0, { ...box(232, 200), topAccent: 'rgb(92, 64, 51)' }, { ...box(232, 200), topAccent: 'rgb(92, 64, 51)' });
    expect(kpiGridViolations([uniform])).toEqual([]);
  });

  test('the same rows measured from a real DOM', async ({ page }) => {
    const tile = (icon: boolean): string =>
      `<app-stat-tile style="display:block"><div class="stat-tile" style="height:96px">${icon ? '<span class="stat-tile__icon"></span>' : ''}</div></app-stat-tile>`;
    // the old expenses row: four columns, a fifth tile alone on the next row, one icon
    await page.setContent(
      `<main><div class="kpi-grid" style="display:grid;grid-template-columns:repeat(4,232px);gap:16px">${[true, false, false, false, false].map(tile).join('')}</div></main>`,
    );
    const measured = await page.evaluate(measureKpiGrids);
    expect(measured[0].tiles.map((t) => t.width)).toEqual([232, 232, 232, 232, 232]);
    expect(kpiGridViolations(measured)).toEqual([
      expect.stringContaining('orphan tile alone on the last of 2 rows'),
      expect.stringContaining('icon on 1 of 5 tiles'),
    ]);

    // the fixed row: five equal columns, every tile with its icon
    await page.setContent(
      `<main><div class="kpi-grid" style="display:grid;grid-template-columns:repeat(5,182px);gap:16px">${[true, true, true, true, true].map(tile).join('')}</div></main>`,
    );
    expect(kpiGridViolations(await page.evaluate(measureKpiGrids))).toEqual([]);
  });
});

// -- dark theme: no surface keeps a light paint -------------------------------

test.describe('dark theme stray-surface gate', () => {
  test('a light card on a dark page is reported; an all-dark page is not', async ({ page }) => {
    await page.setContent(
      `<html data-theme="dark"><body style="margin:0;background:#12100e">
        <div class="stat-tile" style="width:200px;height:80px;background:#201e1b"></div>
        <div class="stat-tile" style="width:200px;height:80px;background:#ffffff"></div>
      </body></html>`,
    );
    const stray = await page.evaluate(measureStraySurfaces, { selector: '.stat-tile', dark: true });
    expect(stray.map((s) => s.background)).toEqual(['rgb(255, 255, 255)']);
    expect(straySurfaceViolations('dark', stray)).toEqual([expect.stringContaining('light surface in dark theme: div.stat-tile paints rgb(255, 255, 255)')]);

    await page.setContent(
      '<html data-theme="dark"><body style="margin:0;background:#12100e"><div class="stat-tile" style="width:200px;height:80px;background:#201e1b"></div></body></html>',
    );
    expect(await page.evaluate(measureStraySurfaces, { selector: '.stat-tile', dark: true })).toEqual([]);
  });

  test('the inverse: a dark card on a light page', async ({ page }) => {
    await page.setContent(
      '<html data-theme="light"><body style="margin:0;background:#f4f3f1"><div class="stat-tile" style="width:200px;height:80px;background:#12100e"></div></body></html>',
    );
    const stray = await page.evaluate(measureStraySurfaces, { selector: '.stat-tile', dark: false });
    expect(straySurfaceViolations('light', stray)).toEqual([expect.stringContaining('dark surface in light theme')]);
  });
});

// -- a page opens at its top --------------------------------------------------

test.describe('page-open scroll gate', () => {
  test('a page that is not at its top after a fresh navigation is reported', () => {
    // what /expenses and /dashboard measured at 360px before the fix
    expect(scrollViolations(523)).toEqual(['the page opened scrolled down by 523px (something took focus or scrolled on load)']);
    expect(scrollViolations(1_106)).toHaveLength(1);
    expect(scrollViolations(0)).toEqual([]);
  });

  test('in a real page: a control with autofocus below the fold is what scrolls it', async ({ page }) => {
    // Chromium focuses the button and scrolls it into view. The app's guard
    // stops PrimeNG from ever writing that attribute (autofocus-default.ts);
    // without the guard this is exactly what happened on every phone page.
    await page.setViewportSize({ width: 360, height: 600 });
    await page.setContent('<div style="height:1400px"></div><button autofocus style="width:120px;height:40px">filters</button>');
    await page.waitForTimeout(300);
    const scrolled = await page.evaluate(() => Math.round(window.scrollY));
    expect(scrolled).toBeGreaterThan(0);
    expect(scrollViolations(scrolled)).toHaveLength(1);

    // the same page without the attribute stays at its top (a fresh page:
    // a document that replaced itself keeps the previous scroll position)
    const clean = await page.context().newPage();
    await clean.setViewportSize({ width: 360, height: 600 });
    await clean.setContent('<div style="height:1400px"></div><button style="width:120px;height:40px">filters</button>');
    expect(await clean.evaluate(() => Math.round(window.scrollY))).toBe(0);
  });
});

// -- console errors: a dropped chunk fetch is verified, never just ignored -----

test.describe('console-error gate', () => {
  const DROPPED = 'console: ERROR TypeError: Failed to fetch dynamically imported module: http://127.0.0.1:8000/app/chunk-QJL7WCQS.js';
  const serve = (status: number) => async (): Promise<number> => status;

  test('a chunk the server serves was a dropped connection — noted, excused', async () => {
    const result = await consoleErrorViolations([DROPPED], serve(200));
    expect(result.problems).toEqual([]);
    expect(result.notes).toEqual([expect.stringContaining('the module is served (HTTP 200): chunk-QJL7WCQS.js')]);
  });

  test('the same sentence for a chunk that is NOT served fails the gate, with its status', async () => {
    // 404: missing from the bundle; 0: the request itself failed
    for (const status of [404, 0]) {
      const result = await consoleErrorViolations([DROPPED], serve(status));
      expect(result.notes).toEqual([]);
      expect(result.problems).toEqual([expect.stringContaining(`console errors: ${DROPPED} — that module answers HTTP ${status}`)]);
    }
  });

  test('any other console or page error still fails', async () => {
    const result = await consoleErrorViolations(
      ['console: ERROR NullInjectorError', 'pageerror: TypeError: x is undefined', 'console: net::ERR_NO_BUFFER_SPACE', 'console: net::ERR_ABORTED'],
      serve(200),
    );
    expect(result.problems).toEqual(['console errors: console: ERROR NullInjectorError', 'console errors: pageerror: TypeError: x is undefined']);
  });
});

// -- R4-04: the stylesheet that picks the column count ------------------------

test.describe('R4-04 KPI grid stylesheet', () => {
  const require = createRequire(resolve(__dirname, '../package.json'));
  const sass = require('sass');
  const file = resolve(__dirname, '../src/styles/03-blocks/kpi-grid.scss');
  const source = readFileSync(file, 'utf8');

  /** what kpi-cols() answers for 1..16 tiles under a ceiling */
  const table = (ceiling: number): number[] => {
    const fn = source.slice(source.indexOf('@function'), source.indexOf('/* the containers a KPI row'));
    const probe = `@use 'sass:math';\n${fn}\n.result { @for $n from 1 through 16 { --c#{$n}: #{kpi-cols($n, ${ceiling})}; } }`;
    const css = sass.compileString(probe).css as string;
    return [...css.matchAll(/--c(\d+):\s*(\d+)/g)].sort((a, b) => Number(a[1]) - Number(b[1])).map((m) => Number(m[2]));
  };

  test('a full row (ceiling 5): five-up, 3+3, 4+3, 4+4, 5+4... and never a lone last tile', () => {
    expect(table(5)).toEqual([1, 2, 3, 4, 5, 3, 4, 4, 5, 5, 4, 4, 5, 5, 5, 4]);
  });

  test('every layout under every ceiling is inside the ceiling and orphan-free', () => {
    for (const ceiling of [2, 3, 4, 5]) {
      table(ceiling).forEach((columns, i) => {
        const tiles = i + 1;
        expect(columns, `${tiles} tiles, ceiling ${ceiling}`).toBeLessThanOrEqual(Math.min(tiles, ceiling));
        expect(columns).toBeGreaterThan(0);
        if (tiles > columns) {
          expect(tiles % columns, `${tiles} tiles in ${columns} columns leaves ${tiles % columns}`).not.toBe(1);
        }
      });
    }
  });

  test('the stylesheet really emits those rules (Sass leaves a custom property un-evaluated unless it is interpolated)', () => {
    const css = sass.compile(file).css as string;
    expect(css).toContain('@container (min-width: 964px)');
    // exactly five children at the widest tier -> five columns
    expect(css).toMatch(/\.kpi-grid:has\(> :nth-child\(5\)\):not\(:has\(> :nth-child\(6\)\)\)\s*\{\s*--kpi-cols: 5;/);
    // exactly six -> three (3 + 3, not 5 + 1)
    expect(css).toMatch(/\.kpi-grid:has\(> :nth-child\(6\)\):not\(:has\(> :nth-child\(7\)\)\)\s*\{\s*--kpi-cols: 3;/);
    // the base is a single column, so a phone stacks
    expect(css).toMatch(/\.kpi-grid\s*\{[^}]*--kpi-cols, 1/);
    // and a browser without :has() is not left with one column
    expect(css).toContain('@supports not (selector(:has(*)))');
    expect(css).toContain('repeat(auto-fit, minmax(180px, 1fr))');
  });
});

// -- R5-01: header actions and the sticky bar ---------------------------------

/** a page header action row, laid out the way the app lays it out (RTL) */
function headerPage(buttons: string, options: { gap?: number; sticky?: string } = {}): string {
  const gap = options.gap ?? MIN_ACTION_GAP_PX;
  return `<html lang="ar" dir="rtl"><head><style>
    :root { --color-primary: #5c4033; }
    body { margin: 0; width: 600px; }
    .page-header__actions { display: flex; }
    .page-actions { display: flex; align-items: center; gap: ${gap}px; }
    .sticky-actions { padding: 12px 16px; border-top: 1px solid #ccc; }
    .p-button { display: inline-block; padding: 4px 8px; border: 1px solid #5c4033; }
    .primary { background: #5c4033; color: #fff; }
    .soft { background: #ecfdf5; color: #047857; border-color: transparent; }
    .ghost { background: rgba(0, 0, 0, 0); color: #5c4033; border-color: rgba(0, 0, 0, 0); }
  </style></head><body><main>
    <app-page-header>
      <div class="page-header__actions"><div class="page-actions">${buttons}</div></div>
    </app-page-header>
    ${options.sticky ?? ''}
  </main></body></html>`;
}

/** one action button; `look` is primary (brand fill) | soft (tint) | ghost (text) */
const action = (label: string, look = 'primary'): string =>
  `<app-action-button><a class="p-button ${look}"><svg width="16" height="16"><use href="#i"></use></svg><span class="p-button-label">${label}</span></a></app-action-button>`;

test.describe('R5-01 header action gate', () => {
  test('the custody header as delivered in round 4: two primaries touching over an empty bar', async ({ page }) => {
    await page.setContent(
      headerPage(action('CREATE') + action('PRINT') + action('EDIT', 'ghost'), {
        gap: 0,
        sticky: '<div class="sticky-actions"></div>',
      }),
    );
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual([
      'header: 2 primary buttons — "CREATE", "PRINT" (one per header)',
      'header: 0px between "CREATE" and "PRINT" (at least 8px)',
      'header: 0px between "PRINT" and "EDIT" (at least 8px)',
      'empty element in the action area: div.sticky-actions 600x25',
    ]);
  });

  test('the fixed header — one primary, one secondary, More, 8px apart, no bar — is clean', async ({ page }) => {
    await page.setContent(headerPage(action('CREATE') + action('EDIT', 'ghost') + action('MORE', 'ghost')));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual([]);
  });

  test('a soft tint beside the primary is not a second primary; a split button is ONE primary whose parts may touch', async ({ page }) => {
    const split = `<app-split-action-button><a class="p-button primary"><span class="p-button-label">NEW</span></a><button class="p-button primary"><svg width="16" height="16"></svg></button></app-split-action-button>`;
    // the expenses / custodies header: soft quick action + ONE split primary
    await page.setContent(headerPage(action('QUICK', 'soft') + split));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual([]);
    // …but a standalone primary beside that split button is still two
    await page.setContent(headerPage(action('CREATE') + split));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual(['header: 2 primary buttons — "CREATE", "NEW" (one per header)']);
  });

  test('less air than 8px is reported, 8px is not', async ({ page }) => {
    await page.setContent(headerPage(action('CREATE') + action('EDIT', 'ghost'), { gap: 4 }));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual(['header: 4px between "CREATE" and "EDIT" (at least 8px)']);
    await page.setContent(headerPage(action('CREATE') + action('EDIT', 'ghost'), { gap: 8 }));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual([]);
  });

  test('an icon is content: an icon-only button is not "empty", a blank spacer is', async ({ page }) => {
    const iconOnly = '<app-action-button><button class="p-button ghost" aria-label="MORE"><svg width="16" height="16"></svg></button></app-action-button>';
    await page.setContent(headerPage(iconOnly + '<span class="spacer"></span>'));
    // the spacer collapses (no size) -> nothing to report
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual([]);
    await page.setContent(headerPage(iconOnly + '<span class="spacer" style="width:12px;height:12px"></span>'));
    expect(actionAreaViolations(await page.evaluate(measureActionAreas))).toEqual(['empty element in the action area: span.spacer 12x12']);
  });
});

// -- R5-02: a chart with nothing to plot --------------------------------------

/** series values as the API hands them over (null / undefined happen) */
const points = (...values: (number | null | undefined)[]): { value: number }[] =>
  values.map((value) => ({ value: value as number }));

test.describe('R5-02 chartIsEmpty', () => {
  test('no series at all, or every value zero: nothing to plot', () => {
    expect(chartIsEmpty()).toBe(true);
    expect(chartIsEmpty([])).toBe(true);
    expect(chartIsEmpty(null)).toBe(true);
    // the dashboard's quiet week: seven days, seven zeros
    expect(chartIsEmpty(points(0, 0, 0, 0, 0, 0, 0))).toBe(true);
    expect(chartIsEmpty(points(0), points(0, 0))).toBe(true);
  });

  test('one non-zero value anywhere makes it a chart', () => {
    expect(chartIsEmpty(points(0, 0, 39, 0, 0, 0, 0))).toBe(false);
    expect(chartIsEmpty(points(-400_000, 0, 0))).toBe(false);
    expect(chartIsEmpty(points(0.004))).toBe(false);
  });

  test('a missing or non-finite value counts as zero', () => {
    expect(chartIsEmpty(points(0, Number.NaN, 0))).toBe(true);
    expect(chartIsEmpty(points(null, undefined))).toBe(true);
  });

  test('a compare chart is empty only when BOTH of its series are', () => {
    expect(chartIsEmpty(points(0, 0, 0), points(0, 0, 0))).toBe(true);
    expect(chartIsEmpty(points(0, 0, 0), [])).toBe(true);
    // no income at all, but expenses: still a chart
    expect(chartIsEmpty(points(0, 0, 0), points(0, 215, 0))).toBe(false);
  });
});

test.describe('R5-02 chart card gate', () => {
  const CANVAS = '<div class="chart-card__plot"><canvas width="10" height="10"></canvas></div>';
  const EMPTY = '<div class="empty-state"><p class="empty-state__title">nothing to plot</p></div>';
  const LEGEND = '<ul class="chart-legend"><li><button class="chart-legend__item"><span class="chart-legend__label">direction</span></button></li></ul>';
  const card = (title: string, attrs: string, body: string, host = 'app-chart-card'): string =>
    `<html><body><${host}><div class="chart-card" ${attrs}><span class="chart-card__title">${title}</span><div class="chart-card__body">${body}</div></div></${host}></body></html>`;

  test('only cards rendered by app-chart-card are charts — detail pages reuse the look for ordinary cards', async ({ page }) => {
    // the custody detail page: a facts card and a ledger card with the same class, no chart
    await page.setContent(
      card('facts', '', '<div class="key-value-list">agent, treasury, date</div>', 'section') + card('info', '', '<p>important information</p>', 'section'),
    );
    expect(await page.evaluate(measureChartCards)).toEqual([]);
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([]);
  });

  test('the all-zero chart as delivered in round 4 painted a canvas and said nothing', async ({ page }) => {
    await page.setContent(card('7 days', '', CANVAS));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual(['chart "7 days" does not say what it shows (no data-view)']);
  });

  test('an empty chart that still paints a canvas, or shows no empty state, is reported', async ({ page }) => {
    await page.setContent(card('7 days', 'data-empty="true" data-view="chart"', CANVAS));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([
      'chart "7 days" has nothing to plot but still renders a canvas',
      'chart "7 days" has nothing to plot and shows no empty state',
    ]);
    await page.setContent(card('7 days', 'data-empty="true" data-view="chart"', EMPTY));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([]);
  });

  test('with data: no canvas, no table, no legend to turn hidden series back on — each is reported', async ({ page }) => {
    await page.setContent(card('in/out', 'data-empty="false" data-view="chart"', EMPTY));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual(['chart "in/out" renders neither a canvas nor a table']);
    await page.setContent(card('in/out', 'data-empty="false" data-view="table"', CANVAS));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual(['chart "in/out" is in table view but renders no table']);
    await page.setContent(card('in/out', 'data-empty="false" data-view="hidden"', '<p class="u-muted">all hidden</p>'));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual(['chart "in/out" hides every series but offers no legend to turn them back on']);
    await page.setContent(card('in/out', 'data-empty="false" data-view="hidden"', LEGEND));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([]);
  });

  test('N-09: a card that is loading says so and shows a skeleton — never an empty state, never a canvas', async ({ page }) => {
    const SKELETON = '<div class="chart-card__loading"></div>';
    await page.setContent(card('donut', 'data-empty="false" data-view="loading" data-loading="true"', SKELETON));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([]);
    // what the card did while its data was on its way before N-09: the empty
    // state, in a box that grew when the chart replaced it
    await page.setContent(card('donut', 'data-empty="true" data-view="chart" data-loading="true"', EMPTY));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([
      'chart "donut" is loading but does not say so (data-view is chart)',
      'chart "donut" is loading and shows no skeleton',
      'chart "donut" is loading and already shows the empty state',
    ]);
    // a canvas drawn into the loading state: no skeleton either — both are said
    await page.setContent(card('donut', 'data-empty="false" data-view="loading" data-loading="true"', CANVAS));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual([
      'chart "donut" is loading and shows no skeleton',
      'chart "donut" is loading and already renders a canvas',
    ]);
    // and the flag must be honest the other way round
    await page.setContent(card('donut', 'data-empty="false" data-view="loading"', SKELETON));
    expect(chartCardViolations(await page.evaluate(measureChartCards))).toEqual(['chart "donut" says it is loading (data-view) but is not']);
  });
});

// -- R5-03: a table that scrolls sideways on a phone -------------------------

/** one table as the gate measures it; `over` replaces what differs */
const table = (over: Partial<PhoneTableMeasure> = {}): PhoneTableMeasure => {
  const merged: PhoneTableMeasure = {
    scrolls: false,
    declared: ['agent', 'status', 'amount'],
    applied: true,
    rendered: ['agent', 'status', 'amount'],
    pinned: false,
    bodyPinned: false,
    firstWidth: 107,
    fades: 0,
    hintVisible: false,
    dir: 'rtl',
    fadePaint: [],
    divider: 'none',
    lastRowLine: true,
    ...over,
  };
  // unless a test says otherwise, the body cells are pinned like their header
  merged.bodyPinned = over.bodyPinned ?? merged.pinned;
  return merged;
};

/** the app is RTL: the start fade touches the pinned column on its right, the end fade sits on the left edge */
const PAINTED_RIGHT: Pick<PhoneTableMeasure, 'pinned' | 'fades' | 'fadePaint' | 'divider' | 'hintVisible'> = {
  pinned: true,
  fades: 2,
  fadePaint: [
    { edge: 'start', opaque: 'right' },
    { edge: 'end', opaque: 'left' },
  ],
  divider: 'left',
  hintVisible: true,
};

/** …and this is how round 5 shipped it: both fades mirrored, the divider on the far side */
const PAINTED_MIRRORED: Pick<PhoneTableMeasure, 'fadePaint' | 'divider'> = {
  fadePaint: [
    { edge: 'start', opaque: 'left' },
    { edge: 'end', opaque: 'right' },
  ],
  divider: 'right',
};

/** /custodies on a phone: key columns declared and applied, the table scrolls */
const CUSTODIES: Partial<PhoneTableMeasure> = {
  scrolls: true,
  declared: ['agent', 'status', 'amount'],
  applied: true,
  rendered: ['agent', 'status', 'amount', 'transferred_in', 'spent', 'actions'],
};

test.describe('R5-03 phone table gate', () => {
  test('on a phone a table that scrolls must pin its first column, fade its edge and say so', () => {
    expect(phoneTableViolations([table(CUSTODIES)], true)).toEqual([
      'table #0 [agent, status, amount…] scrolls but its first column is not pinned',
      'table #0 [agent, status, amount…] scrolls but shows no edge fade',
      'table #0 [agent, status, amount…] scrolls but shows no hint',
    ]);
    expect(phoneTableViolations([table({ ...CUSTODIES, pinned: true, divider: 'left', fades: 1, hintVisible: true })], true)).toEqual([]);
    // a table that fits says nothing
    expect(phoneTableViolations([table({ ...CUSTODIES, scrolls: false })], true)).toEqual([]);
  });

  test('on a phone the declared key columns come first — the round-4 order buried the status seventh', () => {
    const round4 = table({
      ...CUSTODIES,
      pinned: true,
      divider: 'left',
      fades: 1,
      hintVisible: true,
      applied: false,
      rendered: ['agent', 'amount', 'transferred_in', 'transferred_out', 'spent', 'remaining', 'spent_percent', 'status', 'actions'],
    });
    expect(phoneTableViolations([round4], true)).toEqual([
      'table #0 [agent, amount, transferred_in…] starts with "agent, amount, transferred_in" on a phone — declared "agent, status, amount"',
    ]);
    // a declared column that the table does not have is simply not a lead column
    const phantom = { pinned: true, divider: 'left' as const, fades: 1, hintVisible: true };
    expect(phoneTableViolations([table({ ...CUSTODIES, ...phantom, declared: ['agent', 'status', 'amount', 'ghost'], rendered: ['agent', 'status', 'amount'] })], true)).toEqual([]);
  });

  test('on a phone: a pinned column wider than 160px, and a hint on a table that fits, are reported', () => {
    const wide = table({ ...CUSTODIES, pinned: true, divider: 'left', firstWidth: PINNED_MAX_WIDTH_PX + 4, fades: 1, hintVisible: true });
    expect(phoneTableViolations([wide], true)).toEqual([`table #0 [agent, status, amount…] pinned column is ${PINNED_MAX_WIDTH_PX + 4}px wide (at most ${PINNED_MAX_WIDTH_PX})`]);
    expect(phoneTableViolations([table({ ...CUSTODIES, scrolls: false, hintVisible: true })], true)).toEqual([
      'table #0 [agent, status, amount…] fits its card but shows the scroll hint',
    ]);
  });

  test('the fade and the divider are judged by where they PAINT — as round 5 shipped them, they were mirrored', () => {
    // the elements all exist and the structural half of the gate is green…
    const shipped = table({ ...CUSTODIES, ...PAINTED_RIGHT, ...PAINTED_MIRRORED });
    expect(shipped.pinned).toBe(true);
    expect(shipped.fades).toBe(2);
    // …but the fade erased the row lines 36px inside the table and left the cut-off content untouched
    expect(phoneTableViolations([shipped], true)).toEqual([
      'table #0 [agent, status, amount…] start fade paints its opaque end on the left — it must be against the pinned column (right)',
      'table #0 [agent, status, amount…] end fade paints its opaque end on the right — it must be on the card edge (left)',
      "table #0 [agent, status, amount…] pinned column's divider is on its right edge — it belongs on the exposed left edge",
    ]);
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT })], true)).toEqual([]);
    // only one edge has columns behind it: only that fade is judged
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, fades: 1, fadePaint: [{ edge: 'end', opaque: 'left' }] })], true)).toEqual([]);
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, fades: 1, fadePaint: [{ edge: 'end', opaque: 'none' }] })], true)).toEqual([
      'table #0 [agent, status, amount…] end fade paints its opaque end nowhere — it must be on the card edge (left)',
    ]);
    // a table that fits has no fade and no divider to judge
    expect(phoneTableViolations([table({ ...CUSTODIES, scrolls: false, ...PAINTED_MIRRORED })], true)).toEqual([]);
    // the same paint is wrong the other way round in an LTR page
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, dir: 'ltr' })], true)).toEqual([
      'table #0 [agent, status, amount…] start fade paints its opaque end on the right — it must be against the pinned column (left)',
      'table #0 [agent, status, amount…] end fade paints its opaque end on the left — it must be on the card edge (right)',
      "table #0 [agent, status, amount…] pinned column's divider is on its left edge — it belongs on the exposed right edge",
    ]);
  });

  test('every table says which columns lead on a phone — round 5 left 18 of 49 unsaid', () => {
    // an undeclared table is reported whether it scrolls or happens to fit
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, declared: null, applied: false })], true)).toEqual(['table #0 [agent, status, amount…] does not say which columns lead on a phone']);
    expect(phoneTableViolations([table({ declared: null, applied: false })], true)).toEqual(['table #0 [agent, status, amount] does not say which columns lead on a phone']);
    // the round-4 order plus no declaration reads as "not said", not as a wrong order
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, declared: null, applied: false, rendered: ['agent', 'amount', 'transferred_in', 'status', 'actions'] })], true)).toEqual([
      'table #0 [agent, amount, transferred_in…] does not say which columns lead on a phone',
    ]);
    // on a wider screen a declaration is neither required nor applied
    expect(phoneTableViolations([table({ ...CUSTODIES, declared: null, applied: false, scrolls: true })], false)).toEqual([]);
  });

  test('on a wider screen none of it may appear: desktop tables stay as verified', () => {
    const leaked = table({ ...CUSTODIES, pinned: true, fades: 1, hintVisible: true });
    expect(phoneTableViolations([leaked], false)).toEqual([
      'table #0 [agent, status, amount…] applied its phone column order on a wider screen',
      'table #0 [agent, status, amount…] shows an edge fade on a wider screen',
      'table #0 [agent, status, amount…] shows the phone hint on a wider screen',
      'table #0 [agent, status, amount…] pins its first column on a wider screen',
    ]);
    // a wide table does scroll on a desktop — and then only scrolls
    expect(phoneTableViolations([table({ ...CUSTODIES, applied: false, declared: null, scrolls: true, rendered: ['agent', 'total_received', 'current_balance'] })], false)).toEqual([]);
  });

  test('the markers are read back from the DOM the table really renders', async ({ page }) => {
    await page.setContent(`<html lang="ar" dir="rtl"><body>
      <div class="ledger-table" data-scroll="true" data-scroll-start="false" data-scroll-end="true"
           data-columns="agent,status,amount,actions" data-phone-columns="agent,status,amount" data-phone-first="true">
        <p class="ledger-table__scroll-hint" style="display:inline-flex">more</p>
        <div class="p-datatable-table-container" style="width:200px;overflow-x:auto">
          <table class="p-datatable-table" style="width:700px"><thead><tr>
            <th style="position:sticky;inset-inline-start:0;width:107px;box-shadow:inset 1px 0 0 #e2dcd4">agent</th><th>status</th><th>amount</th>
          </tr></thead></table>
        </div>
        <span class="ledger-table__fade ledger-table__fade--end" style="opacity:1;background:linear-gradient(to right,#fff,rgba(0,0,0,0))"></span>
        <span class="ledger-table__fade ledger-table__fade--start" style="opacity:0;background:linear-gradient(to left,#fff,rgba(0,0,0,0))"></span>
      </div>
      <div class="ledger-table" data-scroll="false" data-columns="name,email" style="display:none"></div>
    </body></html>`);
    const measured = await page.evaluate(measurePhoneTables);
    expect(measured[0]).toEqual({
      scrolls: true,
      declared: ['agent', 'status', 'amount'],
      applied: true,
      rendered: ['agent', 'status', 'amount', 'actions'],
      pinned: true,
      bodyPinned: null,
      firstWidth: measured[0].firstWidth,
      fades: 1,
      hintVisible: true,
      dir: 'rtl',
      fadePaint: [{ edge: 'end', opaque: 'left' }],
      divider: 'left',
      lastRowLine: null,
    });
    // (the width is whatever the box model makes of 107px + padding + border)
    expect(measured[0].firstWidth).toBeGreaterThan(100);
    expect(measured[1].scrolls).toBe(false);
    expect(phoneTableViolations([measured[0]], true)).toEqual([]);
    expect(phoneTableViolations([measured[0]], false)).toHaveLength(4);
  });

  /**
   * A selectable table (N-04) as its rows are built: the selection cell comes
   * first and is hidden on a phone; the data cells follow. `pinning` is the
   * `inset-inline-start` of the first DATA header cell and the `position` of
   * the first DATA body cell — what decides whether they stay put.
   */
  const selectableTable = (headerInset: string, bodyPosition: string): string => `<html lang="ar" dir="rtl"><body>
    <div class="ledger-table" data-scroll="true" data-selectable="true"
         data-columns="user,amount,actions" data-phone-columns="user,amount" data-phone-first="true">
      <div class="p-datatable-table-container" style="width:200px;overflow-x:auto">
        <table class="p-datatable-table" style="width:222px;table-layout:fixed">
          <thead><tr>
            <th class="ledger-table__cell-select" style="display:none;position:sticky;inset-inline-start:0;width:44px">select</th>
            <th data-field="user" style="position:sticky;inset-inline-start:${headerInset};width:111px">user</th>
            <th data-field="amount" style="width:111px">amount</th>
          </tr></thead>
          <tbody><tr>
            <td class="ledger-table__cell-select" style="display:none"><input type="checkbox"></td>
            <td style="position:${bodyPosition};inset-inline-start:0;width:111px">a</td>
            <td style="width:111px">b</td>
          </tr></tbody>
        </table>
      </div>
    </div>
  </body></html>`;

  test('a hidden cell is not a column — the pinning must be on the first cell that renders', async ({ page }) => {
    // (the fixture has no fade, hint or divider: only what this test is about)
    const aboutPinning = (violations: string[]): string[] => violations.filter((line) => /not pinned/.test(line));
    /** the width of the rendered data column, read from the element itself */
    const columnWidth = async (): Promise<number> => Math.round((await page.locator('th[data-field="user"]').boundingBox())?.width ?? 0);
    // The expenses list on a phone as round 6 first shipped it: the hidden selection cell
    // carried the pinning (the stylesheet said `th:first-child`, which a hidden cell still
    // is), and the first data column scrolled 250px away. This gate read that same hidden
    // cell and was green: `sticky`, inset 0, and a width of 0 that no limit was set against.
    await page.setContent(selectableTable('auto', 'static'));
    const [shipped] = await page.evaluate(measurePhoneTables);
    expect(shipped.pinned).toBe(false);
    expect(shipped.bodyPinned).toBe(false);
    // the width is the rendered column's own, not the hidden cell's 0
    expect(await columnWidth()).toBeGreaterThan(100);
    expect(shipped.firstWidth).toBe(await columnWidth());
    expect(aboutPinning(phoneTableViolations([shipped], true))).toEqual(['table #0 [user, amount, actions] scrolls but its first column is not pinned']);
    // fixed: the first data cell is the pinned one, in the header and in the row
    await page.setContent(selectableTable('0', 'sticky'));
    const [fixed] = await page.evaluate(measurePhoneTables);
    expect(fixed.pinned).toBe(true);
    expect(fixed.bodyPinned).toBe(true);
    expect(fixed.firstWidth).toBe(await columnWidth());
    expect(aboutPinning(phoneTableViolations([fixed], true))).toEqual([]);
    // the fix is in the stylesheet, not in the table: a table that is not selectable is unchanged
    await page.setContent(selectableTable('0', 'sticky').replace(' data-selectable="true"', ''));
    expect(aboutPinning(phoneTableViolations(await page.evaluate(measurePhoneTables), true))).toEqual([]);
  });

  test('a pinned header alone is not a pinned column', () => {
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, bodyPinned: false })], true)).toEqual([
      'table #0 [agent, status, amount…] scrolls but the cells of its first column are not pinned (the header alone is)',
    ]);
    // an unpinned column is said once
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, pinned: false, divider: 'none' })], true)).toEqual([
      'table #0 [agent, status, amount…] scrolls but its first column is not pinned',
    ]);
    // no body row: nothing to say
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, bodyPinned: null })], true)).toEqual([]);
  });

  test('round 6 — in every header the name is on one line with its arrow beside it, and nothing is clipped', () => {
    const header = (over: Partial<TableHeaderMeasure> = {}): TableHeaderMeasure => ({ field: 'amount', lines: 1, arrow: 'beside', clipped: false, ...over });
    expect(tableHeaderViolations([header()])).toEqual([]);
    // as it was on 26 columns: the arrow under a name that fitted on one line
    expect(tableHeaderViolations([header({ arrow: 'below' })])).toEqual([
      'the sort arrow of the column "amount" sits under its name — it belongs beside it, on one line',
    ]);
    // as it was on 22: the name broken to fit a narrow column (four lines, on one report)
    expect(tableHeaderViolations([header({ field: 'net_transfers', lines: 4, arrow: 'below' })])).toEqual([
      'the sort arrow of the column "net_transfers" sits under its name — it belongs beside it, on one line',
      'the name of the column "net_transfers" runs on 4 lines — a column is as wide as its header',
    ]);
    expect(tableHeaderViolations([header({ clipped: true, arrow: 'none' })])).toEqual(['the header of the column "amount" is clipped']);
    // a cell without a name (the selection cell) has nothing to say
    expect(tableHeaderViolations([header({ field: '', lines: null, arrow: 'none' })])).toEqual([]);
  });

  test('round 6 — the header is measured from the DOM: two inline boxes break, one unbreakable row does not', async ({ page }) => {
    const table = (layout: 'fixed' | 'auto', label: string, fixedWidth = 70): string => `<html lang="ar" dir="rtl"><body><div class="ledger-table"><table class="p-datatable-table" style="width:220px;table-layout:${layout}"><thead><tr>
      <th data-field="custody" style="padding:4px;line-height:13px;white-space:normal;overflow-wrap:anywhere;overflow:hidden;${layout === 'fixed' ? `width:${fixedWidth}px` : ''}">${label}</th>
      <th data-field="amount" style="padding:4px">المبلغ</th>
    </tr></thead></table></div></body></html>`;
    const arrow = '<p-sorticon style="display:inline-flex;width:12px;height:12px"></p-sorticon>';
    // as it was: the name and the arrow two inline boxes. A name too long for its column breaks inside
    // its box (22 columns ran on two to four lines) and the arrow drops to a line of its own under it…
    await page.setContent(table('fixed', `<span class="ledger-table__header-text" style="display:inline-flex">التحويلات الواردة (ج.م)</span>${arrow}`));
    const [shipped] = await page.evaluate(measureTableHeaders);
    expect(shipped).toMatchObject({ field: 'custody', arrow: 'below' });
    expect(shipped.lines, 'the name broken to fit the column').toBeGreaterThan(1);
    expect(tableHeaderViolations([shipped])).toEqual([
      'the sort arrow of the column "custody" sits under its name — it belongs beside it, on one line',
      `the name of the column "custody" runs on ${shipped.lines} lines — a column is as wide as its header`,
    ]);
    // …and a name that fits its column exactly still loses its arrow to the next line (26 columns)
    await page.setContent(table('fixed', `<span class="ledger-table__header-text" style="display:inline-flex">مصروفات</span>${arrow}`, 60));
    const [shortName] = await page.evaluate(measureTableHeaders);
    expect(shortName).toMatchObject({ field: 'custody', lines: 1, arrow: 'below' });
    expect(tableHeaderViolations([shortName])).toEqual(['the sort arrow of the column "custody" sits under its name — it belongs beside it, on one line']);
    // now: one unbreakable row, and an automatic layout — the column grows to hold it
    await page.setContent(table('auto', `<span class="ledger-table__header-label" style="display:inline-flex;flex-wrap:nowrap;gap:4px;align-items:center;white-space:nowrap"><span class="ledger-table__header-text" style="display:inline-flex;white-space:nowrap">التحويلات الواردة (ج.م)</span>${arrow}</span>`));
    const [fixed] = await page.evaluate(measureTableHeaders);
    expect(fixed).toMatchObject({ field: 'custody', lines: 1, arrow: 'beside', clipped: false });
    expect(tableHeaderViolations(await page.evaluate(measureTableHeaders))).toEqual([]);
    // the same row in a column that is forced narrow (a fixed layout and a width): the column cannot
    // grow, the cell clips — and says so
    await page.setContent(table('fixed', `<span class="ledger-table__header-label" style="display:inline-flex;flex-wrap:nowrap;white-space:nowrap"><span class="ledger-table__header-text" style="display:inline-flex;white-space:nowrap">عهد الطلبات</span>${arrow}</span>`, 50));
    const clipped = await page.evaluate(measureTableHeaders);
    expect(clipped[0]).toMatchObject({ field: 'custody', clipped: true });
    expect(tableHeaderViolations(clipped)).toEqual(['the header of the column "custody" is clipped']);
  });

  test('round 6 — no page block inside the page header: the blank pages round 5 shipped', async ({ page }) => {
    // as three pages were: the header never closed, so the tab bar and the table became its content —
    // in a flex row, where the table got 0px and the page looked empty (and <main> kept its text)
    const unclosed = `<html lang="ar" dir="rtl"><body><app-page-header><header class="page-header"><div class="page-header__meta" style="display:flex">
      <app-section-nav><nav class="section-nav"></nav></app-section-nav>
      <app-async-page><app-data-table><div class="ledger-table"></div></app-data-table></app-async-page>
    </div></header></app-page-header></body></html>`;
    await page.setContent(unclosed);
    expect(await page.evaluate(measureContentInPageHeader)).toEqual(['app-section-nav', 'app-async-page', 'app-data-table']);
    expect(contentInPageHeaderViolations(await page.evaluate(measureContentInPageHeader))).toEqual([
      '<app-section-nav> is inside the page header — the page header is never closed (it is closed, or ends with "/>")',
      '<app-async-page> is inside the page header — the page header is never closed (it is closed, or ends with "/>")',
      '<app-data-table> is inside the page header — the page header is never closed (it is closed, or ends with "/>")',
    ]);
    // as it should be: the header closed, the page after it
    await page.setContent(`<html lang="ar" dir="rtl"><body><app-page-header><header class="page-header"><div class="page-header__actions"><button>export</button></div></header></app-page-header>
      <app-section-nav><nav class="section-nav"></nav></app-section-nav>
      <app-async-page><app-data-table><div class="ledger-table"></div></app-data-table></app-async-page></body></html>`);
    expect(await page.evaluate(measureContentInPageHeader)).toEqual([]);
    expect(contentInPageHeaderViolations(await page.evaluate(measureContentInPageHeader))).toEqual([]);
  });

  test('the paint is read from real gradients and shadows — the round-5 rules, verbatim, are reported', async ({ page }) => {
    const sheet = (end: string, start: string, divider: string): string => `<html lang="ar" dir="rtl"><head><style>
      .ledger-table__fade--end { inset-inline-end: 0; background: ${end}; }
      .ledger-table__fade--start { inset-inline-start: 107px; background: ${start}; }
      .ledger-table th:first-child { box-shadow: ${divider}; }
    </style></head><body>
      <div class="ledger-table" style="position:relative;width:326px" data-columns="agent,status,amount" data-phone-columns="agent,status,amount" data-phone-first="true" data-scroll="true">
        <p class="ledger-table__scroll-hint">more</p>
        <div class="p-datatable-table-container" style="width:200px;overflow-x:auto">
          <table class="p-datatable-table" style="width:700px"><thead><tr>
            <th style="position:sticky;inset-inline-start:0;width:107px">agent</th><th>status</th><th>amount</th>
          </tr></thead></table>
        </div>
        <span class="ledger-table__fade ledger-table__fade--end"></span>
        <span class="ledger-table__fade ledger-table__fade--start"></span>
      </div></body></html>`;
    const surface = 'rgb(32, 30, 27)';
    const clear = 'rgba(0, 0, 0, 0)';
    // exactly what round 5 shipped
    await page.setContent(sheet(`linear-gradient(to left, ${surface}, ${clear})`, `linear-gradient(to right, ${surface}, ${clear})`, 'inset -1px 0 0 rgb(58, 56, 53)'));
    const shipped = await page.evaluate(measurePhoneTables);
    expect(shipped[0].fadePaint).toEqual([{ edge: 'end', opaque: 'right' }, { edge: 'start', opaque: 'left' }]);
    expect(shipped[0].divider).toBe('right');
    expect(shipped[0]).toMatchObject({ pinned: true, fades: 2, hintVisible: true, dir: 'rtl' });
    // (in DOM order: the end fade is appended first)
    expect(phoneTableViolations(shipped, true)).toEqual([
      'table #0 [agent, status, amount] end fade paints its opaque end on the right — it must be on the card edge (left)',
      'table #0 [agent, status, amount] start fade paints its opaque end on the left — it must be against the pinned column (right)',
      "table #0 [agent, status, amount] pinned column's divider is on its right edge — it belongs on the exposed left edge",
    ]);
    // the fix: each fade opaque where it sits, the divider on the exposed edge
    await page.setContent(sheet(`linear-gradient(to right, ${surface}, ${clear})`, `linear-gradient(to left, ${surface}, ${clear})`, 'inset 1px 0 0 rgb(58, 56, 53)'));
    const fixed = await page.evaluate(measurePhoneTables);
    expect(fixed[0].fadePaint).toEqual([{ edge: 'end', opaque: 'left' }, { edge: 'start', opaque: 'right' }]);
    expect(fixed[0].divider).toBe('left');
    expect(phoneTableViolations(fixed, true)).toEqual([]);
    // the same paint written as angles, and with the opaque colour given last
    await page.setContent(sheet(`linear-gradient(90deg, ${surface}, ${clear})`, `linear-gradient(90deg, ${clear}, ${surface})`, 'inset 1px 0 0 rgb(58, 56, 53)'));
    expect((await page.evaluate(measurePhoneTables))[0].fadePaint).toEqual([{ edge: 'end', opaque: 'left' }, { edge: 'start', opaque: 'right' }]);
    // a fade that is opaque at both ends, or a shadow with no offset, is "none" — and reported
    await page.setContent(sheet(`linear-gradient(to right, ${surface}, ${surface})`, `linear-gradient(to left, ${surface}, ${clear})`, 'inset 0 0 0 rgb(58, 56, 53)'));
    const odd = (await page.evaluate(measurePhoneTables))[0];
    expect(odd.fadePaint[0].opaque).toBe('none');
    expect(odd.divider).toBe('none');
  });

  test('a scrolling table keeps the line under its last row — round 5 deleted it above a totals row', async ({ page }) => {
    // the rule as round 5 shipped it: the last body row's line made transparent whenever a footer exists
    const withRows = (lastBottom: string): string => `<html lang="ar" dir="rtl"><head><style>
      tr td { border-block-end: 1px solid rgb(226, 220, 212); padding: 8px; }
      tr.last td { border-block-end-color: ${lastBottom}; }
      th:first-child, td:first-child { width: 90px; max-width: 90px; }
      /* the first column is pinned, in the header and in the rows (a fixture, not the rule under test) */
      th:first-child, tbody td:first-child { position: sticky; inset-inline-start: 0; }
    </style></head><body>
      <div class="ledger-table" style="position:relative;width:326px" data-columns="treasury,in,out" data-phone-columns="treasury" data-phone-first="true" data-scroll="true">
        <div class="p-datatable-table-container" style="width:200px;overflow-x:auto">
          <table class="p-datatable-table" style="width:700px;border-collapse:separate;border-spacing:0">
            <thead><tr><th>treasury</th><th>in</th><th>out</th></tr></thead>
            <tbody><tr><td>main</td><td>1</td><td>2</td></tr><tr class="last"><td>cash</td><td>3</td><td>4</td></tr></tbody>
            <tfoot><tr><th>total</th><td>4</td><td>6</td></tr></tfoot>
          </table>
        </div></body></html>`;
    // the fade, the hint and the pinned column are in order here; only the line is in question
    const complete = (measured: PhoneTableMeasure): PhoneTableMeasure => ({ ...measured, pinned: true, divider: 'left', fades: 1, hintVisible: true });
    await page.setContent(withRows('transparent'));
    const shipped = await page.evaluate(measurePhoneTables);
    expect(shipped[0].lastRowLine).toBe(false);
    expect(phoneTableViolations([complete(shipped[0])], true)).toEqual(['table #0 [treasury, in, out] loses the line under its last row']);
    // every ordinary row: the line is there
    await page.setContent(withRows('rgb(226, 220, 212)'));
    const fixed = await page.evaluate(measurePhoneTables);
    expect(fixed[0].lastRowLine).toBe(true);
    expect(phoneTableViolations([complete(fixed[0])], true)).toEqual([]);
    // a table with nothing in its body has no line to lose
    await page.setContent(withRows('transparent').replace(/<tbody>[\s\S]*<\/tbody>/, ''));
    expect((await page.evaluate(measurePhoneTables))[0].lastRowLine).toBe(null);
    // and only a table that scrolls is judged
    expect(phoneTableViolations([table({ ...CUSTODIES, scrolls: false, lastRowLine: false })], true)).toEqual([]);
    expect(phoneTableViolations([table({ ...CUSTODIES, ...PAINTED_RIGHT, lastRowLine: false })], true)).toEqual(['table #0 [agent, status, amount…] loses the line under its last row']);
  });
});

// -- reported, not fixed: a form field wider than its grid --------------------

test.describe('a form field wider than its grid (round 5: found, reported, not fixed)', () => {
  test('is counted and said on every run, at phone width only', async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 700 });
    // the grid as the app ships it: a track is never narrower than 320px, and the card on a 360px phone gives it 286px
    const form = (columns: string): string => `<html lang="ar" dir="rtl"><head><style>
      body { margin: 0; } main { padding: 16px; }
      .form-section { box-sizing: border-box; padding: 20px; border: 1px solid; border-radius: 12px; }
      .form-section__grid { display: grid; grid-template-columns: ${columns}; gap: 16px; }
      .form-section__grid > * { min-width: 0; }
    </style></head><body><main><section class="form-section"><div class="form-section__grid">
      <div class="field"><label>title</label><input></div><div class="field"><label>amount</label><input></div>
    </div></section></main></body></html>`;
    await page.setContent(form('repeat(auto-fit, minmax(320px, 1fr))'));
    const shipped = await page.evaluate(measureFormOverflow);
    expect(shipped).toMatchObject({ fields: 2, wide: 2 });
    expect(shipped.worst).toBe(34);
    expect(formOverflowNote(shipped)).toBe(`form fields wider than their grid: 2 of 2, by up to 34px (reported in round 5, not fixed)`);
    // the fix, for the record: a track may shrink with its card
    await page.setContent(form('repeat(auto-fit, minmax(min(320px, 100%), 1fr))'));
    const fixed = await page.evaluate(measureFormOverflow);
    expect(fixed).toMatchObject({ fields: 2, wide: 0, worst: 0 });
    expect(formOverflowNote(fixed)).toBeNull();
    // and on a wider screen the shipped grid has nothing to report: the tracks fit
    await page.setViewportSize({ width: 1280, height: 700 });
    await page.setContent(form('repeat(auto-fit, minmax(320px, 1fr))'));
    expect(formOverflowNote(await page.evaluate(measureFormOverflow))).toBeNull();
    // a page without a form has nothing to say
    await page.setContent('<html><body><main><p>no form here</p></main></body></html>');
    expect(formOverflowNote(await page.evaluate(measureFormOverflow))).toBeNull();
  });

  test('the matrix says what it reported in one line: visits, routes, and the light totals — never doubled by the dark run', () => {
    const note = (route: string, where: string, wide: number, fields: number, worst: number): string =>
      `${route} [${where}] form fields wider than their grid: ${wide} of ${fields}, by up to ${worst}px (reported in round 5, not fixed)`;
    const notes = [note('expenses~new', 'light/360', 10, 10, 34), note('expenses~new', 'dark/360', 10, 10, 34), note('settings', 'light/360', 2, 3, 20), note('settings', 'dark/360', 2, 3, 20)];
    expect(reportedSummary(notes)).toBe(
      '4 visits on 2 routes (dark/360, light/360): 12 of 13 fields in light, by up to 34px — form fields wider than their grid: 2 of 3, by up to 20px (reported in round 5, not fixed)',
    );
    // a note of another shape is still counted, just not summed
    expect(reportedSummary(['a~b [light/1280] something else (reported in round 5, not fixed)'])).toBe(
      '1 visit on 1 route (light/1280) — something else (reported in round 5, not fixed)',
    );
  });
});
