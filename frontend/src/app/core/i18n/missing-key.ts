import { isDevMode } from '@angular/core';

/**
 * Dev-only warning when a translation lookup falls back to the raw key or
 * code (T5-01): the silent fallback let the "unused keys" cleanup delete
 * dynamically-built enum labels unnoticed. In production it stays quiet —
 * the fallback itself keeps the UI readable — but during development every
 * miss shows in the console so regressions are caught immediately.
 */
export function warnMissingTranslation(key: string): void {
  if (isDevMode()) {
    // eslint-disable-next-line no-console -- the whole point of this helper
    console.warn(`[i18n] missing translation key: ${key}`);
  }
}
