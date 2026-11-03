import { expect, test } from '@playwright/test';

// Social case actions (Task 3 T3-50..T3-52): full form, list row actions and
// the reject dialog's required notes. Runs against the live PHP server like
// the other specs; tolerant of the current data state.
async function login(page: import('@playwright/test').Page, email = 'donia.a5ra2019@gmail.com') {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', '123456789');
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('case form renders every unified-form section and the researcher select', async ({ page }) => {
  await login(page);
  await page.goto('/app/cases/new');
  await expect(page.getByText('البيانات الأساسية')).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('البيانات الشخصية')).toBeVisible();
  await expect(page.getByText('معلومات السكن')).toBeVisible();
  await expect(page.getByText('المعلومات المالية')).toBeVisible();
  await expect(page.getByText('معلومات الأسرة')).toBeVisible();
  await expect(page.getByText('المعلومات الصحية')).toBeVisible();
  await expect(page.getByText('نوع المساعدة المطلوبة')).toBeVisible();
  await expect(page.getByText('المستندات والصور')).toBeVisible();
  await expect(page.locator('#c-researcher')).toBeVisible();
  // T34: the governorate select loads from /lookups
  await expect(page.locator('#c-gov')).toBeVisible();
});

test('family members repeater adds and removes rows', async ({ page }) => {
  await login(page);
  await page.goto('/app/cases/new');
  await expect(page.getByRole('button', { name: 'إضافة قريب' })).toBeVisible({ timeout: 15000 });
  await page.getByRole('button', { name: 'إضافة قريب' }).click();
  await page.getByRole('button', { name: 'إضافة قريب' }).click();
  await expect(page.locator('[id^="member-name-"]')).toHaveCount(2);
  await page.getByRole('button', { name: 'إزالة' }).first().click();
  await expect(page.locator('[id^="member-name-"]')).toHaveCount(1);
});

test('case list exposes the row actions and both row modals', async ({ page }) => {
  await login(page);
  await page.goto('/app/cases');
  const firstAction = page.locator('.row-actions').first();
  await expect(firstAction).toBeVisible({ timeout: 15000 });
  // quick-details modal (Blade viewCaseDetails)
  await firstAction.locator('button[title="عرض التفاصيل"]').click();
  await expect(page.getByText('تفاصيل الحالة الاجتماعية').first()).toBeVisible({ timeout: 15000 });
  await page.getByRole('button', { name: 'إلغاء' }).last().click();
  // family members modal (Blade loadFamilyMembers)
  await firstAction.locator('button[title="عرض الأقارب"]').click();
  await expect(page.getByText(/أفراد عائلة/).first()).toBeVisible({ timeout: 15000 });
  await page.getByRole('button', { name: 'إغلاق' }).last().click();
});

test('rejecting a case requires typed notes', async ({ page }) => {
  await login(page);
  await page.goto('/app/cases');
  const firstRow = page.locator('.ledger-table__row a[href*="/cases/"]').first();
  await expect(firstRow).toBeVisible({ timeout: 15000 });
  await firstRow.click();
  await page.waitForURL(/\/cases\/\d+$/, { timeout: 15000 });
  const reject = page.getByRole('button', { name: 'الرفض' });
  if (await reject.isVisible({ timeout: 5000 }).catch(() => false)) {
    await reject.click();
    // refusal path: confirming with an empty reason shows the validation message
    await page.getByRole('button', { name: 'رفض', exact: true }).last().click();
    await expect(page.getByText('أدخل 3 أحرف على الأقل')).toBeVisible();
    await page.getByRole('button', { name: 'إلغاء' }).last().click();
  }
});
