import { expect, test, type Page } from '@playwright/test';
import { actionJson, answerList, confirmButton, dialog, gate, label, login, mockApi, undoToast } from './n06-support';

/**
 * N-06 — one request per submit, a confirm that says what it confirms, and
 * the undo of a cancelled transfer and of "mark as read".
 *
 * Against the real app. Every write is answered by this file and nothing
 * reaches the database; the lists are the server's real answers with the
 * test's rows in them. Needs the PHP server + the packed SPA.
 */

const NOW = new Date().toISOString();
const TRANSFER = { id: 9201, sender: 'مرسل تجريبي', receiver: 'مستلم تجريبي', custody_id: 7, amount: '750.00', status: 'pending', notes: null, requested_at: NOW, decided_at: null, decided_by: null, rejection_reason: null, can_decide: false, can_cancel: true };
const TRANSFER_DETAIL = { id: 9001, sender: 'مرسل تجريبي', receiver: 'مستلم تجريبي', custody_id: 7, amount: '1234.50', status: 'pending', notes: null, rejection_reason: null, requested_at: NOW, decided_at: null, decided_by: null, cancelled_at: null, cancelled_by: null, can_decide: true, can_cancel: false };

async function fulfillJson(route: { fulfill: (options: { status: number; contentType: string; body: string }) => Promise<void> }, status: number, body: string): Promise<void> {
  await route.fulfill({ status, contentType: 'application/json', body });
}

/** the purchase form with its required fields filled */
async function fillPurchaseForm(page: Page, title: string): Promise<void> {
  await page.goto('/app/procurement/purchase/new', { waitUntil: 'domcontentloaded' });
  const field = page.locator('input[name="title"]');
  await field.waitFor({ state: 'visible', timeout: 25_000 });
  await field.fill(title);
  for (const index of [0, 1]) {
    await page.locator('form p-select').nth(index).click();
    await page.locator('.p-select-overlay .p-select-option').first().click();
  }
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test('a double-clicked submit sends one POST — and a refused one is not sent again behind the user', async ({ page }) => {
  test.setTimeout(180_000);
  const api = await mockApi(page);
  const held = gate();
  let status = 200;
  const purchase = /\/purchase-requests$/;
  api.onWrite = async (write, route) => {
    if (!purchase.test(write.path)) {
      await route.abort();
      return;
    }
    await held.wait;
    await fulfillJson(route, status, status === 200 ? actionJson('تم إنشاء الطلب') : JSON.stringify({ message: 'خطأ من الخادم للاختبار' }));
  };

  await fillPurchaseForm(page, 'طلب شراء لقياس الإرسال المزدوج');
  const submit = page.locator('form button[type="submit"]');
  await submit.dblclick();

  // while the request is held: the button is disabled with its spinner, and ONE request went out
  await expect(submit).toBeDisabled();
  expect(api.count('POST', purchase), 'a double click is one request').toBe(1);

  held.open();
  await page.waitForURL(/procurement\/purchase$/, { timeout: 20_000 });
  expect(api.count('POST', purchase), 'nothing followed the first request').toBe(1);

  // a server error: shown once, the form keeps what was typed — and the request is not repeated
  status = 500;
  await fillPurchaseForm(page, 'طلب ثان');
  await submit.click();
  await expect(page.locator('.toast-host__item--error')).toBeVisible();
  await expect(page.locator('form .dialog__alert')).toContainText('خطأ من الخادم للاختبار');
  // the interceptor used to send every failed request again after 250 ms
  await page.waitForTimeout(1200);
  expect(api.count('POST', purchase), 'a refused write is not retried').toBe(2);
  await expect(page.locator('input[name="title"]')).toHaveValue('طلب ثان');
});

test('a held Ctrl+Enter in the reason dialog sends one request, under the field the server validates', async ({ page }) => {
  test.setTimeout(120_000);
  const api = await mockApi(page);
  await page.route('**/api/v1/custody-transfers/9001', (route) => route.fulfill({ json: { data: TRANSFER_DETAIL, meta: null } }));
  const reject = /\/custody-transfers\/9001\/reject$/;
  const held = gate();
  api.onWrite = async (write, route) => {
    if (!reject.test(write.path)) {
      await route.abort();
      return;
    }
    await held.wait;
    await fulfillJson(route, 200, actionJson('تم رفض التحويل'));
  };

  await page.goto('/app/transfers/9001', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: label('transfer.detail.reject'), exact: true }).click();

  // the confirm says what it is about: the money first, then the two parties
  const details = dialog(page).locator('.confirm-details');
  await expect(details).toContainText('1,234.50');
  await expect(details).toContainText(TRANSFER_DETAIL.sender);
  await expect(details).toContainText(TRANSFER_DETAIL.receiver);
  await expect(details.locator('dt').first()).toHaveText(label('common.amount_egp'));

  await dialog(page).locator('textarea').fill('سبب الرفض للاختبار');
  // the key held down: four presses before any answer
  for (let press = 0; press < 4; press++) {
    await page.keyboard.press('Control+Enter');
  }
  expect(api.count('POST', reject), 'a held key is one request').toBe(1);

  // the field the endpoint validates (`reason` was refused with a validation error every time)
  const sent = JSON.parse(api.writes.find((write) => reject.test(write.path))?.body ?? '{}') as Record<string, unknown>;
  expect(sent).toHaveProperty('rejection_reason', 'سبب الرفض للاختبار');
  expect(sent).not.toHaveProperty('reason');

  held.open();
  await expect(dialog(page)).toBeHidden();
  await expect(page.locator('.toast-host__text')).toHaveText('تم رفض التحويل');
});

