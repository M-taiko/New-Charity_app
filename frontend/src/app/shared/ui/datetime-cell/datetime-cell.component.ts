import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { TimePipe } from '@shared/pipes/time.pipe';

/**
 * Two-line datetime cell (B-04): date on top, muted time below. A one-line
 * `dd/mm/yyyy h:mm PM` string overflows narrow RTL table cells and the
 * ellipsis eats the LEADING digits (`0/11/2026`). Two short nowrap lines in
 * an LTR-isolated block never clip, at any column width.
 */
@Component({
  selector: 'app-datetime-cell',
  standalone: true,
  imports: [DatetimePipe, TimePipe],
  template: `
    <span class="dt-cell">
      <span class="dt-cell__date">{{ value() | datetime: false }}</span>
      @if (withTime()) {
        <span class="dt-cell__time">{{ value() | time }}</span>
      }
    </span>
  `,
  styles: `
    .dt-cell {
      display: inline-flex;
      flex-direction: column;
      gap: 1px;
      direction: ltr;
      unicode-bidi: isolate;
      text-align: end;
      white-space: nowrap;
      line-height: 1.35;
      font-variant-numeric: tabular-nums;
    }
    .dt-cell__time {
      font-size: 11px;
      color: var(--color-text-muted);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DatetimeCellComponent {
  value = input.required<string | null | undefined>();
  withTime = input(true);
}
