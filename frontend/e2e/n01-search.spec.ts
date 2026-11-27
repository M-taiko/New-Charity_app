import { mkdirSync } from 'node:fs';
import { expect, test, type Locator, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { label, login } from './n06-support';

/**
 * N-01 — the command palette with record search, used with the keyboard
 * only. The server's answer is fixed by the test for everything about the
 * palette itself (what it lists, how it walks, where it goes); the last
 * behaviour test goes to the real endpoint with a name taken from the real
 * data. Nothing is written anywhere.
 *
 * Needs the PHP server + the packed SPA.
 */

const OUT = 'e2e/screenshots/n01';
const TERM = 'حسام';
const RAW_KEY = /[a-z_]+\.[a-z0-9_]+(?:\.[a-z0-9_]+)+/;

const palette = (page: Page): Locator => page.locator('.p-dialog', { has: page.locator('.command-palette__input') });
const input = (page: Page): Locator => palette(page).locator('input.command-palette__input');
const options = (page: Page): Locator => palette(page).locator('[role="option"]');
const headings = (page: Page): Locator => palette(page).locator('.command-palette__group');

/** what GET /api/v1/search answers for "حسام": the shape of the real one, with fixed records — and nothing for any other text */
function answerFor(q: string, tag = ''): { data: unknown; meta: unknown } {
  if (q !== TERM) {
    return { data: { custodies: [], expenses: [], social_cases: [], users: [] }, meta: { q, per_group: 5, groups: ['custodies', 'expenses', 'social_cases', 'users'] } };
  }
  return {
    data: {
      custodies: [
        { id: 43, title: 'حسام إسماعيل', subtitle: 'الخزينة الرئيسية' },
        { id: 42, title: 'حسام إسماعيل', subtitle: null },
      ],
      expenses: [{ id: 149, title: `تسديد سلفة حسام${tag}`, subtitle: 'حسام إسماعيل' }],
      social_cases: [],
      users: [{ id: 3, title: `حسام إسماعيل${tag}`, subtitle: 'h@example.test' }],
    },
    meta: { q, per_group: 5, groups: ['custodies', 'expenses', 'social_cases', 'users'] },
  };
}

interface SearchMock {
  readonly asked: string[];
}

/** every search request is answered by the test; `answer` may wait (to imitate a slow network) */
async function mockSearch(page: Page, answer: (q: string) => Promise<{ data: unknown; meta: unknown }> | { data: unknown; meta: unknown }): Promise<SearchMock> {
  const asked: string[] = [];
  await page.route('**/api/v1/search?*', async (route) => {
    const q = new URL(route.request().url()).searchParams.get('q') ?? '';
    asked.push(q);
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(await answer(q)) });
  });
  return { asked };
}

async function openWithKeyboard(page: Page): Promise<void> {
  // the shortcut belongs to the top bar: it must be on screen before the key is pressed
  await page.locator('.topbar__search').waitFor({ state: 'visible', timeout: 30_000 });
  await page.keyboard.press('Control+k');
  await expect(palette(page)).toBeVisible({ timeout: 8_000 });
  await expect(input(page)).toBeFocused();
}

/** the option the arrow keys are on: named by the input, selected, and the one drawn as active */
/** the page is at `path` with `q` in its address — compared decoded, whatever else the address carries */
function expectAt(page: Page, path: string, q: string | null): Promise<void> {
  return expect(page).toHaveURL((url: URL) => url.pathname === `/app${path}` && url.searchParams.get('q') === q, { timeout: 15_000 });
}

async function expectActive(page: Page, index: number): Promise<void> {
  await expect(input(page)).toHaveAttribute('aria-activedescendant', `command-palette-list-${index}`);
  const option = options(page).nth(index);
  await expect(option).toHaveAttribute('aria-selected', 'true');
  await expect(option).toHaveClass(/command-palette__item--active/);
  await expect(palette(page).locator('[aria-selected="true"]')).toHaveCount(1);
}

