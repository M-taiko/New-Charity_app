import { mkdirSync } from 'node:fs';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { actionJson, answerList, confirmButton, dialog, label, login, mockApi } from './n06-support';

/**
 * N-04 — bulk actions on the real lists. The rows are the server's; the bulk
 * endpoints are answered by the test, so no record is ever changed. What is
 * checked is everything around them: the selection (a row, the page, the
 * keyboard), the bar, the confirm with the count and the exact total, the one
 * request, the toast that lists what was refused, and the selection clearing
 * with a filter.
 *
 * Needs the PHP server + the packed SPA.
 */

const OUT = 'e2e/screenshots/n04';
const RAW_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;

const card = (page: Page): Locator => page.locator('app-data-table .ledger-table');
/** the checkboxes a user can reach: the column's on a wide screen, the ones in the first cell on a phone */
const rowBoxes = (page: Page): Locator => card(page).locator('tbody input.ledger-table__checkbox:visible');
const pageBox = (page: Page): Locator => card(page).locator('thead input.ledger-table__checkbox:visible');
const bar = (page: Page): Locator => page.locator('.ledger-table__bulk');
const barCount = (page: Page): Locator => bar(page).locator('.ledger-table__bulk-count');
const selectedRows = (page: Page): Locator => card(page).locator('tbody tr[aria-selected="true"]');
/** a button of the bar by its exact name ("مراجعة المحدد" must not also match "إلغاء مراجعة المحدد") */
const barButton = (page: Page, name: string): Locator => bar(page).getByRole('button', { name, exact: true });

interface ExpenseRow {
  readonly id: number;
  readonly amount: string;
  readonly description: string;
}

/** the server's answer to the bulk review: the first id is done, the second refused — per id, never all-or-nothing */
function bulkAnswer(ids: readonly number[], action: 'review' | 'unreview'): string {
  const results = ids.map((id, index) =>
    index === 1
      ? { id, status: 'refused', code: 'expense.already_reviewed', message: 'المصروف مراجَع أصلاً' }
      : { id, status: 'done' },
  );
  const done = results.filter((line) => line.status === 'done').length;
  const verb = action === 'review' ? 'تمت مراجعة' : 'تم إلغاء مراجعة';

  return JSON.stringify({
    data: { results, done, refused: results.length - done, amount: '0.00' },
    message: `${verb} ${done}`,
    level: 'warning',
    meta: { action, requested: ids.length },
  });
}

async function openList(page: Page, route: string): Promise<ExpenseRow[]> {
  const answered = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return response.request().method() === 'GET' && url.pathname === `/api/v1/${route}` && url.search !== '';
  }, { timeout: 30_000 });
  await page.goto(`/app/${route}`, { waitUntil: 'domcontentloaded' });
  const rows = ((await (await answered).json()) as { data: ExpenseRow[] }).data;
  await card(page).locator('tbody tr[tabindex="0"]').first().waitFor({ timeout: 30_000 });
  await page.waitForTimeout(500);

  return rows;
}

/** every request to the bulk endpoints, as the test receives them */
async function mockBulk(page: Page, answer: (path: string, body: Record<string, unknown>) => string): Promise<{ calls: { path: string; body: Record<string, unknown> }[] }> {
  const calls: { path: string; body: Record<string, unknown> }[] = [];
  await page.route('**/api/v1/expenses/bulk-review', async (route) => {
    const body = JSON.parse(route.request().postData() ?? '{}') as Record<string, unknown>;
    calls.push({ path: '/expenses/bulk-review', body });
    await route.fulfill({ status: 200, contentType: 'application/json', body: answer('/expenses/bulk-review', body) });
  });
  await page.route('**/api/v1/social-cases/bulk-active', async (route) => {
    const body = JSON.parse(route.request().postData() ?? '{}') as Record<string, unknown>;
    calls.push({ path: '/social-cases/bulk-active', body });
    await route.fulfill({ status: 200, contentType: 'application/json', body: answer('/social-cases/bulk-active', body) });
  });
  return { calls };
}

/** the total of amounts, by hand: whole piasters */
function totalOf(rows: readonly ExpenseRow[]): string {
  const piasters = rows.reduce((sum, row) => sum + Math.round(Number(row.amount) * 100), 0);

  return `${Math.floor(piasters / 100)}.${String(piasters % 100).padStart(2, '0')}`;
}

/** the header cells that are sorted, as "field:direction" */
async function sortedHeaders(page: Page): Promise<string[]> {
  return card(page).locator('thead th[aria-sort]').evaluateAll((cells) =>
    cells.filter((cell) => cell.getAttribute('aria-sort') !== 'none').map((cell) => `${cell.getAttribute('data-field')}:${cell.getAttribute('aria-sort')}`),
  );
}

