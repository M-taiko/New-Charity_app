import { expect, test } from '@playwright/test';

// Live smoke of the six secondary dialogs added in the final pass.
async function login(page: import('@playwright/test').Page, email = 'donia.a5ra2019@gmail.com') {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('quick-expense dialog opens from my-expenses', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses/mine');
  await expect(page.getByRole('button', { name: 'مصروف سريع' })).toBeVisible({ timeout: 15000 });
});

test('attendance add dialog opens', async ({ page }) => {
  await login(page);
  await page.goto('/app/hr/attendance');
  await expect(page.getByRole('button', { name: 'إضافة السجل' })).toBeVisible({ timeout: 15000 });
});

test('KPI add dialog opens', async ({ page }) => {
  await login(page);
  await page.goto('/app/hr/kpi');
  await expect(page.getByRole('button', { name: 'إضافة المؤشر' })).toBeVisible({ timeout: 15000 });
});

test('salary calculate dialog opens', async ({ page }) => {
  await login(page);
  await page.goto('/app/hr/salaries');
  await expect(page.getByRole('button', { name: 'حساب المرتبات' })).toBeVisible({ timeout: 15000 });
});

test('broadcast send dialog opens', async ({ page }) => {
  await login(page);
  await page.goto('/app/broadcasts');
  await expect(page.getByRole('button', { name: 'إرسال رسالة عاجلة' })).toBeVisible({ timeout: 15000 });
});

test('supplier add dialog opens', async ({ page }) => {
  await login(page);
  await page.goto('/app/procurement/suppliers');
  await expect(page.getByRole('button', { name: 'إضافة مورد' })).toBeVisible({ timeout: 15000 });
});
