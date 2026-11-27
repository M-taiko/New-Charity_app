import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import type { Locator, Page, Route } from '@playwright/test';

/**
 * Support for the N-06 specs: an API where every write is answered by the test
 * and nothing reaches the database, list answers built on the server's real
 * ones, and the Arabic labels read from the translation file.
 */

export const EMAIL = 'donia.a5ra2019@gmail.com';
export const PASSWORD = '123456789';

/** the app's own Arabic — read, never retyped, so a renamed label fails loudly instead of silently */
export const AR = JSON.parse(readFileSync(resolve(__dirname, '../src/assets/i18n/ar.json'), 'utf8')) as Record<string, unknown>;
export function label(path: string): string {
  const value = path.split('.').reduce<unknown>((node, key) => (node as Record<string, unknown> | undefined)?.[key], AR);
  if (typeof value !== 'string' || value === '') {
    throw new Error(`no translation for ${path}`);
  }
  return value;
}

export interface Write {
  readonly method: string;
  /** the path after /api/v1, e.g. `/expenses/9101/mark-reviewed` */
  readonly path: string;
  readonly body: string;
}

/** what the test does with a write: answer it, hold it, refuse it */
export type WriteAnswer = (write: Write, route: Route) => Promise<void>;

export interface MockApi {
  readonly writes: Write[];
  /** set it to answer writes; unset, every write is refused */
  onWrite: WriteAnswer | null;
  /** how many writes of this method hit a path matching `pattern` */
  count(method: string, pattern: RegExp): number;
}

/** JSON as the server's action endpoints answer */
export function actionJson(message: string, data: unknown = null): string {
  return JSON.stringify({ data, message, level: 'success' });
}

/**
 * Refuses every write unless the test answers it. Handlers match in reverse
 * registration order: register this FIRST, list answers after.
 * `POST /broadcasts/active` is the top bar READING the active broadcast, and
 * the login/logout posts are the session — none writes data.
 */
export async function mockApi(page: Page): Promise<MockApi> {
  const writes: Write[] = [];
  const api: MockApi = {
    writes,
    onWrite: null,
    count: (method, pattern) => writes.filter((write) => write.method === method && pattern.test(write.path)).length,
  };
  await page.route('**/api/v1/**', async (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname.replace('/api/v1', '');
    const sessionOrRead = /\/broadcasts\/active|\/auth\/(login|logout)/.test(path);
    if (request.method() !== 'GET' && !sessionOrRead) {
      const write: Write = { method: request.method(), path, body: request.postData() ?? '' };
      writes.push(write);
      if (api.onWrite !== null) {
        await api.onWrite(write, route);
      } else {
        await route.abort();
      }
      return;
    }
    await route.continue();
  });
  return api;
}

/** the server's real answer, changed by `edit` — every meta stays genuine */
export async function answerList(route: Route, edit: (json: Record<string, unknown>) => void): Promise<void> {
  try {
    const real = await route.fetch();
    const json = (await real.json()) as Record<string, unknown>;
    edit(json);
    await route.fulfill({ response: real, json });
  } catch {
    // the page went away while the answer was on its way (the test already ended)
  }
}

/** a held response: the handler waits until the test opens the gate */
export function gate(): { wait: Promise<void>; open: () => void } {
  let open!: () => void;
  const wait = new Promise<void>((resolveGate) => { open = resolveGate; });
  return { wait, open };
}

export async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

/** one table row, found by text that only it has (it survives the list's reloads) */
export function rowWithText(page: Page, text: string): Locator {
  return page.locator('app-data-table tbody tr').filter({ hasText: text });
}

/** the toast that offers an undo (there is at most one at a time) */
export function undoToast(page: Page): Locator {
  return page.locator('.toast-host__item').filter({ has: page.locator('.toast-host__undo') });
}

/** the visible dialog of the moment (confirm, reason or form) */
export function dialog(page: Page): Locator {
  return page.getByRole('dialog').filter({ visible: true });
}

/** the last button of a dialog's footer — its confirm */
export function confirmButton(page: Page): Locator {
  return dialog(page).locator('.p-dialog-footer button').last();
}

/** the ratio of two luminances, the lighter first */
export function contrast(a: number, b: number): number {
  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}
