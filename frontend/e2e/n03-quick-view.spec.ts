import { mkdirSync } from 'node:fs';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { actionJson, answerList, label, login, mockApi } from './n06-support';

/**
 * N-03 — the quick-view drawer, on the real lists (custodies, expenses,
 * social cases): how it opens (a click on the row, Enter or Space on the
 * focused row), what it is (a dialog on the start side — the right in RTL —
 * 420px wide, the whole screen on a phone), and what it never does (move the
 * list, its filters, its scroll or the address). Its actions are the row's
 * own, under the same abilities.
 *
 * Nothing is written to the server. Needs the PHP server + the packed SPA.
 */

const OUT = 'e2e/screenshots/n03';
const ROUTES = ['custodies', 'expenses', 'social-cases'] as const;
/** a letter almost every Arabic record contains: a filter that keeps rows on screen */
const TERM = 'ا';
const RAW_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;
const START_SIDE_WIDTH = 420;
const MIN_BUTTON_GAP = 8;

const drawer = (page: Page): Locator => page.locator('.p-dialog.quick-view');
/** the data rows: an activatable row is the one with a tabindex (the loading skeleton's rows have none) */
const realRows = (page: Page): Locator => page.locator('app-data-table tbody tr[tabindex="0"]');
const rows = (page: Page): Locator => realRows(page);
const drawerButtons = (page: Page): Locator => drawer(page).locator('footer button, .p-dialog-footer button, .p-dialog-header button');

/** the list is on screen with its real rows (a row that is loading is a row too — and has no tabindex) */
async function openList(page: Page, route: string): Promise<void> {
  await page.goto(`/app/${route}`, { waitUntil: 'domcontentloaded' });
  await realRows(page).first().waitFor({ timeout: 30_000 });
  await page.waitForTimeout(500);
}

/** the first cell of the row that holds no link or button — a click there is a click on the row */
async function clickRowItself(page: Page, row: Locator): Promise<void> {
  const cells = row.locator('td');
  for (let index = 0; index < await cells.count(); index++) {
    const cell = cells.nth(index);
    const isActions = await cell.evaluate((el) => el.classList.contains('ledger-table__cell-actions'));
    if (!isActions && (await cell.locator('a, button, input, p-select').count()) === 0) {
      await cell.click();
      return;
    }
  }
  throw new Error('no plain cell in the row to click');
}

/** the drawer is open and has settled */
async function expectDrawerOpen(page: Page): Promise<void> {
  await expect(drawer(page)).toBeVisible({ timeout: 10_000 });
  await expect(drawer(page).locator('.skeleton')).toHaveCount(0, { timeout: 15_000 });
  await expect(drawer(page).locator('dl')).toBeVisible();
  await page.waitForTimeout(450);
}

/** the accessibility contract of a modal drawer, read from the page */
async function drawerContract(page: Page): Promise<void> {
  const dialog = drawer(page);
  await expect(dialog).toHaveAttribute('role', 'dialog');
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  // its name is its title
  const titleId = await dialog.getAttribute('aria-labelledby');
  expect(titleId, 'the dialog names a title element').toBeTruthy();
  const title = await page.evaluate((id) => document.getElementById(id)?.textContent?.trim() ?? '', titleId as string);
  expect(title.length).toBeGreaterThan(0);
  // every button in it is named, and the footer's buttons stand apart
  const names = await drawerButtons(page).evaluateAll((buttons) => buttons.map((button) => (button.getAttribute('aria-label') ?? button.textContent ?? '').trim()));
  expect(names.length).toBeGreaterThan(0);
  for (const name of names) {
    expect(name.length, `a button without a name: ${JSON.stringify(names)}`).toBeGreaterThan(0);
  }
  const footer = dialog.locator('.quick-view__actions');
  const boxes = await footer.locator('a, button').evaluateAll((buttons) => buttons.map((button) => button.getBoundingClientRect()));
  for (let index = 1; index < boxes.length; index++) {
    // in RTL the first button is the right-most: the gap is from its left edge to the next one's right edge
    const gap = boxes[index - 1].left - boxes[index].right;
    expect(gap, `the gap between the drawer's buttons ${index - 1} and ${index}`).toBeGreaterThanOrEqual(MIN_BUTTON_GAP);
  }
  // no raw translation key in the drawer
  const text = await dialog.innerText();
  const raw = RAW_KEY.exec(text);
  expect(raw, `raw key "${raw?.[0] ?? ''}" in the drawer`).toBeNull();
}

