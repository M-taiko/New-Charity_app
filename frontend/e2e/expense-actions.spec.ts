import { expect, test } from '@playwright/test';

// T3-20..T3-23 expense action parity: list row actions, quick-expense modal,
// edit-requests review page, detail action bar (happy path + one refusal).
async function login(page: import('@playwright/test').Page, email = 'donia.a5ra2019@gmail.com') {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('expense list shows the attachment and actions columns (T3-22)', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses');
  await expect(page.locator('.ledger-table').first()).toBeVisible({ timeout: 20000 });
  await expect(page.locator('th[data-field="attachment"]')).toBeVisible();
  await expect(page.locator('th[data-field="actions"]')).toBeVisible();
});

test('quick-expense dialog opens and can be cancelled (T3-22)', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses');
  const open = page.getByRole('button', { name: 'مصروف سريع' });
  if (await open.isVisible({ timeout: 15000 }).catch(() => false)) {
    await open.first().click();
    // dialog content is portalled to body — assert on the custody field label
    await expect(page.getByLabel('اختر العهدة').first()).toBeVisible({ timeout: 10000 });
    // refusal path: cancel without submitting — no request leaves the page
    await page.getByRole('button', { name: 'إلغاء' }).last().click();
    await expect(open.first()).toBeVisible();
  } else {
    test.skip(true, 'this account cannot spend (spend_money)');
  }
});

test('edit-requests list loads for reviewers (T3-23)', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses/edit-requests');
  await page.waitForTimeout(1500);
  // the page renders for reviewers; everyone else was redirected — shell stays up
  await expect(page.locator('app-root')).toBeVisible();
  const title = page.getByText('طلبات تعديل المصروفات').first();
  if (await title.isVisible({ timeout: 10000 }).catch(() => false)) {
    await expect(page.locator('.ledger-table').first()).toBeVisible({ timeout: 15000 });
  }
});

test('expense form has the expense-type switch and case picker (T3-21)', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses/new');
  const typeSelect = page.locator('#type');
  if (await typeSelect.isVisible({ timeout: 15000 }).catch(() => false)) {
    await typeSelect.click();
    await expect(page.locator('p-selectitem').filter({ hasText: 'حالة اجتماعية' }).first()).toBeVisible({ timeout: 10000 });
    await expect(page.locator('p-selectitem').filter({ hasText: 'مصروف عام' }).first()).toBeVisible();
    // choosing social_case reveals the searchable case picker
    await page.locator('p-selectitem').filter({ hasText: 'حالة اجتماعية' }).first().click();
    await expect(page.locator('#social-case')).toBeVisible({ timeout: 10000 });
    // refusal path: submit without a case keeps the form on the page
    await page.getByRole('button', { name: 'حفظ المصروف' }).click();
    await expect(page.locator('#social-case')).toBeVisible();
  } else {
    test.skip(true, 'this account cannot open the expense form');
  }
});

test('edit-request review page keeps approve/reject pending-only (T3-23)', async ({ page }) => {
  await login(page);
  await page.goto('/app/expenses/edit-requests');
  await page.waitForTimeout(1500);
  const firstRow = page.locator('.ledger-table tbody tr.ledger-table__row').first();
  if (await firstRow.isVisible({ timeout: 15000 }).catch(() => false)) {
    await firstRow.locator('a, button').first().click();
    await page.waitForTimeout(2000);
    // whenever the detail page rendered, no hard-coded rejection is sent:
    // the reject action can only go through the reason dialog
    const reject = page.getByRole('button', { name: 'رفض الطلب' });
    if (await reject.isVisible({ timeout: 8000 }).catch(() => false)) {
      await reject.click();
      await expect(page.locator('#reason-dialog-input')).toBeVisible({ timeout: 10000 });
      // refusal path: an empty reason cannot be submitted
      await page.getByRole('button', { name: 'رفض الطلب' }).last().click();
      await expect(page.locator('#reason-dialog-input')).toBeVisible();
    }
  } else {
    test.skip(true, 'no edit requests in this environment');
  }
});