function moneyText(value: string): string {
  const [whole, fraction] = value.split('.');

  return `${Number(whole).toLocaleString('en-US')}.${fraction}`;
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test('"expenses": two rows selected, one request, and the refused id listed — never all-or-nothing', async ({ page }) => {
  test.setTimeout(150_000);
  const api = await mockApi(page);
  const bulk = await mockBulk(page, (path, body) => bulkAnswer(body.ids as number[], body.action as 'review'));
  const rows = await openList(page, 'expenses');
  expect(rows.length).toBeGreaterThan(2);
  const picked = rows.slice(0, 2);
  await expect(bar(page)).toHaveCount(0);

  // the keyboard reaches a row's checkbox, and Space selects it
  await rowBoxes(page).first().focus();
  await page.keyboard.press('Space');
  await expect(bar(page)).toBeVisible();
  await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', '1'));
  // the click on a checkbox is not a click on the row: the quick view (N-03) does not open
  await rowBoxes(page).nth(1).check();
  await expect(page.locator('.p-dialog.quick-view')).toHaveCount(0);
  await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', '2'));
  await expect(selectedRows(page)).toHaveCount(2);
  // a selection column with a select-all for the page, in its mixed state now
  await expect(pageBox(page)).toHaveAttribute('aria-label', label('table.bulk.select_all'));
  expect(await pageBox(page).evaluate((el) => (el as HTMLInputElement).indeterminate)).toBe(true);
  await expect(bar(page)).toHaveAttribute('role', 'region');
  await expect(bar(page)).toHaveAttribute('aria-label', label('table.bulk.bar_label'));
  const barText = await bar(page).innerText();
  const raw = RAW_KEY.exec(barText);
  expect(raw, `raw key "${raw?.[0] ?? ''}" in the bar`).toBeNull();

  // the confirm: the count and the exact total of what was selected
  await barButton(page, label('expense.bulk.review_selected')).click();
  await expect(dialog(page)).toBeVisible();
  const details = dialog(page).locator('.confirm-details');
  await expect(details).toContainText(label('expense.bulk.count'));
  await expect(details).toContainText(moneyText(totalOf(picked)));
  await expect(dialog(page)).toContainText(label('expense.bulk.review_confirm').replace('{{count}}', '2').replace('{{total}}', moneyText(totalOf(picked))));

  // nothing was sent before the confirm
  expect(bulk.calls).toEqual([]);
  const sent = page.waitForRequest((request) => new URL(request.url()).pathname === '/api/v1/expenses/bulk-review', { timeout: 10_000 });
  await confirmButton(page).click();
  await sent;
  await expect.poll(() => bulk.calls.length).toBe(1);
  expect(bulk.calls[0]).toEqual({ path: '/expenses/bulk-review', body: { action: 'review', ids: picked.map((row) => row.id) } });

  // the toast says what was done, and lists the refused id with its reason
  const toast = page.locator('.toast-host__item').last();
  await expect(toast).toContainText('تمت مراجعة 1');
  await expect(toast).toContainText(`#${picked[1].id} (المصروف مراجَع أصلاً)`);
  await expect(toast).toContainText(label('table.bulk.failed_list').replace('{{list}}', ''));
  // the selection is gone with the action
  await expect(bar(page)).toHaveCount(0);
  await expect(selectedRows(page)).toHaveCount(0);
  // and nothing else was written
  expect(api.writes.filter((write) => !/bulk-review/.test(write.path))).toEqual([]);
});

test('"expenses": select-all selects the page, and a filter change clears the selection', async ({ page }) => {
  test.setTimeout(150_000);
  await mockApi(page);
  await mockBulk(page, (path, body) => bulkAnswer(body.ids as number[], 'review'));
  const rows = await openList(page, 'expenses');
  expect(rows.length).toBeGreaterThan(2);

  await pageBox(page).check();
  await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', String(rows.length)));
  await expect(selectedRows(page)).toHaveCount(rows.length);
  expect(await pageBox(page).evaluate((el) => (el as HTMLInputElement).checked)).toBe(true);
  // one off: the page box is in its mixed state again
  await rowBoxes(page).first().uncheck();
  await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', String(rows.length - 1)));
  expect(await pageBox(page).evaluate((el) => (el as HTMLInputElement).indeterminate)).toBe(true);

  // the search changes: what was selected was selected in another list
  const filtered = page.waitForResponse((response) => {
    const url = new URL(response.url());
    return url.pathname === '/api/v1/expenses' && url.searchParams.get('q') === 'بنزين';
  }, { timeout: 20_000 });
  await card(page).locator('input[type="search"]').fill('بنزين');
  await filtered;
  await expect(bar(page)).toHaveCount(0);
  await expect(selectedRows(page)).toHaveCount(0);

  // "clear the selection" does the same without a request
  await card(page).locator('input[type="search"]').fill('');
  await page.waitForTimeout(1200);
  if ((await rowBoxes(page).count()) > 0) {
    await rowBoxes(page).first().check();
    await expect(bar(page)).toBeVisible();
    await barButton(page, label('table.bulk.clear')).click();
    await expect(bar(page)).toHaveCount(0);
    await expect(selectedRows(page)).toHaveCount(0);
  }
});

test('a double click on the confirm sends one request', async ({ page }) => {
  test.setTimeout(150_000);
  await mockApi(page);
  const bulk = await mockBulk(page, (path, body) => bulkAnswer(body.ids as number[], 'review'));
  await openList(page, 'expenses');
  await rowBoxes(page).first().check();
  await barButton(page, label('expense.bulk.review_selected')).click();
  await expect(dialog(page).locator('.confirm-details')).toBeVisible();
  await confirmButton(page).dblclick();
  await expect.poll(() => bulk.calls.length, { timeout: 10_000 }).toBe(1);
  await page.waitForTimeout(800);
  expect(bulk.calls).toHaveLength(1);
});

test('only an accountant or a manager may review the selection; only a manager may unreview it', async ({ page }) => {
  test.setTimeout(150_000);
  await mockApi(page);
  await mockBulk(page, (path, body) => bulkAnswer(body.ids as number[], body.action as 'review'));
  // the signed-in user as an accountant only
  await page.route('**/api/v1/me', async (route) => {
    const real = await route.fetch();
    const json = (await real.json()) as { data: { abilities: Record<string, boolean> } };
    await route.fulfill({ response: real, json: { ...json, data: { ...json.data, abilities: { ...json.data.abilities, is_manager: false, is_accountant: true } } } });
  });
  await openList(page, 'expenses');
  await rowBoxes(page).first().check();
  await expect(barButton(page, label('expense.bulk.review_selected'))).toBeVisible();
  await expect(barButton(page, label('expense.bulk.unreview_selected'))).toHaveCount(0);
});

test('"social cases": activate or deactivate the selection is for whoever manages cases, behind a confirm with the count', async ({ page }) => {
  test.setTimeout(150_000);
  const api = await mockApi(page);
  const bulk = await mockBulk(page, (path, body) =>
    JSON.stringify({
      data: { results: (body.ids as number[]).map((id) => ({ id, status: 'done' })), done: (body.ids as number[]).length, refused: 0, amount: '0.00' },
      message: body.active ? `تم تنشيط ${(body.ids as number[]).length}` : `تم إيقاف ${(body.ids as number[]).length}`,
      level: 'success',
      meta: { action: body.active ? 'activate' : 'deactivate', requested: (body.ids as number[]).length },
    }),
  );
  const rows = await openList(page, 'social-cases');
  expect(rows.length).toBeGreaterThan(1);
  const picked = rows.slice(0, 2);

  await rowBoxes(page).nth(0).check();
  await rowBoxes(page).nth(1).check();
  await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', '2'));
  await barButton(page, label('case.bulk.deactivate_selected')).click();
  await expect(dialog(page).locator('.confirm-details')).toContainText(label('case.bulk.count'));
  await expect(dialog(page)).toContainText(label('case.bulk.deactivate_confirm').replace('{{count}}', '2'));

  const sent = page.waitForRequest((request) => new URL(request.url()).pathname === '/api/v1/social-cases/bulk-active', { timeout: 10_000 });
  await confirmButton(page).click();
  await sent;
  expect(bulk.calls).toEqual([{ path: '/social-cases/bulk-active', body: { active: false, ids: picked.map((row) => row.id) } }]);
  await expect(page.locator('.toast-host__item').last()).toContainText('تم إيقاف 2');
  await expect(bar(page)).toHaveCount(0);
  expect(api.writes.filter((write) => !/bulk-active/.test(write.path))).toEqual([]);
});

for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`the selection and the bar — ${theme}, ${width}px`, async ({ page }) => {
      test.setTimeout(150_000);
      await page.setViewportSize({ width, height: 800 });
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((value) => sessionStorage.setItem('visual-matrix.theme', value), theme);
      await mockApi(page);
      await mockBulk(page, (path, body) => bulkAnswer(body.ids as number[], 'review'));
      await openList(page, 'expenses');
      mkdirSync(OUT, { recursive: true });
      const phone = width < 600;
      // the selection column on a wide screen; on a phone its box sits in the first, pinned cell
      const selectColumn = card(page).locator('thead th.ledger-table__cell-select');
      if (phone) {
        await expect(selectColumn).toBeHidden();
        await expect(card(page).locator('thead input.ledger-table__checkbox--inline')).toBeVisible();
        // …and that first column IS pinned (R5-03): the table scrolls under it. Round 6 first
        // shipped the pinning on the hidden selection cell, and this column scrolled 250px away
        const moved = await card(page).evaluate((element) => {
          const container = element.querySelector<HTMLElement>('.p-datatable-table-container');
          if (container === null) {
            return null;
          }
          const rendered = (cells: NodeListOf<HTMLElement>): HTMLElement | undefined => [...cells].find((cell) => getComputedStyle(cell).display !== 'none');
          const lead = (selector: string): HTMLElement | undefined => rendered(element.querySelectorAll<HTMLElement>(selector));
          const cells = [lead('.p-datatable-table thead th'), lead('.p-datatable-table tbody tr:first-child > td')];
          const left = (): (number | null)[] => cells.map((cell) => (cell ? Math.round(cell.getBoundingClientRect().left) : null));
          const before = left();
          const max = container.scrollWidth - container.clientWidth;
          // RTL: the browser counts from 0 at the start edge towards negative values
          container.scrollLeft = -Math.min(250, max);
          const scrolled = container.scrollLeft;
          const after = left();
          container.scrollLeft = 0;
          return { before, after, scrolled, scrolls: max > 1 };
        });
        expect(moved?.scrolls, 'the expenses table is wider than a phone card').toBe(true);
        expect(Math.abs(moved?.scrolled ?? 0), 'the table really scrolled').toBeGreaterThan(100);
        expect(moved?.after, 'the first cell of the header and of the first row stay put').toEqual(moved?.before);
      } else {
        await expect(selectColumn).toBeVisible();
        await expect(card(page).locator('thead input.ledger-table__checkbox--inline')).toBeHidden();
      }
      // the select-all is a click on the box, not on the header: it selects the page and sorts nothing
      const sortedBefore = await sortedHeaders(page);
      await pageBox(page).check();
      await expect(barCount(page)).toHaveText(label('table.bulk.selected').replace('{{count}}', String(await rowBoxes(page).count())));
      const sortedAfter = await sortedHeaders(page);
      expect(sortedAfter, 'a select-all must not sort').toEqual(sortedBefore);
      await rowBoxes(page).nth(1).uncheck();
      await page.waitForTimeout(500);
      // a selected row is one colour on every cell that renders — the pinned one included
      const tint = await selectedRows(page).first().evaluate((row) =>
        [...row.querySelectorAll<HTMLElement>(':scope > td')]
          .filter((cell) => getComputedStyle(cell).display !== 'none')
          .map((cell) => getComputedStyle(cell).backgroundColor),
      );
      expect(tint.length).toBeGreaterThan(2);
      expect(new Set(tint).size, 'every rendered cell of a selected row has the same background').toBe(1);
      await page.screenshot({ path: `${OUT}/selection__${theme}__${width}.png`, animations: 'disabled' });
      // the bar is on screen whatever the scroll, inside the viewport, above what sits at the bottom
      await page.evaluate(() => window.scrollTo(0, 500));
      await page.waitForTimeout(400);
      const box = (await bar(page).boundingBox())!;
      expect(box.y).toBeGreaterThanOrEqual(0);
      expect(Math.round(box.y + box.height)).toBeLessThanOrEqual(800);
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(Math.round(box.x + box.width)).toBeLessThanOrEqual(width + 1);
      if (phone) {
        // above the floating quick-expense button and the bottom navigation, when they are there
        const fab = page.locator('.agent-fab');
        if (await fab.isVisible().catch(() => false)) {
          const fabBox = (await fab.boundingBox())!;
          expect(Math.round(box.y + box.height), 'the bar ends above the floating button').toBeLessThanOrEqual(Math.round(fabBox.y));
        }
      }
      // the bar's buttons are named and stand apart
      const names = await bar(page).locator('button').evaluateAll((buttons) => buttons.map((button) => (button.textContent ?? '').trim()));
      expect(names.filter((name) => name.length === 0)).toEqual([]);
      await page.screenshot({ path: `${OUT}/bar__${theme}__${width}.png`, animations: 'disabled' });
    });
  }
}
