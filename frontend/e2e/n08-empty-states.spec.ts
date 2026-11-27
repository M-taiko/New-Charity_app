import { mkdirSync } from 'node:fs';
import { expect, test, type Page, type Route } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { paramsFiltered, reloadList } from '../src/app/shared/lib/list-state';

/**
 * N-08 — empty-state onboarding, and the two states it must be told apart from.
 *
 * The seeded account has records in every list, so neither state exists on the
 * real data. Both are produced here by answering the list requests from the
 * test: the server's REAL response is fetched and only its rows are emptied,
 * so every meta (summary, sortable, filters) stays genuine. Nothing is written
 * to the database.
 *
 * A role WITHOUT an ability is produced the same way: the abilities in the
 * /me response are changed before the app reads them.
 *
 * Needs the PHP server + the packed SPA, like the matrix.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';
const OUT = 'e2e/screenshots/p2';
const NO_MATCH = 'zzzz-no-such-record';

/** the server's real answer to a list request, with no rows (and a total of 0) */
async function answerEmpty(route: Route): Promise<void> {
  try {
    const real = await route.fetch();
    const json = (await real.json()) as { data: unknown[]; meta: Record<string, unknown> };
    await route.fulfill({ response: real, json: { ...json, data: [], meta: { ...json.meta, total: 0, last_page: 1, to: 0 } } });
  } catch {
    // the page went away while the answer was on its way (the test already ended)
  }
}

/** refuses every write to the API. Handlers match in reverse registration order, so register this FIRST.
 *  `POST /broadcasts/active` is the top bar READING the active broadcast — it writes nothing. */
async function refuseWrites(page: Page): Promise<string[]> {
  const writes: string[] = [];
  await page.route('**/api/v1/**', async (route) => {
    const url = route.request().url();
    const readThroughPost = /\/broadcasts\/active/.test(url) || /\/auth\/logout/.test(url);
    if (route.request().method() !== 'GET' && !readThroughPost) {
      writes.push(`${route.request().method()} ${url}`);
      await route.abort();
      return;
    }
    await route.continue();
  });
  return writes;
}

/** N-08 regression: for a moment after "clear the filters" the list is empty AND unfiltered (the old
 *  result is held while the reload runs) — the page must not take that for "no data at all" */
async function noOnboardingFlash(page: Page): Promise<void> {
  for (let sample = 0; sample < 8; sample++) {
    expect(await page.locator('app-async-page > app-empty-state').count(), 'the onboarding state flashed').toBe(0);
    await expect(page.locator('.ledger-table'), 'the table stays').toHaveCount(1);
    await page.waitForTimeout(50);
  }
}

/** the list answers with rows unless the request carries `needle`, then with none */
async function emptyWhen(page: Page, list: string, needle: (url: URL) => boolean): Promise<void> {
  await page.route(`**/api/v1/${list}?*`, async (route) => {
    if (needle(new URL(route.request().url()))) {
      await answerEmpty(route);
    } else {
      await route.continue();
    }
  });
}

/** a "me" whose abilities are edited — a role without them */
async function meWith(page: Page, abilities: Record<string, boolean>): Promise<void> {
  await page.route('**/api/v1/me', async (route) => {
    const real = await route.fetch();
    const json = (await real.json()) as { data: { abilities: Record<string, boolean> } };
    await route.fulfill({ response: real, json: { ...json, data: { ...json.data, abilities: { ...json.data.abilities, ...abilities } } } });
  });
}

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

