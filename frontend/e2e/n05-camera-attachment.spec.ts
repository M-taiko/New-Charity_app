import { mkdirSync } from 'node:fs';
import { expect, test, type Page } from '@playwright/test';
import { seedThemeInitScript } from './gates';
import { exceedsLimit, fitWithin, megabytes, shrinkPlan, shrinkWith } from '../src/app/shared/lib/image-shrink';

/**
 * N-05 — camera capture, and a photo shrunk on the client.
 *
 * Unit: the shrink decision, with an encoder that answers whatever the test
 * wants — which attempt wins, what happens when none fits.
 *
 * Browser, against the real app: a photo of more than 6 MB is generated on a
 * canvas and taken through the camera input at phone width; the upload is
 * intercepted and its attachment part measured. Nothing is written: every
 * write is refused, and the one request that is allowed through is captured
 * and aborted.
 *
 * Needs the PHP server + the packed SPA, like the matrix.
 */

const EMAIL = 'donia.a5ra2019@gmail.com';
const PASSWORD = '123456789';
const LIMIT = 2_000_000;
const FORM = '/app/procurement/purchase/new';
const OUT = 'e2e/screenshots/n05';

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

test.describe('N-05 the shrink decision', () => {
  test('the plan tries the longest side first, then a smaller one, and a lower quality for each', () => {
    const plan = shrinkPlan(4000, 3000);
    expect(plan).toHaveLength(20);
    expect(plan[0]).toMatchObject({ width: 2000, height: 1500, quality: 0.82 });
    expect(plan[4]).toMatchObject({ width: 1600, height: 1200, quality: 0.82 });
    expect(plan[1].quality).toBeLessThan(plan[0].quality);
    // the last attempt is the smallest and the most compressed
    expect(plan[19]).toMatchObject({ width: 800, height: 600, quality: 0.45 });
  });

  test('a photo is never enlarged, and its ratio is kept', () => {
    expect(fitWithin(1200, 900, 2000)).toEqual({ width: 1200, height: 900 });
    expect(fitWithin(300, 4000, 2000)).toEqual({ width: 150, height: 2000 });
    expect(fitWithin(3, 5, 2000)).toEqual({ width: 3, height: 5 });
    // a degenerate size still yields a drawable one
    expect(fitWithin(0, 0, 2000)).toEqual({ width: 1, height: 1 });
  });

  test('the first attempt within the limit wins — and only the attempts before it are encoded', async () => {
    const sizes = [1_900_000];
    const encoded: number[] = [];
    const result = await shrinkWith(4000, 3000, LIMIT, async (step) => {
      encoded.push(step.quality);
      return new Blob([new Uint8Array(sizes[encoded.length - 1] ?? 1)]);
    });
    expect(result.under).toBe(true);
    expect(result.step.quality).toBe(0.82);
    expect(encoded).toHaveLength(1);
  });

  test('when the first size does not fit, the plan goes on until one does', async () => {
    const sizes = [2_400_000, 2_100_000, 1_700_000];
    let attempt = 0;
    const qualities: number[] = [];
    const result = await shrinkWith(4000, 3000, LIMIT, async (step) => {
      qualities.push(step.quality);
      return new Blob([new Uint8Array(sizes[attempt++] ?? 1)]);
    });
    expect(result.under).toBe(true);
    expect(result.step).toMatchObject({ width: 2000, height: 1500, quality: 0.58 });
    expect(result.blob.size).toBe(1_700_000);
    // the first side three times, each more compressed than the one before
    expect(qualities).toEqual([0.82, 0.7, 0.58]);
  });

  test('when nothing fits, the smallest attempt comes back and says it did not fit', async () => {
    const sizes = [9_000_000, 5_000_000, 3_000_000, 2_500_000];
    let calls = 0;
    const result = await shrinkWith(4000, 3000, LIMIT, async () => {
      calls++;
      return new Blob([new Uint8Array(sizes[Math.min(calls - 1, sizes.length - 1)])]);
    });
    expect(result.under).toBe(false);
    expect(calls).toBe(20);
    expect(result.blob.size).toBe(2_500_000);
    expect(result.step.quality).toBe(0.45);
  });

  test('the limit and the label', () => {
    expect(exceedsLimit(2_000_000, LIMIT)).toBe(false);
    expect(exceedsLimit(2_000_001, LIMIT)).toBe(true);
    expect(megabytes(6_291_456)).toBe('6.0');
    expect(megabytes(0)).toBe('0.0');
  });
});

