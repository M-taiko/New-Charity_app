import { mkdirSync } from 'node:fs';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { label, login } from './n06-support';

/**
 * N-02 — saved views, on the real lists: save the table as it is (filters,
 * search, sort, visible columns), reload, apply, and everything is back; the
 * chip of the view on screen and its "معدَّل"; rename, the default view a
 * fresh visit starts with, delete.
 *
 * Views live in the browser's storage: nothing is written to the server.
 * Needs the PHP server + the packed SPA.
 */

const OUT = 'e2e/screenshots/n02';
const TERM = 'قياس';
const VIEW = 'عرض الاختبار';
const RENAMED = 'عرض ثانٍ';
const RAW_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;

interface ListCase {
  readonly route: string;
  /** the API list the page loads */
  readonly api: string;
  /** a column to hide: its field, and the i18n key of its header */
  readonly hide: { readonly field: string; readonly header: string };
  /** a sortable column: its field and the key the server sorts by */
  readonly sort: { readonly field: string; readonly key: string };
  readonly otherSort: { readonly field: string; readonly key: string };
  /** a table filter to save with the view: the n-th filter field of the panel, and the option's text */
  readonly filter?: { readonly index: number; readonly option: string };
}

const LISTS: readonly ListCase[] = [
  {
    route: 'expenses', api: '/expenses',
    hide: { field: 'type', header: 'expense.list.col_type' },
    sort: { field: 'amount', key: 'amount' }, otherSort: { field: 'created_at', key: 'created_at' },
    filter: { index: 1, option: label('enums.review_state.reviewed') },
  },
  {
    route: 'custodies', api: '/custodies',
    hide: { field: 'transferred_in', header: 'custody.list.col_transferred_in' },
    sort: { field: 'amount', key: 'amount' }, otherSort: { field: 'status', key: 'status' },
  },
  {
    route: 'social-cases', api: '/social-cases',
    hide: { field: 'assistance_type', header: 'case.list.col_category' },
    sort: { field: 'requested_amount', key: 'amount' }, otherSort: { field: 'created_at', key: 'created_at' },
  },
];

// ── the pieces of the table the tests drive ────────────────────────────────

const views = (page: Page): Locator => page.locator('app-table-views');
const panel = (page: Page): Locator => views(page).locator('.table-views__panel');
const chip = (page: Page): Locator => views(page).locator('.table-views__chip');
const header = (page: Page, field: string): Locator => page.locator(`app-data-table thead th[data-field="${field}"]`);
const search = (page: Page): Locator => page.locator('app-data-table input[type="search"]');

/** the panel is open; the toggle would close it if it already were */
async function openViews(page: Page): Promise<void> {
  if (!(await panel(page).isVisible())) {
    await views(page).locator('button').first().click();
  }
  await expect(panel(page)).toBeVisible();
}

async function toggleColumn(page: Page, headerKey: string, visible: boolean): Promise<void> {
  const box = panel(page).locator('.table-views__column', { hasText: label(headerKey) }).locator('input[type="checkbox"]');
  if (visible) {
    await box.check();
  } else {
    await box.uncheck();
  }
}

/** click a header until the table sorts by it, descending — a direction a fresh table never has */
async function sortDescending(page: Page, field: string): Promise<void> {
  for (let click = 0; click < 3; click++) {
    if ((await header(page, field).getAttribute('aria-sort')) === 'descending') {
      return;
    }
    await header(page, field).click();
    await page.waitForTimeout(250);
  }
  await expect(header(page, field)).toHaveAttribute('aria-sort', 'descending');
}

async function saveCurrentView(page: Page, name: string): Promise<void> {
  await panel(page).locator('input[name="view-name"]').fill(name);
  await panel(page).locator('.table-views__form p-button button').first().click();
  await expect(chip(page)).toContainText(name);
}

/** the list request that carries every parameter of the view (registered before the action) */
function listRequestWith(page: Page, api: string, expected: Record<string, string>): Promise<string> {
  return page
    .waitForRequest((request) => {
      if (request.method() !== 'GET') {
        return false;
      }
      const url = new URL(request.url());
      return url.pathname === `/api/v1${api}` && Object.entries(expected).every(([key, value]) => url.searchParams.get(key) === value);
    }, { timeout: 20_000 })
    .then((request) => request.url());
}