/** whole page: the real viewport made as tall as the document (how the matrix does it) */
async function wholePage(page: Page, name: string): Promise<void> {
  mkdirSync(OUT, { recursive: true });
  await page.evaluate(() => document.fonts.ready);
  const height = await page.evaluate(() => document.documentElement.scrollHeight);
  const width = page.viewportSize()?.width ?? 1280;
  await page.setViewportSize({ width, height });
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${OUT}/${name}.png` });
  await page.setViewportSize({ width, height: 800 });
}

/** the first-run state on a page: its title, and the call to action (label + where it goes) */
function readOnboarding(): { title: string; hint: string; cta: string; href: string } | null {
  const state = document.querySelector<HTMLElement>('app-async-page app-empty-state .empty-state, .ledger-table .empty-state');
  if (!state) {
    return null;
  }
  const link = state.querySelector<HTMLAnchorElement>('.empty-state__action a');
  return {
    title: (state.querySelector('.empty-state__title')?.textContent ?? '').trim(),
    hint: (state.querySelector('.empty-state__text')?.textContent ?? '').trim(),
    cta: (state.querySelector('.empty-state__action')?.textContent ?? '').trim(),
    href: link?.getAttribute('href') ?? '',
  };
}

test.describe('N-08 the decision', () => {
  test('a list is "filtered" only when a value is set and differs from its default — paging never counts', () => {
    expect(paramsFiltered({ page: 3, per_page: 50, sort: '-amount', status: 'all', q: '' })).toBe(false);
    expect(paramsFiltered({ type: 'all', reviewed: null, no_direction: false, date_from: '' }, { type: 'all' })).toBe(false);
    expect(paramsFiltered({ q: 'ahmed' })).toBe(true);
    expect(paramsFiltered({ status: 'pending' })).toBe(true);
    expect(paramsFiltered({ no_direction: true }, { no_direction: false })).toBe(true);
    expect(paramsFiltered({ amount: { min: null, max: 500 } })).toBe(true);
    // a default that is not the empty value: only a change away from it filters
    expect(paramsFiltered({ status: 'pending' }, { status: 'pending' })).toBe(false);
    expect(paramsFiltered({ status: 'closed' }, { status: 'pending' })).toBe(true);
  });

  test('a reload that Angular refuses (a request is in flight) is made again once that request has settled', async () => {
    // Angular's own rule: reload() returns false and does nothing while a request is pending
    const state = { inFlight: true };
    let asks = 0;
    const resource = {
      reload: (): boolean => (state.inFlight ? false : (asks++, true)),
      isLoading: (): boolean => state.inFlight,
    };
    const settle = (): Promise<void> => new Promise((resolve) => setTimeout(resolve, 150));

    // refused while in flight: nothing is asked yet
    reloadList(resource);
    expect(asks).toBe(0);
    await settle();
    expect(asks, 'not before the in-flight request settled').toBe(0);
    state.inFlight = false;
    await settle();
    expect(asks, 'asked again once it settled').toBe(1);

    // the retry is one-shot: a later reload cycle does not ask by itself
    state.inFlight = true;
    state.inFlight = false;
    await settle();
    expect(asks).toBe(1);

    // accepted at once when nothing is in flight
    reloadList(resource);
    expect(asks).toBe(2);
    // and a resource that does not say whether it reloaded counts as accepted
    let calls = 0;
    reloadList({ reload: () => void calls++, isLoading: () => true });
    expect(calls).toBe(1);
  });
});

for (const theme of ['light', 'dark'] as const) {
  for (const width of [1280, 360]) {
    test(`N-08 first run: every list says what to do next, and only for a role that may — ${theme} @ ${width}`, async ({ page }) => {
      test.setTimeout(150_000);
      await page.setViewportSize({ width, height: 800 });
      await login(page);
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
      // nothing may be changed by this test. Registered first: the list mocks
      // registered after it take precedence
      const writes = await refuseWrites(page);
      for (const list of ['custodies', 'expenses', 'social-cases', 'purchase-requests', 'tasks']) {
        await page.route(`**/api/v1/${list}?*`, answerEmpty);
      }

      /** route → what its first-run state must say and where its call to action goes (this account may do all of them) */
      const expected: [string, string, string][] = [
        ['/custodies', 'لا توجد عهدات بعد', '/custodies/new'],
        ['/expenses', 'لم تُسجَّل مصروفات بعد', '/expenses/new'],
        ['/expenses/mine', 'لم تسجّل أي مصروفات بعد', '/expenses/new'],
        ['/social-cases', 'لا توجد حالات بعد', '/social-cases/new'],
        ['/social-cases/mine', 'لا توجد حالات مسجلة باسمك بعد', '/social-cases/new'],
        ['/procurement/purchase', 'لا توجد طلبات شراء بعد', '/procurement/purchase/new'],
        ['/tasks', 'لا توجد مهام بعد', '/tasks/new'],
      ];
      for (const [route, title, href] of expected) {
        await page.goto('/app' + route, { waitUntil: 'domcontentloaded' });
        await page.locator('app-empty-state .empty-state__title').first().waitFor({ state: 'visible', timeout: 25_000 });
        await page.waitForTimeout(400);
        const state = await page.evaluate(readOnboarding);
        expect(state, route).not.toBeNull();
        // a different state from "no results": its own title, a hint, and a way forward
        expect(state!.title, `${route} title`).toBe(title);
        expect(state!.hint.length, `${route} hint`).toBeGreaterThan(10);
        expect(state!.cta.length, `${route} call to action`).toBeGreaterThan(3);
        // the app lives under /app: a router link carries that prefix
        expect(state!.href, `${route} call to action goes to`).toBe('/app' + href);
        expect(page.url(), 'the address did not change').toContain('/app' + route);
        if (width === 1280 || route === '/custodies' || route === '/expenses') {
          await wholePage(page, `n08-first-run__${route.replaceAll('/', '~')}__${theme}__${width}`);
        }
      }
      // the header keeps its own create button on the page that has one — the state's is the SECOND way in
      await page.goto('/app/custodies', { waitUntil: 'domcontentloaded' });
      await page.locator('app-empty-state .empty-state__action a').waitFor({ state: 'visible', timeout: 25_000 });
      expect(await page.locator('app-page-header .p-button').count()).toBeGreaterThan(0);
      expect(writes).toEqual([]);
    });
  }
}

test.describe('N-08 a role without the ability gets no button', () => {
  for (const [route, list, abilities] of [
    ['/custodies', 'custodies', { may_create_custody: false, is_agent: false }],
    ['/expenses', 'expenses', { may_spend: false, owns_custody: false }],
    ['/social-cases', 'social-cases', { may_manage_social_cases: false }],
  ] as const) {
    test(`${route}: no call to action in the empty state, and none in the header either`, async ({ page }) => {
      test.setTimeout(120_000);
      await login(page);
      await page.setViewportSize({ width: 1280, height: 800 });
      // Handlers match in reverse registration order, so the guard goes FIRST:
      // registered after the others it would answer /me itself, and the edited
      // abilities would never be read
      await refuseWrites(page);
      await meWith(page, abilities);
      await page.route(`**/api/v1/${list}?*`, answerEmpty);
      await page.goto('/app' + route, { waitUntil: 'domcontentloaded' });
      await page.locator('app-empty-state .empty-state__title').first().waitFor({ state: 'visible', timeout: 25_000 });
      await page.waitForTimeout(400);
      const state = await page.evaluate(readOnboarding);
      expect(state!.title.length).toBeGreaterThan(3);
      expect(state!.cta, 'no call to action for this role').toBe('');
      // and the page header offers no way around it: no link to the create page,
      // no "record" button (the "⋯" menu is not a way around anything)
      await expect(page.locator('app-page-header a[href$="/new"], app-page-header .p-button-soft, app-page-header .p-splitbutton'), 'the header').toHaveCount(0);
      // the abilities really were read from the edited answer
      const applied = await page.evaluate(async () => {
        const me = await (await fetch('/api/v1/me')).json();
        return me.data.abilities as Record<string, boolean>;
      });
      for (const [ability, value] of Object.entries(abilities)) {
        expect(applied[ability], ability).toBe(false);
      }
    });
  }
});

test.describe('N-08 no results for this filter: the table stays, and offers the way back', () => {
  test('typing a search nothing matches leaves the toolbar in place and "clear the filters" brings the rows back', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await emptyWhen(page, 'expenses', (url) => url.searchParams.get('q') === NO_MATCH);
    await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });

    await page.locator('.ledger-table__filter').fill(NO_MATCH);
    await page.locator('.ledger-table .empty-state').waitFor({ state: 'visible', timeout: 15_000 });
    // the state is the other one, and the table around it is still there: search box, "⋯" menu
    expect((await page.locator('.ledger-table .empty-state__title').innerText()).trim()).toBe('لا توجد نتائج مطابقة');
    await expect(page.locator('.ledger-table__filter')).toBeVisible();
    await expect(page.locator('.ledger-table .topbar__icon-btn')).toBeVisible();
    // and it is not the onboarding state
    expect(await page.locator('.ledger-table .empty-state__action').innerText()).toContain('مسح الفلاتر');
    await wholePage(page, 'n08-no-results__expenses__light__1280');

    // the way back: the search is gone from the request and the rows return
    const cleared = page.waitForRequest((request) => /\/api\/v1\/expenses\?/.test(request.url()) && !new URL(request.url()).searchParams.get('q'));
    await page.locator('.ledger-table .empty-state__action button').click();
    await cleared;
    // … without the page flashing its "no data at all" state while the reload runs
    await noOnboardingFlash(page);
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 15_000 });
    await expect(page.locator('.ledger-table__filter')).toHaveValue('');
  });

  test('the social-cases search reaches the server (it used to be typed into nothing)', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    // every list request the page makes, in order: the search must be the last one asked
    const asked: string[] = [];
    await emptyWhen(page, 'social-cases', (url) => {
      asked.push(url.searchParams.get('q') ?? '');
      return url.searchParams.get('q') === NO_MATCH;
    });
    await page.goto('/app/social-cases', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });
    await page.locator('.ledger-table__filter').fill(NO_MATCH);
    await page.locator('.ledger-table .empty-state').waitFor({ state: 'visible', timeout: 15_000 });
    expect(asked.at(-1), `the list requests, in order: ${JSON.stringify(asked)}`).toBe(NO_MATCH);
    // the page's own onboarding state did not replace the table
    await expect(page.locator('app-async-page > app-empty-state')).toHaveCount(0);
  });

  test('a filter the page keeps outside the table (the status chips) counts too — on custodies', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await emptyWhen(page, 'custodies', (url) => url.searchParams.get('status') === 'rejected');
    await page.goto('/app/custodies', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });
    // the chips in their declared order: all, pending, accepted, active, rejected, closed
    const chip = page.locator('.filter-chips__chip').nth(4);
    await chip.click();
    await page.locator('.ledger-table .empty-state').waitFor({ state: 'visible', timeout: 15_000 });
    await expect(chip).toHaveAttribute('aria-pressed', 'true');
    await page.locator('.ledger-table .empty-state__action button').click();
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 15_000 });
    await expect(chip, 'the chip is no longer pressed').toHaveAttribute('aria-pressed', 'false');
  });

  test('"no data at all" and "no results" never show the same words', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    let noRowsAtAll = false;
    await page.route('**/api/v1/tasks?*', async (route) => {
      if (noRowsAtAll || new URL(route.request().url()).searchParams.get('q') === NO_MATCH) {
        await answerEmpty(route);
      } else {
        await route.continue();
      }
    });
    // tasks has no page-level filter: the table answers both from its own state
    await page.goto('/app/tasks', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });
    await page.locator('.ledger-table__filter').fill(NO_MATCH);
    await page.locator('.ledger-table .empty-state').waitFor({ state: 'visible', timeout: 15_000 });
    const filtered = (await page.locator('.ledger-table .empty-state__title').innerText()).trim();
    await page.locator('.ledger-table .empty-state__action button').click();
    await noOnboardingFlash(page);
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 15_000 });
    // and the same list with nothing in it at all — the page's own state, other words
    noRowsAtAll = true;
    await page.goto('/app/tasks', { waitUntil: 'domcontentloaded' });
    await page.locator('app-empty-state .empty-state__title').first().waitFor({ state: 'visible', timeout: 25_000 });
    await page.waitForTimeout(400);
    const first = await page.evaluate(readOnboarding);
    expect(first!.title).not.toBe(filtered);
    expect(first!.cta.length).toBeGreaterThan(3);
  });

  test('on a slow network: no "no results" before the answer, and a search cleared while its request is still in flight still ends with the rows', async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    // the list answers slowly; a search nothing matches answers empty
    await page.route('**/api/v1/tasks?*', async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 1_200));
      if (new URL(route.request().url()).searchParams.get('q') === NO_MATCH) {
        await answerEmpty(route);
      } else {
        await route.continue();
      }
    });
    await page.goto('/app/tasks', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });

    // the search is typed and its request is in flight for over a second
    await page.locator('.ledger-table__filter').fill(NO_MATCH);
    await page.waitForTimeout(400);
    // the server has not answered: no "no results" state (and no button to clear) yet
    await expect(page.locator('.ledger-table .empty-state'), 'no "no results" before the server answered').toHaveCount(0);
    // … and the box is emptied again BEFORE the answer lands
    await page.locator('.ledger-table__filter').fill('');
    // the final state: the rows, an empty box, no onboarding state
    await page.locator('.ledger-table tbody tr').nth(3).waitFor({ state: 'visible', timeout: 25_000 });
    await expect(page.locator('.ledger-table__filter')).toHaveValue('');
    expect(await page.evaluate(readOnboarding)).toBeNull();
    const last = await page.evaluate(() => [...document.querySelectorAll('.ledger-table tbody tr')].length);
    expect(last).toBeGreaterThanOrEqual(4);
  });

  test("a search typed while a load is still on its way: no empty state until the search's OWN answer — however the reload was refused", async ({ page }) => {
    test.setTimeout(120_000);
    await login(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    /**
     * A list asks twice when it opens (the resource's own load, then the table's first
     * query). The second answer is held back here, and the search is typed while it is
     * in flight: Angular refuses to restart an in-progress load, and `reloadList` asks
     * again up to 30ms after the old answer lands. In that gap the page is "not
     * loading", with the OLD rows under the NEW search box — which narrows them to
     * nothing. Nothing may be called "empty" before the search's own answer: round 6
     * flashed "no results for this filter" there, before the search was even asked.
     */
    const asked: string[] = [];
    let unfiltered = 0;
    await page.route('**/api/v1/social-cases?*', async (route) => {
      const q = new URL(route.request().url()).searchParams.get('q') ?? '';
      asked.push(q);
      if (q === NO_MATCH) {
        await answerEmpty(route);
        return;
      }
      // the list's own second load: slow, as on a poor connection
      if (++unfiltered >= 2) {
        await new Promise((resolve) => setTimeout(resolve, 1_500));
      }
      await route.continue();
    });
    await page.goto('/app/social-cases', { waitUntil: 'domcontentloaded' });
    await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });
    // the second load is on its way now, held for 1.5s: there is time to type inside it
    await expect.poll(() => unfiltered, { timeout: 10_000 }).toBe(2);
    // every empty state the table ever gets, and when — in the page's own clock
    await page.evaluate(() => {
      const inserted: number[] = [];
      (window as unknown as { __emptyStates: number[] }).__emptyStates = inserted;
      const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
          for (const node of mutation.addedNodes) {
            if (node instanceof Element && node.querySelector('.empty-state') !== null) {
              inserted.push(performance.now());
            }
          }
        }
      });
      observer.observe(document.querySelector('.ledger-table') ?? document.body, { childList: true, subtree: true });
    });
    await page.locator('.ledger-table__filter').fill(NO_MATCH);
    // the search's answer has come: "no results for this filter" is the final state
    await page.locator('.ledger-table .empty-state__action button').waitFor({ state: 'visible', timeout: 25_000 });
    await page.waitForTimeout(600);
    // when the browser started the search's own request — the same clock as the insertions above.
    // (Measured from the answer instead, the state that the answer itself brings read as 9ms
    // "early" once: the runner stamps the answer after the browser has it.)
    const { inserted, searchAskedAt } = await page.evaluate((term) => {
      const list = (window as unknown as { __emptyStates?: number[] }).__emptyStates ?? [];
      const entry = performance.getEntriesByType('resource').find((candidate) => candidate.name.includes(`q=${term}`));
      return { inserted: list.map((at) => Math.round(at)), searchAskedAt: entry ? Math.round(entry.startTime) : null };
    }, NO_MATCH);
    const before = inserted.filter((at) => searchAskedAt !== null && at < searchAskedAt);
    expect(asked.filter((q) => q === NO_MATCH), 'the search was asked once').toHaveLength(1);
    expect(searchAskedAt, 'the search request is in the page timing').not.toBeNull();
    expect(
      before.length,
      `empty states put into the table BEFORE the search was asked (${JSON.stringify(inserted.map((at) => at - (searchAskedAt ?? 0)))} ms around it)`,
    ).toBe(0);
    expect(inserted.length, 'the answer itself brings the state').toBeGreaterThan(0);
  });
});

/** "no results for this filter" as it looks, for the delivery: both themes, both widths */
for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`N-08 "no results for this filter" as it looks — ${theme} @ ${width}`, async ({ page }) => {
      test.setTimeout(120_000);
      await login(page);
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
      await page.setViewportSize({ width, height: 800 });
      await refuseWrites(page);
      await emptyWhen(page, 'expenses', (url) => url.searchParams.get('q') === NO_MATCH);
      await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
      await page.locator('.ledger-table tbody tr').first().waitFor({ state: 'visible', timeout: 25_000 });
      await page.locator('.ledger-table__filter').fill(NO_MATCH);
      await page.locator('.ledger-table .empty-state__action button').waitFor({ state: 'visible', timeout: 15_000 });
      await page.waitForTimeout(500);
      await wholePage(page, `n08-no-results__expenses__${theme}__${width}`);
    });
  }
}