async function login(page: Page): Promise<void> {
  await page.goto('/app/login', { waitUntil: 'networkidle' });
  await page.fill('input[type="email"]', EMAIL);
  await page.fill('input[type="password"]', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/dashboard/, { timeout: 20_000 });
  await page.locator('.sidebar__link').first().waitFor({ state: 'visible', timeout: 30_000 });
}

/** a photo of at least `minBytes`, painted on a canvas in the page (noise does not compress) */
async function paintPhoto(page: Page, minBytes: number): Promise<{ buffer: Buffer; size: number; width: number; height: number }> {
  const photo = await page.evaluate(async (min: number) => {
    for (const [width, height] of [
      [3000, 2250],
      [3600, 2700],
      [4000, 3000],
    ]) {
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext('2d')!;
      const pixels = context.createImageData(width, height);
      for (let i = 0; i < pixels.data.length; i += 4) {
        pixels.data[i] = (i * 2654435761) % 256;
        pixels.data[i + 1] = (i * 40503) % 256;
        pixels.data[i + 2] = (i * 97) % 256;
        pixels.data[i + 3] = 255;
      }
      context.putImageData(pixels, 0, 0);
      for (const quality of [0.9, 0.97]) {
        const blob = await new Promise<Blob>((resolve) => canvas.toBlob((b) => resolve(b as Blob), 'image/jpeg', quality));
        if (blob.size >= min) {
          const bytes = new Uint8Array(await blob.arrayBuffer());
          let binary = '';
          for (let i = 0; i < bytes.length; i += 0x8000) {
            binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
          }
          return { b64: btoa(binary), size: blob.size, width, height };
        }
      }
    }
    throw new Error('could not paint a photo large enough');
  }, minBytes);
  return { buffer: Buffer.from(photo.b64, 'base64'), size: photo.size, width: photo.width, height: photo.height };
}

/** the bytes of one part of a multipart body (the file's own bytes, without headers) */
function partSize(request: { headers: () => Record<string, string>; postDataBuffer(): Buffer | null }, name: string): { size: number; type: string } | null {
  const body = request.postDataBuffer();
  const type = request.headers()['content-type'] ?? request.headers()['Content-Type'] ?? '';
  const boundary = /boundary=(?:"([^"]+)"|([^;]+))/.exec(type);
  if (!body || !boundary) {
    return null;
  }
  const marker = Buffer.from('--' + (boundary[1] ?? boundary[2]));
  const parts = body.toString('latin1').split(marker.toString('latin1'));
  for (const part of parts) {
    const headerEnd = part.indexOf('\r\n\r\n');
    if (headerEnd < 0 || !part.slice(0, headerEnd).includes(`name="${name}"`)) {
      continue;
    }
    const bytes = Buffer.from(part, 'latin1');
    // the part ends with CRLF (before the next boundary)
    return { size: bytes.length - headerEnd - 4 - 2, type: /Content-Type: ([^\r]+)/.exec(part)?.[1] ?? '' };
  }
  return null;
}