test('cancelling a transfer shows its amount, sends once, and its undo puts it back', async ({ page }) => {
  test.setTimeout(120_000);
  const api = await mockApi(page);
  const state = { status: 'pending' };
  await page.route('**/api/v1/custody-transfers?*', (route) =>
    answerList(route, (json) => {
      json.data = [{ ...TRANSFER, status: state.status, can_cancel: state.status === 'pending' }];
      json.meta = { ...(json.meta as Record<string, unknown>), total: 1 };
    }),
  );
  const cancel = /\/custody-transfers\/9201\/cancel$/;
  const restore = /\/custody-transfers\/9201\/restore$/;
  const held = gate();
  api.onWrite = async (write, route) => {
    if (cancel.test(write.path)) {
      await held.wait;
      state.status = 'cancelled';
      await fulfillJson(route, 200, actionJson(label('transfers.cancel_success')));
      return;
    }
    if (restore.test(write.path)) {
      state.status = 'pending';
      await fulfillJson(route, 200, actionJson('تمت إعادة التحويل المُلغى وتجميد مبلغه من جديد'));
      return;
    }
    await route.abort();
  };

  await page.goto('/app/transfers', { waitUntil: 'domcontentloaded' });
  const stamp = page.locator('app-data-table .stamp').first();
  await expect(stamp).toHaveText(label('enums.transfer_status.pending'));

  await page.getByRole('button', { name: label('transfers.cancel'), exact: true }).first().click();
  const details = dialog(page).locator('.confirm-details');
  await expect(details).toContainText('750.00');
  await expect(details).toContainText(TRANSFER.receiver);

  await confirmButton(page).dblclick();
  expect(api.count('POST', cancel), 'a double-clicked confirm is one request').toBe(1);
  held.open();

  await expect(stamp).toHaveText(label('enums.transfer_status.cancelled'));
  const undo = page.locator('.toast-host__undo');
  await expect(undo).toBeVisible();
  await expect(page.locator('.toast-host__text')).toHaveText(label('transfers.cancel_success'));

  await undo.click();
  await expect.poll(() => api.count('POST', restore)).toBe(1);
  expect(api.count('POST', cancel), 'the undo is the inverse, not the action again').toBe(1);
  // the list shows the transfer pending again
  await expect(stamp).toHaveText(label('enums.transfer_status.pending'));
  await expect(page.locator('.toast-host__text')).toHaveText('تمت إعادة التحويل المُلغى وتجميد مبلغه من جديد');
});

