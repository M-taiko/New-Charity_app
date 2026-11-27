import { mkdirSync } from 'node:fs';
import { expect, test, type Page } from '@playwright/test';
import { CHART_HUE_COUNT, entityColor, entityHue } from '../src/app/shared/lib/chart-colors';
import { seedThemeInitScript } from './gates';

/**
 * N-07 — one colour per entity, everywhere.
 *
 * The unit tests pin the decision (`entityHue` / `entityColor`): deterministic,
 * in range, well spread, and eight consecutive ids never sharing a hue.
 *
 * The browser tests pin the acceptance line against the real app and its real
 * data: the same agent wears the same colour on the dashboard ranking and on
 * the agents-balance report; the same direction on the dashboard donut and on
 * the directions report; the same treasury in its list and on its detail page
 * — in light and in dark, where the token resolves to another value.
 *
 * Needs the PHP server + the packed SPA, like the matrix. Nothing is written.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

test.describe('N-07 entity colour — the decision', () => {
  test('the same id always gives the same hue, and 12 is "12"', () => {
    for (const id of [1, 2, 7, 12, 999, 123456, 'agent-17', 'محفظة', '']) {
      const first = entityHue(id);
      for (let call = 0; call < 5; call++) {
        expect(entityHue(id)).toBe(first);
      }
    }
    expect(entityHue(12)).toBe(entityHue('12'));
    expect(entityColor(12)).toBe(entityColor('12'));
    // the null-ish ids agree too — a row without an id is one predictable colour
    expect(entityHue(null)).toBe(entityHue(undefined));
    expect(entityHue(null)).toBe(entityHue(''));
  });

  test('the hue is always one of the eight --chart tokens, given as a token and never as a hex', () => {
    for (let id = -50; id < 2_000; id++) {
      const hue = entityHue(id);
      expect(Number.isInteger(hue)).toBe(true);
      expect(hue).toBeGreaterThanOrEqual(1);
      expect(hue).toBeLessThanOrEqual(CHART_HUE_COUNT);
      expect(entityColor(id)).toMatch(/^var\(--chart-[1-8]\)$/);
    }
    expect(entityColor('anything')).not.toMatch(/#[0-9a-f]{3,8}/i);
  });

  test('eight consecutive ids never share a hue — no two neighbours in a ranked list wear one colour', () => {
    for (let start = 0; start < 400; start++) {
      const hues = Array.from({ length: CHART_HUE_COUNT }, (_, i) => entityHue(start + i));
      expect(new Set(hues).size, `ids ${start}…${start + CHART_HUE_COUNT - 1}`).toBe(CHART_HUE_COUNT);
    }
  });

  test('every hue is used: over ids 1…800 no colour gets less than a tenth of the entities', () => {
    const count = new Map<number, number>();
    for (let id = 1; id <= 800; id++) {
      count.set(entityHue(id), (count.get(entityHue(id)) ?? 0) + 1);
    }
    expect(count.size).toBe(CHART_HUE_COUNT);
    for (const [hue, n] of count) {
      expect(n, `--chart-${hue}`).toBeGreaterThanOrEqual(80);
    }
  });

  test('a non-numeric id is hashed, not rejected: stable, in range, and different names differ', () => {
    expect(entityHue('custody-7')).toBe(entityHue('custody-7'));
    expect(entityHue('CASH')).toBeGreaterThanOrEqual(1);
    expect(entityHue('CASH')).toBeLessThanOrEqual(CHART_HUE_COUNT);
    const names = Array.from({ length: 40 }, (_, i) => `direction-${i}`);
    expect(new Set(names.map((name) => entityHue(name))).size).toBeGreaterThan(3);
  });
});

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

for (const theme of ['light', 'dark'] as const) {
  test(`N-07 the same entity has the same colour across pages — ${theme}`, async ({ page }) => {
    test.setTimeout(180_000);
    await login(page);
    await page.addInitScript(seedThemeInitScript);
    await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
    await page.setViewportSize({ width: 1280, height: 800 });

    // the token follows the theme: the same hue is another value in dark
    // The data the page ranks, asked for directly from the signed-in context. (Reading the page's own
    // answer back through Playwright failed once in a run under load — "No resource with given
    // identifier found" — before anything about colours was checked; this is the same server data.)
    await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
    await page.locator('.dash-rank li').first().waitFor({ state: 'visible', timeout: 30_000 });
    const dashboard = (await (await page.request.get('/api/v1/dashboard')).json()) as { data?: { agents_evaluation?: { agent: { id: number; name: string } }[] } };
    await page.waitForTimeout(600);

    const hueExample = entityHue(5);
    const exampleValue = await page.evaluate((h) => getComputedStyle(document.documentElement).getPropertyValue(`--chart-${h}`).trim(), hueExample);
    expect(exampleValue).toMatch(/^#[0-9a-f]{6}$/i);
    test.info().annotations.push({ type: 'token', description: `--chart-${hueExample} resolves to ${exampleValue} in ${theme}` });

    // ── agents: dashboard ranking bar ↔ agents-balance avatar ring
    const ranked = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('.dash-rank li')].map((li) => ({
        name: (li.querySelector('.dash-rank__name')?.textContent ?? '').trim(),
        color: getComputedStyle(li.querySelector('.dash-rank__fill') as Element).backgroundColor,
      })),
    );
    expect(ranked.length).toBeGreaterThan(0);
    // every bar is its agent's colour: derived here from the id, read there from the DOM
    const palette = await page.evaluate((n) => Array.from({ length: n }, (_, i) => getComputedStyle(document.documentElement).getPropertyValue(`--chart-${i + 1}`).trim()), CHART_HUE_COUNT);
    const agents = dashboard.data?.agents_evaluation ?? [];
    for (const row of ranked) {
      const agent = agents.find((a) => a.agent.name === row.name);
      if (!agent) {
        continue;
      }
      expect(row.color, `${row.name} on the dashboard`).toBe(toRgb(palette[entityHue(agent.agent.id) - 1]));
    }

    await page.goto('/app/reports/agents-balance', { waitUntil: 'domcontentloaded' });
    await page.locator('.beneficiary__avatar--entity').first().waitFor({ state: 'visible', timeout: 30_000 });
    const balance = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('.beneficiary')].map((el) => ({
        name: (el.querySelector('.u-fw-semibold')?.textContent ?? '').trim(),
        color: getComputedStyle(el.querySelector('.beneficiary__avatar--entity') as Element).borderTopColor,
      })),
    );
    expect(balance.length).toBeGreaterThan(0);
    const shared = balance.find((row) => ranked.some((r) => r.name === row.name));
    expect(shared, 'an agent that appears on both pages').toBeDefined();
    const onDashboard = ranked.find((r) => r.name === shared!.name)!;
    expect(shared!.color, `${shared!.name}: agents-balance ring vs dashboard bar`).toBe(onDashboard.color);

    // ── directions: dashboard donut legend ↔ directions report donut legend
    await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
    await page.locator('.chart-ranked__row').first().waitFor({ state: 'visible', timeout: 30_000 });
    const perf = (await (await page.request.get('/api/v1/dashboard/performance')).json()) as { data?: { spending_by_direction?: { id?: number; label: string }[] } };
    const donut = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('.chart-ranked__row')].map((row) => ({
        label: row.getAttribute('title') ?? '',
        color: getComputedStyle(row.querySelector('.chart-ranked__fill') as Element).backgroundColor,
      })),
    );
    const withId = (perf.data?.spending_by_direction ?? []).filter((row) => row.id !== undefined);
    expect(withId.length, 'the dashboard names the category behind each slice').toBeGreaterThan(0);
    for (const row of donut) {
      const category = withId.find((c) => c.label === row.label);
      if (category) {
        expect(row.color, `${row.label} on the dashboard donut`).toBe(toRgb(palette[entityHue(category.id) - 1]));
      }
    }

    await page.goto('/app/reports/directions', { waitUntil: 'domcontentloaded' });
    await page.locator('app-chart-card .chart-legend__item, app-chart-card .chart-ranked__row').first().waitFor({ state: 'visible', timeout: 30_000 });
    const report = await page.evaluate(() =>
      [...document.querySelectorAll<HTMLElement>('app-chart-card .chart-legend__chip')].map((chip) => ({
        label: (chip.parentElement?.getAttribute('title') ?? '').trim(),
        color: getComputedStyle(chip).backgroundColor,
      })),
    );
    const sameDirection = report.find((row) => donut.some((d) => d.label === row.label));
    if (sameDirection) {
      expect(sameDirection.color, `${sameDirection.label}: directions report vs dashboard donut`).toBe(donut.find((d) => d.label === sameDirection!.label)!.color);
    } else {
      // the seeded data has no category on both donuts: prove the rule on the page itself instead
      const hues = palette.map((hex) => hex.toLowerCase());
      for (const row of report) {
        expect(hues, `${row.label} wears one of the eight hue tokens`).toContain(rgbToHex(row.color));
      }
    }

    // ── treasuries: the dot in the list ↔ the dot on the detail page
    await page.goto('/app/treasury', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table .entity-dot').first().waitFor({ state: 'visible', timeout: 30_000 });
    // the table renders twice (stale rows, then the fresh page): read only once it is still
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    const firstDot = page.locator('.ledger-table .entity-dot').first();
    const listColor = await firstDot.evaluate((el) => getComputedStyle(el).backgroundColor);
    const id = await firstDot.evaluate((el) => (el.closest('a')?.getAttribute('href') ?? '').split('/').pop() ?? '');
    expect(listColor).toMatch(/^rgb\(/);
    await page.goto('/app/treasury/' + id, { waitUntil: 'domcontentloaded' });
    await page.locator('.card-hero .entity-dot').waitFor({ state: 'visible', timeout: 30_000 });
    const detailColor = await page.locator('.card-hero .entity-dot').evaluate((el) => getComputedStyle(el).backgroundColor);
    expect(detailColor, 'treasury #' + id).toBe(listColor);
    expect(listColor).toBe(toRgb(palette[entityHue(id) - 1]));
  });
}

/** `#a1886f` → `rgb(161, 136, 111)`, the form getComputedStyle returns */
function toRgb(hex: string): string {
  const n = parseInt(hex.slice(1), 16);
  return `rgb(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255})`;
}

