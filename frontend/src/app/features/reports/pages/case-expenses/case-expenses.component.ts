import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, ListResponse, Lookups } from '@shared/models/api.models';

interface CaseExpensesData {
  summary: { total_amount: string; count: number };
  by_category: { category: string; count: number; total: string; percentage: number }[];
  expenses: {
    id: number; case: string | null; user: string | null; item: string | null;
    category: string | null; amount: string; date: string | null; description: string | null;
  }[];
}

/**
 * Social case expenses report (plan 20, T3-110): Blade parity — the case /
 * category / date filters, the average card and the detailed expenses table.
 */
@Component({
  selector: 'app-case-expenses-report',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, DatePickerModule, SelectModule,
    PageHeaderComponent, PrintSheetComponent, StatTileComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.case_expenses.title' | translate">
        <app-page-header [title]="'report.case_expenses.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>

        <div class="filter-bar">
          <div class="filter-bar__row">
            <div class="field">
              <label class="field__label" for="case-exp-filter-case">{{ 'report.case_expenses.filter_case' | translate }}</label>
              <p-select appendTo="body" inputId="case-exp-filter-case" name="case"
                [options]="caseOptions()" optionLabel="label" optionValue="value" [filter]="true"
                [ngModel]="params().social_case_id" (ngModelChange)="setParams({ social_case_id: $event })"
                [ariaLabel]="'report.case_expenses.filter_case' | translate"
              />
            </div>
            <div class="field">
              <label class="field__label" for="case-exp-filter-category">{{ 'report.case_expenses.filter_category' | translate }}</label>
              <p-select appendTo="body" inputId="case-exp-filter-category" name="category"
                [options]="categoryOptions()" optionLabel="label" optionValue="value" [filter]="true"
                [ngModel]="params().category_id" (ngModelChange)="setParams({ category_id: $event })"
                [ariaLabel]="'report.case_expenses.filter_category' | translate"
              />
            </div>
            <div class="field">
              <label class="field__label" for="case-exp-filter-date">{{ 'report.filters.range' | translate }}</label>
              <p-datepicker appendTo="body" inputId="case-exp-filter-date" name="date" selectionMode="range"
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
          <app-stat-tile [label]="'common.total'" [value]="d.summary.total_amount" [money]="true" />
          <app-stat-tile [label]="'common.count'" [value]="d.summary.count" />
          <app-stat-tile [label]="'report.case_expenses.average'" [value]="average()" [money]="true" />
        </div>

        <div class="ledger-table u-mt-4">
          <div class="ledger-table__header">
            <span class="ledger-table__title">{{ 'report.case_expenses.col_category' | translate }}</span>
          </div>
          <table>
            <thead><tr>
              <th>{{ 'report.case_expenses.col_category' | translate }}</th>
              <th>{{ 'report.case_expenses.col_count' | translate }}</th>
              <th>{{ 'report.case_expenses.col_total' | translate }}</th>
              <th>{{ 'report.case_expenses.col_percent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.by_category; track row.category) {
                <tr class="ledger-table__row">
                  <td>{{ row.category }}</td>
                  <td class="ledger-table__cell-num">{{ row.count }}</td>
                  <td class="ledger-table__cell-num">{{ row.total | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.percentage }}%</td>
                </tr>
              } @empty {
                <tr><td colspan="4" class="u-muted">{{ 'report.case_expenses.empty' | translate }}</td></tr>
              }
            </tbody>
          </table>
        </div>

        <div class="ledger-table u-mt-4">
          <table>
            <thead><tr>
              <th>{{ 'report.case_expenses.col_date' | translate }}</th>
              <th>{{ 'report.case_expenses.col_case' | translate }}</th>
              <th>{{ 'report.case_expenses.col_category' | translate }}</th>
              <th>{{ 'report.case_expenses.col_item' | translate }}</th>
              <th>{{ 'report.case_expenses.col_amount' | translate }}</th>
              <th>{{ 'report.case_expenses.col_agent' | translate }}</th>
              <th>{{ 'report.case_expenses.col_description' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.expenses; track row.id) {
                <tr class="ledger-table__row">
                  <td><span class="u-bidi">{{ row.date | datetime }}</span></td>
                  <td>{{ row.case ?? '—' }}</td>
                  <td>{{ row.category ?? '—' }}</td>
                  <td>{{ row.item ?? '—' }}</td>
                  <td class="ledger-table__cell-num">{{ row.amount | money }}</td>
                  <td>{{ row.user ?? '—' }}</td>
                  <td>{{ row.description ?? '—' }}</td>
                </tr>
              } @empty {
                <tr><td colspan="7" class="u-muted">{{ 'report.case_expenses.empty' | translate }}</td></tr>
              }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CaseExpensesPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  params = signal<{ social_case_id: number | null; category_id: number | null; date_from: string; date_to: string }>({
    social_case_id: null, category_id: null, date_from: '', date_to: '',
  });

  data = resource<CaseExpensesData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<CaseExpensesData>>('/reports/social-case-expenses', {
          params: cleanParams(this.params()),
        }),
      ).then((r) => r.data),
  });

  /** reference data for the category filter (cacheable endpoint) */
  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
  });

  /** case options for the social-case filter (approved + all registered cases) */
  cases = resource<{ id: number; name: string }[], unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ListResponse<{ id: number; name: string }>>('/social-cases', { params: { per_page: 100 } }),
      )
        .then((r) => r.data)
        .catch(() => [] as { id: number; name: string }[]),
  });

  categoryOptions = computed(() => {
    const options: { label: string; value: number }[] = [
      { label: this.translate.instant('report.filters.all'), value: 0 },
    ];
    const walk = (nodes: DirectionNode[]): void => {
      for (const node of nodes) {
        options.push({ label: node.name, value: node.id });
        walk(node.children);
      }
    };
    walk(this.lookups.value()?.direction_tree ?? []);
    return options;
  });

  caseOptions = computed(() => [
    { label: this.translate.instant('report.filters.all'), value: 0 },
    ...(this.cases.value() ?? []).map((c) => ({ label: c.name, value: c.id })),
  ]);

  dateRange = signal<Date[]>([]);

  average = computed(() => {
    const d = this.data.value();
    if (!d || d.summary.count === 0) {
      return '0.00';
    }
    return ((parseFloat(d.summary.total_amount) || 0) / d.summary.count).toFixed(2);
  });

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
    this.setParams({ social_case_id: null, category_id: null, date_from: '', date_to: '' });
  }

  setParams(patch: Partial<{ social_case_id: number | null; category_id: number | null; date_from: string; date_to: string }>): void {
    this.params.update((p) => ({ ...p, ...patch }));
    this.data.reload();
  }

  printPage(): void {
    window.print();
  }
}

function cleanParams(params: Record<string, string | number | null>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value === null || value === 0 || value === '') {
      continue;
    }
    result[key] = typeof value === 'number' ? String(value) : value;
  }
  return result;
}

function toIsoDate(date: Date): string {
  const copy = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return copy.toISOString().slice(0, 10);
}
