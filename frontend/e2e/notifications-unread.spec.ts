import { expect, test, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';

/**
 * R5-04.1 — is an unread notification visibly marked?
 *
 * The visual matrix cannot answer that: the seeded account has 0 unread
 * notifications, so every delivered screenshot shows read rows only. This
 * spec answers it against a MOCKED response — the browser's request for the
 * list is answered here, the database is never written to and nothing is
 * clicked. It asserts the markers and saves the proof under
 * `e2e/screenshots/mock/` (a sub-folder on purpose: the matrix set stays
 * exactly 72 routes × light/dark × 360/1280).
 *
 * Needs the PHP server + the packed SPA, like the matrix.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';
const OUT = 'e2e/screenshots/mock';

/** three unread (today ×2, yesterday) and three read — every group and both states */
const NOW = Date.now();
const HOUR = 3_600_000;
const MOCK = [
  { id: 101, title: '\u0645\u0635\u0631\u0648\u0641 \u062C\u062F\u064A\u062F \u0644\u0644\u0645\u0631\u0627\u062C\u0639\u0629', message: '\u0639\u0628\u062F\u0627\u0644\u0631\u062D\u0645\u0646 \u064A\u0627\u0633\u0631 \u0633\u062C\u0651\u0644 \u0645\u0635\u0631\u0648\u0641\u0627\u064B \u062C\u062F\u064A\u062F\u0627\u064B \u0628\u0642\u064A\u0645\u0629 1,250.00 \u062C.\u0645\u060C \u0648\u0647\u0648 \u0628\u0627\u0646\u062A\u0638\u0627\u0631 \u0645\u0631\u0627\u062C\u0639\u062A\u0643 \u0648\u0627\u0639\u062A\u0645\u0627\u062F\u0647.', type: 'info', is_read: false, link: '/expenses/1', created_at: new Date(NOW - 5 * 60_000).toISOString() },
  { id: 102, title: '\u0637\u0644\u0628 \u0639\u0647\u062F\u0629 \u0634\u062E\u0635\u064A\u0629 \u064A\u062D\u062A\u0627\u062C \u0645\u0648\u0627\u0641\u0642\u0629', message: 'hossam ismaiel \u0637\u0644\u0628 \u0639\u0647\u062F\u0629 \u0634\u062E\u0635\u064A\u0629 \u0628\u0642\u064A\u0645\u0629 215 \u062C.\u0645', type: 'warning', is_read: false, link: '/custodies/1', created_at: new Date(NOW - 3 * HOUR).toISOString() },
  { id: 103, title: '\u062A\u0645 \u0627\u0639\u062A\u0645\u0627\u062F \u0637\u0644\u0628 \u0634\u0631\u0627\u0621', message: '\u062A\u0645 \u0627\u0639\u062A\u0645\u0627\u062F \u0637\u0644\u0628 \u0627\u0644\u0634\u0631\u0627\u0621 \u0631\u0642\u0645 12', type: 'success', is_read: true, link: '/procurement/purchase/1', created_at: new Date(NOW - 9 * HOUR).toISOString() },
  { id: 104, title: '\u062A\u0630\u0643\u064A\u0631 \u0628\u0635\u0644\u0627\u062D\u064A\u0629 \u0627\u0644\u0639\u0647\u062F', message: '\u0644\u062F\u064A\u0643 \u0639\u0647\u062F\u0629 \u062A\u0642\u062A\u0631\u0628 \u0645\u0646 \u062A\u0627\u0631\u064A\u062E \u0631\u062F\u0651\u0647\u0627', type: 'warning', is_read: false, link: null, created_at: new Date(NOW - 26 * HOUR).toISOString() },
  { id: 105, title: '\u0645\u0635\u0631\u0648\u0641 \u062C\u062F\u064A\u062F', message: '\u0645\u0635\u0631\u0648\u0641 \u062C\u062F\u064A\u062F: \u0628\u0648\u0641\u064A\u0647 \u0627\u0644\u062C\u0645\u0639\u064A\u0629 - 39 \u062C.\u0645', type: 'info', is_read: true, link: '/expenses/9', created_at: new Date(NOW - 31 * HOUR).toISOString() },
  { id: 106, title: '\u0637\u0644\u0628 \u0631\u062F \u0639\u0647\u062F\u0629', message: 'Mohamed Tarek \u0637\u0644\u0628 \u0631\u062F \u0645\u0628\u0644\u063A 500 \u062C.\u0645', type: 'warning', is_read: true, link: '/custodies/4', created_at: new Date(NOW - 12 * 24 * HOUR).toISOString() },
];

/** WCAG contrast of two opaque css colours */
function contrast(a: string, b: string): number {
  const lum = (css: string): number => {
    const [r, g, b2] = (css.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number).map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b2;
  };
  const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x);
  return (hi + 0.05) / (lo + 0.05);
}

