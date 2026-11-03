import { expect, test, type Page } from '@playwright/test';

// T3-100 → T3-103 comms actions: chat delete/polls, broadcasts targeting +
// deactivate/reactivate + topbar banner, notifications mark-all, profile picture.
// Run against the packed SPA on the PHP server (see playwright.config.ts).
// Labels are the merged ar.json strings (scripts/i18n-fragments/*.json).

const MANAGER = { email: 'donia.a5ra2019@gmail.com', password: '123456789' };

/** 1×1 transparent PNG for the upload happy path */
const PNG_PIXEL = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64',
);

async function login(page: Page, email = MANAGER.email): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', email);
  await page.fill('input[type="password"]', MANAGER.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20000 });
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 15000 });
}

test('chat: delete own message — confirm dialog, refusal cancel, then delete', async ({ page }) => {
  await login(page);
  await page.goto('/app/chat');
  await expect(page.locator('.chat__composer')).toBeVisible({ timeout: 15000 });

  // seed a disposable message
  const marker = `رسالة حذف ${Date.now()}`;
  await page.fill('.chat__composer textarea', marker);
  await page.getByRole('button', { name: 'إرسال' }).click();
  await expect(page.getByText(marker)).toBeVisible({ timeout: 15000 });

  // refusal: cancel the confirm — the message must survive
  const row = page.locator('.chat__msg', { hasText: marker }).last();
  await row.locator('button[aria-label="حذف"]').click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog).toBeVisible();
  await dialog.getByRole('button', { name: 'إلغاء' }).click();
  await expect(page.getByText(marker)).toBeVisible();

  // happy path: confirm — the message disappears
  await row.locator('button[aria-label="حذف"]').click();
  await dialog.getByRole('button', { name: 'حذف' }).click();
  await expect(page.locator('.chat__msg', { hasText: marker })).toHaveCount(0, { timeout: 15000 });
});

test('chat: create poll → vote → close; empty question is refused locally', async ({ page }) => {
  await login(page);
  await page.goto('/app/chat');

  await page.getByRole('button', { name: 'إنشاء استفتاء' }).first().click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog).toBeVisible();

  // refusal: no question / no options → local validation, nothing is posted
  await dialog.getByRole('button', { name: 'إنشاء استفتاء' }).click();
  await expect(dialog.getByText('هذا الحقل مطلوب')).toBeVisible();
  await expect(dialog).toBeVisible();

  // happy path: question + two options
  const question = `استفتاء ${Date.now()}`;
  await dialog.locator('#poll-question').fill(question);
  await dialog.getByPlaceholder('الخيار 1').fill('نعم');
  await dialog.getByPlaceholder('الخيار 2').fill('لا');
  await dialog.getByRole('button', { name: 'إنشاء استفتاء' }).click();
  await expect(dialog).toBeHidden({ timeout: 15000 });
  await expect(page.getByText('تم إنشاء الاستفتاء')).toBeVisible({ timeout: 15000 });

  const poll = page.locator('.chat__msg', { hasText: question }).last();
  await expect(poll).toBeVisible({ timeout: 15000 });

  // vote own poll — the chosen option gets the check mark
  await poll.getByRole('button', { name: /نعم/ }).click();
  await expect(poll.getByRole('button', { name: /✓ نعم/ })).toBeVisible({ timeout: 15000 });

  // close it (creator) — closed stamp replaces the close button
  await poll.getByRole('button', { name: 'إغلاق التصويت' }).click();
  await expect(poll.getByText('استفتاء مغلق')).toBeVisible({ timeout: 15000 });

  // cleanup: remove the carrier message
  await poll.locator('button[aria-label="حذف"]').click();
  await page.locator('.p-dialog').getByRole('button', { name: 'حذف' }).click();
  await expect(page.locator('.chat__msg', { hasText: question })).toHaveCount(0, { timeout: 15000 });
});

