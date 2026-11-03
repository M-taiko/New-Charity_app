import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { DialogModule } from 'primeng/dialog';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ItemResponse } from '@shared/models/api.models';

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
 */
@Component({
  selector: 'app-directions-report',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, DatePickerModule, DialogModule,
    PageHeaderComponent, PrintSheetComponent, StatTileComponent, ChartCardComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.directions.title' | translate">
        <app-page-header [title]="'report.directions.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>

        <div class="filter-bar">
          <div class="filter-bar__row">
            <div class="field">
              <label class="field__label" for="directions-filter-date">{{ 'report.filters.range' | translate }}</label>
              <p-datepicker appendTo="body" inputId="directions-filter-date" name="date" selectionMode="range"
                [showIcon]="true"
                [ngModel]="dateRange()" (ngModelChange)="onDateRange($event)"
                [ariaLabel]="'report.filters.range' | translate"
              />
            </div>
            <div class="filter-bar__spacer"></div>
            <p-button [label]="'common.reset' | translate" size="small" [outlined]="true" (click)="resetFilters()" />
          </div>
        </div>

        <div class="kpi-grid">
          <app-stat-tile [label]="'common.total'" [value]="d.grand_total" [money]="true" />
          <app-stat-tile [label]="'report.directions.rows_count'" [value]="d.rows.length" />
          <app-stat-tile [label]="'report.directions.operations'" [value]="operationsCount()" />
          <app-stat-tile [label]="'report.directions.col_average'" [value]="averageAmount()" [money]="true" />
        </div>

        <div class="kpi-grid u-mt-4">
          <app-chart-card [title]="'report.directions.charts'" [series]="rootsSeries()" />
        </div>

        <div class="ledger-table u-mt-4">
          <div class="ledger-table__header"><span class="ledger-table__title">{{ 'report.directions.details' | translate }}</span></div>
          <table>
            <thead><tr>
              <th>{{ 'report.directions.col_level' | translate }}</th>
              <th>{{ 'report.directions.col_item' | translate }}</th>
              <th>{{ 'report.directions.col_code' | translate }}</th>
              <th>{{ 'report.directions.col_parent' | translate }}</th>
              <th>{{ 'report.directions.col_total' | translate }}</th>
              <th>{{ 'report.directions.col_count' | translate }}</th>
              <th>{{ 'report.directions.col_average' | translate }}</th>
              <th>{{ 'report.directions.col_reviewer' | translate }}</th>
              <th>{{ 'report.directions.col_percent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.rows; track row.id) {
                <tr class="ledger-table__row" style="cursor: pointer" tabindex="0" role="button"
                  (click)="openDrill(row)" (keydown.enter)="openDrill(row)"
                  [attr.aria-label]="'report.directions.expenses_dialog' | translate">
                  <td><span class="stamp">{{ levelKey(row.level) | translate }}</span></td>
                  <td>{{ row.name }}</td>
                  <td class="u-bidi">{{ row.code ?? '—' }}</td>
                  <td>{{ row.parent_name ?? '—' }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_amount | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.expense_count }}</td>
                  <td class="ledger-table__cell-num">{{ row.average_amount | money }}</td>
                  <td>{{ row.reviewed_by ?? '—' }}</td>
                  <td class="ledger-table__cell-num">{{ row.percentage }}%</td>
                </tr>
              } @empty { <tr><td colspan="9" class="u-muted">{{ 'report.directions.empty' | translate }}</td></tr> }
            </tbody>
          </table>
        </div>
      </app-print-sheet>

      <!-- Drill-down: the row's expenses (GET /category-item-expenses) -->
      <p-dialog appendTo="body" [visible]="drillVisible()" (visibleChange)="drillVisible.set($event)"
        [header]="'report.directions.expenses_dialog' | translate" [modal]="true"
        [draggable]="false" [resizable]="false" styleClass="dialog"
        [breakpoints]="{ '576px': '100vw' }" [style]="{ width: 'min(720px, 94vw)' }">
        <p class="u-small u-mt-2">{{ drillTitle() }}</p>
        @if (drillLoading()) {
          <div class="skeleton skeleton--tile" role="status"></div>
        } @else {
          <div class="ledger-table">
            <table>
              <thead><tr>
                <th>{{ 'report.case_expenses.col_date' | translate }}</th>
                <th>{{ 'report.case_expenses.col_description' | translate }}</th>
                <th>{{ 'report.directions.col_user' | translate }}</th>
                <th>{{ 'report.directions.col_reviewer' | translate }}</th>
                <th>{{ 'common.amount' | translate }}</th>
                <th>{{ 'common.actions' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (row of drillRows(); track row.id) {
                  <tr class="ledger-table__row">
                    <td><span class="u-bidi">{{ row.created_at | datetime }}</span></td>
                    <td>{{ row.description ?? '—' }}</td>
                    <td>{{ row.created_by ?? '—' }}</td>
                    <td>{{ row.approved_by ?? '—' }}</td>
                    <td class="ledger-table__cell-num">{{ row.amount | money }}</td>
                    <td>
                      <a [routerLink]="['/expenses', row.id]">{{ 'common.view' | translate }}</a>
                    </td>
                  </tr>
                } @empty {
                  <tr><td colspan="6" class="u-muted">{{ 'report.directions.empty_drill' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
          <p class="u-flex u-flex-between u-mt-2">
            <span>{{ 'report.directions.drill_count' | translate }}: {{ drillRows().length }}</span>
            <span class="u-bidi">{{ 'common.total' | translate }}: {{ drillTotal() | money }}</span>
          </p>
        }
      </p-dialog>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DirectionsReportPage {
  private http = inject(HttpClient);

  params = signal<{ date_from: string; date_to: string }>({ date_from: '', date_to: '' });

  data = resource<AnalyticsData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<AnalyticsData>>('/reports/categories-analytics', {
          params: cleanParams(this.params()),
        }),
      ).then((r) => r.data),
  });

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
