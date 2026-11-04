import type { PipeTransform } from '@angular/core';
import { Injectable, Pipe, inject } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';
import { warnMissingTranslation } from '@core/i18n/missing-key';

/**
 * The interface never prints a raw code (plan 14.6). Unknown codes fall back
 * to the code itself so nothing renders blank.
 */
/** also injectable for programmatic use in components (no manual `new`) */
@Pipe({ name: 'enumLabel', standalone: true })
@Injectable({ providedIn: 'root' })
export class EnumLabelPipe implements PipeTransform {
  private translate = inject(TranslateService);

  transform(value: string | null | undefined, namespace: string): string {
    if (!value) {
      return '—';
    }
    const key = `enums.${namespace}.${value}`;
    const label = this.translate.instant(key);
    if (label === key) {
      warnMissingTranslation(key); // dev only — status-stamp must not silently fall back (T5-01)
      return value;
    }
    return label;
  }
}
