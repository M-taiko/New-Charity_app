import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Tiny inline sparkline (D-05 / P-01): the KPI card's bottom area chart.
 * Pure SVG polyline, no chart library — values scale to a 100x28 viewBox.
 */
@Component({
  selector: 'app-sparkline',
  standalone: true,
  template: `
    <svg class="sparkline" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">
      @if (points(); as p) {
        <polyline [attr.points]="p" />
      }
    </svg>
  `,
  styles: `
    .sparkline {
      display: block;
      width: 100%;
      height: 28px;
      overflow: visible;
    }
    polyline {
      fill: none;
      stroke: var(--color-secondary);
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
    :host([data-tone='up']) polyline {
      stroke: var(--status-active-fg);
    }
    :host([data-tone='down']) polyline {
      stroke: var(--status-danger-fg);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '[attr.data-tone]': 'tone()' },
})
export class SparklineComponent {
  /** raw numeric values, oldest first */
  values = input.required<number[]>();
  /** 'up' | 'down' | '' — colours the line by the trend */
  tone = input<'up' | 'down' | ''>('');

  points = computed(() => {
    const values = this.values().filter((v) => Number.isFinite(v));
    if (values.length < 2) {
      return '';
    }
    const max = Math.max(...values);
    const min = Math.min(...values);
    const span = max - min || 1;
    return values
      .map((v, i) => `${(i / (values.length - 1)) * 100},${25 - ((v - min) / span) * 22}`)
      .join(' ');
  });
}
