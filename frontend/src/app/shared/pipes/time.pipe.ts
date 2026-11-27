import type { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';

/**
 * Time-of-day only (B-04): the second line of a two-line datetime cell.
 * Same localization rules as the datetime pipe — 12-hour, Western digits.
 */
@Pipe({ name: 'time' })
export class TimePipe implements PipeTransform {
  transform(value: string | null | undefined): string {
    if (!value) {
      return '';
    }
    const date = new Date(value);
    if (isNaN(date.getTime())) {
      return '';
    }
    return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(date);
  }
}