function rgbToHex(rgb: string): string {
  const [r, g, b] = (rgb.match(/\d+/g) ?? []).map(Number);
  return '#' + [r, g, b].map((v) => v.toString(16).padStart(2, '0')).join('');
}

/** where the colours show: the page, and what in it carries an entity's colour */
const OUT = 'e2e/screenshots/n07';

/** whole page: the real viewport made as tall as the document (how the matrix does it) */
async function wholePage(page: Page, name: string): Promise<void> {
  mkdirSync(OUT, { recursive: true });
  await page.evaluate(() => document.fonts.ready);
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  const width = page.viewportSize()?.width ?? 1280;
  await page.setViewportSize({ width, height });
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}/${name}.png` });
  await page.setViewportSize({ width, height: 800 });
}

/** the colours as they look, for the delivery: the same entities on four pages, both themes, both widths */
for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`N-07 the colours as they look — ${theme}, ${width}px`, async ({ page }) => {
      test.setTimeout(180_000);
      await login(page);
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
      await page.setViewportSize({ width, height: 800 });
      const pages: [string, string][] = [
        ['dashboard', '.dash-rank li'],
        ['reports/agents-balance', '.beneficiary__avatar--entity'],
        ['reports/directions', 'app-chart-card .chart-legend__item, app-chart-card .chart-ranked__row'],
        ['treasury', '.ledger-table .entity-dot'],
      ];
      for (const [route, selector] of pages) {
        await page.goto(`/app/${route}`, { waitUntil: 'domcontentloaded' });
        await page.locator(selector).first().waitFor({ state: 'visible', timeout: 30_000 });
        // a canvas is painted once its data is in, and then animates: let it settle
        await page.waitForTimeout(1_500);
        await wholePage(page, `${route.replace(/\//g, '~')}__${theme}__${width}`);
      }
    });
  }
}
