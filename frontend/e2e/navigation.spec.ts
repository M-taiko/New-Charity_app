import { expect, test, type Page } from '@playwright/test';

/**
 * SPA navigation must never flash blank (user report: "the page blinks when
 * switching between pages — blank then content").
 *
 * Clicks through real sidebar links and samples every animation frame:
 *   - the page opacity must stay 1 (the old page-enter animation faded the
 *     whole <body> in from opacity 0 on every route change)
 *   - <main> must never lose its children once painted (lazy route chunks
 *     are preloaded; the router's view transition keeps the old page until
 *     the new one rendered)
 */
const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 30_000 });
  // let the preloader finish fetching every route chunk in the background
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1_500);
}

test.describe('SPA navigation has no blank flash', () => {
  test('page opacity stays 1 and main is never emptied on route change', async ({ page }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (e) => pageErrors.push(String(e).slice(0, 160)));
    await login(page);

    const routes = ['/custodies', '/expenses', '/treasury', '/social-cases', '/reports/agents-balance', '/tasks', '/hr'];
    for (const route of routes) {
      const result = await page.evaluate(async (target) => {
        const link = [...document.querySelectorAll<HTMLAnchorElement>('a[routerLink]')]
          .find((a) => a.getAttribute('href')?.endsWith(target));
        if (link) {
          link.click();
        } else {
          history.pushState({}, '', '/app' + target);
          window.dispatchEvent(new PopStateEvent('popstate'));
        }
        let blankFrames = 0;
        let frames = 0;
        let started = false;
        let minOpacity = 1;
        await new Promise<void>((resolve) => {
          const tick = () => {
            const main = document.querySelector('main');
            const kids = main?.childElementCount ?? 0;
            const opacity = Number(getComputedStyle(document.body).opacity);
            minOpacity = Math.min(minOpacity, opacity);
            frames++;
            if (kids > 0 && !started) {
              started = true;
            } else if (started && kids === 0) {
              blankFrames++;
            }
            if (frames < 90) {
              requestAnimationFrame(tick);
            } else {
              resolve();
            }
          };
          requestAnimationFrame(tick);
        });
        return { blankFrames, minOpacity: Math.round(minOpacity * 100) / 100 };
      }, route);

      expect(result.minOpacity, `${route}: page faded to opacity ${result.minOpacity}`).toBe(1);
      expect(result.blankFrames, `${route}: <main> was emptied for ${result.blankFrames} frame(s)`).toBe(0);
      await page.waitForTimeout(700);
    }

    // rapid navigation while a view transition is still running must not
    // surface an unhandled AbortError pageerror
    for (const route of ['/custodies', '/expenses', '/treasury', '/custodies']) {
      await page.evaluate((target) => {
        [...document.querySelectorAll<HTMLAnchorElement>('a[routerLink]')]
          .find((a) => a.getAttribute('href')?.endsWith(target))?.click();
      }, route);
      await page.waitForTimeout(120);
    }
    await page.waitForTimeout(1_500);
    expect(pageErrors, `unhandled page errors during navigation: ${pageErrors.join(' | ')}`).toEqual([]);
  });
});
