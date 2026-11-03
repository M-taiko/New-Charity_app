import { expect, test } from '@playwright/test';

// T3-10/T3-11/T3-12 custody actions: tab structure, manager detail actions,
// and one refused action (server error surfaces inside the dialog).
// Live smoke against the seeded server, same style as dialogs.spec.ts.

async function login(page: import('@playwright/test').Page, email: string) {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('my-custodies shows lifecycle tabs and the new-request shortcut (agent)', async ({ page }) => {
  await login(page, 'agent-t@t.local');
  await page.goto('/app/custodies/mine');
  await expect(page.getByRole('button', { name: 'طلب عهدة جديدة' })).toBeVisible({ timeout: 15000 });
  for (const label of ['قيد الانتظار', 'التحويلات المعلقة', 'المغلقة والمنتهية']) {
    await expect(page.getByRole('button', { name: new RegExp(label) })).toBeVisible();
  }
});

test('custody detail shows print and edit for a treasury manager', async ({ page }) => {
  await login(page, 'donia.a5ra2019@gmail.com');
  await page.goto('/app/custodies');
  const firstRow = page.locator('.ledger-table tbody tr').first();
  await expect(firstRow).toBeVisible({ timeout: 15000 });
  await firstRow.locator('a').first().click();
  await expect(page.getByRole('button', { name: 'طباعة' })).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('button', { name: 'تعديل' })).toBeVisible();
});

test('refused external receipt shows the server message inside the dialog', async ({ page }) => {
  await login(page, 'researcher-t@t.local');
  await page.goto('/app/custodies/mine');
  const openBtn = page.getByRole('button', { name: 'استلام خارجي' });
  await expect(openBtn).toBeVisible({ timeout: 15000 });
  await openBtn.click();
  await page.fill('#ext-source', 'جهة اختبار');
  const amount = page.locator('#ext-amount input');
  await amount.fill('25');
  // refuse: the server rejects the submit (here: mocked 422 business rule)
  await page.route('**/api/v1/my-external-receipt', (route) =>
    route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'تم رفض العملية من الخادم' }) }),
  );
  await page.getByRole('button', { name: 'حفظ' }).click();
  await expect(page.locator('.dialog__alert')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('.dialog__alert')).toContainText('تم رفض العملية من الخادم');
});