test.beforeEach(async ({ page }) => {
  await login(page);
  await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
  await page.locator('main h1').first().waitFor({ timeout: 30_000 });
});

test('keyboard only: Ctrl+K opens it, two characters ask once, the arrows walk, Enter opens the record', async ({ page }) => {
  test.setTimeout(120_000);
  const search = await mockSearch(page, (q) => answerFor(q));
  await openWithKeyboard(page);

  // a combobox over one list
  await expect(input(page)).toHaveAttribute('role', 'combobox');
  await expect(input(page)).toHaveAttribute('aria-controls', 'command-palette-list');
  await expect(palette(page).locator('#command-palette-list')).toHaveAttribute('role', 'listbox');
  await expect(palette(page)).toHaveAttribute('aria-modal', 'true');

  // one character: nothing is asked, and the palette says why
  await page.keyboard.type(TERM.slice(0, 1));
  await page.waitForTimeout(600);
  expect(search.asked).toEqual([]);
  await expect(palette(page).locator('.command-palette__hint')).toHaveText(label('command.keep_typing'));

  // the rest, typed quickly: the pause is waited for, then ONE request for the whole word
  await page.keyboard.type(TERM.slice(1));
  await page.waitForTimeout(100);
  expect(search.asked, 'nothing before the debounce').toEqual([]);
  await expect(options(page).first()).toBeVisible({ timeout: 8_000 });
  await page.waitForTimeout(500);
  expect(search.asked, 'one request for the word, not one per keystroke').toEqual([TERM]);

  // grouped, with the typed part marked
  await expect(headings(page)).toHaveText([label('command.groups.custodies'), label('command.groups.expenses'), label('command.groups.users')]);
  await expect(options(page)).toHaveCount(7);
  const first = options(page).nth(0);
  await expect(first).toContainText('حسام إسماعيل');
  await expect(first.locator('mark')).toHaveText(TERM);
  await expect(first).toContainText('#43');
  await expect(options(page).nth(4)).toHaveText(label('command.see_all').replace('{{group}}', label('command.groups.expenses')));
  const text = await palette(page).innerText();
  const raw = RAW_KEY.exec(text);
  expect(raw, `raw key "${raw?.[0] ?? ''}" in the palette`).toBeNull();

  // the arrows walk the one list: the first item is where the cursor starts
  await expectActive(page, 0);
  for (let index = 1; index <= 4; index++) {
    await page.keyboard.press('ArrowDown');
    await expectActive(page, index);
  }
  await page.keyboard.press('ArrowUp');
  await expectActive(page, 3);
  await page.keyboard.press('End');
  await expectActive(page, 6);
  await page.keyboard.press('Home');
  await expectActive(page, 0);
  // the arrow keys never left the input: the cursor stays in the field
  await expect(input(page)).toBeFocused();

  // Enter opens the first record
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/app\/custodies\/43$/, { timeout: 15_000 });
  await expect(palette(page)).toBeHidden();
});

test('a slow answer to an earlier text never replaces the answer to the text on screen', async ({ page }) => {
  test.setTimeout(120_000);
  let asked = 0;
  const search = await mockSearch(page, async (q) => {
    asked++;
    // the first request is slow; the second answers at once
    if (asked === 1) {
      await new Promise((resolve) => setTimeout(resolve, 1200));
      return answerFor(q, ' القديم');
    }
    return answerFor(q, ' الجديد');
  });
  await openWithKeyboard(page);
  await page.keyboard.type('حسا');
  await expect.poll(() => search.asked.length, { timeout: 5_000 }).toBe(1);
  await page.keyboard.type('م');
  await expect.poll(() => search.asked.length, { timeout: 5_000 }).toBe(2);
  await expect(options(page).nth(3)).toContainText('الجديد', { timeout: 8_000 });
  // the first answer arrives now
  await page.waitForTimeout(2000);
  await expect(options(page).nth(3)).toContainText('الجديد');
  await expect(palette(page)).not.toContainText('القديم');
});

