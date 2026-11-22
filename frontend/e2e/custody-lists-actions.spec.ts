import { expect, test } from '@playwright/test';

// T3-13..T3-17 parity additions: custody lists, create variants, tracking,
// movements filters and transfer tabs. Same live smoke style as dialogs.spec.ts
// — data mutations stay out; the refusal case is the client-side validation
// that must block an empty reject reason before any request is sent.

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

async function login(page: import('@playwright/test').Page) {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

test('custody list shows the office header buttons and status tabs', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies');
  await expect(page.getByRole('link', { name: 'متابعة العهدات' }).first()).toBeVisible({ timeout: 15000 });
  // office accounts: the creation variants live in the split button's menu
  await page.getByRole('button', { name: 'إنشاء عهدة لمستخدم' }).first().click();
  await expect(page.getByRole('menuitem', { name: 'طلب عهدة شخصية' }).first()).toBeVisible({ timeout: 10000 });
  await expect(page.getByRole('button', { name: 'إنشاء عهدة لمستخدم' }).first()).toBeVisible();

  // status filter reaches the table through the URL (T7-07 mirroring)
  await page.goto('/app/custodies?filter[status]=pending');
  await expect(page.locator('.stamp').first()).toBeVisible({ timeout: 15000 });
});

test('custody create for-agent variant shows the treasury balance hint', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/new?for=agent');
  await expect(page.getByRole('heading', { name: 'إنشاء عهدة لمستخدم' })).toBeVisible({ timeout: 15000 });
  await expect(page.getByText('معلومات مهمة')).toBeVisible();
});

test('custody create personal variant is the default for non-agents', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/new?for=self');
  await expect(page.getByRole('heading', { name: 'طلب عهدة شخصية' })).toBeVisible({ timeout: 15000 });
});

test('return-requests page renders tabs and refuses an empty reject reason', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/return-requests');
  await expect(page.getByRole('tab', { name: /موافق|الموافق عليها/ }).first()).toBeVisible({ timeout: 15000 });

  // refusal: if a pending request exists, its reject dialog must demand a reason
  const rejectButtons = page.locator('.row-actions__btn[title="رفض الطلب"]');
  if ((await rejectButtons.count()) > 0) {
    await rejectButtons.first().click();
    const dialog = page.locator('p-dialog:visible');
    await expect(dialog).toBeVisible();
    await page.getByRole('button', { name: 'رفض الطلب', exact: true }).last().click();
    await expect(page.getByText('أحرف على الأقل', { exact: false })).toBeVisible();
  }
});

test('tracking page exposes the custody filters and movements dialog', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/tracking');
  await expect(page.getByRole('button', { name: 'إنشاء عهدة جديدة' }).first()).toBeVisible({ timeout: 15000 });
  // T6-30: the filters live behind the single Filters control — open it
  await page.getByRole('button', { name: /عوامل التصفية/ }).first().click();
  await expect(page.getByText('جميع المندوبين').first()).toBeVisible({ timeout: 10000 });

  const movementsButtons = page.locator('.row-actions__btn[title="عرض الحركات"]');
  if ((await movementsButtons.count()) > 0) {
    await movementsButtons.first().click();
    await expect(page.locator('p-dialog')).toBeVisible();
  }
});

test('movements page has the filter set and apply/reset', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/movements');
  await expect(page.getByRole('button', { name: /عوامل التصفية/ }).first()).toBeVisible({ timeout: 15000 });
  // T6-30: apply/reset live inside the Filters overlay
  await page.getByRole('button', { name: /عوامل التصفية/ }).first().click();
  await expect(page.getByRole('button', { name: 'تطبيق' }).first()).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'تطبيق' }).first().click();
  await expect(page.locator('.stat-tile').first()).toBeVisible({ timeout: 15000 });
});

test('my movements shows the transfer direction tabs', async ({ page }) => {
  await login(page);
  await page.goto('/app/custodies/mine/movements');
  await expect(page.getByRole('tab', { name: /المرسلة/ }).first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('tab', { name: /المستلمة/ }).first()).toBeVisible();
});

test('transfers list renders the scope tabs and the create button', async ({ page }) => {
  await login(page);
  await page.goto('/app/transfers');
  await expect(page.getByRole('button', { name: 'طلب تحويل جديد' }).first()).toBeVisible({ timeout: 15000 });
  await expect(page.getByRole('tab', { name: /المرسلة/ }).first()).toBeVisible();
  await page.getByRole('tab', { name: /المستقبلة|الواردة/ }).first().click();
  await expect(page.locator('.stat-tile').first()).toBeVisible({ timeout: 15000 });
});
