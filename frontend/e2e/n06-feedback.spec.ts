import { mkdirSync } from 'node:fs';
import { expect, test, type Page } from '@playwright/test';
import { relativeLuminance, seedThemeInitScript } from './gates';
import { actionJson, answerList, confirmButton, contrast, dialog, gate, label, login, mockApi, rowWithText, undoToast } from './n06-support';

/**
 * N-06 — the review decision on the expenses list: shown at once, undone from
 * the toast (button and Ctrl+Z), put back when the server refuses. Then the
 * toast and the confirm as they read in light/dark × 360/1280.
 *
 * Against the real app. Every write is answered by this file and nothing
 * reaches the database; the list is the server's real answer with the test's
 * row in it. Needs the PHP server + the packed SPA.
 */

const OUT = 'e2e/screenshots/n06';
const NOW = new Date().toISOString();
const EXPENSE = { id: 9101, user: 'مندوب تجريبي', custody_id: 4, case: null, type: 'general', amount: '250.00', description: 'مصروف للقياس', created_at: NOW, expense_date: NOW, direction: 'زكاة', has_direction: true, reviewed: false, reviewed_by: null, reviewed_at: null, is_quick_expense: false, items_count: 0, has_attachment: false, can_review: true, can_unreview: false, can_edit: true };
const REVIEWED = label('enums.review_state.reviewed');
const NOT_REVIEWED = label('enums.review_state.not_reviewed');
const REVIEW = label('expense.list.review');
const MARK = /\/expenses\/9101\/mark-reviewed$/;
const UNDO = /\/expenses\/9101\/review-undo$/;
/** `ToastService.UNDO_DURATION_MS` — a toast with an undo stays this long (a plain one leaves after 3.5 s) */
const UNDO_MS = 8000;
const AA = 4.5;

async function fulfillJson(route: { fulfill: (options: { status: number; contentType: string; body: string }) => Promise<void> }, status: number, body: string): Promise<void> {
  await route.fulfill({ status, contentType: 'application/json', body });
}

/** the expenses list with one row the test owns; `state.reviewed` is what the server would now say */
async function expensesWithTestRow(page: Page, state: { reviewed: boolean }): Promise<void> {
  await page.route('**/api/v1/expenses?*', (route) =>
    answerList(route, (json) => {
      const meta = json.meta as Record<string, unknown>;
      const rest = (json.data as { id: number }[]).filter((line) => line.id !== EXPENSE.id);
      json.data = [{ ...EXPENSE, reviewed: state.reviewed, reviewed_by: state.reviewed ? 'محمد طارق' : null, reviewed_at: state.reviewed ? NOW : null, can_review: !state.reviewed, can_unreview: state.reviewed }, ...rest];
      json.meta = { ...meta, total: Number(meta.total ?? 0) + 1 };
    }),
  );
}

/** what the rig is holding back */
interface Control {
  /** the review's answer waits for this gate */
  gate: ReturnType<typeof gate>;
  /** the status the next review is answered with */
  status: number;
}

interface Rig {
  readonly api: Awaited<ReturnType<typeof mockApi>>;
  readonly control: Control;
}

/** the list, with the review and its undo answered by the test through `control` */
async function rig(page: Page, state: { reviewed: boolean }): Promise<Rig> {
  const api = await mockApi(page);
  await expensesWithTestRow(page, state);
  const control: Control = { gate: gate(), status: 200 };
  api.onWrite = async (write, route) => {
    if (MARK.test(write.path)) {
      await control.gate.wait;
      if (control.status === 200) {
        state.reviewed = true;
        await fulfillJson(route, 200, actionJson('تمت مراجعة المصروف'));
      } else {
        await fulfillJson(route, control.status, JSON.stringify({ message: 'رفض الخادم للاختبار', errors: {} }));
      }
      return;
    }
    if (UNDO.test(write.path)) {
      state.reviewed = false;
      await fulfillJson(route, 200, actionJson('تم التراجع: أعيد المصروف إلى حالته قبل المراجعة'));
      return;
    }
    await route.abort();
  };
  return { api, control };
}

