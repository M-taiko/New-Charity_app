import { expect, test, type Page } from '@playwright/test';

/**
 * Visual QA matrix (Task4 T4-28): every route × light/dark × 360/1280 px.
 * Saves one screenshot per combination under e2e/screenshots/ and FAILS on
 * the four things code scanning cannot catch:
 *   1. horizontal page scroll
 *   2. console errors / page errors during load
 *   3. a literal i18n key shown to the user ("custody.list.title" on screen)
 *   4. an empty <main> (blank page)
 *
 * Review the screenshot set once per delivery round; pages that still look
 * unfinished become the next batch of polish tasks.
 *
 * Needs the PHP server + seeded data + the packed SPA (same as spa.spec.ts).
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

/** every leaf route (app.routes.ts + feature routes); :id → seeded id 1 */
const ROUTES: readonly string[] = [
  'dashboard',
  'treasury', 'treasury/new', 'treasury/transfer', 'treasury/1', 'treasury/1/edit',
  'custodies', 'custodies/new', 'custodies/mine', 'custodies/mine/movements',
  'custodies/movements', 'custodies/return-requests', 'custodies/tracking',
  'custodies/1', 'custodies/1/edit',
  'transfers', 'transfers/new', 'transfers/1',
  'expenses', 'expenses/new', 'expenses/mine', 'expenses/1', 'expenses/1/edit',
  'expenses/edit-requests', 'expenses/edit-requests/1', 'expenses/1/edit-request',
  'directions',
  'reports/agents-balance', 'reports/case-expenses', 'reports/directions',
  'reports/governorates', 'reports/items', 'reports/reconciliation', 'reports/researchers',
  'social-cases', 'social-cases/new', 'social-cases/mine', 'social-cases/1', 'social-cases/1/edit',
  'hr', 'hr/employees', 'hr/employees/new', 'hr/employees/1/edit', 'hr/employees/1/salaries',
  'hr/attendance', 'hr/kpi', 'hr/salaries', 'hr/salaries/1', 'hr/salaries/1/edit',
  'procurement/purchase', 'procurement/purchase/new', 'procurement/purchase/1',
  'procurement/maintenance', 'procurement/maintenance/new', 'procurement/maintenance/1',
  'procurement/suppliers',
  'tasks', 'tasks/new', 'tasks/1',
  'chat', 'notifications', 'broadcasts', 'activity', 'activity/mine',
  'users', 'users/new', 'users/1', 'users/1/edit',
  'settings', 'profile',
];

const THEMES = ['light', 'dark'] as const;
const VIEWPORTS = [
  { name: '360', width: 360, height: 800 },
  { name: '1280', width: 1280, height: 800 },
] as const;

/** dotted Latin identifiers that are never legitimate UI text (i18n keys) */
const RAW_I18N_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 15_000 });
}

async function checkRoute(
  page: Page,
  route: string,
  theme: string,
  viewport: { name: string; width: number; height: number },
): Promise<void> {
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

  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-theme', t);
  }, theme);
  await page.goto('/app/' + route, { waitUntil: 'domcontentloaded' });

  // settle: header or error state visible, then one paint for skeletons→content
  await expect(page.locator('app-page-header, .error-state').first()).toBeVisible({ timeout: 15_000 });
  await page.waitForTimeout(1_200);

  // 1 — no horizontal page scroll
  const overflow = await page.evaluate(
    () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
  );
  expect(overflow, `${route} [${theme}/${viewport.name}] horizontal scroll +${overflow}px`).toBeLessThanOrEqual(1);

  // 3 — no literal i18n key rendered
  const mainText = (await page.locator('main').first().innerText().catch(() => '')) || '';
  const rawKey = RAW_I18N_KEY.exec(mainText);
  expect(rawKey, `${route} [${theme}/${viewport.name}] literal i18n key "${rawKey?.[0] ?? ''}"`).toBeNull();

  // 4 — never an empty <main>
  expect(mainText.trim().length, `${route} [${theme}/${viewport.name}] empty <main>`).toBeGreaterThan(0);

  // 2 — console/page errors (dead API calls, template errors)
  // noise: aborted requests while navigating on
  const realErrors = errors.filter((e) => !e.includes('net::ERR_ABORTED'));
  expect(realErrors, `${route} [${theme}/${viewport.name}] console errors`).toEqual([]);

  page.removeListener('console', onConsole);
  page.removeListener('pageerror', onPageError);

  await page.screenshot({
    path: `e2e/screenshots/${route.replaceAll('/', '~')}__${theme}__${viewport.name}.png`,
    fullPage: false,
  });
}

test.describe.serial('visual QA matrix', () => {
  test('every route in light/dark × 360/1280 with no scroll, errors, raw keys or blank pages', async ({ page }) => {
    test.slow();
    await login(page);
    for (const route of ROUTES) {
      for (const theme of THEMES) {
        for (const viewport of VIEWPORTS) {
          await checkRoute(page, route, theme, viewport);
        }
      }
    }
  });
});
