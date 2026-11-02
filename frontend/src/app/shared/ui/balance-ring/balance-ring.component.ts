import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { MoneyPipe } from '@shared/pipes/money.pipe';

export interface RingSegment {
  /** i18n label key */
  label: string;
  amount: string;
}

/**
 * Balance ring + legend (plan 11.5): how a custody is divided. Series colors
 * follow the fixed order brand, light, ink, taupe, mid, sand (plan 10.1.4) —
 * same pair meaning everywhere. The ring draws once (CSS animation).
 */
const SERIES_COLORS = [
  'var(--color-primary)',
  'var(--palette-light)',
  'var(--color-text)',
  'var(--palette-taupe)',
  'var(--color-text-muted)',
  'var(--palette-sand)',
];

@Component({
  selector: 'app-balance-ring',
  standalone: true,
  imports: [TranslatePipe, MoneyPipe],
  template: `
    <div class="balance-ring">
      <svg class="balance-ring__svg" viewBox="0 0 120 120" role="img" [attr.aria-label]="ariaLabel()">
        <circle class="balance-ring__track" cx="60" cy="60" r="48" fill="none" stroke-width="10" />
        @for (seg of arcs(); track $index) {
          <circle
            cx="60"
            cy="60"
            r="48"
            fill="none"
            stroke-width="10"
            [attr.stroke]="seg.color"
            [attr.stroke-dasharray]="seg.dash"
            [attr.stroke-dashoffset]="seg.offset"
            transform="rotate(-90 60 60)"
          />
        }
        <text class="balance-ring__center-value u-bidi" x="60" y="57" text-anchor="middle">
          {{ total() | money: false }}
        </text>
        <text class="balance-ring__center-label" x="60" y="74" text-anchor="middle">
          {{ 'glossary.remaining' | translate }}
        </text>
      </svg>
      <div class="balance-ring__legend">
        @for (seg of segments(); track seg.label; let i = $index) {
          <span class="u-flex u-gap-1 u-small">
            <span class="balance-ring__dot" [style.background]="color(i)"></span>
            {{ seg.label | translate }}: <strong class="u-bidi">{{ seg.amount | money }}</strong>
          </span>
        }
      </div>
    </div>
  `,
  styles: `
    .balance-ring__dot {
      width: 10px;
      height: 10px;
      border-radius: 2px;
      display: inline-block;
      border: 1px solid var(--color-hairline);
      flex-shrink: 0;
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BalanceRingComponent {
  segments = input.required<RingSegment[]>();
  total = input('0');

  private readonly circumference = 2 * Math.PI * 48;

  arcs = computed(() => {
    const totalValue = this.segments().reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0);
    if (totalValue <= 0) {
      return [];
    }
    let offset = 0;
    return this.segments().map((seg, index) => {
      const fraction = Math.max((parseFloat(seg.amount) || 0) / totalValue, 0);
      const length = fraction * this.circumference;
      const arc = {
        color: SERIES_COLORS[index % SERIES_COLORS.length],
        dash: `${length} ${this.circumference - length}`,
        offset: -offset,
      };
      offset += length;
      return arc;
    });
  });

  color(index: number): string {
    return SERIES_COLORS[index % SERIES_COLORS.length];
  }

  ariaLabel(): string {
    return 'custody.detail.breakdown.title';
  }
}
