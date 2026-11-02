import { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

/**
 * The interface never prints a raw code (plan 14.6). Unknown codes fall back
 * to the code itself so nothing renders blank.
 */
@Pipe({ name: 'enumLabel', standalone: true })
export class EnumLabelPipe implements PipeTransform {
  constructor(private translate: TranslateService) {}

  transform(value: string | null | undefined, namespace: string): string {
    if (!value) {
      return '—';
    }
    const key = `enums.${namespace}.${value}`;
    const label = this.translate.instant(key);
    return label !== key ? label : value;
  }
}