/** what applying the view put on screen */
async function expectViewOnScreen(page: Page, c: ListCase): Promise<void> {
  await expect(header(page, c.hide.field), 'the hidden column').toHaveCount(0);
  await expect(header(page, c.sort.field), 'the sorted column').toHaveAttribute('aria-sort', 'descending');
  await expect(search(page)).toHaveValue(TERM);
}

/** the three icon buttons of a saved view, in their order: the default, rename, delete */
const viewButtons = (page: Page, name: string): Locator => panel(page).locator('.table-views__item', { hasText: name }).locator('.table-views__actions button');

async function forgetTableStorage(page: Page): Promise<void> {
  await page.evaluate(() => {
    for (const key of Object.keys(localStorage)) {
      if (key.includes('.table.')) {
        localStorage.removeItem(key);
      }
    }
  });
}

async function openList(page: Page, route: string): Promise<void> {
  await page.goto(`/app/${route}`, { waitUntil: 'domcontentloaded' });
  await page.locator('app-data-table tbody tr').first().waitFor({ timeout: 30_000 });
  await views(page).waitFor();
}

test.beforeEach(async ({ page }) => {
  await login(page);
  await forgetTableStorage(page);
});

for (const c of LISTS) {
  test(`"${c.route}": save → reload → apply brings back filters, sort and columns; then rename, default, delete`, async ({ page }) => {
    test.setTimeout(240_000);
    await openList(page, c.route);
    expect(await chip(page).count(), 'a fresh table has no view on screen').toBe(0);

    // ── the table as the view will remember it ──
    await sortDescending(page, c.sort.field);
    await openViews(page);
    await toggleColumn(page, c.hide.header, false);
    await search(page).fill(TERM);
    if (c.filter !== undefined) {
      await page.locator('app-table-filters button').first().click();
      const field = page.locator('app-table-filters .table-filters__field').nth(c.filter.index);
      await field.locator('p-select').click();
      await page.locator('.p-select-overlay .p-select-option', { hasText: c.filter.option }).first().click();
      await page.locator('app-table-filters .table-filters__actions p-button button').last().click();
      await expect(page.locator('app-table-filters .table-filters__chip').first()).toBeVisible();
    }
    await openViews(page);
    await saveCurrentView(page, VIEW);
    await page.keyboard.press('Escape');
    await expect(panel(page)).toHaveCount(0);

    // ── the table moves away from the view ──
    await search(page).fill('');
    await header(page, c.otherSort.field).click();
    await page.waitForTimeout(250);
    await openViews(page);
    await toggleColumn(page, c.hide.header, true);
    await page.keyboard.press('Escape');
    if (c.filter !== undefined) {
      await page.locator('app-table-filters .table-filters__chip').first().click();
    }
    await expect(header(page, c.hide.field), 'the column is back').toHaveCount(1);
    // the chip says so, and nothing else changed
    await expect(chip(page)).toContainText(VIEW);
    await expect(chip(page)).toContainText(label('table.views.edited'));

    // ── a reload, then the view ──
    await openList(page, c.route);
    await expect(search(page)).toHaveValue('');
    await expect(header(page, c.hide.field)).toHaveCount(1);
    const expected: Record<string, string> = { q: TERM, sort: `-${c.sort.key}` };
    if (c.filter !== undefined) {
      // the expenses page sends its review filter by its own name
      expected.reviewed = 'reviewed';
    }
    const requested = listRequestWith(page, c.api, expected);
    await openViews(page);
    await panel(page).locator('.table-views__apply', { hasText: VIEW }).click();
    const url = await requested;
    console.log(`[n02] ${c.route} applied with: ${decodeURIComponent(url.split('?')[1])}`);
    await expectViewOnScreen(page, c);
    if (c.filter !== undefined) {
      await expect(page.locator('app-table-filters .table-filters__chip').first()).toBeVisible();
    }
    await expect(chip(page)).toContainText(VIEW);
    await expect(chip(page)).not.toContainText(label('table.views.edited'));

    // ── rename ──
    await openViews(page);
    await expect(viewButtons(page, VIEW).nth(1)).toHaveAttribute('aria-label', label('table.views.rename').replace('{{name}}', VIEW));
    await viewButtons(page, VIEW).nth(1).click();
    await panel(page).locator('input[name="rename-view"]').fill(RENAMED);
    // the rename form's own save (the save form at the top is disabled with an empty name)
    await panel(page).locator('.table-views__item form p-button button').first().click();
    await expect(panel(page).locator('.table-views__apply', { hasText: RENAMED })).toBeVisible();
    await expect(chip(page)).toContainText(RENAMED);

    // ── the default: a fresh visit starts in it ──
    await viewButtons(page, RENAMED).nth(0).click();
    await expect(viewButtons(page, RENAMED).nth(0)).toHaveAttribute('aria-pressed', 'true');
    await expect(viewButtons(page, RENAMED).nth(0)).toHaveAttribute('aria-label', label('table.views.unset_default').replace('{{name}}', RENAMED));
    await expect(panel(page).locator('.table-views__default')).toHaveText(label('table.views.default_badge'));
    await page.keyboard.press('Escape');
    await search(page).fill('');
    const onVisit = listRequestWith(page, c.api, expected);
    await openList(page, c.route);
    await onVisit;
    await expectViewOnScreen(page, c);
    await expect(chip(page)).toContainText(RENAMED);

    // ── delete (a confirmation, never a native dialog) ──
    await openViews(page);
    await expect(viewButtons(page, RENAMED).nth(2)).toHaveAttribute('aria-label', label('table.views.delete').replace('{{name}}', RENAMED));
    await viewButtons(page, RENAMED).nth(2).click();
    await expect(panel(page).locator('.table-views__confirm')).toContainText(label('table.views.confirm_delete').replace('{{name}}', RENAMED));
    await panel(page).locator('.table-views__confirm button', { hasText: label('common.delete') }).click();
    await expect(panel(page)).toContainText(label('table.views.empty'));
    await expect(chip(page)).toHaveCount(0);
    // the panel never shows a raw key
    const text = await panel(page).innerText();
    const raw = RAW_KEY.exec(text);
    expect(raw, `raw key "${raw?.[0] ?? ''}" in the panel`).toBeNull();
  });
}

