import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { PageActionsComponent } from '@shared/ui/page-actions/page-actions.component';
import type { PageAction } from '@shared/ui/page-actions/page-actions.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, Lookups } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface Figures {
  today: number;
  this_month: number;
  this_year: number;
  grand_total: number;
  by_day: { date: string; label: string; amount: number }[];
  governorates_top: {
    label: string; standard: boolean; total: number; approved: number;
    pending: number; rejected: number; expenses_total: number; average: number;
  }[];
}

interface DirectionalStats {
  figures: Figures;
  explorer: {
    current: { id: number; name: string } | null;
    children: { id: number; name: string; amount: number; is_item: boolean }[];
  };
}

interface ReportsData {
  treasury_balance: string; total_spent: string; active_custodies: number;
  total_social_cases: number; approved_cases: number; pending_cases: number; rejected_cases: number;
  social_case_expenses: string;
  total_custodies: number; closed_custodies: number;
  custody_amount: string; custody_spent: string; custody_returned: string;
  direction_figures: Figures;
  expenses_by_category: { id: number; name: string; amount: string }[];
  expenses_by_day: { date: string; label: string; amount: string }[];
  custodies_by_agents: { agent_id: number | null; agent_name: string; remaining: string; count: number }[];
  cases_by_researchers: { researcher_id: number | null; researcher_name: string; cases_count: number; spent: string }[];
}

/**
 * Reports dashboard (plan 20, T34, T3-110): every Blade card, chart and table
 * of reports/dashboard.blade.php, plus a direction explorer (T33) — the
 * direction-affected figures and the category chart refresh from
 * GET /directional-stats without leaving the page.
 * T4-20/21/27: async-page states + static ledger table in the shared layout.
 */