/** runs in the page: the marker of every row, with the dot's real backdrop */
function measureUnread(): { unread: boolean; dot: string; card: string; weight: string; sr: string }[] {
  const painted = (el: Element | null): string => {
    for (let node = el; node; node = node.parentElement) {
      const bg = getComputedStyle(node).backgroundColor;
      if (bg && !/,\s*0\)$/.test(bg)) {
        return bg;
      }
    }
    return 'rgb(255, 255, 255)';
  };
  return [...document.querySelectorAll<HTMLElement>('.notice')].map((row) => {
    const dot = row.querySelector<HTMLElement>('.notice__dot') as HTMLElement;
    const title = row.querySelector<HTMLElement>('.notice__title') as HTMLElement;
    return {
      unread: row.classList.contains('notice--unread'),
      dot: getComputedStyle(dot).backgroundColor,
      card: painted(dot.parentElement),
      weight: getComputedStyle(title).fontWeight,
      sr: (row.querySelector('.u-visually-hidden')?.textContent ?? '').trim(),
    };
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

for (const theme of ['light', 'dark'] as const) {
  for (const width of [1280, 360]) {
    test(`unread notifications are visibly marked — ${theme} @ ${width} (mocked data)`, async ({ page }) => {
      test.setTimeout(120_000);
      await login(page);
      await page.addInitScript(seedThemeInitScript);
      // nothing may be marked read while this runs: only GETs leave the page
      const writes: string[] = [];
      page.on('request', (request) => {
        if (request.method() !== 'GET' && /\/notifications(\/|$|\?)/.test(request.url())) {
          writes.push(`${request.method()} ${request.url()}`);
        }
      });
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
      await page.route('**/api/v1/notifications?*', (route) =>
        route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: MOCK, meta: { page: 1, per_page: 25, total: 499, last_page: 20 }, links: [] }) }),
      );
      await page.setViewportSize({ width, height: 800 });
      await page.goto('/app/notifications', { waitUntil: 'domcontentloaded' });
      await page.locator('.notice').first().waitFor({ state: 'visible', timeout: 25_000 });
      await page.waitForTimeout(800);

      const rows = await page.evaluate(measureUnread);
      expect(rows).toHaveLength(6);
      const unread = rows.filter((r) => r.unread);
      const read = rows.filter((r) => !r.unread);
      expect(unread).toHaveLength(3);
      expect(read).toHaveLength(3);

      // the three groups the page is built from
      const headings = await page.locator('.notices__heading').allInnerTexts();
      expect(headings).toHaveLength(3);

      // every unread row: a SOLID dot against its card (≥ 3:1 — a UI indicator,
      // WCAG 1.4.11), a bold title, and the words for screen readers
      for (const row of unread) {
        expect(contrast(row.dot, row.card), `unread dot ${row.dot} on ${row.card}`).toBeGreaterThanOrEqual(3);
        expect(row.weight).toBe('700');
        expect(row.sr).not.toBe('');
      }
      // every read row: a faint hollow dot, the regular weight, no "unread" word
      for (const row of read) {
        expect(contrast(row.dot, row.card), `read dot ${row.dot} on ${row.card}`).toBeLessThan(3);
        expect(row.weight).toBe('600');
        expect(row.sr).toBe('');
      }
      // the two states are told apart by the dot alone (colour never the only cue: weight + sr text)
      expect(new Set(unread.map((r) => r.dot)).size).toBe(1);
      expect(unread[0].dot).not.toBe(read[0].dot);
      // what was measured, for the log
      console.log(
        `[notifications-unread][${theme} @ ${width}] unread: dot ${unread[0].dot} on ${unread[0].card} = ${contrast(unread[0].dot, unread[0].card).toFixed(2)}:1, title weight ${unread[0].weight}, sr "${unread[0].sr}" | read: dot ${read[0].dot} on ${read[0].card} = ${contrast(read[0].dot, read[0].card).toFixed(2)}:1, title weight ${read[0].weight} | groups: ${headings.join(' / ')}`,
      );

      // the proof, as a whole-page picture
      await page.evaluate(() => document.fonts.ready);
      const height = await page.evaluate(() => document.documentElement.scrollHeight);
      await page.setViewportSize({ width, height });
      await page.waitForTimeout(300);
      await page.screenshot({ path: `${OUT}/notifications-unread__${theme}__${width}.png` });

      // nothing was marked read by looking
      expect(writes).toEqual([]);
    });
  }
}
