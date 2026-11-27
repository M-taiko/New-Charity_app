import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import {
  RAW_I18N_KEY,
  auditRoute,
  reportedSummary,
  screenshotWholePage,
  seedThemeInitScript,
  type AuditContext,
  type Theme,
  type Viewport,
} from './gates';

/**
 * Visual QA matrix (Task4 T4-28): every route × light/dark × 360/1280 px.
 * Saves one WHOLE-PAGE screenshot per combination under e2e/screenshots/ and
 * fails on what code scanning cannot catch. The gates themselves live in
 * `e2e/gates.ts` — next to `e2e/gates.spec.ts`, which proves each one can fail.
 *
 *   scroll · console errors · raw i18n keys · empty <main> · focus steal ·
 *   sprite refs · ellipsized numbers · sidebar state and pitch · clipped
 *   cells · dashboard tile geometry · equal card heights · actions buttons ·
 *   one table per screen — and since round 4: the theme is really PAINTED
 *   (R4-01: all "dark" screenshots used to be light), no raw `true/false` in
 *   a table cell (R4-03), one KPI-row rule (R4-04), no surface painted in the
 *   other theme's colour, and a page that opens at its top.
 *
 * Needs the PHP server + seeded data + the packed SPA (same as spa.spec.ts).
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';

/** every leaf route (app.routes.ts + feature routes); :id → seeded id 1 */
const ROUTES: readonly string[] = [
  'dashboard',
  'treasury', 'treasury/new', 'treasury/transfer', 'treasury/1', 'treasury/1/edit',
  'custodies', 'custodies/new', 'custodies/mine', 'custodies/mine/movements',
  'custodies/movements', 'custodies/return-requests', 'custodies/tracking',
  'custodies/1', 'custodies/1/edit',
  'transfers', 'transfers/new', 'transfers/1',
  'expenses', 'expenses/new', 'expenses/mine', 'expenses/1', 'expenses/1/edit',
  'expenses/edit-requests', 'expenses/edit-requests/1', 'expenses/1/edit-request',
  'directions',
  'reports/agents-balance', 'reports/case-expenses', 'reports/directions',
  'reports/governorates', 'reports/items', 'reports/reconciliation', 'reports/researchers',
  'social-cases', 'social-cases/new', 'social-cases/mine', 'social-cases/1', 'social-cases/1/edit',
  'hr', 'hr/employees', 'hr/employees/new', 'hr/employees/1/edit', 'hr/employees/1/salaries',
  'hr/attendance', 'hr/kpi', 'hr/salaries', 'hr/salaries/1', 'hr/salaries/1/edit',
  'procurement/purchase', 'procurement/purchase/new', 'procurement/purchase/1',
  'procurement/maintenance', 'procurement/maintenance/new', 'procurement/maintenance/1',
  'procurement/suppliers',
  'tasks', 'tasks/new', 'tasks/1',
  'chat', 'notifications', 'broadcasts', 'activity', 'activity/mine',
  'users', 'users/new', 'users/1', 'users/1/edit',
  'settings', 'profile',
  // R3-07.3: error pages join the audited routes
  'forbidden', 'not-found',
];

const THEMES: readonly Theme[] = ['light', 'dark'];
const VIEWPORTS: readonly Viewport[] = [
  { name: '360', width: 360, height: 800 },
  { name: '1280', width: 1280, height: 800 },
];

/** the packed sprite's symbol ids — the source of truth for the icon gate (B-01).
 *  public/app lives at the REPO root (frontend/ is a sibling of public/). */
const CONTEXT: AuditContext = {
  spriteIds: new Set(
    [...readFileSync(resolve(__dirname, '../../public/app/assets/icons/sprite.svg'), 'utf8').matchAll(/id="([^"]+)"/g)].map((m) => m[1]),
  ),
};

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });

  // guard the login page itself (it is never a checkRoute target): submit
  // empty fields and assert the validation state renders NO raw i18n key
  // (the errors.generic/required_hint banner once leaked untranslated)
  await page.click('button[type="submit"]');
  await page.waitForTimeout(400);
  const loginText = (await page.locator('form').first().innerText().catch(() => '')) || '';
  const loginRawKey = RAW_I18N_KEY.exec(loginText);
  expect(loginRawKey, `login page raw i18n key "${loginRawKey?.[0] ?? ''}"`).toBeNull();

  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await expect(page.locator('.sidebar__link').first()).toBeVisible({ timeout: 30_000 });
}

test.describe.serial('visual QA matrix', () => {
  test('every route in light/dark × 360/1280 with no scroll, errors, raw keys, blank pages or false-dark renders', async ({ page }) => {
    // 288 visits (72 routes × light/dark × 360/1280) × ~3.4s ≈ 16 minutes —
    // no ceiling for this soak test
    test.setTimeout(0);
    await login(page);

    // R4-01: the theme reaches the app through the storage it restores from.
    // The old `document.documentElement.setAttribute('data-theme', t)` before
    // `goto()` was discarded by the navigation, so all 144 "dark" screenshots
    // were light (mean brightness 243 — identical to the light set).
    await page.addInitScript(seedThemeInitScript);

    const excused: string[] = [];
    const reported: string[] = [];
    const NOT_FIXED = '(reported in round 5, not fixed)';
    for (const route of ROUTES) {
      for (const theme of THEMES) {
        for (const viewport of VIEWPORTS) {
          const { problems, notes } = await auditRoute(page, { route, theme, viewport }, CONTEXT);
          excused.push(...notes.filter((note) => !note.includes(NOT_FIXED)));
          reported.push(...notes.filter((note) => note.includes(NOT_FIXED)));

          // R4-08.1: whole page — a 1280×800 crop cut every long page
          // (dashboard, forms, custody detail) in half. Taken BEFORE the
          // assertion so a failing visit leaves its picture behind.
          await screenshotWholePage(
            page,
            `e2e/screenshots/${route.replaceAll('/', '~')}__${theme}__${viewport.name}.png`,
            viewport,
          );

          // soft on purpose: one run reports EVERY failing visit instead of
          // stopping at the first and costing 16 minutes per finding
          expect.soft(problems, problems.join('\n')).toEqual([]);
        }
      }
    }
    // nothing is silenced quietly: what the gates saw and excused is in the
    // log — and what is known and deliberately not fixed is counted apart
    const visits = ROUTES.length * THEMES.length * VIEWPORTS.length;
    console.log(`visual matrix: ${visits} visits; excused by a verified reason: ${excused.length}; reported and not fixed: ${reported.length} visits`);
    for (const note of excused) {
      console.log(`  excused: ${note}`);
    }
    if (reported.length) {
      console.log(`  reported, not fixed: ${reportedSummary(reported)}`);
    }
  });
});
