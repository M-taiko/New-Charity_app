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
