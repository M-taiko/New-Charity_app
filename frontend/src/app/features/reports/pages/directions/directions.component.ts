import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { ContentDialogComponent } from '@shared/ui/content-dialog/content-dialog.component';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ItemResponse } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface AnalyticsRow {
  id: number;
  name: string;
  level: number | string;
  code: string | null;
  parent_name: string | null;
  total_amount: string;
  expense_count: number;
  average_amount: string;
  reviewed_by: string | null;
  percentage: number;
}

interface AnalyticsData {
  grand_total: string;
  rows: AnalyticsRow[];
}

/** GET /category-item-expenses row (bare array response, not enveloped). */
interface DrillExpense {
  id: number;
  description: string | null;
  amount: number;
  created_at: string | null;
  created_by: string | null;
  approved_by: string | null;
  category: string | null;
  item: string | null;
}

/**
 * Direction analytics (plan 20, T33; T3-110): covers the Blade
 * expense-categories-analytics page — date filter, summary cards, the
 * level-badged statistics table and the per-row expenses drill-down
 * (GET /category-item-expenses) that Blade opened in a modal.
 * T4-20/21/22/27: async-page states + static ledger tables in the shared layout.
 */
@Component({
  selector: 'app-directions-report',
  standalone: true,
  imports: [
    FieldComponent, TranslatePipe, ContentDialogComponent, RouterLink, FormsModule, ButtonModule, DatePickerModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableCellDirective, 
    PrintSheetComponent, StatTileComponent, ChartCardComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    <app-print-sheet [title]="'report.directions.title' | translate">
      <app-page-header [title]="'report.directions.title' | translate" accent="reports" iconName="report-analytics">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="filter-bar">
            <div class="filter-bar__row">
              <app-field label="report.filters.range" controlId="directions-filter-date">
                <p-datepicker appendTo="body" inputId="directions-filter-date" name="date" selectionMode="range"
                  [showIcon]="true"
                  [ngModel]="dateRange()" (ngModelChange)="onDateRange($event)"
                  [ariaLabel]="'report.filters.range' | translate"
                />
              </app-field>
              <div class="filter-bar__spacer"></div>
              <p-button [label]="'common.reset' | translate" size="small" [outlined]="true" (click)="resetFilters()" />
            </div>
          </div>

          <div class="kpi-grid">
            <app-stat-tile [label]="'common.total'" [value]="d.grand_total" [money]="true" accent="reports" iconName="report-analytics" />
            <app-stat-tile [label]="'report.directions.rows_count'" [value]="d.rows.length" />
            <app-stat-tile [label]="'report.directions.operations'" [value]="operationsCount()" />
            <app-stat-tile [label]="'report.directions.col_average'" [value]="averageAmount()" [money]="true" />
          </div>

          <div class="kpi-grid u-mt-4">
            <app-chart-card [title]="'report.directions.charts'" [series]="rootsSeries()" />
          </div>

          <div class="u-mt-4">
            <app-data-table [exportQuery]="exportQuery()" listUrl="/reports/categories-analytics" mode="static" [autoFilters]="true" [title]="'report.directions.details'" [columns]="rowCols" [rows]="d.rows" [emptyLabel]="'report.directions.empty'">
              <ng-template appCell="level" let-row><span class="stamp">{{ levelKey(row.level) | translate }}</span></ng-template>
              <ng-template appCell="name" let-row>{{ row.name }}</ng-template>
              <ng-template appCell="code" let-row><span class="u-bidi">{{ row.code ?? '—' }}</span></ng-template>
              <ng-template appCell="parent_name" let-row>{{ row.parent_name ?? '—' }}</ng-template>
              <ng-template appCell="total_amount" let-row><span class="u-bidi">{{ row.total_amount | money }}</span></ng-template>
              <ng-template appCell="expense_count" let-row><span class="u-bidi">{{ row.expense_count }}</span></ng-template>
              <ng-template appCell="average_amount" let-row><span class="u-bidi">{{ row.average_amount | money }}</span></ng-template>
              <ng-template appCell="reviewed_by" let-row>{{ row.reviewed_by ?? '—' }}</ng-template>
              <ng-template appCell="percentage" let-row><span class="u-bidi">{{ row.percentage }}%</span></ng-template>
            </app-data-table>
          </div>
        }
        <div empty>
          <app-empty-state [title]="'report.directions.empty'" [text]="'common.no_data'" accent="reports" iconName="report-analytics" />
        </div>
      </app-async-page>
    </app-print-sheet>

    <!-- Drill-down: the row's expenses (GET /category-item-expenses) -->
    @if (data.value()) {
      <app-content-dialog [visible]="drillVisible()" (visibleChange)="drillVisible.set($event)"
        [header]="'report.directions.expenses_dialog'"
        [width]="'min(720px, 94vw)'">
        <p class="u-small u-mt-2">{{ drillTitle() }}</p>
        @if (drillLoading()) {
          <div class="skeleton skeleton--tile" role="status"></div>
        } @else {
          <app-data-table mode="static" [autoFilters]="true" [columns]="drillCols" [rows]="drillRows()" [emptyLabel]="'report.directions.empty_drill'">
            <ng-template appCell="created_at" let-row><span class="u-bidi">{{ row.created_at | datetime }}</span></ng-template>
            <ng-template appCell="description" let-row>{{ row.description ?? '—' }}</ng-template>
            <ng-template appCell="created_by" let-row>{{ row.created_by ?? '—' }}</ng-template>
            <ng-template appCell="approved_by" let-row>{{ row.approved_by ?? '—' }}</ng-template>
            <ng-template appCell="amount" let-row><span class="u-bidi">{{ row.amount | money }}</span></ng-template>
            <ng-template appCell="actions" let-row><a [routerLink]="['/expenses', row.id]">{{ 'common.view' | translate }}</a></ng-template>
          </app-data-table>
          <p class="u-flex u-flex-between u-mt-2">
            <span>{{ 'report.directions.drill_count' | translate }}: {{ drillRows().length }}</span>
            <span class="u-bidi">{{ 'common.total' | translate }}: {{ drillTotal() | money }}</span>
          </p>
        }
      </app-content-dialog>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DirectionsReportPage {
  private http = inject(HttpClient);

  params = signal<{ date_from: string; date_to: string }>({ date_from: '', date_to: '' });

  /** T7-06: the report's own period filters ride the export query */
  readonly exportQuery = computed(() => cleanParams(this.params()));

  data = resource<AnalyticsData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<AnalyticsData>>('/reports/categories-analytics', {
          params: cleanParams(this.params()),
        }),
      ).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.rows ?? []).length === 0;

  readonly rowCols = [
    { field: 'level', header: 'report.directions.col_level' },
    { field: 'name', header: 'report.directions.col_item' },
    { field: 'code', header: 'report.directions.col_code' },
    { field: 'parent_name', header: 'report.directions.col_parent' },
    { field: 'total_amount', header: 'report.directions.col_total', numeric: true },
    { field: 'expense_count', header: 'report.directions.col_count', numeric: true },
    { field: 'average_amount', header: 'report.directions.col_average', numeric: true },
    { field: 'reviewed_by', header: 'report.directions.col_reviewer' },
    { field: 'percentage', header: 'report.directions.col_percent', numeric: true },
  ];

  readonly drillCols = [
    { field: 'created_at', header: 'report.case_expenses.col_date' },
    { field: 'description', header: 'report.case_expenses.col_description' },
    { field: 'created_by', header: 'report.directions.col_user' },
    { field: 'approved_by', header: 'report.directions.col_reviewer' },
    { field: 'amount', header: 'common.amount', numeric: true },
    { field: 'actions', header: 'common.actions' },
  ];

  dateRange = signal<Date[]>([]);

  drillVisible = signal(false);
  drillLoading = signal(false);
  drillTitle = signal('');
  drillRows = signal<DrillExpense[]>([]);

  rootsSeries = computed(() =>
    (this.data.value()?.rows ?? [])
      .filter((r) => r.level === 1)
      .slice(0, 6)
      .map((r) => ({ label: r.name, value: parseFloat(r.total_amount) || 0 })),
  );

  operationsCount = computed(() =>
    (this.data.value()?.rows ?? []).reduce((sum, row) => sum + row.expense_count, 0),
  );

  averageAmount = computed(() => {
    const rows = this.data.value()?.rows ?? [];
    if (rows.length === 0) {
      return '0.00';
    }
    const total = rows.reduce((sum, row) => sum + (parseFloat(row.total_amount) || 0), 0);
    return (total / rows.length).toFixed(2);
  });

  drillTotal = computed(() =>
    this.drillRows().reduce((sum, row) => sum + row.amount, 0).toFixed(2),
  );

  levelKey(level: number | string): string {
    if (level === 1) return 'report.directions.level_1';
    if (level === 2) return 'report.directions.level_2';
    if (level === 3) return 'report.directions.level_3';
    return 'report.directions.level_item';
  }

  /** row click → its expenses via the drill-down endpoint (categories or items) */
  openDrill(row: AnalyticsRow): void {
    this.drillTitle.set(row.name);
    this.drillRows.set([]);
    this.drillVisible.set(true);
    void this.loadDrill(row);
  }

  private async loadDrill(row: AnalyticsRow): Promise<void> {
    this.drillLoading.set(true);
    try {
      const rows = await firstValueFrom(
        this.http.get<DrillExpense[]>('/category-item-expenses', {
          params: { item_id: row.id, item_type: String(row.level) },
        }),
      );
      this.drillRows.set(rows);
    } catch {
      this.drillRows.set([]);
    } finally {
      this.drillLoading.set(false);
    }
  }

  onDateRange(value: Date[] | undefined): void {
    const range = value ?? [];
    this.dateRange.set(range);
    if (range.length === 2) {
      this.setParams({ date_from: toIsoDate(range[0]), date_to: toIsoDate(range[1]) });
    } else if (range.length === 0) {
      this.setParams({ date_from: '', date_to: '' });
    }
  }

  resetFilters(): void {
    this.dateRange.set([]);
    this.setParams({ date_from: '', date_to: '' });
  }

  setParams(patch: Partial<{ date_from: string; date_to: string }>): void {
    this.params.update((p) => ({ ...p, ...patch }));
    this.data.reload();
  }

  printPage(): void {
    window.print();
  }
}

function cleanParams(params: Record<string, string>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== '') {
      result[key] = value;
    }
  }
  return result;
}

function toIsoDate(date: Date): string {
  const copy = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return copy.toISOString().slice(0, 10);
}
