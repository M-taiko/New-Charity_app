import type { TranslateService } from '@ngx-translate/core';

/**
 * Arabic plurals (plan 14.5): zero/one/two/few/many/other via Intl.PluralRules.
 * Keys follow the pattern `${base}.${form}`.
 */
export function arabicPlural(translate: TranslateService, base: string, count: number): string {
  const rules = new Intl.PluralRules('ar-EG');
  const form = rules.select(count);
  const key = `${base}.${form}`;
  const value = translate.instant(key);
  if (value === key) {
    const fallback = translate.instant(`${base}.other`);
    return fallback !== `${base}.other` ? fallback : String(count);
  }
  return value;
}