test.describe('N-05 on the real form', () => {
  test.beforeEach(async ({ page }) => {
    await login(page);
  });

  test('a 6 MB photo taken with the camera on a phone is uploaded at 2 MB or less', async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: 360, height: 800 });
    await page.addInitScript(seedThemeInitScript);
    await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), 'light');
    // nothing may be written: the form's one write is captured, measured and refused
    const writes: { size: number; type: string }[] = [];
    await page.route('**/api/v1/**', async (route) => {
      if (route.request().method() !== 'GET' && /\/purchase-requests(\?|$)/.test(route.request().url())) {
        writes.push(partSize(route.request(), 'attachment') ?? { size: -1, type: '' });
        await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'refused by the test', errors: {} }) });
        return;
      }
      if (route.request().method() !== 'GET' && !/\/broadcasts\/active|\/auth\/logout/.test(route.request().url())) {
        await route.abort();
        return;
      }
      await route.continue();
    });
    await page.goto(FORM, { waitUntil: 'domcontentloaded' });
    await page.locator('app-attachment-field').waitFor({ state: 'visible', timeout: 25_000 });

    // the camera button is on a phone, and opens the back camera
    const capture = page.locator('app-attachment-field p-button.attachment-field__capture');
    await expect(capture).toBeVisible();
    const input = page.locator('app-attachment-field input[capture]');
    await expect(input).toHaveAttribute('capture', 'environment');
    await expect(input).toHaveAttribute('accept', 'image/*');

    const photo = await paintPhoto(page, 6 * 1_048_576);
    expect(photo.size).toBeGreaterThanOrEqual(6 * 1_048_576);
    await input.setInputFiles({ name: 'receipt.jpg', mimeType: 'image/jpeg', buffer: photo.buffer });

    // the field says what it did, before anything is sent
    const preview = page.locator('app-attachment-field .attachment-field__preview');
    await preview.waitFor({ state: 'visible', timeout: 30_000 });
    await expect(preview.locator('.field__hint')).toContainText(megabytes(photo.size));
    await expect(preview.locator('img.attachment-field__thumb')).toBeVisible();

    // the upload: the form's required fields (title, category, priority), then submit
    await page.fill('input[name="title"]', 'طلب شراء للقياس');
    for (const select of [0, 1]) {
      await page.locator('form p-select').nth(select).click();
      await page.locator('.p-select-overlay .p-select-option').first().click();
    }
    await page.locator('button[type="submit"]').click();
    await expect.poll(() => writes.length, { timeout: 30_000 }).toBe(1);
    expect(writes[0].size).toBeGreaterThan(100_000);
    expect(writes[0].size, 'the attachment part of the multipart body').toBeLessThanOrEqual(LIMIT);
    expect(writes[0].type).toBe('image/jpeg');
    console.log(`[n05] photo ${photo.width}×${photo.height}, ${photo.size.toLocaleString('en-US')} bytes → uploaded as ${writes[0].size.toLocaleString('en-US')} bytes (${((writes[0].size / photo.size) * 100).toFixed(1)}%)`);
  });

  test('the checks are real: a wrong type and an oversized file are refused before any request', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1280, height: 800 });
    const posts: string[] = [];
    await page.route('**/api/v1/**', async (route) => {
      if (route.request().method() !== 'GET' && !/\/broadcasts\/active|\/auth\/logout/.test(route.request().url())) {
        posts.push(route.request().url());
        await route.abort();
        return;
      }
      await route.continue();
    });
    await page.goto(FORM, { waitUntil: 'domcontentloaded' });
    await page.locator('app-attachment-field').waitFor({ state: 'visible', timeout: 25_000 });
    const field = page.locator('app-attachment-field');
    const picker = field.locator('p-fileupload input[type="file"]');
    await expect(picker).toHaveAttribute('accept', '.pdf,.jpg,.jpeg,.png');

    // a Word document: the expense forms take it, this endpoint does not
    await picker.setInputFiles({ name: 'offer.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: Buffer.alloc(2_000) });
    await expect(field.locator('.field__error')).toHaveText(/نوع الملف غير مدعوم/);
    await expect(field.locator('.attachment-field__preview')).toHaveCount(0);
    expect(posts).toEqual([]);

    // an oversized PDF is refused at once — it used to be sent and refused by the server
    await picker.setInputFiles({ name: 'big.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(LIMIT + 40_000, 0x25) });
    await expect(field.locator('.field__error')).toHaveText(/أكبر من الحد المسموح/);
    await expect(field.locator('.attachment-field__preview')).toHaveCount(0);
    expect(posts).toEqual([]);

    // a small image is taken as it is (nothing to shrink), is shown, and can be dropped again
    const tiny = await page.evaluate(
      async () =>
        new Promise<string>((resolve) => {
          const canvas = document.createElement('canvas');
          canvas.width = 24;
          canvas.height = 24;
          const context = canvas.getContext('2d')!;
          context.fillStyle = 'rgb(92, 64, 51)';
          context.fillRect(0, 0, 24, 24);
          canvas.toBlob((blob) => {
            const bytes = new Uint8Array((blob as Blob).size);
            resolve(btoa(String.fromCharCode(...bytes)));
          }, 'image/png');
        }),
    );
    await picker.setInputFiles({ name: 'small.png', mimeType: 'image/png', buffer: Buffer.from(tiny, 'base64') });
    await expect(field.locator('.attachment-field__preview')).toBeVisible();
    await expect(field.locator('.attachment-field__preview .field__hint'), 'nothing to shrink, so nothing is said').toHaveCount(0);
    await field.locator('.attachment-field__preview button').click();
    await expect(field.locator('.attachment-field__preview')).toHaveCount(0);
    expect(posts).toEqual([]);
  });

  test('the desktop UI is unchanged: no camera button, the same two controls as before', async ({ page }) => {
    test.setTimeout(120_000);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/app/expenses/new', { waitUntil: 'domcontentloaded' });
    await page.locator('app-attachment-field').waitFor({ state: 'visible', timeout: 25_000 });
    const field = page.locator('app-attachment-field');
    await expect(field.locator('p-button.attachment-field__capture')).toBeHidden();
    // the camera button is there but out of the layout, and its input out of the tab order
    expect(await field.locator('p-button.attachment-field__capture').evaluate((el) => getComputedStyle(el).display)).toBe('none');
    await expect(field.locator('input[capture]')).toHaveAttribute('tabindex', '-1');
    await expect(field.locator('p-fileupload')).toBeVisible();
    await expect(field.locator('.field__hint')).toHaveText('PDF أو JPG أو PNG أو DOC أو DOCX — بحد أقصى 2 ميجابايت — رفع ملف جديد يستبدل الملف الحالي');
    // the capture input still works where a desktop user reaches it: a shrunk photo, no camera needed
    const photo = await paintPhoto(page, 6 * 1_048_576);
    await field.locator('input[capture]').setInputFiles({ name: 'desk.jpg', mimeType: 'image/jpeg', buffer: photo.buffer });
    await expect(field.locator('.attachment-field__preview .field__hint')).toContainText(megabytes(photo.size));
    const shown = await field.locator('.attachment-field__preview .u-small').innerText();
    expect(parseFloat(shown.replace(/[^\d.]/g, ''))).toBeLessThanOrEqual(2);
  });
});