test('pages are still found, and the ones opened last are remembered', async ({ page }) => {
  test.setTimeout(120_000);
  const search = await mockSearch(page, (q) => answerFor(q));
  await openWithKeyboard(page);
  // nothing typed: pages, no request
  await expect(options(page).first()).toBeVisible();
  expect(search.asked).toEqual([]);
  await page.keyboard.type('خزين');
  await expect(options(page)).toHaveCount(1);
  await expect(options(page).first()).toHaveText(label('nav.treasury'));
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(/\/app\/treasury$/, { timeout: 15_000 });

  // opened again: that page is under "recently opened"
  await openWithKeyboard(page);
  await expect(headings(page)).toHaveText([label('command.recent')]);
  await expect(options(page).first()).toHaveText(label('nav.treasury'));
});

test('"see all results" opens the list with the text as its filter', async ({ page }) => {
  test.setTimeout(120_000);
  const search = await mockSearch(page, (q) => answerFor(q));
  await openWithKeyboard(page);
  await page.keyboard.type(TERM);
  await expect(options(page)).toHaveCount(7, { timeout: 8_000 });
  const custodiesAll = options(page).nth(2);
  await expect(custodiesAll).toHaveText(label('command.see_all').replace('{{group}}', label('command.groups.custodies')));

  const listAsked = page.waitForRequest((request) => {
    const url = new URL(request.url());
    return url.pathname === '/api/v1/custodies' && url.searchParams.get('q') === TERM;
  }, { timeout: 15_000 });
  await custodiesAll.click();
  await listAsked;
  await expectAt(page, '/custodies', TERM);
  await expect(palette(page)).toBeHidden();
  // the list's search box carries the text, and the list was asked with it
  await expect(page.locator('app-data-table input[type="search"]')).toHaveValue(TERM);
  expect(search.asked).toEqual([TERM]);
});

test('an agent is led to their own lists, and is offered no group they may not search', async ({ page }) => {
  test.setTimeout(120_000);
  const search = await mockSearch(page, (q) => answerFor(q));
  // the signed-in user as an agent: not the management lists, not users
  await page.route('**/api/v1/me', async (route) => {
    const real = await route.fetch();
    const json = (await real.json()) as { data: { abilities: Record<string, boolean> } };
    await route.fulfill({ response: real, json: { ...json, data: { ...json.data, abilities: { ...json.data.abilities, may_manage_treasury: false, may_view_all_expenses: false, may_view_all_records: false, may_manage_users: false, is_manager: false, is_accountant: false, is_researcher: false, is_agent: true } } } });
  });
  await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
  await page.locator('main h1').first().waitFor({ timeout: 30_000 });

  await openWithKeyboard(page);
  await page.keyboard.type(TERM);
  await expect(options(page).first()).toBeVisible({ timeout: 8_000 });
  // custodies and expenses only — the answer's users are not shown to someone who may not search them
  await expect(headings(page)).toHaveText([label('command.groups.custodies'), label('command.groups.expenses')]);
  await expect(palette(page)).not.toContainText('h@example.test');

  // "see all results" in custodies: their own custodies, with the text
  await options(page).nth(2).click();
  await expectAt(page, '/custodies/mine', TERM);
  await expect(page.locator('app-data-table input[type="search"]').first()).toHaveValue(TERM);

  // and in expenses: their own expenses
  await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
  await openWithKeyboard(page);
  await page.keyboard.type(TERM);
  await expect(options(page).nth(4)).toBeVisible({ timeout: 8_000 });
  await expect(options(page).nth(4)).toHaveText(label('command.see_all').replace('{{group}}', label('command.groups.expenses')));
  await options(page).nth(4).click();
  await expectAt(page, '/expenses/mine', TERM);
  await expect(page.locator('app-data-table input[type="search"]').first()).toHaveValue(TERM);
  expect(search.asked.every((q) => q === TERM)).toBe(true);
});

