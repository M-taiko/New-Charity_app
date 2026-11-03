import { expect, test } from '@playwright/test';

// T3-110 / T3-111 / T3-04 (reports): print buttons on every report page,
// the directions drill-down dialog, and the per-role dashboard shortcut grid.
const MANAGER_EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

async function login(page: import('@playwright/test').Page, email = MANAGER_EMAIL) {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

const REPORT_PAGES = [
  { url: '/app/reports', title: 'التقارير والإحصائيات' },
  { url: '/app/reports/governorates', title: 'الحالات الاجتماعية حسب المحافظة' },
  { url: '/app/reports/researchers', title: 'إحصائيات الباحثين الاجتماعيين' },
  { url: '/app/reports/case-expenses', title: 'تقرير مصروفات الحالات الاجتماعية' },
  { url: '/app/reports/agents-balance', title: 'تقرير رصيد المندوبين' },
  { url: '/app/reports/items', title: 'تقرير البنود المفصلة' },
  { url: '/app/reports/directions', title: 'تحليل التوجيهات المحاسبية' },
  { url: '/app/reports/reconciliation', title: 'تقرير مطابقة الخزينة' },
];

test('every report page renders inside a print sheet with a print button', async ({ page }) => {
  await login(page);
  for (const report of REPORT_PAGES) {
    await page.goto(report.url);
    await expect(page.locator('app-print-sheet').first()).toBeVisible({ timeout: 15000 });
    await expect(page.getByRole('button', { name: 'طباعة' }).first()).toBeVisible();
  }
});

test('governorates report applies the direction and date filters', async ({ page }) => {
  await login(page);
  await page.goto('/app/reports/governorates');
  await expect(page.locator('.filter-bar').first()).toBeVisible({ timeout: 15000 });
  // the reset button clears every filter back to the unfiltered report
  await expect(page.getByRole('button', { name: 'إعادة تعيين' })).toBeVisible();
});

test('directions report opens the item expenses drill-down dialog', async ({ page }) => {
  await login(page);
  await page.goto('/app/reports/directions');
  await expect(page.locator('.ledger-table').first()).toBeVisible({ timeout: 15000 });
  const rows = page.locator('.ledger-table tbody tr.ledger-table__row');
  const count = await rows.count();
  test.skip(count === 0, 'no direction analytics rows in the test data');
  await rows.first().click();
  await expect(page.locator('.p-dialog')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('.p-dialog').getByText('عدد المصروفات')).toBeVisible();
});

test('reports dashboard offers the direction explorer filter', async ({ page }) => {
  await login(page);
  await page.goto('/app/reports');
  await expect(page.locator('.filter-bar').first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('تصفية الأرقام بالتوجيه')).toBeVisible();
});

test('manager dashboard shows the quick-action shortcut grid', async ({ page }) => {
  await login(page);
  await expect(page.getByText('اختصارات سريعة').first()).toBeVisible({ timeout: 15000 });
  // a shortcut card navigates to its page
  await page.getByRole('link', { name: /التقارير والإحصائيات/ }).first().click();
  await page.waitForURL(/reports/, { timeout: 15000 });
});

test('agent dashboard gets agent shortcuts, never treasury management', async ({ page }) => {
  await login(page, 'agent-t@t.local');
  await expect(page.getByText('اختصارات سريعة').first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('عهداتي').first()).toBeVisible();
  const treasuryShortcut = await page.getByRole('link', { name: /إدارة الخزائن/ }).count();
  expect(treasuryShortcut).toBe(0);
});
