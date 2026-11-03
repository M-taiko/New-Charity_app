import type { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';

/**
 * Dates (T20, T29): UTC from the API → the viewer's local time, 12-hour clock,
 * left-to-right isolated, Western digits.
 */
@Pipe({ name: 'datetime' })
export class DatetimePipe implements PipeTransform {
  transform(value: string | null | undefined, withTime = true): string {
    if (!value) {
      return '—';
    }
    const date = new Date(value);
    if (isNaN(date.getTime())) {
      return '—';
    }
    const day = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
    if (!withTime) {
      return day;
    }
    const time = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(date);
    return `${day} ${time}`;
  }
}