test('marking notifications read can be undone — the whole batch by its instant, one alone by its id', async ({ page }) => {
  test.setTimeout(120_000);
  const api = await mockApi(page);
  const ids = [9301, 9302, 9303];
  const MARKED_AT = '2026-11-25T10:00:00+00:00';
  // when each was read (null = unread): 9303 was read long before the batch
  const readAt: Record<number, string | null> = { 9301: null, 9302: null, 9303: '2026-10-01T08:00:00+00:00' };
  await page.route('**/api/v1/notifications?*', (route) =>
    answerList(route, (json) => {
      json.data = ids.map((id) => ({ id, title: `إشعار تجريبي ${id}`, message: `نص الإشعار ${id}`, type: 'info', is_read: readAt[id] !== null, link: null, created_at: NOW }));
      json.meta = { ...(json.meta as Record<string, unknown>), total: ids.length };
    }),
  );
  const unread = /\/notifications\/unread$/;
  api.onWrite = async (write, route) => {
    if (/\/notifications\/mark-all-read$/.test(write.path)) {
      // like the endpoint: one instant for everything that was unread
      ids.forEach((id) => { readAt[id] ??= MARKED_AT; });
      await fulfillJson(route, 200, actionJson('تم تعليم جميع الإشعارات كمقروءة', { marked_at: MARKED_AT }));
      return;
    }
    if (unread.test(write.path)) {
      // like the endpoint: `since` puts back what was read at or after it; `ids` puts back those
      const body = JSON.parse(write.body) as { since?: string; ids?: number[] };
      const back = body.ids ?? ids.filter((id) => readAt[id] !== null && String(readAt[id]) >= String(body.since));
      back.forEach((id) => { readAt[id] = null; });
      await fulfillJson(route, 200, actionJson('تم التراجع: أعيدت الإشعارات إلى غير مقروءة', { count: back.length }));
      return;
    }
    const one = /\/notifications\/(\d+)\/read$/.exec(write.path);
    if (one !== null) {
      readAt[Number(one[1])] = new Date().toISOString();
      await fulfillJson(route, 200, actionJson('تم'));
      return;
    }
    await route.abort();
  };

  await page.goto('/app/notifications', { waitUntil: 'domcontentloaded' });
  const unreadRows = (): number => page.locator('.notice--unread').count();
  await expect.poll(unreadRows).toBe(2);

  // the whole batch
  await page.getByRole('button', { name: label('notifications.mark_all') }).click();
  await expect(page.locator('.toast-host__undo')).toBeVisible();
  await expect.poll(unreadRows).toBe(0);
  await page.locator('.toast-host__undo').click();
  await expect.poll(() => api.count('POST', unread)).toBe(1);
  const batch = JSON.parse(api.writes.find((write) => unread.test(write.path))?.body ?? '{}') as Record<string, unknown>;
  expect(batch).toEqual({ since: MARKED_AT });
  await expect.poll(unreadRows, 'the batch is unread again').toBe(2);

  // one alone
  await page.locator('.notice').filter({ hasText: 'إشعار تجريبي 9301' }).locator('.notice__link').click();
  await expect.poll(() => api.count('POST', /\/notifications\/9301\/read$/)).toBe(1);
  await expect(undoToast(page).locator('.toast-host__text')).toHaveText(label('notification.marked_read'));
  await undoToast(page).locator('.toast-host__undo').click();
  await expect.poll(() => api.count('POST', unread)).toBe(2);
  const single = JSON.parse(api.writes.filter((write) => unread.test(write.path)).at(-1)?.body ?? '{}') as Record<string, unknown>;
  expect(single).toEqual({ ids: [9301] });
  await expect.poll(unreadRows).toBe(2);
});
