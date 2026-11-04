import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { LedgerTableComponent, LedgerBodyDirective, LedgerColDirective } from '@shared/ui/ledger-table/ledger-table.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, ListResponse, Lookups } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

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
 * T4-20/21/27: async-page states + static ledger tables in the shared layout.
 */
@Component({
  selector: 'app-case-expenses-report',
  standalone: true,
  imports: [
    FieldComponent, TranslatePipe, FormsModule, ButtonModule, DatePickerModule, SelectModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    LedgerTableComponent, LedgerBodyDirective, LedgerColDirective,
    PrintSheetComponent, StatTileComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    <app-print-sheet [title]="'report.case_expenses.title' | translate">
      <app-page-header [title]="'report.case_expenses.title' | translate">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="filter-bar">
            <div class="filter-bar__row">
              <app-field label="report.case_expenses.filter_case" controlId="case-exp-filter-case">
                <p-select appendTo="body" inputId="case-exp-filter-case" name="case"
                  [options]="caseOptions()" optionLabel="label" optionValue="value" [filter]="true"
                  [ngModel]="params().social_case_id" (ngModelChange)="setParams({ social_case_id: $event })"
                  [ariaLabel]="'report.case_expenses.filter_case' | translate"
                />
              </app-field>
              <app-field label="report.case_expenses.filter_category" controlId="case-exp-filter-category">
                <p-select appendTo="body" inputId="case-exp-filter-category" name="category"
                  [options]="categoryOptions()" optionLabel="label" optionValue="value" [filter]="true"
                  [ngModel]="params().category_id" (ngModelChange)="setParams({ category_id: $event })"
                  [ariaLabel]="'report.case_expenses.filter_category' | translate"
                />
              </app-field>
              <app-field label="report.filters.range" controlId="case-exp-filter-date">
                <p-datepicker appendTo="body" inputId="case-exp-filter-date" name="date" selectionMode="range"
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
            <app-stat-tile [label]="'common.total'" [value]="d.summary.total_amount" [money]="true" />
            <app-stat-tile [label]="'common.count'" [value]="d.summary.count" />
            <app-stat-tile [label]="'report.case_expenses.average'" [value]="average()" [money]="true" />
          </div>

          <div class="u-mt-4">
            <app-ledger-table [title]="'report.case_expenses.col_category'"
              [columns]="categoryCols" [rows]="d.by_category" [emptyLabel]="'report.case_expenses.empty'">
              <ng-template appLedgerBody let-row>
                <tr class="ledger-table__row">
                  <td appLedgerCol="category">{{ row.category }}</td>
                  <td appLedgerCol="count" class="u-bidi">{{ row.count }}</td>
                  <td appLedgerCol="total"><span class="u-bidi">{{ row.total | money }}</span></td>
                  <td appLedgerCol="percentage" class="u-bidi">{{ row.percentage }}%</td>
                </tr>
              </ng-template>
            </app-ledger-table>
          </div>

          <div class="u-mt-4">
            <app-ledger-table [columns]="expenseCols" [rows]="d.expenses" [emptyLabel]="'report.case_expenses.empty'">
              <ng-template appLedgerBody let-row>
                <tr class="ledger-table__row">
                  <td appLedgerCol="date"><span class="u-bidi">{{ row.date | datetime }}</span></td>
                  <td appLedgerCol="case">{{ row.case ?? '—' }}</td>
                  <td appLedgerCol="category">{{ row.category ?? '—' }}</td>
                  <td appLedgerCol="item">{{ row.item ?? '—' }}</td>
                  <td appLedgerCol="amount"><span class="u-bidi">{{ row.amount | money }}</span></td>
                  <td appLedgerCol="user">{{ row.user ?? '—' }}</td>
                  <td appLedgerCol="description">{{ row.description ?? '—' }}</td>
                </tr>
              </ng-template>
            </app-ledger-table>
          </div>
        }
        <div empty>
          <app-empty-state [title]="'report.case_expenses.empty'" [text]="'common.no_data'" />
        </div>
      </app-async-page>
    </app-print-sheet>
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

  isEmpty = (): boolean => (this.data.value()?.expenses ?? []).length === 0;

  readonly categoryCols = [
    { field: 'category', header: 'report.case_expenses.col_category' },
    { field: 'count', header: 'report.case_expenses.col_count', numeric: true },
    { field: 'total', header: 'report.case_expenses.col_total', numeric: true },
    { field: 'percentage', header: 'report.case_expenses.col_percent', numeric: true },
  ];

  readonly expenseCols = [
    { field: 'date', header: 'report.case_expenses.col_date' },
    { field: 'case', header: 'report.case_expenses.col_case' },
    { field: 'category', header: 'report.case_expenses.col_category' },
    { field: 'item', header: 'report.case_expenses.col_item' },
    { field: 'amount', header: 'report.case_expenses.col_amount', numeric: true },
    { field: 'user', header: 'report.case_expenses.col_agent' },
    { field: 'description', header: 'report.case_expenses.col_description' },
  ];

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
