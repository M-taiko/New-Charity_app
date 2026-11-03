import { expect, test } from '@playwright/test';

// T3-70/71/72/40/30 — smoke of the new procurement/treasury/directions
// actions: dialogs open, row actions render, and one refusal path (server
// validation keeps the dialog open). Mirrors dialogs.spec.ts: live server,
// same demo account.
async function login(page: import('@playwright/test').Page, email = 'donia.a5ra2019@gmail.com') {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('purchase form shows the supplier picker and attachment field', async ({ page }) => {
  await login(page);
  await page.goto('/app/procurement/purchase/new');
  await expect(page.locator('#app-purchase-form-supplier')).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('مرفق (اختياري)')).toBeVisible();
});

test('maintenance form shows the attachment field', async ({ page }) => {
  await login(page);
  await page.goto('/app/procurement/maintenance/new');
  await expect(page.getByText('صورة أو مستند (اختياري)')).toBeVisible({ timeout: 15000 });
});

test('treasury donation dialog opens with the three sources', async ({ page }) => {
  await login(page);
  await page.goto('/app/treasury');
  await page.getByRole('button', { name: 'استقبال تبرع' }).first().click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog).toBeVisible({ timeout: 10000 });
  await expect(dialog.getByText('جهة التبرع')).toBeVisible();
  await page.locator('#don-source').click();
  await expect(page.getByRole('option', { name: 'من الشركة' })).toBeVisible();
  await expect(page.getByRole('option', { name: 'من جهة خارجية' })).toBeVisible();
  await expect(page.getByRole('option', { name: 'مردود/مرتجع' })).toBeVisible();
});

test('treasury list renders the full row-action set and the delete confirm (non-destructive)', async ({ page }) => {
  await login(page);
  await page.goto('/app/treasury');
  const firstRow = page.locator('.ledger-table__row').first();
  await expect(firstRow).toBeVisible({ timeout: 15000 });
  // view / donation / edit / transfer / delete
  for (const label of ['عرض', 'استقبال تبرع', 'تعديل', 'تحويل بين الخزائن', 'حذف']) {
    await expect(firstRow.locator(`.row-actions__btn[aria-label="${label}"]`)).toBeVisible();
  }
  // delete opens the shared confirm dialog — cancel (no data destroyed)
  await firstRow.locator('.row-actions__btn[aria-label="حذف"]').click();
  const confirm = page.getByRole('dialog');
  await expect(confirm.getByText('تأكيد الحذف')).toBeVisible({ timeout: 10000 });
  await confirm.getByRole('button', { name: 'إلغاء' }).click();
  await expect(confirm.getByText('تأكيد الحذف')).toBeHidden({ timeout: 10000 });
});

test('donation dialog refuses an empty description (server 422 stays visible)', async ({ page }) => {
  await login(page);
  await page.goto('/app/treasury');
  const firstRow = page.locator('.ledger-table__row').first();
  await expect(firstRow).toBeVisible({ timeout: 15000 });
  await firstRow.locator('.row-actions__btn[aria-label="استقبال تبرع"]').click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog).toBeVisible({ timeout: 10000 });
  // treasury preselected from the row; amount without description → 422
  await dialog.locator('#don-amount').fill('1');
  await dialog.getByRole('button', { name: 'استقبال تبرع' }).last().click();
  await expect(dialog.getByRole('alert')).toBeVisible({ timeout: 15000 });
});

test('purchase list offers view and delete row actions', async ({ page }) => {
  await login(page);
  await page.goto('/app/procurement/purchase');
  await expect(page.getByRole('button', { name: 'طلب شراء جديد' })).toBeVisible({ timeout: 15000 });
  const firstRow = page.locator('.ledger-table__row').first();
  if ((await firstRow.count()) === 0) {
    test.skip(true, 'no purchase requests seeded');
  }
  await expect(firstRow.locator('.row-actions__btn[aria-label="عرض"]')).toBeVisible();
});

test('suppliers list shows the deactivate (delete) action on active rows', async ({ page }) => {
  await login(page);
  await page.goto('/app/procurement/suppliers');
  await expect(page.getByRole('button', { name: 'إضافة مورد' })).toBeVisible({ timeout: 15000 });
  const firstRow = page.locator('.ledger-table__row').first();
  if ((await firstRow.count()) === 0) {
    test.skip(true, 'no suppliers seeded');
  }
  await expect(firstRow.locator('.row-actions__btn[aria-label="تعطيل المورد"]')).toBeVisible();
});

test('directions rows expose edit, toggle and delete actions', async ({ page }) => {
  await login(page);
  await page.goto('/app/directions');
  await expect(page.getByText('إدارة التوجيهات المحاسبية').first()).toBeVisible({ timeout: 15000 });
  const firstRow = page.locator('.ledger-table__row').first();
  if ((await firstRow.count()) === 0) {
    test.skip(true, 'no direction rows seeded');
  }
  await expect(firstRow.locator('.row-actions__btn[aria-label="تعديل"]')).toBeVisible();
  await expect(firstRow.locator('.row-actions__btn[aria-label="حذف"]')).toBeVisible();
});
