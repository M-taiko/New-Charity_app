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

/**
 * Titled chart card (plan 16.2). Chart.js loads lazily on the routes that
 * use it. The chart reacts to series() changes — when async data arrives,
 * the chart is created or updated. "View as table" is the a11y version.
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
        @if (series().length === 0) {
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
              @for (row of tableRows(); track row.label) {
                <tr>
                  <td>{{ row.label }}</td>
                  <td class="u-bidi">{{ row.value | money: false }}</td>
                  <td class="u-bidi">{{ row.percent }}</td>
                </tr>
              }
            </tbody>
          </table>
        } @else {
          <div #host class="u-full" [style.height.px]="height()" style="position: relative;"></div>
          <app-chart-print-helper [canvas]="printCanvas()" />
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
  chartType = input<'doughnut' | 'bar'>('doughnut');

  private motion = inject(MotionService);
  private host = viewChild<ElementRef<HTMLDivElement>>('host');
  readonly asTable = signal(false);

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

  tableRows = computed(() => {
    const total = this.series().reduce((sum, s) => sum + s.value, 0);
    return this.series().map((s) => ({
      label: s.label,
      value: s.value,
      percent: total > 0 ? Math.round((s.value / total) * 100) : 0,
    }));
  });

  constructor() {
    // React to series changes: create or update chart when data arrives
    effect(async () => {
      const series = this.series();
      const showTable = this.asTable();

      if (series.length === 0 || showTable) {
        this.destroyChart();
        return;
      }

      // Wait a tick for the #host div to render (it's inside @else)
      await new Promise((r) => setTimeout(r, 0));

      const el = this.host()?.nativeElement;
      if (!el) return;

      await this.renderChart(el, series);
    });
  }

  private async renderChart(el: HTMLDivElement, series: ChartSeries[]): Promise<void> {
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

    const labels = series.map((s) => s.label);
    const values = series.map((s) => s.value);
    const colors = series.map((_, i) => this.palette[i % this.palette.length]);
    const styles = getComputedStyle(document.documentElement);
    const surfaceColor = styles.getPropertyValue('--color-surface').trim() || '#fff';
    // legend/label text follows the theme text color (T6-60 rule: never the
    // surface color, which made legend text invisible)
    const textColor = styles.getPropertyValue('--color-text').trim() || '#4F2F1D';

    this.chart = new this.chartLib(canvas, {
      type: this.chartType(),
      data: {
        labels,
        datasets: [
          {
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
          legend: {
            display: series.length <= 6,
            position: 'bottom',
            labels: { boxWidth: 10, font: { size: 11 }, color: textColor },
          },
        },
        ...(this.chartType() === 'bar'
          ? {
              scales: {
                y: { beginAtZero: true, ticks: { color: textColor }, grid: { color: surfaceColor } },
                x: { ticks: { color: textColor } },
              },
            }
          : {}),
      },
    }) as typeof this.chart;
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