test('the control works from the keyboard: Enter opens, the name field takes the focus, Escape closes and gives it back', async ({ page }) => {
  test.setTimeout(120_000);
  await openList(page, 'expenses');
  const toggle = views(page).locator('button').first();
  await toggle.focus();
  await expect(toggle).toHaveAttribute('aria-expanded', 'false');
  await page.keyboard.press('Enter');
  await expect(panel(page)).toBeVisible();
  await expect(toggle).toHaveAttribute('aria-expanded', 'true');
  await expect(panel(page).locator('input[name="view-name"]')).toBeFocused();
  // the panel names itself, and its fields name themselves
  await expect(panel(page)).toHaveAttribute('role', 'dialog');
  await expect(panel(page)).toHaveAttribute('aria-label', label('table.views.title'));
  await page.keyboard.press('Escape');
  await expect(panel(page)).toHaveCount(0);
  await expect(toggle).toBeFocused();
});

test('the last visible column cannot be hidden, and the stored views are the user', async ({ page }) => {
  test.setTimeout(120_000);
  await openList(page, 'expenses');
  await openViews(page);
  const boxes = panel(page).locator('.table-views__column input[type="checkbox"]');
  const count = await boxes.count();
  expect(count).toBeGreaterThan(2);
  // hide every column but the last one
  for (let index = 0; index < count - 1; index++) {
    await boxes.nth(index).uncheck();
  }
  // exactly one DATA column is left. The expenses table is selectable (N-04): its selection
  // cell has no field of its own, and hiding every column must not take it away
  await expect(page.locator('app-data-table thead th[data-field]:not([data-field="actions"])')).toHaveCount(1);
  await expect(page.locator('app-data-table thead th.ledger-table__cell-select')).toHaveCount(1);
  // the last one is checked and cannot be unchecked; the hidden ones can be brought back
  const last = boxes.nth(count - 1);
  await expect(last).toBeChecked();
  await expect(last).toBeDisabled();
  await expect(boxes.nth(0)).toBeEnabled();
  await expect(boxes.nth(0)).not.toBeChecked();
  // what is saved is stored under the table's page and the signed-in user
  await saveCurrentView(page, 'عمود واحد');
  const keys = await page.evaluate(() => Object.keys(localStorage).filter((key) => key.includes('.views.')));
  expect(keys.some((key) => /table\.expenses\/list\.component\.views\.\d+$/.test(key))).toBe(true);
  const stored = await page.evaluate(() => {
    const key = Object.keys(localStorage).find((candidate) => /table\.expenses\/list\.component\.views\.\d+$/.test(candidate));
    return key === undefined ? null : JSON.parse(localStorage.getItem(key) ?? 'null');
  }) as { views: { name: string; hiddenColumns: string[] }[] } | null;
  expect(stored?.views.map((view) => view.name)).toEqual(['عمود واحد']);
  expect(stored?.views[0].hiddenColumns).toHaveLength(count - 1);
});

