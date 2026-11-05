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
import { DataTableComponent, TableBodyDirective, TableColDirective } from '@shared/ui/data-table/data-table.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, Lookups } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface ItemsData {
  summary: { total_amount: string; count: number };
  items: { item: string; category: string | null; count: number; total: string; average: string; percentage: number }[];
}

/** Expense items report (plan 20, T3-110): Blade filters, cards, table + pie chart.
 *  T4-20/21/27: async-page states + static ledger table in the shared layout. */
@Component({
  selector: 'app-items-report',
  standalone: true,
  imports: [
    FieldComponent, TranslatePipe, FormsModule, ButtonModule, DatePickerModule, SelectModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableBodyDirective, TableColDirective,
    PrintSheetComponent, StatTileComponent, ChartCardComponent, MoneyPipe,
  ],
  template: `
    <app-print-sheet orientation="landscape" [title]="'report.items.title' | translate">
      <app-page-header [title]="'report.items.title' | translate">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="filter-bar">
            <div class="filter-bar__row">
              <app-field label="report.items.filter_category" controlId="items-filter-category">
                <p-select appendTo="body" inputId="items-filter-category" name="category"
                  [options]="categoryOptions()" optionLabel="label" optionValue="value" [filter]="true"
                  [ngModel]="params().category_id" (ngModelChange)="setParams({ category_id: $event })"
                  [ariaLabel]="'report.items.filter_category' | translate"
                />
              </app-field>
              <app-field label="report.filters.range" controlId="items-filter-date">
                <p-datepicker appendTo="body" inputId="items-filter-date" name="date" selectionMode="range"
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
            <app-stat-tile [label]="'report.items.items_count'" [value]="d.items.length" />
            <app-stat-tile [label]="'report.items.average'" [value]="average()" [money]="true" />
          </div>

          <div class="u-mt-4">
            <app-data-table mode="static" [autoFilters]="true" [columns]="cols" [rows]="d.items" [emptyLabel]="'report.items.empty'">
              <ng-template appTableBody let-row>
                <tr class="ledger-table__row">
                  <td appTableCol="item">{{ row.item }}</td>
                  <td appTableCol="category">{{ row.category ?? '—' }}</td>
                  <td appTableCol="count" class="u-bidi">{{ row.count }}</td>
                  <td appTableCol="total"><span class="u-bidi">{{ row.total | money }}</span></td>
                  <td appTableCol="average"><span class="u-bidi">{{ row.average | money }}</span></td>
                  <td appTableCol="percentage" class="u-bidi">{{ row.percentage }}%</td>
                </tr>
              </ng-template>
            </app-data-table>
          </div>

          @if (d.items.length > 0) {
            <div class="kpi-grid u-mt-4">
              <app-chart-card [title]="'report.items.chart'" [series]="itemsSeries()" />
            </div>
          }
        }
        <div empty>
          <app-empty-state [title]="'report.items.empty'" [text]="'common.no_data'" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ItemsReportPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  params = signal<{ category_id: number | null; date_from: string; date_to: string }>({
    category_id: null, date_from: '', date_to: '',
  });

  data = resource<ItemsData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<ItemsData>>('/reports/expense-items', {
          params: cleanParams(this.params()),
        }),
      ).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.items ?? []).length === 0;

  readonly cols = [
    { field: 'item', header: 'report.items.col_item' },
    { field: 'category', header: 'report.items.col_category' },
    { field: 'count', header: 'report.items.col_count', numeric: true },
    { field: 'total', header: 'report.items.col_total', numeric: true },
    { field: 'average', header: 'report.items.col_average', numeric: true },
    { field: 'percentage', header: 'report.items.col_percent', numeric: true },
  ];

  /** reference data for the category filter (cacheable endpoint) */
  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
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

  dateRange = signal<Date[]>([]);

  itemsSeries = computed(() =>
    (this.data.value()?.items ?? [])
      .slice(0, 6)
      .map((row) => ({ label: row.item, value: parseFloat(row.total) || 0 })),
  );

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
    this.setParams({ category_id: null, date_from: '', date_to: '' });
  }

  setParams(patch: Partial<{ category_id: number | null; date_from: string; date_to: string }>): void {
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
