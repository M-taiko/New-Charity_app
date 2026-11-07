import type {
  ElementRef,
  OnDestroy} from '@angular/core';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  effect,
  inject,
  input,
  signal,
  viewChild,
} from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { MotionService } from '@core/motion/motion.service';
import { ChartPrintHelperComponent } from '../print-sheet/print-sheet.component';

export interface ChartSeries {
  label: string;
  value: number;
}

/** One rendered legend row — chip color follows the series index (T7-03). */
export interface ChartLegendItem {
  index: number;
  label: string;
  value: number;
  percent: number;
  color: string;
  hidden: boolean;
}

/**
 * Titled chart card (plan 16.2). Chart.js loads lazily on the routes that
 * use it. The chart reacts to series() changes — when async data arrives,
 * the chart is created or updated. "View as table" is the a11y version.
 *
 * T7-03: the Chart.js canvas legend is always off; the legend is an HTML
 * list under the chart (chip + label + value/percent) that wraps on narrow
 * cards, truncates long labels with a title tooltip, and toggles series
 * visibility on click. The same data drives the table view and print.
 */
@Component({
  selector: 'app-chart-card',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, MoneyPipe, ChartPrintHelperComponent],
  template: `
    <div class="chart-card">
      <div class="chart-card__head">
        <span class="chart-card__title">{{ title() | translate }}</span>
        <span class="chart-card__spacer"></span>
        <!-- stopPropagation: the card may sit inside a routerLink wrapper —
             the toggle must switch views in place, never navigate -->
        <p-button
          [label]="(asTable() ? 'common.view_chart' : 'common.view_table') | translate"
          [text]="true"
          size="small"
          (onClick)="toggleView($event)"
        />
      </div>

      <div class="chart-card__body">
        @if (visibleItems().length === 0) {
          <p class="u-muted">{{ 'common.no_data' | translate }}</p>
        } @else if (asTable()) {
          <table class="chart-card__table">
            <thead>
              <tr>
                <th scope="col">{{ 'common.name' | translate }}</th>
                <th scope="col">{{ 'common.value' | translate }}</th>
                <th scope="col">%</th>
              </tr>
            </thead>
            <tbody>
              @for (row of visibleItems(); track row.index) {
                <tr>
                  <td>{{ row.label }}</td>
                  <td class="u-bidi">{{ row.value | money: false }}</td>
                  <td class="u-bidi">{{ row.percent }}</td>
                </tr>
              }
            </tbody>
          </table>
        } @else {
          <div class="chart-card__plot">
            <div #host class="u-full" [style.height.px]="height()" style="position: relative;"></div>
            @if (chartType() === 'doughnut') {
              <span class="chart-card__total u-bidi" aria-hidden="true">{{ visibleTotal() | money: false }}</span>
            }
          </div>
          <app-chart-print-helper [canvas]="printCanvas()" />
          <ul class="chart-legend">
            @for (item of legendItems(); track item.index) {
              <li>
                <button
                  type="button"
                  class="chart-legend__item"
                  [class.is-hidden]="item.hidden"
                  [title]="item.label"
                  [attr.aria-pressed]="!item.hidden"
                  [attr.aria-label]="item.label + ' — ' + (item.value | money: false)"
                  (click)="toggleSeries(item.index, $event)"
                >
                  <span class="chart-legend__chip" [style.background]="item.color"></span>
                  <span class="chart-legend__label">{{ item.label }}</span>
                  <span class="chart-legend__value u-bidi">{{ item.value | money: false }} · {{ item.percent }}%</span>
                </button>
              </li>
            }
          </ul>
        }
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ChartCardComponent implements OnDestroy {
  title = input.required<string>();
  series = input.required<ChartSeries[]>();
  height = input(220);
  chartType = input<'doughnut' | 'bar' | 'line'>('doughnut');

  private motion = inject(MotionService);
  private host = viewChild<ElementRef<HTMLDivElement>>('host');
  readonly asTable = signal(false);
  /** indexes of series hidden via the legend toggle (T7-03) */
  private readonly hidden = signal<ReadonlySet<number>>(new Set());
  private prevSeries: readonly ChartSeries[] | null = null;

  // multi-hue, color-blind-safe series palette (T6-40) — 8 colors, ordered so
  // adjacent slices stay distinguishable in both themes
  private readonly palette = ['#0F766E', '#B8860B', '#7A4F9E', '#2F6CB3', '#B3555E', '#4F8F3A', '#8A6D3B', '#5E5E5E'];
  private chart: { destroy: () => void; data: { labels: string[]; datasets: { data: number[] }[] } } | null = null;
  /** the live canvas — the print helper flattens it to an image on beforeprint (T6-70.5) */
  readonly printCanvas = signal<HTMLCanvasElement | null>(null);
  private chartLib: (new (
    el: HTMLCanvasElement,
    cfg: Record<string, unknown>,
  ) => { destroy: () => void }) | null = null;

  toggleView(event: Event): void {
    event.stopPropagation();
    event.preventDefault();
    this.asTable.set(!this.asTable());
  }

  /** legend rows for every series; percents are computed over the visible total */
  legendItems = computed<ChartLegendItem[]>(() => {
    const items = this.series();
    const hidden = this.hidden();
    const visible = items.filter((_, i) => !hidden.has(i));
    const total = visible.reduce((sum, s) => sum + s.value, 0);
    return items.map((s, i) => ({
      index: i,
      label: s.label,
      value: s.value,
      percent: total > 0 && !hidden.has(i) ? Math.round((s.value / total) * 100) : 0,
      color: this.palette[i % this.palette.length],
      hidden: hidden.has(i),
    }));
  });

  visibleItems = computed(() => this.legendItems().filter((item) => !item.hidden));

  visibleTotal = computed(() => this.visibleItems().reduce((sum, item) => sum + item.value, 0));

  constructor() {
    // React to series/legend changes: create or update chart when data arrives.
    effect(async () => {
      const series = this.series();
      // a fresh series payload resets the toggles — indexes may point at other rows
      if (series !== this.prevSeries) {
        this.hidden.set(new Set());
        this.prevSeries = series;
      }
      const hidden = this.hidden();
      const showTable = this.asTable();

      if (series.length === 0 || showTable) {
        this.destroyChart();
        return;
      }

      // Wait a tick for the #host div to render (it's inside @else)
      await new Promise((r) => setTimeout(r, 0));

      const el = this.host()?.nativeElement;
      if (!el) return;

      await this.renderChart(el, this.visibleItems());
    });
  }

  toggleSeries(index: number, event: Event): void {
    event.stopPropagation();
    event.preventDefault();
    const next = new Set(this.hidden());
    if (next.has(index)) {
      next.delete(index);
    } else {
      next.add(index);
    }
    this.hidden.set(next);
  }

  private async renderChart(el: HTMLDivElement, items: ChartLegendItem[]): Promise<void> {
    // Load Chart.js once — use /auto which registers all scales and controllers
    if (!this.chartLib) {
      const mod = (await import('chart.js/auto')) as unknown as {
        Chart: new (
          el: HTMLCanvasElement,
          cfg: Record<string, unknown>,
        ) => { destroy: () => void };
      };
      this.chartLib = mod.Chart;
    }

    // Destroy old chart
    this.destroyChart();

    // Clear host and create canvas
    el.innerHTML = '';
    const canvas = document.createElement('canvas');
    canvas.style.width = '100%';
    canvas.style.height = '100%';
    el.appendChild(canvas);
    this.printCanvas.set(canvas);

    const labels = items.map((s) => s.label);
    const values = items.map((s) => s.value);
    const colors = items.map((s) => s.color);
    const styles = getComputedStyle(document.documentElement);
    const surfaceColor = styles.getPropertyValue('--color-surface').trim() || '#fff';
    // axis/tooltip text follows the theme text color (T6-60 rule: never the
    // surface color, which made text invisible)
    const textColor = styles.getPropertyValue('--color-text').trim() || '#4F2F1D';
    const type = this.chartType();

    this.chart = new this.chartLib(canvas, {
      type,
      data: {
        labels,
        datasets: [
          type === 'line'
            ? {
                data: values,
                label: this.title(),
                borderColor: colors[0],
                backgroundColor: colors[0],
                borderWidth: this.isNarrow() ? 1.5 : 2.5,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHitRadius: 12,
                fill: false,
                tension: 0,
              }
            : {
                data: values,
                backgroundColor: colors,
                borderColor: surfaceColor,
                borderWidth: 2,
              },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        resizeDelay: 150,
        animation: this.motion.level() === 'off'
          ? false
          : this.motion.level() === 'full'
            ? { duration: 800, easing: 'easeOutQuart', delay: (ctx: { type?: string; dataIndex?: number }) => (ctx.type === 'data' ? (ctx.dataIndex ?? 0) * 90 : 0) }
            : { duration: 350 },
        plugins: {
          // the legend is the HTML list under the canvas — never canvas dots (T7-03)
          legend: { display: false },
          tooltip: { titleColor: textColor, bodyColor: textColor },
        },
        ...(type !== 'doughnut'
          ? {
              scales: {
                y: { beginAtZero: true, ticks: { color: textColor, precision: 0 }, grid: { color: surfaceColor } },
                x: { ticks: { color: textColor } },
              },
            }
          : {}),
      },
    }) as typeof this.chart;
  }

  /** phone-width flag for thinner line strokes (T7-03) */
  private isNarrow(): boolean {
    return typeof window !== 'undefined' && window.matchMedia('(max-width: 640px)').matches;
  }

  private destroyChart(): void {
    if (this.chart) {
      this.chart.destroy();
      this.chart = null;
    }
  }

  ngOnDestroy(): void {
    this.destroyChart();
  }
}
