import { readFileSync, readdirSync } from 'node:fs';
import { join, relative } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';

/**
 * N-09 — the small performance leftovers.
 *
 * 1. `@for` without `track`: counted precisely, on the real source. The
 *    review's grep (`grep -rn "@for" src/app | grep -v "track "`) reports two
 *    lines; both are the text `@formkit` (an import and a comment), and a
 *    `@for` without `track` is a compile error in Angular anyway. The count
 *    here is of real loops.
 * 2. A chart's box does not change when its data arrives (fixed height before
 *    data) — measured on the dashboard with the slow request held back.
 * 3. CLS on /dashboard, at 360 and 1280 and in both themes — the acceptance
 *    line. Before this task: 0.242 at 1280 and 0.053 at 360.
 *
 * Needs the PHP server + the packed SPA, like the matrix. Nothing is written.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';
const SRC = join(__dirname, '..', 'src', 'app');

function sources(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);
    return entry.isDirectory() ? sources(path) : entry.name.endsWith('.ts') ? [path] : [];
  });
}

test('N-09 every @for loop has a track expression — zero without, counted on the source', () => {
  const files = sources(SRC);
  const loops: { file: string; line: number; tracked: boolean; text: string }[] = [];
  const falsePositives: string[] = [];
  for (const file of files) {
    readFileSync(file, 'utf8')
      .split(/\r?\n/)
      .forEach((text, index) => {
        if (text.includes('@for') && !/@for\s*\(/.test(text)) {
          falsePositives.push(`${relative(SRC, file)}:${index + 1}  ${text.trim().slice(0, 70)}`);
        }
        // a real loop opens with `@for (`; its header runs to the first `{`
        const open = /@for\s*\(/.exec(text);
        if (!open) {
          return;
        }
        const head = text.slice(open.index).split('{')[0];
        loops.push({ file: relative(SRC, file), line: index + 1, tracked: /\btrack\b/.test(head), text: head.trim().slice(0, 80) });
      });
  }
  expect(loops.length).toBeGreaterThan(50);
  const untracked = loops.filter((loop) => !loop.tracked);
  expect(untracked.map((loop) => `${loop.file}:${loop.line}  ${loop.text}`)).toEqual([]);
  // what the review's grep matched: not loops at all
  expect(falsePositives.length).toBeGreaterThan(0);
  for (const line of falsePositives) {
    expect(line).toMatch(/@formkit/);
  }
});

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

/** installed before the page's own scripts: every layout shift with the elements that moved */
function watchShifts(): void {
  const w = window as unknown as { __shifts: { value: number; at: number; moved: string[] }[] };
  w.__shifts = [];
  if (location.pathname.includes('/login')) {
    return;
  }
  const name = (el: Element | null): string => {
    if (!el) {
      return '(document)';
    }
    const cls = typeof el.className === 'string' ? el.className.trim().split(/\s+/).filter((c) => !c.startsWith('ng-')).slice(0, 2).join('.') : '';
    return `${el.tagName.toLowerCase()}${cls ? '.' + cls : ''}`;
  };
  new PerformanceObserver((list) => {
    for (const entry of list.getEntries() as (PerformanceEntry & { value: number; hadRecentInput?: boolean; sources?: { node?: Element }[] })[]) {
      if (entry.hadRecentInput) {
        continue;
      }
      w.__shifts.push({ value: entry.value, at: Math.round(entry.startTime), moved: (entry.sources ?? []).slice(0, 3).map((source) => name(source.node ?? null)) });
    }
  }).observe({ type: 'layout-shift', buffered: true });
}

/** one chart card's box, before and after its data */
function readCards(): { title: string; card: number; plot: number; loading: boolean }[] {
  return [...document.querySelectorAll<HTMLElement>('app-chart-card .chart-card')].map((el) => {
    const body = el.querySelector<HTMLElement>('.chart-card__body') as HTMLElement;
    const height = (node: Element | null): number => (node ? Math.round(node.getBoundingClientRect().height * 10) / 10 : 0);
    return {
      title: (el.querySelector('.chart-card__title')?.textContent ?? '').trim(),
      card: height(el),
      plot: height(body.querySelector('.chart-card__plot, .chart-card__loading')),
      loading: el.hasAttribute('data-loading'),
    };
  });
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [1280, 360]) {
    test(`N-09 /dashboard does not shift while loading and its charts keep their box — ${theme} @ ${width}`, async ({ page }) => {
      test.setTimeout(120_000);
      await page.setViewportSize({ width, height: 800 });
      await login(page);
      await page.addInitScript(seedThemeInitScript, theme);
      await page.addInitScript(watchShifts);
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);

      // the slow request is held back, so the loading state is on screen long enough to be measured
      await page.route('**/api/v1/dashboard/performance*', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 2_000));
        await route.continue();
      });
      await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
      await page.locator('app-chart-card .chart-card').first().waitFor({ state: 'visible', timeout: 30_000 });
      await page.waitForTimeout(500);
      const before = await page.evaluate(readCards);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1_500);
      const after = await page.evaluate(readCards);

      expect(after.length).toBeGreaterThanOrEqual(2);
      // the boxes: the same before the data and after it (a sub-pixel of canvas rounding is allowed)
      for (let i = 0; i < after.length; i++) {
        expect(Math.abs(after[i].card - before[i].card), `${after[i].title}: card height`).toBeLessThanOrEqual(1);
      }
      expect(before.some((card) => card.loading), 'a card was measured in its loading state').toBe(true);
      expect(after.some((card) => card.loading)).toBe(false);

      // and the page as a whole
      const shifts = await page.evaluate(() => (window as unknown as { __shifts: { value: number; at: number; moved: string[] }[] }).__shifts);
      const cls = shifts.reduce((sum, shift) => sum + shift.value, 0);
      const report = shifts.map((shift) => `+${shift.value.toFixed(4)} @ ${shift.at}ms ${shift.moved.join(', ')}`).join(' | ');
      test.info().annotations.push({ type: 'cls', description: `CLS ${cls.toFixed(4)} — ${report || 'no layout shift'}` });
      console.log(
        `[n09][${theme} @ ${width}] CLS ${cls.toFixed(4)} from ${shifts.length} shifts${report ? ` (${report})` : ''}; chart cards ${after.map((card, i) => `${card.title}: ${before[i].card} → ${card.card}px`).join(' · ')}`,
      );
      // "≈ 0": measured 0.0000 on the delivered build; the bound leaves room for a stray sub-pixel
      expect(cls, `CLS on /dashboard (${shifts.length} shifts): ${report}`).toBeLessThan(0.005);
    });
  }
}