/** the field as it looks, for the delivery: the page it sits in and the field itself */
for (const theme of ['light', 'dark'] as const) {
  for (const width of [360, 1280]) {
    test(`N-05 the field as it looks — ${theme}, ${width}px`, async ({ page }) => {
      test.setTimeout(150_000);
      await login(page);
      await page.addInitScript(seedThemeInitScript);
      await page.evaluate((t) => sessionStorage.setItem('visual-matrix.theme', t), theme);
      await page.setViewportSize({ width, height: 800 });
      // nothing may be written: every write but the two the page makes on its own is refused
      await page.route('**/api/v1/**', async (route) => {
        if (route.request().method() !== 'GET' && !/\/broadcasts\/active|\/auth\/logout/.test(route.request().url())) {
          await route.abort();
          return;
        }
        await route.continue();
      });
      await page.goto(FORM, { waitUntil: 'domcontentloaded' });
      const field = page.locator('app-attachment-field');
      await field.waitFor({ state: 'visible', timeout: 25_000 });
      // on a phone a photo is "taken" through the camera input: the preview says what it became
      if (width < 600) {
        const photo = await paintPhoto(page, 6 * 1_048_576);
        await field.locator('input[capture]').setInputFiles({ name: 'receipt.jpg', mimeType: 'image/jpeg', buffer: photo.buffer });
        await field.locator('.attachment-field__preview').waitFor({ state: 'visible', timeout: 30_000 });
      }
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(400);
      await field.screenshot({ path: `${OUT}/field__${theme}__${width}.png`, animations: 'disabled' });
      await wholePage(page, `form__${theme}__${width}`);
    });
  }
}