test('the two panels never lie on top of each other: opening one closes the other', async ({ page }) => {
  test.setTimeout(120_000);
  await openList(page, 'expenses');
  await openViews(page);
  await page.locator('app-table-filters button').first().click();
  await expect(page.locator('app-table-filters .table-filters__panel')).toBeVisible();
  await expect(panel(page)).toHaveCount(0);
  await openViews(page);
  await expect(panel(page)).toBeVisible();
  await expect(page.locator('app-table-filters .table-filters__panel')).toHaveCount(0);
});

for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`the control, the panel and the chip — ${theme}, ${width}px`, async ({ page }) => {
      test.setTimeout(150_000);
      await page.setViewportSize({ width, height: 800 });
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((value) => sessionStorage.setItem('visual-matrix.theme', value), theme);
      await openList(page, 'expenses');
      mkdirSync(OUT, { recursive: true });
      const toolbar = page.locator('.ledger-table__header');
      await toolbar.scrollIntoViewIfNeeded();
      await page.waitForTimeout(400);
      await page.screenshot({ path: `${OUT}/toolbar__${theme}__${width}.png`, animations: 'disabled' });

      await openViews(page);
      await toggleColumn(page, 'expense.list.col_type', false);
      await saveCurrentView(page, VIEW);
      await page.waitForTimeout(500);
      await page.screenshot({ path: `${OUT}/panel__${theme}__${width}.png`, animations: 'disabled' });
      // the panel is inside the viewport
      const box = (await panel(page).boundingBox())!;
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(box.x + box.width).toBeLessThanOrEqual(width + 1);
      expect(box.y).toBeGreaterThanOrEqual(0);
      expect(box.y + box.height).toBeLessThanOrEqual(801);
      await page.keyboard.press('Escape');
      await expect(panel(page)).toHaveCount(0);
      await page.waitForTimeout(300);
      await page.screenshot({ path: `${OUT}/chip__${theme}__${width}.png`, animations: 'disabled' });
      const chipBox = (await chip(page).boundingBox())!;
      expect(chipBox.x).toBeGreaterThanOrEqual(0);
      expect(chipBox.x + chipBox.width).toBeLessThanOrEqual(width + 1);
      // the chip's text reads against its own background
      const ratio = await chip(page).locator('.table-views__chip-name').evaluate((el) => {
        const luminance = (color: string): number => {
          const [r, g, b] = color.match(/[\d.]+/g)!.slice(0, 3).map(Number).map((v) => {
            const s = v / 255;
            return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
          });
          return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        let background = getComputedStyle(el.parentElement!).backgroundColor;
        return (Math.max(luminance(getComputedStyle(el).color), luminance(background)) + 0.05) / (Math.min(luminance(getComputedStyle(el).color), luminance(background)) + 0.05);
      });
      console.log(`[n02] view chip contrast ${theme} ${width}px: ${ratio.toFixed(2)}:1`);
      expect(ratio).toBeGreaterThanOrEqual(4.5);
    });
  }
}
