import { expect, test, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';

/**
 * Button contrast at rest AND under the pointer (R5-01).
 *
 * A screenshot never shows a hover state, which is how this survived five
 * rounds of picture review: in dark mode the general button rule painted the
 * cream primary's ink onto every text button the moment the pointer arrived —
 * "تعديل" and every "⋯" menu trigger went from 17:1 to 1.17:1.
 *
 * For each theme this visits a set of pages, finds every KIND of PrimeNG
 * button (text, outlined, soft, filled, split), reads its label colour and its
 * real painted background (alpha tints composited over what is behind them)
 * at rest and on hover, and fails below 4.5:1 (WCAG AA, normal text).
 *
 * Nothing is clicked: only the pointer moves.
 *
 * Needs the PHP server + the packed SPA, like the matrix.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';
const AA = 4.5;

/** pages that together show every kind of button the app renders */
const ROUTES = [
  'custodies/1',
  'custodies',
  'expenses',
  'expenses/new',
  'treasury',
  'tasks',
  'hr/employees',
  'reports/agents-balance',
  'notifications',
  'users',
];

/**
 * Contrast failures that exist today, reported and deliberately not fixed in
 * round 5: the fix changes the look of pages the review marked "do not touch".
 * Every entry must STILL be failing — once it is fixed, the entry is stale
 * and this spec asks for it to be removed.
 */
const KNOWN_FAILURES: Record<string, string> = {
  'light|p-button-success':
    'White label on the filled success green (#10B981): 2.54:1 at rest. The approve / accept / receive buttons. Reported in round 5 (CHANGELOG "found on the way"); the fix is a dark label on the green, which changes verified pages and waits for the owner.',
};

interface Reading {
  kind: string;
  text: string;
  rest: number;
  hover: number;
}

type Rgb = { r: number; g: number; b: number; a: number };

/** WCAG relative luminance of an opaque colour */
function luminance({ r, g, b }: Rgb): number {
  const [rr, gg, bb] = [r, g, b].map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * rr + 0.7152 * gg + 0.0722 * bb;
}

/** WCAG contrast of two opaque colours, always ≥ 1 */
export function contrast(a: Rgb, b: Rgb): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** `rgb(1, 2, 3)` / `rgba(1, 2, 3, 0.16)` → channels; unparseable → black */
export function parseColor(css: string): Rgb {
  const m = (css.match(/[\d.]+/g) ?? []).map(Number);
  return { r: m[0] ?? 0, g: m[1] ?? 0, b: m[2] ?? 0, a: m.length > 3 ? m[3] : 1 };
}

/** `top` painted over an opaque `backdrop` */
export function composite(top: Rgb, backdrop: Rgb): Rgb {
  return {
    r: top.r * top.a + backdrop.r * (1 - top.a),
    g: top.g * top.a + backdrop.g * (1 - top.a),
    b: top.b * top.a + backdrop.b * (1 - top.a),
    a: 1,
  };
}

/**
 * runs in the page: one button's label colour and its real painted
 * background — every background from the button up to the first opaque one,
 * composited (a soft button is a 16 % tint over the card behind it)
 */
function readButton(el: HTMLElement): { kind: string; text: string; fg: string; bg: string } {
  const channels = (css: string): number[] => (css.match(/[\d.]+/g) ?? []).map(Number);
  const layers: number[][] = [];
  for (let node: HTMLElement | null = el; node; node = node.parentElement) {
    const c = channels(getComputedStyle(node).backgroundColor);
    layers.push(c);
    if (c.length < 4 || c[3] >= 1) {
      break;
    }
  }
  let painted = [255, 255, 255];
  for (const c of layers.reverse()) {
    const a = c[3] ?? 1;
    painted = [c[0] * a + painted[0] * (1 - a), c[1] * a + painted[1] * (1 - a), c[2] * a + painted[2] * (1 - a)];
  }
  const label = el.querySelector<HTMLElement>('.p-button-label') ?? el;
  const kind = [...el.classList].filter((c) => /^p-button-(text|outlined|soft|secondary|success|info|warn|danger)$/.test(c)).sort().join('.') || 'filled';
  const css = (c: number[]): string => `rgb(${c.slice(0, 3).map(Math.round).join(', ')})`;
  return { kind, text: (label.textContent ?? el.getAttribute('aria-label') ?? '').trim().slice(0, 24), fg: css(channels(getComputedStyle(label).color)), bg: css(painted) };
}

/** every distinct button kind on the page: contrast at rest and on hover */
async function readButtons(page: Page): Promise<Reading[]> {
  const buttons = page.locator('.p-button:visible');
  const count = await buttons.count();
  const readings = new Map<string, Reading>();
  for (let i = 0; i < count; i++) {
    const button = buttons.nth(i);
    const box = await button.boundingBox();
    if (!box || box.y < 0 || box.y + box.height > 900) {
      continue; // not under the pointer's reach without scrolling
    }
    if (await button.evaluate((el: HTMLElement) => el.hasAttribute('disabled') || el.getAttribute('aria-disabled') === 'true')) {
      continue;
    }
    const rest = await button.evaluate(readButton);
    if (readings.has(rest.kind)) {
      continue;
    }
    await button.hover();
    await page.waitForTimeout(200);
    const hover = await button.evaluate(readButton);
    await page.mouse.move(2, 2);
    await page.waitForTimeout(120);
    const ratio = (reading: { fg: string; bg: string }): number => contrast(parseColor(reading.fg), parseColor(reading.bg));
    readings.set(rest.kind, { kind: rest.kind, text: rest.text, rest: ratio(rest), hover: ratio(hover) });
  }
  return [...readings.values()];
}

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

for (const theme of ['light', 'dark'] as const) {
  test(`every kind of button keeps a readable label at rest and under the pointer — ${theme}`, async ({ page }) => {
    test.setTimeout(300_000);
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await page.addInitScript(seedThemeInitScript);
    await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);

    const failures: string[] = [];
    const knownSeen = new Set<string>();
    const measured = new Map<string, Reading>();
    for (const route of ROUTES) {
      await page.goto('/app/' + route, { waitUntil: 'domcontentloaded' });
      await page.locator('app-page-header, .form-section, .error-state, .empty-state, .stat-tile').first().waitFor({ state: 'visible', timeout: 25_000 });
      await page.waitForTimeout(900);
      for (const reading of await readButtons(page)) {
        if (!measured.has(reading.kind)) {
          measured.set(reading.kind, reading);
        }
        const worst = Math.min(reading.rest, reading.hover);
        if (worst >= AA) {
          continue;
        }
        const key = `${theme}|${reading.kind}`;
        if (KNOWN_FAILURES[key]) {
          knownSeen.add(key);
          continue;
        }
        failures.push(`${route}: ${reading.kind} "${reading.text}" — rest ${reading.rest.toFixed(2)}:1, hover ${reading.hover.toFixed(2)}:1`);
      }
    }
    // what was exercised, so a pass is never a vacuous one: kind rest/hover
    console.log(
      `[button-states][${theme}] ${measured.size} kinds on ${ROUTES.length} pages: ` +
        [...measured.values()].map((r) => `${r.kind || 'filled'} ${r.rest.toFixed(1)}/${r.hover.toFixed(1)}`).join(' | '),
    );
    expect(failures, `buttons below ${AA}:1 at rest or on hover [${theme}]:\n${failures.join('\n')}`).toEqual([]);
    // the text buttons this spec was written for were on the pages, and are readable
    expect([...measured.keys()].some((kind) => kind.includes('p-button-text')), `[${theme}] no text button was exercised (${[...measured.keys()].join(', ') || 'none'})`).toBe(true);
    // every reported failure must still be there — a fixed one makes its entry stale
    expect(
      Object.keys(KNOWN_FAILURES).filter((key) => key.startsWith(`${theme}|`)).filter((key) => !knownSeen.has(key)),
      `[${theme}] KNOWN_FAILURES entries that were not seen failing — fixed? remove them. Kinds measured: ${[...measured.keys()].join(', ') || 'none'}`,
    ).toEqual([]);
  });
}