test('Escape closes the palette and the focus goes back to the search box that opened it', async ({ page }) => {
  test.setTimeout(120_000);
  await mockSearch(page, (q) => answerFor(q));
  const trigger = page.locator('.topbar__search');
  await trigger.click();
  await expect(palette(page)).toBeVisible();
  await expect(input(page)).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(palette(page)).toBeHidden({ timeout: 8_000 });
  await expect(trigger).toBeFocused({ timeout: 5_000 });
  // Ctrl+K opens it again, and Ctrl+K closes it
  await page.keyboard.press('Control+k');
  await expect(palette(page)).toBeVisible();
  await page.keyboard.press('Control+k');
  await expect(palette(page)).toBeHidden({ timeout: 8_000 });
});

test('against the real endpoint: typing an agent name finds their custodies', async ({ page }) => {
  test.setTimeout(120_000);
  // a name from the real data: the agent of the first custody
  const list = await page.request.get('/api/v1/custodies?per_page=1', { headers: { Accept: 'application/json' } });
  const agentName = (((await list.json()) as { data: { agent: string | null }[] }).data[0]?.agent ?? '').trim();
  expect(agentName.length).toBeGreaterThan(1);
  const word = agentName.split(/\s+/)[0];
  expect(word.length).toBeGreaterThanOrEqual(2);

  const asked: string[] = [];
  page.on('request', (request) => {
    if (/\/api\/v1\/search\?/.test(request.url())) {
      asked.push(decodeURIComponent(new URL(request.url()).searchParams.get('q') ?? ''));
    }
  });
  await openWithKeyboard(page);
  await page.keyboard.type(word);
  await expect(options(page).first()).toBeVisible({ timeout: 10_000 });
  expect(asked).toEqual([word]);
  // the first result is a custody of that agent
  const first = options(page).first();
  await expect(first).toContainText(agentName);
  await expect(first.locator('mark')).toHaveText(word);
  await expect(headings(page).first()).toHaveText(label('command.groups.custodies'));
  const id = /#(\d+)/.exec(await first.innerText())?.[1];
  expect(id).toBeTruthy();
  await page.keyboard.press('Enter');
  await expect(page).toHaveURL(new RegExp(`/app/custodies/${id}$`), { timeout: 15_000 });
  console.log(`[n01] "${word}" → the custody #${id} of ${agentName}, from the real endpoint`);
});

for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`the palette with records — ${theme}, ${width}px`, async ({ page }) => {
      test.setTimeout(120_000);
      await page.setViewportSize({ width, height: 800 });
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((value) => sessionStorage.setItem('visual-matrix.theme', value), theme);
      await page.goto('/app/dashboard', { waitUntil: 'domcontentloaded' });
      await page.locator('main h1').first().waitFor({ timeout: 30_000 });
      await mockSearch(page, (q) => answerFor(q));
      await openWithKeyboard(page);
      await page.keyboard.type(TERM);
      await expect(options(page)).toHaveCount(7, { timeout: 8_000 });
      await page.waitForTimeout(400);
      mkdirSync(OUT, { recursive: true });
      await page.screenshot({ path: `${OUT}/palette__${theme}__${width}.png`, animations: 'disabled' });
      const box = (await palette(page).boundingBox())!;
      expect(box.x).toBeGreaterThanOrEqual(0);
      expect(Math.round(box.x + box.width)).toBeLessThanOrEqual(width + 1);
      // the typed part is marked, and it reads against its mark
      const ratio = await options(page).nth(0).locator('mark').evaluate((el) => {
        const luminance = (color: string): number => {
          const [r, g, b] = color.match(/[\d.]+/g)!.slice(0, 3).map(Number).map((v) => {
            const s = v / 255;
            return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
          });
          return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        const style = getComputedStyle(el);
        return (Math.max(luminance(style.color), luminance(style.backgroundColor)) + 0.05) / (Math.min(luminance(style.color), luminance(style.backgroundColor)) + 0.05);
      });
      console.log(`[n01] the marked text's contrast ${theme} ${width}px: ${ratio.toFixed(2)}:1`);
      expect(ratio).toBeGreaterThanOrEqual(4.5);
    });
  }
}