function row(page: Page) {
  return rowWithText(page, EXPENSE.user);
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test('a review shows at once, is undone from the toast, and goes back when the server refuses', async ({ page }) => {
  test.setTimeout(150_000);
  const state = { reviewed: false };
  const { api, control } = await rig(page, state);
  await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
  const stamp = row(page).locator('.stamp');
  const review = row(page).getByRole('button', { name: REVIEW, exact: true });
  await expect(stamp).toHaveText(NOT_REVIEWED);

  await review.click();
  // the confirm says what it is about — the money first
  const details = dialog(page).locator('.confirm-details');
  await expect(details).toContainText('250.00');
  await expect(details).toContainText(EXPENSE.user);
  await expect(details.locator('dt').first()).toHaveText(label('common.amount_egp'));
  // the dialog's ONE primary: it was red, as if destructive (a green or amber one reads 2.54:1 / 2.15:1 in light)
  const confirm = confirmButton(page);
  await expect(confirm).not.toHaveClass(/p-button-(danger|success|warn)/);

  await confirm.dblclick();
  // before the server answers: shown reviewed, no second decision on the row, one request
  await expect(stamp).toHaveText(REVIEWED);
  await expect(review).toHaveCount(0);
  expect(api.count('POST', MARK), 'a double-clicked confirm is one request').toBe(1);

  control.gate.open();
  await expect(undoToast(page)).toBeVisible();
  await expect(undoToast(page).locator('.toast-host__text')).toHaveText('تمت مراجعة المصروف');
  // the list came back from the server's answer: still reviewed
  await expect(stamp).toHaveText(REVIEWED);

  await undoToast(page).locator('.toast-host__undo').click();
  await expect.poll(() => api.count('POST', UNDO)).toBe(1);
  expect(api.count('POST', MARK), 'the undo is the inverse, not the action again').toBe(1);
  await expect(stamp).toHaveText(NOT_REVIEWED);
  await expect(review).toBeVisible();
  await expect(undoToast(page)).toHaveCount(0);

  // refused: shown reviewed at once, then put back, with the server's reason
  control.gate = gate();
  control.status = 422;
  await review.click();
  await confirmButton(page).click();
  await expect(stamp).toHaveText(REVIEWED);
  expect(api.count('POST', MARK), 'one request again').toBe(2);
  control.gate.open();
  await expect(stamp).toHaveText(NOT_REVIEWED);
  await expect(review).toBeVisible();
  await expect(page.locator('.toast-host__item--error .toast-host__text')).toHaveText('رفض الخادم للاختبار');
  expect(api.count('POST', UNDO), 'a refused review has nothing to undo').toBe(1);
});

test('Ctrl+Z undoes the newest undoable toast — but never inside a field', async ({ page }) => {
  test.setTimeout(120_000);
  const state = { reviewed: false };
  const { api, control } = await rig(page, state);
  await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
  await row(page).getByRole('button', { name: REVIEW, exact: true }).click();
  await confirmButton(page).click();
  control.gate.open();
  await expect(undoToast(page)).toBeVisible();
  await expect(undoToast(page).locator('.toast-host__undo')).toContainText(label('common.undo'));

  // in the table's search field the key is the field's own undo
  await page.locator('app-data-table input[type="search"]').click();
  await page.keyboard.press('Control+Z');
  await page.waitForTimeout(500);
  expect(api.count('POST', UNDO), 'no undo from inside a field').toBe(0);
  await expect(undoToast(page)).toBeVisible();

  // outside any field it undoes the toast
  await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
  await page.keyboard.press('Control+Z');
  await expect.poll(() => api.count('POST', UNDO)).toBe(1);
  await expect(undoToast(page)).toHaveCount(0);
  await expect(row(page).locator('.stamp')).toHaveText(NOT_REVIEWED);
});

test('the undo stays for eight seconds, and the toast closes with a named button', async ({ page }) => {
  test.setTimeout(120_000);
  const state = { reviewed: false };
  const { api, control } = await rig(page, state);
  await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
  await row(page).getByRole('button', { name: REVIEW, exact: true }).click();
  await confirmButton(page).click();
  control.gate.open();
  const undo = undoToast(page);
  await expect(undo).toBeVisible();
  await expect(undo.locator('.toast-host__close')).toHaveAttribute('aria-label', label('common.close'));
  // timed INSIDE the page: the moment the toast node appears and the moment it leaves
  await page.evaluate(() => {
    const host = document.querySelector('.toast-host');
    const marks: { born?: number; gone?: number } = {};
    (window as unknown as { __toastMarks: typeof marks }).__toastMarks = marks;
    const note = (): void => {
      const item = host?.querySelector('.toast-host__item');
      const present = item !== null && item !== undefined;
      if (present && marks.born === undefined) {
        marks.born = performance.now();
      }
      if (!present && marks.born !== undefined && marks.gone === undefined) {
        marks.gone = performance.now();
      }
    };
    // the toast is already on screen: its birth is now, a frame or two late at most
    note();
    new MutationObserver(note).observe(host as Node, { childList: true });
  });
  const bornAt = await page.evaluate(() => (window as unknown as { __toastMarks: { born?: number } }).__toastMarks.born);
  expect(bornAt).toBeDefined();
  await page.waitForTimeout(4000);
  await expect(undo, 'a plain toast is gone after 3.5 s; this one waits for the undo').toBeVisible();
  await expect(undo).toBeHidden({ timeout: UNDO_MS + 3000 });
  const lifetime = await page.evaluate(() => {
    const marks = (window as unknown as { __toastMarks: { born?: number; gone?: number } }).__toastMarks;
    return (marks.gone ?? 0) - (marks.born ?? 0);
  });
  console.log(`[n06] undo toast lifetime, timed in the page: ${Math.round(lifetime)} ms (set: ${UNDO_MS} ms)`);
  expect(Math.abs(lifetime - UNDO_MS), 'the undo window is eight seconds').toBeLessThanOrEqual(500);
});

/** the undo button's label against what is really painted behind it, at rest and under the pointer */
async function undoContrast(page: Page): Promise<{ rest: number; hover: number }> {
  const button = undoToast(page).locator('.toast-host__undo');
  const read = (): Promise<{ text: string; paint: string }> =>
    button.evaluate((el) => {
      // alpha tints composited over what is behind them, up to an opaque one
      const own = getComputedStyle(el);
      let background = own.backgroundColor;
      let node: HTMLElement | null = el.parentElement;
      while (/rgba\(/.test(background) && /,\s*0\.\d+\)$/.test(background) && node !== null) {
        const behind = getComputedStyle(node).backgroundColor;
        const [r, g, b, a] = background.match(/[\d.]+/g)!.map(Number);
        const [br, bg, bb] = behind.match(/[\d.]+/g)!.map(Number);
        background = `rgb(${Math.round(r * a + br * (1 - a))}, ${Math.round(g * a + bg * (1 - a))}, ${Math.round(b * a + bb * (1 - a))})`;
        node = node.parentElement;
      }
      return { text: own.color, paint: background };
    });
  const rest = await read();
  await button.hover();
  await page.waitForTimeout(250);
  const hover = await read();
  await page.mouse.move(2, 2);
  const luminance = (color: string): number => relativeLuminance(color) ?? 0;
  return { rest: contrast(luminance(rest.text), luminance(rest.paint)), hover: contrast(luminance(hover.text), luminance(hover.paint)) };
}

for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`the confirm and the undo toast — ${theme}, ${width}px: pictures, no overflow, AA contrast`, async ({ page }) => {
      test.setTimeout(120_000);
      await page.setViewportSize({ width, height: 800 });
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((value) => sessionStorage.setItem('visual-matrix.theme', value), theme);
      const state = { reviewed: false };
      const { control } = await rig(page, state);
      await page.goto('/app/expenses', { waitUntil: 'domcontentloaded' });
      // below 1280px the row button is its icon alone — the label's text is still its name
      const reviewButton = row(page).getByRole('button', { name: REVIEW, exact: true });
      await expect(reviewButton).toHaveAttribute('aria-label', REVIEW);
      await reviewButton.click();
      const details = dialog(page).locator('.confirm-details');
      await expect(details).toContainText('250.00');
      mkdirSync(OUT, { recursive: true });
      // settled: the dialog's enter animation is finished, nothing is half-drawn
      await page.waitForTimeout(500);
      await page.screenshot({ path: `${OUT}/confirm-with-amount__${theme}__${width}.png`, animations: 'disabled' });
      // the dialog is inside the viewport, and its lines do not run past its edge
      const box = (await dialog(page).boundingBox())!;
      expect(box.x, 'dialog left edge').toBeGreaterThanOrEqual(0);
      expect(box.x + box.width, 'dialog right edge').toBeLessThanOrEqual(width + 1);
      const overflow = await details.evaluate((el) => [...el.querySelectorAll('dd')].some((dd) => dd.scrollWidth > dd.clientWidth + 1));
      expect(overflow, 'a detail line is clipped').toBe(false);

      await confirmButton(page).click();
      control.gate.open();
      const undo = undoToast(page);
      await expect(undo).toBeVisible();
      await expect(undo.locator('.toast-host__text')).toHaveText('تمت مراجعة المصروف');
      console.log(`[n06] toasts on screen (${theme} ${width}px): ${JSON.stringify(await page.locator('.toast-host__item .toast-host__text').allInnerTexts())}`);
      await page.waitForTimeout(500);
      await page.screenshot({ path: `${OUT}/undo-toast__${theme}__${width}.png`, animations: 'disabled' });
      const toastBox = (await undo.boundingBox())!;
      expect(toastBox.x, 'toast left edge').toBeGreaterThanOrEqual(0);
      expect(toastBox.x + toastBox.width, 'toast right edge').toBeLessThanOrEqual(width + 1);
      // the undo button is reachable and named; its label meets AA at rest and under the pointer
      await expect(undo.locator('.toast-host__undo')).toBeVisible();
      const ratios = await undoContrast(page);
      console.log(`[n06] undo button contrast ${theme} ${width}px: rest ${ratios.rest.toFixed(2)}:1, hover ${ratios.hover.toFixed(2)}:1`);
      expect(ratios.rest, 'undo label at rest').toBeGreaterThanOrEqual(AA);
      expect(ratios.hover, 'undo label under the pointer').toBeGreaterThanOrEqual(AA);
      // the close button is a real target
      const close = (await undo.locator('.toast-host__close').boundingBox())!;
      expect(Math.min(close.width, close.height)).toBeGreaterThanOrEqual(24);
    });
  }
}