/** Tab never leaves the drawer: every stop is inside it */
async function expectFocusTrapped(page: Page, stops: number = 12): Promise<void> {
  for (let index = 0; index < stops; index++) {
    await page.keyboard.press('Tab');
    const inside = await page.evaluate(() => document.querySelector('.p-dialog.quick-view')?.contains(document.activeElement) ?? false);
    expect(inside, `Tab stop ${index + 1} left the drawer`).toBe(true);
  }
}

async function closeWithEscape(page: Page): Promise<void> {
  await page.keyboard.press('Escape');
  await expect(drawer(page)).toBeHidden({ timeout: 8_000 });
  await page.waitForTimeout(400);
}

/** every request the page made for the list while something happened */
function listRequests(page: Page, route: string): { count: () => number; start: () => void } {
  let urls: string[] = [];
  let on = false;
  page.on('request', (request) => {
    if (on && request.method() === 'GET' && new URL(request.url()).pathname === `/api/v1/${route}` && new URL(request.url()).search !== '') {
      urls.push(request.url());
    }
  });
  return { count: () => urls.length, start: () => { urls = []; on = true; } };
}

async function scrollY(page: Page): Promise<number> {
  return page.evaluate(() => Math.round(window.scrollY));
}

async function filterAndScroll(page: Page, route: string): Promise<void> {
  await page.locator('app-data-table input[type="search"]').fill(TERM);
  // the list asks twice for a change; between the two answers it is a short skeleton, and the page cannot stay scrolled
  const busy = page.locator('.ledger-table[aria-busy="true"]');
  for (let round = 0; round < 3; round++) {
    await expect(busy).toHaveCount(0, { timeout: 30_000 });
    await realRows(page).first().waitFor({ timeout: 30_000 });
    await page.waitForTimeout(700);
  }
  await expect(busy).toHaveCount(0);
  await page.evaluate(() => window.scrollTo(0, 260));
  await page.waitForTimeout(300);
  // a list too short to scroll (four cases on a tall screen) cannot lose a scroll position: nothing to assert there
  const scrollable = await page.evaluate(() => document.documentElement.scrollHeight > window.innerHeight + 300);
  if (scrollable) {
    expect(await scrollY(page), 'the page is scrolled').toBeGreaterThan(0);
  } else {
    console.log(`[n03] ${route}: the page is not tall enough to scroll at ${page.viewportSize()?.width}×${page.viewportSize()?.height}`);
  }
  void route;
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

for (const route of ROUTES) {
  test(`"${route}": Enter opens the drawer, Escape closes it — the list, its filter, its scroll and the address are exactly as they were`, async ({ page }) => {
    test.setTimeout(150_000);
    await openList(page, route);
    await filterAndScroll(page, route);
    const url = page.url();
    const scroll = await scrollY(page);

    const row = rows(page).first();
    await expect(row).toHaveAttribute('tabindex', '0');
    await expect(row).toHaveAttribute('aria-haspopup', 'dialog');
    await row.focus();
    const requests = listRequests(page, route);
    requests.start();

    await page.keyboard.press('Enter');
    await expectDrawerOpen(page);
    await drawerContract(page);
    // the start side: the right edge in RTL, the full height, 420px wide
    const viewport = page.viewportSize()!;
    const box = (await drawer(page).boundingBox())!;
    expect(Math.round(box.width)).toBe(START_SIDE_WIDTH);
    expect(Math.round(box.x + box.width), 'the drawer ends at the right edge').toBe(viewport.width);
    expect(Math.round(box.x), 'it is the drawer that is 420px wide, on the right — not a centred box').toBe(viewport.width - START_SIDE_WIDTH);
    expect(Math.round(box.y)).toBe(0);
    expect(Math.round(box.height)).toBe(viewport.height);
    // the page behind it does not scroll
    expect(await page.evaluate(() => document.body.classList.contains('p-overflow-hidden'))).toBe(true);
    await expectFocusTrapped(page);
    // the list did not move while the drawer was open
    expect(requests.count(), 'list requests while the drawer was open').toBe(0);

    await closeWithEscape(page);
    expect(page.url(), 'the address').toBe(url);
    await expect(page.locator('app-data-table input[type="search"]'), 'the filter').toHaveValue(TERM);
    expect(Math.abs((await scrollY(page)) - scroll), 'the scroll').toBeLessThanOrEqual(2);
    expect(await row.evaluate((el) => document.activeElement === el), 'the focus is back on the row').toBe(true);
    expect(requests.count(), 'list requests while the drawer was open or closed').toBe(0);

    // Space opens it as well, and a click on the mask closes it the same way
    await row.focus();
    await page.keyboard.press(' ');
    await expectDrawerOpen(page);
    await page.mouse.click(60, 400);
    await expect(drawer(page)).toBeHidden({ timeout: 8_000 });
    await page.waitForTimeout(400);
    expect(page.url()).toBe(url);
    expect(Math.abs((await scrollY(page)) - scroll)).toBeLessThanOrEqual(2);
    expect(requests.count()).toBe(0);
  });

  test(`"${route}": a click on the row opens the drawer, a click on a link or a button in the row does not`, async ({ page }) => {
    test.setTimeout(150_000);
    await openList(page, route);
    const row = rows(page).first();
    await clickRowItself(page, row);
    await expectDrawerOpen(page);
    await drawerContract(page);
    await closeWithEscape(page);
    expect(await row.evaluate((el) => document.activeElement === el), 'the focus is back on the row that was clicked').toBe(true);
    // the row's own eye is untouched: on custodies and expenses it goes to the full page;
    // on social cases it has always opened the details — which is this drawer now
    const eye = row.locator('app-row-actions a, app-row-actions button').first();
    if (route === 'social-cases') {
      await eye.click();
      await expectDrawerOpen(page);
      await closeWithEscape(page);
    } else {
      await eye.click();
      await expect(page).toHaveURL(new RegExp(`/app/${route}/\\d+`), { timeout: 15_000 });
    }
  });

  test(`"${route}": "فتح الصفحة الكاملة" is the detail page of the record in the drawer`, async ({ page }) => {
    test.setTimeout(150_000);
    await openList(page, route);
    const row = rows(page).first();
    const recordUrl = await row.locator('a[href*="/' + route + '/"]').first().getAttribute('href').catch(() => null);
    await clickRowItself(page, row);
    await expectDrawerOpen(page);
    const open = drawer(page).locator('.quick-view__actions a, .quick-view__actions button').filter({ hasText: label('common.open_full_page') });
    await expect(open).toHaveAttribute('href', new RegExp(`^/app/${route}/\\d+$`));
    const href = await open.getAttribute('href');
    if (recordUrl !== null) {
      expect(href).toBe(recordUrl);
    }
    await open.click();
    await expect(page).toHaveURL(new RegExp(`/app/${route}/\\d+`), { timeout: 15_000 });
    await expect(drawer(page)).toBeHidden();
  });
}

test('"expenses": the drawer offers the same decision as the row, record after record', async ({ page }) => {
  test.setTimeout(150_000);
  await openList(page, 'expenses');
  const count = Math.min(6, await rows(page).count());
  expect(count).toBeGreaterThan(0);
  for (let index = 0; index < count; index++) {
    const row = rows(page).nth(index);
    const rowAction = row.locator('.cell-action-text button');
    const rowLabel = (await rowAction.count()) === 0 ? '' : (await rowAction.first().innerText()).trim();
    await row.focus();
    await page.keyboard.press('Enter');
    await expectDrawerOpen(page);
    const offered = drawer(page).locator('.quick-view__actions app-action-button').first();
    if (rowLabel === '') {
      await expect(drawer(page).locator('.quick-view__actions app-action-button')).toHaveCount(0);
    } else {
      await expect(offered).toHaveText(rowLabel);
    }
    await closeWithEscape(page);
  }
});

test('a role without the ability sees no action it may not do — custodies: no approve or reject; social cases: no edit', async ({ page }) => {
  test.setTimeout(150_000);
  const api = await mockApi(page);
  // the signed-in user without the two abilities (the lists are answered from the server's real ones)
  await page.route('**/api/v1/me', async (route) => {
    const real = await route.fetch();
    const json = (await real.json()) as { data: { abilities: Record<string, boolean> } };
    await route.fulfill({ response: real, json: { ...json, data: { ...json.data, abilities: { ...json.data.abilities, may_approve_custody: false, is_agent: false, may_manage_social_cases: false } } } });
  });

  // a pending custody, so there would be something to approve — its summary too
  await page.route('**/api/v1/custodies?*', (route) =>
    answerList(route, (json) => {
      const first = (json.data as { id: number }[])[0];
      json.data = [{ ...first, id: 9401, agent: 'مندوب تجريبي', amount: '500.00', status: 'pending' }];
      json.meta = { ...(json.meta as Record<string, unknown>), total: 1 };
    }),
  );
  await page.route('**/api/v1/custodies/9401', (route) =>
    route.fulfill({ json: { data: { id: 9401, agent: 'مندوب تجريبي', agent_id: 1, treasury: 'الخزينة الرئيسية', accountant: 'محمد طارق', amount: '500.00', transferred_in: '0.00', transferred_out: '0.00', spent: '0.00', returned: '0.00', pending_return: '0.00', frozen: '0.00', remaining: '500.00', status: 'pending', status_detail: null, initiated_by: 'محمد طارق', notes: null, created_at: new Date().toISOString() }, meta: null } }),
  );
  await openList(page, 'custodies');
  await expect(rows(page).first().locator('app-row-actions'), 'the row has nothing to approve').not.toContainText(label('custody.detail.accept'));
  await clickRowItself(page, realRows(page).first());
  await expectDrawerOpen(page);
  // only the way to the full page is left — nothing to approve or reject
  await expect(drawer(page).locator('.quick-view__actions')).toHaveText(label('common.open_full_page'));
  await closeWithEscape(page);

  await page.goto('/app/social-cases', { waitUntil: 'domcontentloaded' });
  await realRows(page).first().waitFor({ timeout: 30_000 });
  await page.waitForTimeout(500);
  await clickRowItself(page, realRows(page).first());
  await expectDrawerOpen(page);
  await expect(drawer(page).locator('.quick-view__actions')).not.toContainText(label('common.edit'));
  await closeWithEscape(page);
  // nothing was written on the way
  expect(api.writes).toEqual([]);
});

test('"expenses": the drawer review is the list\'s own — its confirm, about the record\'s money', async ({ page }) => {
  test.setTimeout(150_000);
  const api = await mockApi(page);
  await page.route('**/api/v1/expenses?*', (route) =>
    answerList(route, (json) => {
      const first = (json.data as Record<string, unknown>)[0];
      const reviewed = { ...first, id: 9402, user: 'مندوب تجريبي', amount: '250.00', reviewed: true, reviewed_by: 'عمر رافعي', can_review: false, can_unreview: true, has_direction: true };
      json.data = [reviewed, ...(json.data as Record<string, unknown>[]).filter((line) => line.id !== 9402)];
      json.meta = { ...(json.meta as Record<string, unknown>), total: 2 };
    }),
  );
  await page.route('**/api/v1/expenses/9402', (route) =>
    route.fulfill({ json: { data: { id: 9402, user: 'مندوب تجريبي', type: 'general', amount: '250.00', description: 'مصروف للقياس', created_at: new Date().toISOString(), expense_date: new Date().toISOString(), direction: 'زكاة', reviewed: true, reviewed_by: 'عمر رافعي', reviewed_at: new Date().toISOString(), is_quick_expense: false, has_attachment: false, can_review: false, can_unreview: true, can_edit: true, case: null, location: null, notes: null, source: null, treasury: null, attachment: null, edit_requests: null, custody_split: [], line_items: null }, meta: null } }),
  );
  api.onWrite = async (write, route) => {
    if (/\/expenses\/9402\/unreview$/.test(write.path)) {
      await route.fulfill({ status: 200, contentType: 'application/json', body: actionJson('تم إلغاء المراجعة وفتح التعديل على المصروف') });
      return;
    }
    await route.abort();
  };

  await openList(page, 'expenses');
  const row = rows(page).filter({ hasText: 'مندوب تجريبي' });
  await row.focus();
  await page.keyboard.press('Enter');
  await expectDrawerOpen(page);
  await expect(drawer(page)).toContainText('250.00');
  await drawer(page).locator('.quick-view__actions app-action-button', { hasText: label('expense.list.unreview') }).click();
  // the drawer stepped aside for the confirm
  await expect(drawer(page)).toBeHidden({ timeout: 8_000 });
  const confirm = page.getByRole('dialog').filter({ visible: true });
  await expect(confirm.locator('.confirm-details')).toContainText('250.00');
  await page.waitForTimeout(600);
  // the drawer did not pull the focus back to the row while the confirm is open
  expect(await confirm.evaluate((el) => el.contains(document.activeElement)), 'the focus is in the confirm, not back on the row').toBe(true);
  await confirm.locator('.p-dialog-footer button').last().click();
  await expect.poll(() => api.count('POST', /\/expenses\/9402\/unreview$/)).toBe(1);
  await expect(drawer(page)).toBeHidden();
});

for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`the drawer as it looks — ${theme}, ${width}px, on the three lists`, async ({ page }) => {
      test.setTimeout(240_000);
      await page.setViewportSize({ width, height: 800 });
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((value) => sessionStorage.setItem('visual-matrix.theme', value), theme);
      mkdirSync(OUT, { recursive: true });
      for (const route of ROUTES) {
        await openList(page, route);
        const row = rows(page).first();
        if (width < 600) {
          // on a phone the row is reached with the keyboard: a cell may be scrolled or covered by the pinned actions
          await row.focus();
          await page.keyboard.press('Enter');
        } else {
          await clickRowItself(page, row);
        }
        await expectDrawerOpen(page);
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${OUT}/${route}__${theme}__${width}.png`, animations: 'disabled' });
        const box = (await drawer(page).boundingBox())!;
        // the start side and the full height on a desktop; the whole screen on a phone
        expect(Math.round(box.y)).toBe(0);
        expect(Math.round(box.height)).toBe(800);
        expect(Math.round(box.width)).toBe(width < 600 ? width : START_SIDE_WIDTH);
        expect(Math.round(box.x + box.width)).toBe(width);
        expect(box.x).toBeGreaterThanOrEqual(0);
        // nothing in it runs past its edge
        const overflow = await drawer(page).evaluate((el) => el.scrollWidth > el.clientWidth + 1);
        expect(overflow, `${route}: the drawer overflows sideways`).toBe(false);
        await closeWithEscape(page);
      }
    });
  }
}
