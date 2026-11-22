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
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { MotionService } from '@core/motion/motion.service';
import { ThemeService } from '@core/theme/theme.service';
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
                  <span class="chart-legend__value u-bidi">{{ item.value | money: false }}</span>
                  <span class="chart-legend__percent u-bidi">· {{ item.percent }}%</span>
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
  private theme = inject(ThemeService);
  private translate = inject(TranslateService);
  private host = viewChild<ElementRef<HTMLDivElement>>('host');
  readonly asTable = signal(false);

  /** money-formatted tooltip line: tabular figures + the localized currency */
  private tooltipMoney(value: number): string {
    const currency = this.translate.instant('common.egp');
    const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${formatted} ${currency}`.trim();
  }
  /** indexes of series hidden via the legend toggle (T7-03) */
  private readonly hidden = signal<ReadonlySet<number>>(new Set());
  private prevSeries: readonly ChartSeries[] | null = null;

  /**
   * The series palette comes from the --chart-* tokens (RD-1.1/RD-1.6) so both
   * themes stay in sync — read at render time, never hardcoded hexes here.
   */
  private readPalette(): string[] {
    const styles = getComputedStyle(document.documentElement);
    return Array.from({ length: 8 }, (_, i) => styles.getPropertyValue(`--chart-${i + 1}`).trim()).filter(Boolean);
  }
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
    // touch the theme signal so legend chips re-read the --chart-* tokens on toggle
    this.theme.theme();
    const palette = this.readPalette();
    const visible = items.filter((_, i) => !hidden.has(i));
    const total = visible.reduce((sum, s) => sum + s.value, 0);
    return items.map((s, i) => ({
      index: i,
      label: s.label,
      value: s.value,
      percent: total > 0 && !hidden.has(i) ? Math.round((s.value / total) * 100) : 0,
      color: palette[i % palette.length],
      hidden: hidden.has(i),
    }));
  });

  visibleItems = computed(() => this.legendItems().filter((item) => !item.hidden));

  visibleTotal = computed(() => this.visibleItems().reduce((sum, item) => sum + item.value, 0));

  constructor() {
    // React to series/legend/theme changes: create or update chart when data
    // arrives or the theme toggles (RD-1.6 — charts re-render per theme).
    effect(async () => {
      const series = this.series();
      // tracked dependency — re-renders with the other palette on theme toggle (RD-1.6)
      this.theme.theme();
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
    // RD-1.6: every chart color comes from tokens — hairline grids, the donut
    // track, tabular body font and inverted tooltip surfaces (light tooltips on
    // #1F1B18 / dark tooltips on #F5EFE8, i.e. text/bg swapped per theme).
    const token = (name: string, fallback: string) => styles.getPropertyValue(name).trim() || fallback;
    const textColor = token('--color-text', '#1f1b18');
    const gridColor = token('--color-hairline', '#e2dcd4');
    const trackColor = token('--chart-track', '#eae4dc');
    const font = { family: token('--font-ui', "'IBM Plex Sans Arabic', Tahoma, sans-serif") };
    const tooltipBg = textColor === '#1f1b18' ? '#1f1b18' : '#f5efe8';
    const tooltipFg = textColor === '#1f1b18' ? '#f5efe8' : '#3a261d';
    const type = this.chartType();

    const lineDataset = {
      data: values,
      label: this.title(),
      borderColor: colors[0],
      backgroundColor: colors[0],
      borderWidth: this.isNarrow() ? 1.5 : 2.5,
      pointRadius: values.length === 1 ? 4 : 0,
      pointHoverRadius: 5,
      pointHitRadius: 12,
      fill: false,
      tension: 0,
    };
    const barDataset = {
      data: values,
      backgroundColor: colors,
      borderColor: gridColor,
      borderWidth: 1,
      borderRadius: 4,
      maxBarThickness: 32,
    };

    this.chart = new this.chartLib(canvas, {
      type,
      data: {
        labels,
        datasets:
          type === 'doughnut'
            ? [
                // full-ring track under the slices — the Stitch donut spec (RD-3.6)
                { data: [values.reduce((sum, v) => sum + v, 0)], backgroundColor: [trackColor], borderWidth: 0, weight: 1 },
                { data: values, backgroundColor: colors, borderWidth: 0, weight: 1 },
              ]
            : [type === 'line' ? lineDataset : barDataset],
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
          tooltip: {
            titleColor: tooltipFg,
            bodyColor: tooltipFg,
            backgroundColor: tooltipBg,
            titleFont: font,
            bodyFont: font,
            padding: 8,
            cornerRadius: 8,
            // UX-6.7: money-formatted tooltips — tabular figures + the currency label
            callbacks: {
              label: (ctx: { parsed: number; label?: string }) =>
                ` ${this.tooltipMoney(ctx.parsed ?? 0)}${ctx.label ? ` — ${ctx.label}` : ''}`,
            },
          },
        },
        ...(type === 'doughnut'
          ? { cutout: '75%' }
          : {
              scales: {
                y: { beginAtZero: true, ticks: { color: textColor, precision: 0, font }, grid: { color: gridColor } },
                x: { ticks: { color: textColor, font }, grid: { display: false } },
              },
            }),
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
