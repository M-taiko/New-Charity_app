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
import { RouterLink } from '@angular/router';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { formatChartAmount, formatChartTick } from '@shared/lib/chart-format';
import { chartIsEmpty } from '@shared/lib/chart-series';
import { entityColorValue } from '@shared/lib/chart-colors';
import { MotionService } from '@core/motion/motion.service';
import { ThemeService } from '@core/theme/theme.service';
import type { ModuleAccent } from '@shared/ui/icon/icon.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ChartPrintHelperComponent } from '../print-sheet/print-sheet.component';

export interface ChartSeries {
  label: string;
  value: number;
  /**
   * N-07: the entity behind the point — an agent, a direction, a treasury. A
   * series that carries it is coloured by `entityColor(id)`, so the same
   * entity wears the same colour on every page; without it the colour follows
   * the position in the list, as before.
   */
  id?: string | number;
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
 *
 * B-07: the card OWNS the one legend — pages never render their own; a
 * single-series bar/line chart is ONE colour (`--chart-1`) — a per-bar
 * rainbow carries no meaning; every tick and tooltip is formatted with
 * en-US digits (compact on axes, full money in tooltips); the donut has no
 * extra track ring and its centre figure auto-shrinks inside the hole.
 * `compare()` renders a second (previous-period) series in `--chart-muted`
 * as grouped bars; `seriesLabel` names the MAIN series of that legend (a
 * comparison title like "income vs expenses" is not a series name — R4-02).
 * `legendVariant="ranked"` renders the donut legend as a ranked list with
 * progress bars (P-01). Axis ticks and tooltip amounts are painted through
 * `chart-format.ts`: Western digits and the sign always in front on the RTL
 * canvas.
 *
 * R5-02: a chart with nothing to plot shows the shared EMPTY STATE, never a
 * canvas. An all-zero series used to draw a bare axis running 0…1 with no
 * bar, slice or point and no explanation (`chartIsEmpty()` decides; the
 * decision is in `chart-series.ts`, away from Angular, so it is unit-tested).
 * `data-empty` / `data-view` on the card let the visual matrix check it.
 */
@Component({
  selector: 'app-chart-card',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, MoneyPipe, ChartPrintHelperComponent, RouterLink, EmptyStateComponent],
  template: `
    <div class="chart-card" [attr.data-empty]="empty() && !loading()" [attr.data-view]="view()" [attr.data-loading]="loading() || null" [style.--chart-h]="height() + 'px'">
      <div class="chart-card__head">
        <span class="chart-card__title">{{ title() | translate }}</span>
        @if (viewAllRoute(); as link) {
          <a [routerLink]="link" class="chart-card__viewall">{{ (viewAllLabel() || 'dashboard.bento.view_all') | translate }}</a>
        }
        <span class="chart-card__spacer"></span>
        <!-- stopPropagation: the card may sit inside a routerLink wrapper —
             the toggle must switch views in place, never navigate.
             N-09: the toggle keeps its place while the card loads and when it
             is empty (invisible, disabled) — a head that grows by a button
             when the data lands moves everything under the card -->
        <p-button
          class="chart-card__toggle"
          [class.chart-card__toggle--reserved]="empty() || loading()"
          [disabled]="empty() || loading()"
          [attr.aria-hidden]="empty() || loading() || null"
          [label]="(asTable() ? 'common.view_chart' : 'common.view_table') | translate"
          [text]="true"
          size="small"
          (onClick)="toggleView($event)"
        />
      </div>

      <!-- N-09: the body has its height from the first frame (--chart-h, the
           height input) — while loading, when empty and with the chart — so
           nothing below the card moves when the data arrives -->
      <div class="chart-card__body">
        @if (loading()) {
          <div class="chart-card__loading skeleton" role="status" [attr.aria-label]="'common.loading' | translate"></div>
        } @else if (empty()) {
          <!-- nothing to plot: the shared empty state, compact (icon + one
               line) — the full one with its illustration stood taller than
               this body. The card head keeps its title and its "view all" link -->
          <app-empty-state [compact]="true" [title]="emptyLabel() || 'chart.empty'" [accent]="accent()" [iconName]="iconName()" />
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
        } @else if (visibleItems().length > 0) {
          <div class="chart-card__plot">
            <div #host class="chart-card__canvas u-full"></div>
            @if (chartType() === 'doughnut') {
              <span class="chart-card__total u-bidi" aria-hidden="true">{{ visibleTotal() | money: false }}</span>
            }
          </div>
          <app-chart-print-helper [canvas]="printCanvas()" />
        } @else {
          <!-- there is data, but every series is switched off in the legend:
               no bare axis for that either — the legend turns them back on -->
          <p class="u-muted">{{ 'chart.all_hidden' | translate }}</p>
        }
        <!-- the legend stays whenever there is data, even with every series
             switched off (it used to disappear together with the plot) -->
        @if (loading() && chartType() === 'doughnut' && legendVariant() === 'ranked') {
          <!-- N-09: as many rows as the loaded legend shows at most, and the
               "show all" line — the card has its final height before the data -->
          <ul class="chart-legend chart-legend--ranked" aria-hidden="true">
            @for (row of legendSlots(); track row) {
              <li class="chart-ranked__row chart-ranked__row--loading">
                <span class="chart-ranked__label skeleton">&nbsp;</span>
                <span class="chart-ranked__amount skeleton">&nbsp;</span>
                <span class="chart-ranked__pct skeleton">&nbsp;</span>
              </li>
            }
          </ul>
          <button type="button" class="chart-card__more chart-card__more--loading skeleton" disabled aria-hidden="true" tabindex="-1">&nbsp;</button>
        } @else if (!empty() && !asTable()) {
          <!-- R2-04 legend rules: one chip per SERIES for compare charts; a
               per-slice legend for donuts ONLY (slice colours carry meaning);
               single-series bar/line charts render NO legend — the card title
               already names the series and per-point dots were a rainbow -->
          @if (hasCompare()) {
            <ul class="chart-legend chart-legend--compare">
              <li>
                <span class="chart-legend__item">
                  <span class="chart-legend__chip" [style.background]="mainColor()"></span>
                  <span class="chart-legend__label">{{ (seriesLabel() || title()) | translate }}</span>
                </span>
              </li>
              <li>
                <span class="chart-legend__item">
                  <span class="chart-legend__chip" [style.background]="compareColor()"></span>
                  <span class="chart-legend__label">{{ compareLabel() | translate }}</span>
                </span>
              </li>
            </ul>
          } @else if (chartType() === 'doughnut' && legendVariant() === 'ranked') {
            <!-- ranked list shows the TOP maxRanked() rows only — listing
                 every slice made this card 600px tall and stretched the
                 neighbouring chart to match; the rest stay one click away -->
            <ul class="chart-legend chart-legend--ranked">
              @for (item of topRanked(); track item.index) {
                <li>
                  <button
                    type="button"
                    class="chart-ranked__row"
                    [class.is-hidden]="item.hidden"
                    [title]="item.label"
                    [attr.aria-pressed]="!item.hidden"
                    [attr.aria-label]="item.label + ' — ' + (item.value | money: false)"
                    (click)="toggleSeries(item.index, $event)"
                  >
                    <span class="chart-ranked__label">{{ item.label }}</span>
                    <span class="chart-ranked__amount u-bidi">{{ item.value | money: false }}</span>
                    <span class="chart-ranked__pct u-bidi">{{ item.percent }}%</span>
                    <span class="chart-ranked__track" aria-hidden="true">
                      <span class="chart-ranked__fill" [style.width.%]="item.percent" [style.background]="item.color"></span>
                    </span>
                  </button>
                </li>
              }
            </ul>
            @if (hiddenRankedCount() > 0) {
              <button type="button" class="chart-card__more u-bidi" (click)="asTable.set(true)">
                {{ 'chart.show_all' | translate: { count: hiddenRankedCount() } }}
              </button>
            }
          } @else if (chartType() === 'doughnut') {
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
  /** previous-period bars — rendered grouped, in --chart-muted (B-07.2) */
  compare = input<ChartSeries[] | null>(null);
  /** i18n key naming the MAIN series of a compare legend (falls back to the card title) */
  seriesLabel = input('');
  /** i18n key for the compare legend row */
  compareLabel = input('');
  /** donut legend as a ranked list with progress bars (P-01) */
  legendVariant = input<'list' | 'ranked'>('list');
  /** axis orientation for bar charts — 'y' renders HORIZONTAL bars (P-08/_5) */
  orientation = input<'x' | 'y'>('x');
  /** R2-03: the widget's "view all" link rides in the card head — the chart
   *  card can then BE the bento widget with no card-in-card */
  viewAllRoute = input<string | null>(null);
  viewAllLabel = input('');
  /** R5-02: i18n key of the empty state's line; empty = the generic one */
  emptyLabel = input('');
  /** R5-02: module whose chip sits on the empty state */
  accent = input<ModuleAccent | ''>('');
  /** R5-02: Tabler icon shown on the empty state (in the module accent) */
  iconName = input('chart');
  /**
   * N-09: the data is on its way. The card then shows a skeleton in a body
   * that already has its full height — a chart used to appear (or pop from
   * its empty state into a taller one) the moment its request landed, and
   * everything under it jumped.
   */
  loading = input(false);

  private motion = inject(MotionService);
  private theme = inject(ThemeService);
  private translate = inject(TranslateService);
  private host = viewChild<ElementRef<HTMLDivElement>>('host');
  readonly asTable = signal(false);

  /** R5-02: nothing to plot — no series, or every value zero (`chart-series.ts`) */
  readonly empty = computed(() => chartIsEmpty(this.series(), this.compare()));

  /**
   * R5-02: what the body shows — read by the visual matrix off `data-view`.
   * `hidden` = there is data but every series is switched off in the legend;
   * then the legend must stay so the user can switch a series back on.
   */
  readonly view = computed<'loading' | 'chart' | 'table' | 'hidden'>(() => {
    if (this.loading()) {
      return 'loading';
    }
    if (this.asTable()) {
      return 'table';
    }
    return this.visibleItems().length === 0 ? 'hidden' : 'chart';
  });

  /** N-09: one placeholder row per row the ranked legend can show */
  readonly legendSlots = computed(() => Array.from({ length: this.maxRanked() }, (_, i) => i));

  /** money-formatted tooltip line: the amount (minus first, Western digits)
   *  followed by the localized currency */
  private tooltipMoney(value: number): string {
    const currency = this.translate.instant('common.egp');
    return `${formatChartAmount(value)} ${currency}`.trim();
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
      // N-07: a series that names its entity keeps that entity's colour on
      // every chart; the rest follow their position in the list
      color: s.id !== undefined ? entityColorValue(s.id, palette) : palette[i % palette.length],
      hidden: hidden.has(i),
    }));
  });

  visibleItems = computed(() => this.legendItems().filter((item) => !item.hidden));

  visibleTotal = computed(() => this.visibleItems().reduce((sum, item) => sum + item.value, 0));

  /** ranked legend rows — sorted by value descending (P-01 donut legend) */
  rankedItems = computed(() => [...this.legendItems()].sort((a, b) => b.value - a.value));

  /** how many ranked rows the list legend shows before "show all" (P-01) */
  maxRanked = input(5);
  topRanked = computed(() => this.rankedItems().slice(0, this.maxRanked()));
  hiddenRankedCount = computed(() => Math.max(0, this.rankedItems().length - this.maxRanked()));

  hasCompare = computed(() => (this.compare() ?? []).length > 0);

  /** the two grouped-bar colours, from tokens at render time (B-07) */
  mainColor = computed(() => {
    this.theme.theme();
    return this.readPalette()[0];
  });

  compareColor = computed(() => {
    this.theme.theme();
    const styles = getComputedStyle(document.documentElement);
    return styles.getPropertyValue('--chart-muted').trim() || '#cbd5e1';
  });

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

      // no canvas while the data is on its way, without data (an empty chart
      // shows the empty state), or when every series is switched off in the
      // legend (it shows a hint, and the legend turns them back on)
      if (this.loading() || this.empty() || showTable || series.length === 0 || this.visibleItems().length === 0) {
        this.destroyChart();
        return;
      }

      // Wait a tick for the #host div to render (it's inside the plot branch)
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
    const font = { family: token('--font-ui', "'IBM Plex Sans Arabic', Tahoma, sans-serif") };
    const tooltipBg = textColor === '#1f1b18' ? '#1f1b18' : '#f5efe8';
    const tooltipFg = textColor === '#1f1b18' ? '#f5efe8' : '#3a261d';
    const type = this.chartType();
    // B-07.2: one series = one colour; the compare series gets --chart-muted
    const mainColor = token('--chart-1', '#5c4033');
    const compareColor = token('--chart-muted', '#cbd5e1');
    const hoverMain = this.darken(mainColor);
    // N-07: bars that each stand for an entity (every series names its id)
    // wear that entity's colour, so a direction is the same colour here as in
    // the donut and in the legend. A bar chart without ids stays one colour.
    const entityBars = type === 'bar' && !this.hasCompare() && this.series().length > 0 && this.series().every((s) => s.id !== undefined);

    const seriesName = this.translate.instant(this.seriesLabel() || this.title());
    const lineDataset = {
      data: values,
      label: seriesName,
      borderColor: mainColor,
      backgroundColor: mainColor,
      borderWidth: this.isNarrow() ? 1.5 : 2.5,
      pointRadius: values.length === 1 ? 4 : 0,
      pointHoverRadius: 5,
      pointHitRadius: 12,
      fill: false,
      tension: 0,
    };
    const barDataset = {
      data: values,
      backgroundColor: entityBars ? colors : mainColor,
      hoverBackgroundColor: entityBars ? colors.map((color) => this.darken(color)) : hoverMain,
      borderRadius: 4,
      maxBarThickness: 32,
    };
    const compareBars = {
      data: (this.compare() ?? []).map((s, i) => (items[i] ? s.value : null)),
      backgroundColor: compareColor,
      hoverBackgroundColor: this.darken(compareColor, 0.9),
      borderRadius: 4,
      maxBarThickness: 32,
    };

    // B-07.3 + R4-02: Western digits everywhere — compact ticks (70K / 1.2M),
    // full money in tooltips, the sign always in front on the RTL canvas
    const tickCallback = (value: string | number): string => formatChartTick(Number(value));

    this.chart = new this.chartLib(canvas, {
      type,
      data: {
        labels,
        datasets:
          type === 'doughnut'
            ? [{ data: values, backgroundColor: colors, borderWidth: 0 }]
            : type === 'line'
              ? [lineDataset]
              : this.hasCompare()
                ? [barDataset, compareBars]
                : [barDataset],
      },
      options: {
        responsive: true,
        // NB the canvas inherits the page's RTL direction — Chart.js has no
        // root `rtl`/`textDirection` switch for axis ticks (only the legend and
        // tooltip plugins read it). Numbers are made direction-proof by
        // `chart-format.ts` instead; Arabic category labels keep the page's
        // direction so multi-word labels stay in reading order.
        maintainAspectRatio: false,
        resizeDelay: 150,
        // horizontal bars for orientation 'y' (P-08/_5)
        ...(type === 'bar' ? { indexAxis: this.orientation() } : {}),
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
              // UX-6.7: money-formatted tooltips. A compare chart names the
              // series in front of the amount (R4-02: the income bars carried
              // no name at all, in the legend or here)
              label: (ctx: { parsed: number | { x?: number; y?: number }; label?: string; datasetIndex?: number }) => {
                const parsed = typeof ctx.parsed === 'number' ? ctx.parsed : (ctx.parsed?.y ?? 0);
                const nameKey = ctx.datasetIndex === 1 ? this.compareLabel() : this.seriesLabel();
                const prefix = this.hasCompare() && nameKey ? `${this.translate.instant(nameKey)}: ` : '';
                return ` ${prefix}${this.tooltipMoney(parsed)}${ctx.label ? ` — ${ctx.label}` : ''}`;
              },
            },
          },
        },
        ...(type === 'doughnut'
          ? { cutout: '68%' }
          : {
              scales: {
                // P-08/_5: orientation 'y' flips the axes — horizontal bars
                // (value ticks ride the x axis, labels the y axis)
                [this.orientation() === 'y' ? 'x' : 'y']: {
                  beginAtZero: true,
                  ticks: { color: textColor, precision: 0, font, callback: tickCallback },
                  grid: { color: gridColor },
                },
                [this.orientation() === 'y' ? 'y' : 'x']: {
                  ticks: { color: textColor, font, maxRotation: 0, autoSkip: true, maxTicksLimit: this.isNarrow() ? 4 : 8 },
                  grid: { display: false },
                },
              },
            }),
      },
    }) as typeof this.chart;

    // B-07.4: the donut centre figure must fit the hole — scale with the
    // rendered card width, never touch the ring
    if (type === 'doughnut') {
      // B-07.4 + R3-03: the centre figure must sit INSIDE the hole (≤85% of
      // the hole diameter). Start at the clamped size, measure the real text
      // box, shrink until it fits; past the 11px floor switch to compact K.
      const plot = el.parentElement;
      const total = plot?.querySelector<HTMLElement>('.chart-card__total') ?? null;
      if (total && el.clientWidth > 0) {
        const hole = el.clientWidth * 0.68; // cutout
        const budget = hole * 0.85;
        const raw = this.visibleTotal();
        const full = raw.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(raw);
        // the total span is inset:0 (full plot) — measure the TEXT box
        const textWidth = (): number => {
          const range = document.createRange();
          range.selectNodeContents(total);
          return range.getBoundingClientRect().width;
        };
        const fits = (text: string, px: number): boolean => {
          total.textContent = text;
          total.style.fontSize = `${px}px`;
          return textWidth() <= budget;
        };
        let size = Math.max(13, Math.min(24, Math.round(el.clientWidth * 0.075)));
        if (!fits(full, size)) {
          for (size--; size >= 11; size--) {
            if (fits(full, size)) break;
          }
          if (size < 11) {
            size = 11;
            if (!fits(compact, size)) {
              fits(compact.split('.')[0], size);
            }
          }
        }
      }
    }
  }

  /** canvas-side hover shade — darken a token hex by a factor (no CSS color-mix, Chrome 109 gate is CSS-only but keep it simple) */
  private darken(hex: string, factor = 0.82): string {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!m) {
      return hex;
    }
    const n = parseInt(m[1], 16);
    const shade = (channel: number) => Math.max(0, Math.round(channel * factor));
    return `#${((shade((n >> 16) & 255) << 16) | (shade((n >> 8) & 255) << 8) | shade(n & 255)).toString(16).padStart(6, '0')}`;
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