@Component({
  selector: 'app-reports-dashboard',
  standalone: true,
  imports: [
    FieldComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableCellDirective,
    StatTileComponent, ChartCardComponent, PrintSheetComponent, MoneyPipe,
    PageActionsComponent, IconComponent,
  ],
  template: `
    <app-print-sheet [title]="'report.dashboard.title' | translate">
      <app-page-header [title]="'report.dashboard.title' | translate" accent="reports" iconName="report-analytics">
        <!-- T7-10: the enforced action bar — one print action, nothing else in the header -->
        <app-page-actions actions [pageActions]="headerActions" />
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/dashboard']">
        @if (data.value(); as d) {
          <!-- T33 direction explorer: direction-affected figures refresh in place -->
          <div class="filter-bar">
            <div class="filter-bar__row">
              <app-field label="report.dashboard.direction_filter" controlId="rep-dashboard-direction">
                <p-select appendTo="body" inputId="rep-dashboard-direction" name="direction"
                  [options]="directionOptions()" optionLabel="label" optionValue="value"
                  [ngModel]="selectedDirection()" (ngModelChange)="onDirection($event)"
                  [ariaLabel]="'report.filters.direction' | translate"
                />
              </app-field>
            </div>
          </div>

          <!-- bento layout — the same rich grid language as the main dashboard -->
          <div class="bento">
            <!-- hero KPI strip: the four headline numbers -->
            <section class="bento__widget bento__widget--full">
              <div class="kpi-grid kpi-grid--4">
                <a class="tile-link" routerLink="/treasury" [title]="'common.open_page' | translate">
                  <app-stat-tile [label]="'report.dashboard.treasury_balance'" [value]="d.treasury_balance" [money]="true" tone="success" accent="treasury" iconName="custody" caption="report.dashboard.treasury_caption" [captionParams]="{ n: fig('today') | money: false }" />
                </a>
                <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
                  <app-stat-tile [label]="'report.dashboard.total_expenses'" [value]="d.total_spent" [money]="true" tone="danger" accent="expenses" iconName="myExpense" caption="report.dashboard.expenses_today_hint" [captionParams]="{ n: fig('today') | money: false }" />
                </a>
                <a class="tile-link" routerLink="/custodies" [title]="'common.open_page' | translate">
                  <app-stat-tile [label]="'report.dashboard.active_custodies'" [value]="d.active_custodies" tone="info" accent="custodies" iconName="custody" caption="report.dashboard.custody_total_hint" [captionParams]="{ n: d.total_custodies }" />
                </a>
                <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
                  <app-stat-tile [label]="'report.dashboard.social_cases'" [value]="d.total_social_cases" tone="primary" accent="cases" iconName="case" caption="report.dashboard.pending_cases_hint" [captionParams]="{ n: d.pending_cases }" />
                </a>
              </div>
            </section>

            <!-- expenses summary (direction-aware): figures + trend chart -->
            <section class="bento__widget bento__widget--wide">
              <div class="chart-card__head">
                <span class="chart-card__title"><app-icon name="chart-pie" chip="reports" [size]="16" /> {{ 'report.dashboard.expenses_summary' | translate }}</span>
                <span class="chart-card__spacer"></span>
                <a routerLink="/expenses" class="dash-viewall">{{ 'dashboard.bento.view_all' | translate }}</a>
              </div>
              <div class="dash-insights">
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.expenses_today' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-warn">{{ fig('today') | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.expenses_month' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-info">{{ fig('this_month') | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.expenses_year' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-danger">{{ fig('this_year') | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.expenses_grand' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-primary">{{ fig('grand_total') | money: false }}</span>
                </div>
              </div>
              <app-chart-card [title]="'report.dashboard.charts.expenses_by_day'" [series]="byDaySeries()" chartType="bar" [height]="220" />
            </section>

            <!-- quick links: the detailed reports as action chips -->
            <nav class="bento__widget" [attr.aria-label]="'report.dashboard.all_reports' | translate">
              <div class="chart-card__head"><span class="chart-card__title"><app-icon name="report-analytics" chip="reports" [size]="16" /> {{ 'report.dashboard.quick_reports' | translate }}</span></div>
              <div class="dash-chips">
                @for (card of reportCards; track card.route) {
                  <a class="dash-chip" [routerLink]="card.route">
                    <app-icon [name]="card.icon" chip="reports" [size]="18" />
                    <span>{{ card.label | translate }}</span>
                  </a>
                }
              </div>
            </nav>

            <!-- custody report: figures + agents chart -->
            <section class="bento__widget bento__widget--wide">
              <div class="chart-card__head">
                <span class="chart-card__title"><app-icon name="wallet" chip="custodies" [size]="16" /> {{ 'report.dashboard.custody_report' | translate }}</span>
                <span class="chart-card__spacer"></span>
                <a routerLink="/reports/agents-balance" class="dash-viewall">{{ 'dashboard.bento.view_all' | translate }}</a>
              </div>
              <div class="dash-insights">
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_amount' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-info">{{ d.custody_amount | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_spent' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-danger">{{ d.custody_spent | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_returned' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-pos">{{ d.custody_returned | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_remaining' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-pos">{{ custodyRemaining() | money: false }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_total' | translate }}</span>
                  <span class="dash-insight__value u-bidi is-primary">{{ d.total_custodies }}</span>
                </div>
                <div class="dash-insight">
                  <span class="dash-insight__label">{{ 'report.dashboard.custody_closed' | translate }}</span>
                  <span class="dash-insight__value u-bidi">{{ d.closed_custodies }}</span>
                </div>
              </div>
              <app-chart-card [title]="'report.dashboard.charts.custodies_by_agents'" [series]="agentsSeries()" chartType="bar" [height]="220" />
            </section>

            <!-- case report: status figures + researchers chart -->
            <section class="bento__widget">
              <div class="chart-card__head">
                <span class="chart-card__title"><app-icon name="case" chip="cases" [size]="16" /> {{ 'report.dashboard.case_report' | translate }}</span>
              </div>
              <ul class="dash-attention">
                <li>
                  <a routerLink="/cases">
                    {{ 'case.list.approved' | translate }}
                    <span class="dash-attention__count u-bidi">{{ d.approved_cases }}</span>
                  </a>
                </li>
                <li>
                  <a routerLink="/cases">
                    {{ 'case.list.pending' | translate }}
                    <span class="dash-attention__count dash-attention__count--neutral u-bidi">{{ d.pending_cases }}</span>
                  </a>
                </li>
                <li>
                  <a routerLink="/cases">
                    {{ 'report.dashboard.rejected' | translate }}
                    <span class="dash-attention__count u-bidi">{{ d.rejected_cases }}</span>
                  </a>
                </li>
              </ul>
              <app-chart-card [title]="'report.dashboard.charts.cases_by_researchers'" [series]="researchersSeries()" chartType="bar" [height]="200" />
            </section>

            <!-- category donut (direction-aware) -->
            <section class="bento__widget bento__widget--wide">
              <app-chart-card [title]="'report.dashboard.charts.expenses_by_category'" [series]="categorySeries()" [height]="240" />
            </section>

            <!-- governorates table (T34) -->
            <section class="bento__widget bento__widget--full">
              <div class="chart-card__head">
                <span class="chart-card__title">{{ 'report.dashboard.governorates_card' | translate }}</span>
                <span class="chart-card__spacer"></span>
                <p-button [label]="'report.governorates.title' | translate" size="small" [text]="true" routerLink="/reports/governorates" />
              </div>
              <app-data-table listUrl="/reports" mode="static" [autoFilters]="true" [columns]="govCols" [rows]="governoratesTop()" [emptyLabel]="'report.governorates.empty'">
                <ng-template appCell="label" let-row>{{ row.label }}
@if (!row.standard) {
  <span class="stamp">{{ 'report.governorates.non_standard' | translate }}</span>
}</ng-template>
                <ng-template appCell="total" let-row><span class="money u-bidi">{{ row.total | money }}</span></ng-template>
                <ng-template appCell="expenses_total" let-row><span class="u-bidi">{{ row.expenses_total | money }}</span></ng-template>
              </app-data-table>
              <p class="u-muted u-small u-mt-2">{{ 'report.dashboard.governorates_hint' | translate }}</p>
            </section>
          </div>
        }
<div empty>
          <app-empty-state [title]="'report.dashboard.empty'" [text]="'common.no_data'" accent="reports" iconName="report-analytics" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReportsDashboardPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  /** T7-10: the header keeps exactly one action — print */
  readonly headerActions: PageAction[] = [
    { kind: 'print', label: 'common.print', run: () => { this.printPage(); } },
  ];

  /** T7-10: report navigation as cards — the sibling report pages of the module */
  readonly reportCards: readonly { label: string; route: string; icon: string }[] = [
    { label: 'report.governorates.title', route: '/reports/governorates', icon: 'map-pin' },
    { label: 'report.researchers.title', route: '/reports/researchers', icon: 'users-group' },
    { label: 'report.case_expenses.title', route: '/reports/case-expenses', icon: 'heart-handshake' },
    { label: 'report.agents_balance.title', route: '/reports/agents-balance', icon: 'wallet' },
    { label: 'report.items.title', route: '/reports/items', icon: 'receipt' },
    { label: 'report.directions.title', route: '/reports/directions', icon: 'sitemap' },
    { label: 'report.reconciliation.title', route: '/reports/reconciliation', icon: 'building-bank' },
  ];

  data = resource<ReportsData, unknown>({
    loader: () =>
      firstValueFrom(this.http.get<ItemResponse<ReportsData>>('/reports')).then((r) => r.data),
  });

  /** the dashboard is empty only when every main counter is zero */
  isEmpty = (): boolean => {
    const d = this.data.value();
    return (
      !!d &&
      d.total_social_cases === 0 &&
      d.total_custodies === 0 &&
      d.active_custodies === 0 &&
      parseFloat(d.total_spent) === 0
    );
  };

  readonly govCols = [
    { field: 'label', header: 'report.governorates.col_governorate' },
    { field: 'total', header: 'report.governorates.col_total', numeric: true },
    { field: 'expenses_total', header: 'report.governorates.col_spent', numeric: true },
  ];

  /** reference data for the direction filter (cacheable endpoint) */
  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
  });

  /** 'all' | 'c:<categoryId>' | 'i:<itemId>' — mirrors the expenses list filter */
  selectedDirection = signal('all');

  /** T33: figures for the selected direction (null = unfiltered totals from /reports) */
  stats = resource<DirectionalStats | null, unknown>({
    loader: () => {
      const value = this.selectedDirection();
      if (value === 'all') {
        return Promise.resolve(null);
      }
      const [kind, id] = value.split(':');
      const params: Record<string, string> = kind === 'i' ? { item: id } : { direction: id };
      return firstValueFrom(this.http.get<DirectionalStats>('/directional-stats', { params })).catch(() => null);
    },
  });

  directionOptions = computed(() => {
    const options: { label: string; value: string }[] = [
      { label: this.translate.instant('report.filters.all'), value: 'all' },
    ];
    const walk = (nodes: DirectionNode[], depth: number): void => {
      for (const node of nodes) {
        options.push({ label: `${'— '.repeat(depth)}${node.name}`, value: `c:${node.id}` });
        walk(node.children, depth + 1);
        for (const item of node.items) {
          options.push({ label: `${'— '.repeat(depth + 1)}${item.name}`, value: `i:${item.id}` });
        }
      }
    };
    walk(this.lookups.value()?.direction_tree ?? [], 0);
    return options;
  });

  /** direction-affected figure — the selected direction's numbers when filtered */
  fig(key: 'today' | 'this_month' | 'this_year' | 'grand_total'): string {
    return String(this.stats.value()?.figures[key] ?? this.data.value()?.direction_figures[key] ?? 0);
  }

  custodyRemaining(): string {
    const d = this.data.value();
    const remaining = (parseFloat(d?.custody_amount ?? '0') || 0) - (parseFloat(d?.custody_spent ?? '0') || 0);
    return remaining.toFixed(2);
  }

  byDaySeries = computed(() => {
    const d = this.data.value();
    const days = this.stats.value()?.figures.by_day ?? d?.direction_figures.by_day ?? [];
    return days.map((day) => ({ label: day.label, value: day.amount }));
  });

  categorySeries = computed(() => {
    const children = this.stats.value()?.explorer.children;
    if (children && children.length > 0) {
      return children.slice(0, 6).map((c) => ({ label: c.name, value: c.amount }));
    }
    return (this.data.value()?.expenses_by_category ?? [])
      .slice(0, 6)
      .map((c) => ({ label: c.name, value: Number(c.amount) }));
  });

  agentsSeries = computed(() =>
    (this.data.value()?.custodies_by_agents ?? [])
      .slice(0, 6)
      .map((row) => ({ label: row.agent_name, value: parseFloat(row.remaining) || 0 })),
  );

  researchersSeries = computed(() =>
    (this.data.value()?.cases_by_researchers ?? [])
      .slice(0, 6)
      .map((row) => ({ label: row.researcher_name, value: row.cases_count })),
  );

  governoratesTop = computed(() => this.data.value()?.direction_figures.governorates_top ?? []);

  onDirection(value: string): void {
    this.selectedDirection.set(value);
    this.stats.reload();
  }

  printPage(): void {
    window.print();
  }
}