test('the contrast math this spec relies on', () => {
  const white = parseColor('rgb(255, 255, 255)');
  const green = parseColor('rgb(16, 185, 129)');
  const ink = parseColor('rgb(31, 27, 24)');
  expect(contrast(white, green)).toBeCloseTo(2.54, 2); // the reported light success failure
  expect(contrast(ink, green)).toBeGreaterThan(4.5); // …and what a dark label would give
  expect(contrast(white, white)).toBe(1);
  // a 16 % tint over a dark card is neither the tint colour nor the card colour:
  // 211 × 0.16 + 30 × 0.84 = 58.96
  const tint = composite(parseColor('rgba(52, 211, 153, 0.16)'), parseColor('rgb(32, 30, 27)'));
  expect(tint.r).toBeCloseTo(35.2, 5);
  expect(tint.g).toBeCloseTo(58.96, 5);
  expect(tint.b).toBeCloseTo(47.16, 5);
  expect(tint.a).toBe(1);
});

/** a page's quiet header buttons ("تعديل", "المزيد", "العودة للقائمة") and whether each one paints a border */
async function textButtonBorders(page: Page): Promise<{ route: string; text: string; painted: string | null }[]> {
  const out: { route: string; text: string; painted: string | null }[] = [];
  for (const route of ['custodies/1', 'social-cases/1', 'users/1']) {
    await page.goto('/app/' + route, { waitUntil: 'domcontentloaded' });
    await page.locator('app-page-header .p-button-text').first().waitFor({ state: 'visible', timeout: 25_000 });
    const readings = await page.locator('app-page-header .p-button-text').evaluateAll((buttons) =>
      buttons.map((el) => {
        const style = getComputedStyle(el);
        // the first of the four sides that paints something
        const sides = [style.borderTopColor, style.borderRightColor, style.borderBottomColor, style.borderLeftColor];
        const painted = sides.find((side) => !/,\s*0\)$/.test(side)) ?? null;
        return { text: (el.textContent ?? el.getAttribute('aria-label') ?? '').trim().slice(0, 24), painted };
      }),
    );
    for (const reading of readings) {
      out.push({ route, text: reading.text, painted: reading.painted });
    }
  }
  return out;
}

test('a text button is text in both themes: the header\'s quiet buttons paint no border — in dark they did', async ({ page }) => {
  test.setTimeout(240_000);
  await page.setViewportSize({ width: 1280, height: 800 });
  await login(page);
  await page.addInitScript(seedThemeInitScript);
  for (const theme of ['light', 'dark'] as const) {
    await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
    const readings = await textButtonBorders(page);
    expect(readings.length, `[${theme}] no text button was found in the headers`).toBeGreaterThan(0);
    const painted = readings.filter((reading) => reading.painted !== null);
    console.log(`[button-states][${theme}] ${readings.length} text buttons in headers, ${painted.length} with a border: ${readings.map((r) => `"${r.text}"`).join(', ')}`);
    expect(painted, `[${theme}] text buttons that paint a border: ${painted.map((r) => `${r.route} "${r.text}" (${r.painted})`).join('; ')}`).toEqual([]);
  }
});
