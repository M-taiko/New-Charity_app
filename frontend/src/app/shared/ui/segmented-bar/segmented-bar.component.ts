import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { RingSegment } from '@shared/ui/balance-ring/balance-ring.component';

const SERIES_COLORS = ['#5D3722', '#9E877A', '#4F2F1D', '#BEAFA7', '#7D5F4E', '#DFD7D3'];

/** Segmented bar sharing the ring's series order and colors (plan 10.1.4). */
@Component({
  selector: 'app-segmented-bar',
  standalone: true,
  imports: [TranslatePipe, MoneyPipe],
  template: `
    <div class="segmented-bar" role="img" [attr.aria-label]="aria()">
      @for (seg of widths(); track $index) {
        <span class="segmented-bar__segment" [style.width.%]="seg.width" [style.background]="seg.color"></span>
      }
    </div>
    <div class="segmented-bar__legend">
      @for (seg of segments(); track seg.label; let i = $index) {
        <span class="u-flex u-gap-1">
          <span class="u-visually-hidden"></span>
          <span style="width: 10px; height: 10px; border-radius: 2px; display: inline-block" [style.background]="color(i)"></span>
          {{ seg.label | translate }}: <strong class="u-bidi">{{ seg.amount | money }}</strong>
        </span>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SegmentedBarComponent {
  segments = input.required<RingSegment[]>();

  widths = computed(() => {
    const total = this.segments().reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0);
    if (total <= 0) {
      return [];
    }
    return this.segments().map((s, i) => ({
      width: ((parseFloat(s.amount) || 0) / total) * 100,
      color: SERIES_COLORS[i % SERIES_COLORS.length],
    }));
  });

  color(index: number): string {
    return SERIES_COLORS[index % SERIES_COLORS.length];
  }

  aria(): string {
    return this.segments().map((s) => `${s.label}: ${s.amount}`).join(', ');
  }
}
