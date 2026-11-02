import { PipeTransform } from '@angular/core';
import { Pipe } from '@angular/core';
import { TranslateService } from '@ngx-translate/core';

/** Arabic relative time ("منذ ساعة") from the same UTC value (plan 11.8). */
@Pipe({ name: 'relativeTime' })
export class RelativeTimePipe implements PipeTransform {
  constructor(private translate: TranslateService) {}

  transform(value: string | null | undefined): string {
    if (!value) {
      return '—';
    }
    const date = new Date(value).getTime();
    if (isNaN(date)) {
      return '—';
    }
    const formatter = new Intl.RelativeTimeFormat('ar-EG', { numeric: 'auto' });
    const divisions: [Intl.RelativeTimeFormatUnit, number][] = [
      ['year', 31_536_000_000],
      ['month', 2_592_000_000],
      ['week', 604_800_000],
      ['day', 86_400_000],
      ['hour', 3_600_000],
      ['minute', 60_000],
    ];
    const diff = date - Date.now();
    const abs = Math.abs(diff);
    if (abs < 60_000) {
      return this.translate.instant('common.just_now');
    }
    for (const [unit, ms] of divisions) {
      if (abs >= ms) {
        return formatter.format(Math.round(diff / ms), unit);
      }
    }
    return formatter.format(0, 'minute');
  }
}
