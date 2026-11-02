import { expect, test } from '@playwright/test';

// Per-role smoke flows (plan 24.1): agent, researcher, supervisor (read-only).

async function login(page: import('@playwright/test').Page, email: string) {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 15000 });
}

test('agent sees my-custodies and my-expenses, not management', async ({ page }) => {
  await login(page, 'agent-t@t.local');
  await expect(page.getByText('عهداتي').first()).toBeVisible();
  await expect(page.getByText('مصروفاتي').first()).toBeVisible();
  const sidebar = page.locator('.sidebar');
  await expect(sidebar.getByText('الخزائن')).toHaveCount(0);
});

test('researcher sees cases and my cases', async ({ page }) => {
  await login(page, 'researcher-t@t.local');
  await expect(page.getByText('الحالات الاجتماعية').first()).toBeVisible();
  await expect(page.getByText('حالاتي').first()).toBeVisible();
});

test('supervisor is read-only: no action buttons anywhere', async ({ page }) => {
  await login(page, 'supervisor-t@t.local');
  // tracking page is visible to supervisor
  await expect(page.getByText('متابعة العهدات').first()).toBeVisible();
  await page.goto('/app/custodies/tracking');
  await expect(page.locator('.ledger-table, .stat-tile').first()).toBeVisible({ timeout: 15000 });
  // no create/add/approve buttons on supervisor pages
  for (const label of ['إنشاء', 'إضافة', 'الموافقة', 'حفظ']) {
    const count = await page.getByRole('button', { name: label }).count();
    expect(count, `button "${label}" must not appear for supervisor`).toBe(0);
  }
});
