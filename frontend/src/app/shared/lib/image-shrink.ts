/* ── N-05: keep a photo under the attachment limit, on the client ──────────── */

/** the longest side a photo is kept at when it must shrink, then the smaller ones, in the order tried */
const SIDES: readonly number[] = [2000, 1600, 1280, 1000, 800];

/** the JPEG quality tried for each of those sides, in the order tried */
const QUALITIES: readonly number[] = [0.82, 0.7, 0.58, 0.45];

/** one attempt: the size the photo is drawn at and the quality it is encoded with */
export interface ShrinkStep {
  readonly width: number;
  readonly height: number;
  readonly quality: number;
}

export interface ShrinkResult {
  readonly file: File;
  /** the attempt's size was within the limit */
  readonly under: boolean;
}

/** draws a photo at the size of one attempt and returns the encoded bytes */
export type Encoder = (photo: ImageBitmap, step: ShrinkStep) => Promise<Blob>;

/** the size a photo is drawn at for a longest side: the ratio is kept, it is never enlarged */
export function fitWithin(width: number, height: number, side: number): { width: number; height: number } {
  const longest = Math.max(width, height);
  const scale = longest > side ? side / longest : 1;
  return { width: Math.max(1, Math.round(width * scale)), height: Math.max(1, Math.round(height * scale)) };
}

/**
 * Every attempt, in the order they are tried: the first whose bytes fit the
 * limit wins. The side goes down first (detail is worth more than a few
 * percent of quality), then the quality for each side.
 */
export function shrinkPlan(width: number, height: number): ShrinkStep[] {
  return SIDES.flatMap((side) => QUALITIES.map((quality) => ({ ...fitWithin(width, height, side), quality })));
}

/** a size for a message: "6.2" for 6.2 megabytes */
export function megabytes(bytes: number): string {
  return (bytes / 1_048_576).toFixed(1);
}

/** does this file have to shrink before it may be uploaded? */
export function exceedsLimit(bytes: number, maxBytes: number): boolean {
  return bytes > maxBytes;
}

/**
 * The decision loop, apart from the browser: `encode` turns one attempt into
 * bytes. The first attempt within the limit wins; when none is (an `encode`
 * that never gets small enough), the LAST, smallest attempt is returned with
 * `under: false` and the caller refuses the file.
 */
export async function shrinkWith(
  width: number,
  height: number,
  maxBytes: number,
  encode: (step: ShrinkStep) => Promise<Blob>,
): Promise<{ blob: Blob; step: ShrinkStep; under: boolean }> {
  let smallest: { blob: Blob; step: ShrinkStep } | null = null;
  for (const step of shrinkPlan(width, height)) {
    const blob = await encode(step);
    if (blob.size <= maxBytes) {
      return { blob, step, under: true };
    }
    if (!smallest || blob.size < smallest.blob.size) {
      smallest = { blob, step };
    }
  }
  return smallest ? { ...smallest, under: false } : { blob: new Blob(), step: { width: 0, height: 0, quality: 0 }, under: false };
}

/** JPEG has no transparency: without the fill, a transparent PNG would turn black */
async function encodeJpeg(photo: ImageBitmap, step: ShrinkStep): Promise<Blob> {
  const canvas = document.createElement('canvas');
  canvas.width = step.width;
  canvas.height = step.height;
  const context = canvas.getContext('2d');
  if (!context) {
    throw new Error('no 2d context');
  }
  context.fillStyle = '#ffffff';
  context.fillRect(0, 0, step.width, step.height);
  context.drawImage(photo, 0, 0, step.width, step.height);
  const blob = await new Promise<Blob | null>((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', step.quality);
  });
  if (!blob) {
    throw new Error('could not encode the photo');
  }
  return blob;
}

/** the file's name with a .jpg ending, for a photo that is re-encoded as JPEG */
function jpegName(name: string): string {
  const base = name.replace(/\.[^.]*$/, '') || 'photo';
  return `${base}.jpg`;
}

/**
 * Shrink a photo that is over an attachment limit — on the client, before the
 * upload (N-05). A phone photo of several megabytes becomes a JPEG of at most
 * the limit; the orientation stored in its EXIF is applied, because the photo
 * is decoded with it. The file keeps its name, with a .jpg ending.
 */
export async function shrinkImage(source: Blob, name: string, maxBytes: number, encode: Encoder = encodeJpeg): Promise<ShrinkResult> {
  // `from-image` honours the EXIF orientation; browsers that ignore the option decode upright already
  const photo = await createImageBitmap(source, { imageOrientation: 'from-image' });
  try {
    const result = await shrinkWith(photo.width, photo.height, maxBytes, (step) => encode(photo, step));
    return { file: new File([result.blob], jpegName(name), { type: 'image/jpeg' }), under: result.under };
  } finally {
    photo.close();
  }
}