test('broadcasts: create form has expiry + targeting; user target without a user is refused', async ({ page }) => {
  await login(page);
  await page.goto('/app/broadcasts');
  await page.getByRole('button', { name: 'إرسال رسالة عاجلة' }).click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog).toBeVisible();

  // T3-101 parity: expiry input + target select
  await expect(dialog.locator('#br-expires')).toHaveAttribute('type', 'datetime-local');
  await expect(dialog.locator('#br-target')).toBeVisible();

  // refusal: specific-user target with no user chosen
  await dialog.locator('#br-target').click();
  await page.getByRole('option', { name: 'لشخص محدد' }).click();
  await dialog.locator('#br-title').fill(`عنوان ${Date.now()}`);
  await dialog.locator('#br-msg').fill('نص اختبار');
  await dialog.getByRole('button', { name: 'إرسال رسالة عاجلة' }).click();
  await expect(dialog.getByText('هذا الحقل مطلوب')).toBeVisible();
  await expect(dialog).toBeVisible(); // stays open, nothing sent
});

test('broadcasts: row deactivate → reactivate cycle through the confirm dialog', async ({ page }) => {
  await login(page);
  await page.goto('/app/broadcasts');
  await expect(page.locator('.ledger-table, .empty-state').first()).toBeVisible({ timeout: 15000 });

  const deactivate = page.locator('.row-actions button[aria-label="إيقاف"]');
  if ((await deactivate.count()) === 0) {
    test.skip(true, 'no active broadcast row to toggle');
  }

  // deactivate (confirm) — the row action flips to reactivate
  await deactivate.first().click();
  const dialog = page.locator('.p-dialog');
  await expect(dialog.getByText(/إيقاف الرسالة/)).toBeVisible();
  await dialog.getByRole('button', { name: 'إيقاف' }).click();
  const reactivate = page.locator('.row-actions button[aria-label="تنشيط"]');
  await expect(reactivate.first()).toBeVisible({ timeout: 15000 });

  // reactivate restores an active broadcast
  await reactivate.first().click();
  await expect(dialog.getByText(/إعادة تفعيل الرسالة/)).toBeVisible();
  await dialog.getByRole('button', { name: 'تنشيط' }).click();
  await expect(deactivate.first()).toBeVisible({ timeout: 15000 });
});

test('topbar: active broadcast banner shows and dismisses', async ({ page }) => {
  await login(page);
  const banner = page.locator('.broadcast-banner');
  if ((await banner.count()) === 0) {
    test.skip(true, 'no active broadcast targeted at this user');
  }
  await expect(banner).toBeVisible();
  await banner.locator('button[aria-label="إخفاء الرسالة"]').click();
  await expect(banner).toHaveCount(0);
  // dismissal persists for the session — a reload keeps it hidden
  await page.reload();
  await expect(page.locator('.broadcast-banner')).toHaveCount(0);
});

test('notifications: mark all as read from the page', async ({ page }) => {
  await login(page);
  await page.goto('/app/notifications');
  const button = page.getByRole('button', { name: 'تعليم الكل كمقروء' });
  await expect(button).toBeVisible({ timeout: 15000 });
  await button.click();
  await expect(page.getByText('تم تعليم جميع الإشعارات كمقروءة')).toBeVisible({ timeout: 15000 });
});

test('profile: picture upload — invalid type refused, PNG accepted, avatar appears in the topbar', async ({ page }) => {
  await login(page);
  await page.goto('/app/profile');
  const input = page.locator('#p-picture');
  await expect(input).toBeAttached();

  // refusal: wrong file type → inline error, no upload button
  await input.setInputFiles({ name: 'not-an-image.txt', mimeType: 'text/plain', buffer: Buffer.from('x') });
  await expect(page.getByText('نوع الصورة غير مدعوم. المسموح: JPEG أو PNG أو GIF')).toBeVisible();
  await expect(page.getByRole('button', { name: 'حفظ الصورة' })).toHaveCount(0);

  // happy path: valid PNG → save → toast + avatar in the user menu
  await input.setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: PNG_PIXEL });
  const save = page.getByRole('button', { name: 'حفظ الصورة' });
  await expect(save).toBeVisible();
  await save.click();
  await expect(page.getByText('تم تحديث الصورة الشخصية بنجاح')).toBeVisible({ timeout: 15000 });
  await expect(page.locator('app-user-menu img.user-chip__avatar')).toBeVisible({ timeout: 15000 });
});
