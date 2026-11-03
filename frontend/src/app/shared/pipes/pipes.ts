import type { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';
import type { TranslateService } from '@ngx-translate/core';
import { arabicPlural } from '@core/i18n/plural';

/** Arabic plural picker (plan 14.5). */
@Pipe({ name: 'plural' })
export class PluralPipe implements PipeTransform {
  constructor(private translate: TranslateService) {}

  transform(base: string, count: number): string {
    const text = arabicPlural(this.translate, base, count);
    return text.replace('{{count}}', String(count));
  }
}

/** Plain number formatting with Western digits (plan 14.8). */
@Pipe({ name: 'numberFormat' })
export class NumberFormatPipe implements PipeTransform {
  transform(value: number | string | null | undefined): string {
    const num = typeof value === 'string' ? parseFloat(value) : (value ?? 0);
    if (!isFinite(num)) {
      return '0';
    }
    return num.toLocaleString('en-US');
  }
}
