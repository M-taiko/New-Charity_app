import { expect, test } from '@playwright/test';

// Manager smoke flow: login → shell → lists → theme → logout (plan 24.1).
const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

test('SPA boots and the manager can work end to end', async ({ page }) => {
  const logs: string[] = [];
  page.on('console', (m) => { if (['error', 'warning'].includes(m.type())) logs.push(m.type() + ': ' + m.text().slice(0, 180)); });
  page.on('pageerror', (e) => logs.push('PAGEERROR: ' + String(e).slice(0, 250)));
  page.on('request', (r) => { if (r.url().includes('/api/')) logs.push('API: ' + r.method() + ' ' + r.url().slice(-50)); });
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await expect(page.locator('input[type="email"]')).toBeVisible();
  await page.waitForTimeout(1500);

  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(4000);
  await page.waitForURL(/dashboard/, { timeout: 20000 });
  // wait for the sidebar (loads async after /me resolves)
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('app-root')).toBeVisible();

  // sidebar built from permissions
  await expect(page.getByText('الخزينة').first()).toBeVisible();

  // a money list loads through /api/v1
  // note: notifications polling (every 15s) keeps the network busy — wait for
  // content, never for networkidle, inside the shell
  await page.goto('/app/treasury');
  await expect(page.locator('.stat-tile').first()).toBeVisible({ timeout: 15000 });

  // theme toggle flips the root attribute both ways (plan 13)
  const before = await page.locator('html').getAttribute('data-theme');
  await page.locator('.theme-toggle').first().click();
  const after = await page.locator('html').getAttribute('data-theme');
  expect(after).not.toBe(before);

  // deep link serves the SPA shell itself
  await page.goto('/app/custodies/mine');
  await expect(page.locator('app-root')).toBeVisible();
});
