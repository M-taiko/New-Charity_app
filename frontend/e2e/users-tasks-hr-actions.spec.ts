import { expect, test } from '@playwright/test';

// T3-60 / T3-80 / T3-90 / T3-91 — one happy path + one refusal per new action,
// against the live PHP server (same style as dialogs.spec.ts). The login
// account is a manager, so every manage_users / task / hr button is visible.
const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

async function login(page: import('@playwright/test').Page) {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
}

/** first row id of a list endpoint, through the logged-in session */
async function firstId(page: import('@playwright/test').Page, path: string): Promise<number | null> {
  const res = await page.request.get(`/api/v1/${path}?per_page=1`);
  if (!res.ok()) {
    return null;
  }
  const body = (await res.json()) as { data?: { id?: number }[] };
  return body.data?.[0]?.id ?? null;
}

/** pick a p-select option different from the current value (a change reloads) */
async function pickOption(page: import('@playwright/test').Page, opener: string, target: string): Promise<void> {
  await page.locator(opener).click();
  await page.getByRole('option', { name: target, exact: true }).first().click();
}

/** a month value other than the given one (1-12) */
function otherMonth(current: number): string {
  return String(current === 1 ? 2 : 1);
}

// ── T3-60 users ───────────────────────────────────────────────────────────

test('users list shows view + edit row actions', async ({ page }) => {
  await login(page);
  await page.goto('/app/users');
  await expect(page.locator('.ledger-table__row').first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('.row-actions a[title="عرض"]').first()).toBeVisible();
  await expect(page.locator('.row-actions a[title="تعديل"]').first()).toBeVisible();
});

test('user detail: delete confirm dialog opens and can be refused', async ({ page }) => {
  await login(page);
  const id = await firstId(page, 'users');
  test.skip(id === null, 'no users seeded');
  await page.goto(`/app/users/${id}`);
  const del = page.getByRole('button', { name: 'حذف' });
  const canDelete = await del.isVisible().catch(() => false);
  test.skip(!canDelete, 'only the signed-in account exists — self-delete is hidden by design');
  await del.click();
  await expect(page.getByText('هل تريد حذف')).toBeVisible({ timeout: 10000 });
  await page.getByRole('button', { name: 'إلغاء' }).click();
  await expect(page.getByText('هل تريد حذف')).toBeHidden({ timeout: 10000 });
  await expect(page).toHaveURL(new RegExp(`/app/users/${id}`));
});

// ── T3-80 tasks ───────────────────────────────────────────────────────────

test('task detail: empty comment cannot be sent (refusal), typed comment posts', async ({ page }) => {
  await login(page);
  const id = await firstId(page, 'tasks');
  test.skip(id === null, 'no tasks seeded');
  await page.goto(`/app/tasks/${id}`);
  const send = page.getByRole('button', { name: 'إرسال التعليق' });
  const formOpen = await send.isVisible().catch(() => false);
  test.skip(!formOpen, 'task is completed/cancelled — comments locked by design');
  // refusal: submit is disabled while the textarea is empty
  await expect(send).toBeDisabled();
  await page.locator('#task-comment').fill('تعليق اختباري من الاختبار الآلي');
  await expect(send).toBeEnabled();
  await send.click();
  await expect(page.getByText('تعليق اختباري من الاختبار الآلي').first()).toBeVisible({ timeout: 15000 });
});

test('task detail: delegate dialog opens and refuses an empty selection', async ({ page }) => {
  await login(page);
  const id = await firstId(page, 'tasks');
  test.skip(id === null, 'no tasks seeded');
  await page.goto(`/app/tasks/${id}`);
  const delegate = page.getByRole('button', { name: 'تفويض إلى شخص آخر' });
  const canDelegate = await delegate.isVisible().catch(() => false);
  test.skip(!canDelegate, 'task is closed — delegation hidden by design');
  await delegate.click();
  await expect(page.locator('#delegate-user')).toBeVisible({ timeout: 10000 });
  // refusal: confirming without choosing a user keeps the dialog open, task unchanged
  await page.getByRole('button', { name: 'تأكيد التفويض' }).click();
  await expect(page.locator('#delegate-user')).toBeVisible({ timeout: 5000 });
  await expect(page).toHaveURL(new RegExp(`/app/tasks/${id}`));
});

test('task detail: cancel confirmation can be refused without changing the task', async ({ page }) => {
  await login(page);
  const id = await firstId(page, 'tasks');
  test.skip(id === null, 'no tasks seeded');
  await page.goto(`/app/tasks/${id}`);
  // only offered on open tasks; skip when the seeded task is closed
  const cancel = page.getByRole('button', { name: 'إلغاء', exact: true });
  if (!(await cancel.isVisible().catch(() => false))) {
    test.skip(true, 'task is completed/cancelled — no cancel action');
  }
  await cancel.click();
  await expect(page.getByText('هل تريد إلغاء المهمة')).toBeVisible({ timeout: 10000 });
  await page.getByRole('dialog').getByRole('button', { name: 'إلغاء' }).click();
  await expect(page.getByText('هل تريد إلغاء المهمة')).toBeHidden({ timeout: 10000 });
});

// ── T3-90 salaries ────────────────────────────────────────────────────────

test('salaries list: month/year filters and the export button exist', async ({ page }) => {
  await login(page);
  await page.goto('/app/hr/salaries');
  await expect(page.getByRole('button', { name: 'تصدير تقرير المرتبات' })).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#sal-filter-month')).toBeVisible();
  await expect(page.locator('#sal-filter-year')).toBeVisible();
  // changing the month reloads the list with the month query param
  const [request] = await Promise.all([
    page.waitForRequest((r) => r.url().includes('/api/v1/hr/salaries') && r.url().includes('month='), { timeout: 15000 }),
    pickOption(page, '#sal-filter-month', otherMonth(new Date().getMonth() + 1)),
  ]);
  expect(request).toBeTruthy();
});

test('salary detail: add-allowance dialog opens with the controller fields', async ({ page }) => {
  await login(page);
  const id = await firstId(page, 'hr/salaries');
  test.skip(id === null, 'no salaries seeded');
  await page.goto(`/app/hr/salaries/${id}`);
  // hidden once the salary is paid (Blade parity)
  const add = page.getByRole('button', { name: 'إضافة', exact: true });
  const isOpen = await add.isVisible().catch(() => false);
  test.skip(!isOpen, 'salary is paid — allowance action hidden by design');
  await add.click();
  await expect(page.locator('#allowance-type')).toBeVisible({ timeout: 10000 });
  await expect(page.locator('#allowance-name')).toBeVisible();
  await expect(page.locator('#allowance-amount')).toBeVisible();
  // refusal: submitting without the required name/amount keeps the dialog open
  await page.getByRole('dialog').getByRole('button', { name: 'إضافة', exact: true }).click();
  await expect(page.locator('#allowance-name')).toBeVisible({ timeout: 5000 });
});

// ── T3-91 attendance ──────────────────────────────────────────────────────

test('attendance month/year selectors reload the list', async ({ page }) => {
  await login(page);
  await page.goto('/app/hr/attendance');
  await expect(page.locator('#att-filter-month')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('#att-filter-year')).toBeVisible();
  const [request] = await Promise.all([
    page.waitForRequest((r) => r.url().includes('/api/v1/hr/attendance') && r.url().includes('year='), { timeout: 15000 }),
    pickOption(page, '#att-filter-year', String(new Date().getFullYear() - 2)),
  ]);
  expect(request).toBeTruthy();
});
