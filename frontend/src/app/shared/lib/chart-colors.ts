import { inject } from '@angular/core';
import { ThemeService } from '@core/theme/theme.service';

/**
 * Reads the --chart-* tokens from :root at call time (RD-1.6) so charts,
 * legends and segmented bars always paint in the active theme's palette —
 * never hardcoded hexes in components.
 */
export function readChartPalette(length = 8): string[] {
  const styles = getComputedStyle(document.documentElement);
  return Array.from({ length }, (_, i) => styles.getPropertyValue(`--chart-${i + 1}`).trim()).filter(Boolean);
}

/**
 * Reactive palette accessor: call inside a computed to bind it to the theme
 * signal, e.g. `palette = computed(() => chartPalette(this.theme))`.
 */
export function chartPalette(theme: ThemeService, length = 8): string[] {
  theme.theme(); // tracked dependency — recomputes when the theme toggles
  return readChartPalette(length);
}

/** Convenience injector-free variant for classes that already inject ThemeService. */
export function useChartPalette(length = 8): () => string[] {
  const theme = inject(ThemeService);
  return () => chartPalette(theme, length);
}

/* ── N-07: one colour per entity, everywhere ────────────────────────────────
   An agent, a direction, a treasury keeps the SAME colour on every page, in
   every chart, legend and ring — and in both themes, because what is derived
   here is a TOKEN (`var(--chart-N)`), never a hex. `--chart-muted` and
   `--chart-track` are not hues and stay out of it. */

/** the hue tokens --chart-1 … --chart-8 */
export const CHART_HUE_COUNT = 8;

/** FNV-1a, 32 bits, over the code points of a string (for ids that are not numbers) */
function fnv1a(text: string): number {
  let hash = 0x811c9dc5;
  for (const char of text) {
    const point = char.codePointAt(0) ?? 0;
    for (let shift = 0; shift < 24 && point >>> shift > 0; shift += 8) {
      hash ^= (point >>> shift) & 0xff;
      hash = Math.imul(hash, 0x01000193) >>> 0;
    }
  }
  return hash >>> 0;
}

/**
 * Which hue an entity gets: 1 … CHART_HUE_COUNT, the number in `--chart-N`.
 * Pure and deterministic — the same id gives the same hue on every page.
 *
 * A numeric id (12 and "12" are the same entity) is spread by the golden-ratio
 * multiplier rather than hashed: an odd multiplier is a bijection on the last
 * three bits, so eight consecutive ids never share a hue — in a ranked list of
 * the top agents no two neighbours wear one colour. Anything else (a name, a
 * code) goes through FNV-1a first.
 */
export function entityHue(id: string | number | null | undefined): number {
  const text = id === null || id === undefined ? '' : String(id);
  const numeric = /^\d{1,12}$/.test(text) ? Number(text) : Number.NaN;
  const mixed = Number.isSafeInteger(numeric) ? Math.imul(numeric, 0x9e3779b1) : fnv1a(text);
  return ((mixed >>> 0) % CHART_HUE_COUNT) + 1;
}

/**
 * The entity's colour as a CSS token reference — `var(--chart-3)`. Bind it to a
 * style from a template (`[style.background]="entityColor(row.id)"`), give it
 * to a class-less chip, a ring or a bar; it follows the theme by itself.
 */
export function entityColor(id: string | number | null | undefined): string {
  return `var(--chart-${entityHue(id)})`;
}

/**
 * The entity's colour as a concrete value, for the places CSS variables cannot
 * reach — a canvas (Chart.js) or an SVG attribute. Reads the token at call time
 * in the active theme; pass a palette already read by the caller to avoid a
 * second style lookup per item.
 */
export function entityColorValue(id: string | number | null | undefined, palette?: readonly string[]): string {
  const hues = palette ?? readChartPalette();
  return hues[entityHue(id) - 1] ?? hues[0] ?? '';
}
