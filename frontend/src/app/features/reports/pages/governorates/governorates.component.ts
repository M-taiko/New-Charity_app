import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
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
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, Lookups } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface GovRow {
  label: string;
  standard: boolean;
  total: number; approved: number; pending: number; rejected: number;
  expenses_total: string;
  average: string;
}

interface GovData {
  governorates: GovRow[];
  grand_total: { total: number; approved: number; pending: number; rejected: number; expenses_total: string; average: string };
}

/**
 * Governorates report (T34, plan 20 — new page): one row per governorate.
 * Date range + direction filters (T3-110) mirror reports/governorates.blade.php —
 * they affect the expense amounts only, never the case counts.
 * T4-20/21/27: async-page states + static ledger table in the shared layout.
 */
@Component({
  selector: 'app-governorates-report',
  standalone: true,
  imports: [
    FieldComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, DatePickerModule, SelectModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableBodyDirective, TableColDirective,
    PrintSheetComponent, StatTileComponent, MoneyPipe,
  ],
  template: `
    <app-print-sheet orientation="landscape" [title]="'report.governorates.title' | translate">
      <app-page-header [title]="'report.governorates.title' | translate">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="filter-bar">
            <div class="filter-bar__row">
              <app-field label="report.filters.direction" controlId="gov-filter-direction">
                <p-select appendTo="body" inputId="gov-filter-direction" name="direction"
                  [options]="directionOptions()" optionLabel="label" optionValue="value"
                  [ngModel]="direction()" (ngModelChange)="onDirection($event)"
                  [ariaLabel]="'report.filters.direction' | translate"
                />
              </app-field>
              <app-field label="report.filters.range" controlId="gov-filter-date">
                <p-datepicker appendTo="body" inputId="gov-filter-date" name="date" selectionMode="range"
                  [showIcon]="true"
                  [ngModel]="dateRange()" (ngModelChange)="onDateRange($event)"
                  [ariaLabel]="'report.filters.range' | translate"
                />
              </app-field>
              <div class="filter-bar__spacer"></div>
              <p-button [label]="'common.reset' | translate" size="small" [outlined]="true" (click)="resetFilters()" />
            </div>
            <p class="u-muted u-small">{{ 'report.governorates.filter_note' | translate }}</p>
          </div>

          <div class="kpi-grid">
            <app-stat-tile [label]="'report.governorates.col_total'" [value]="d.grand_total.total" />
            <app-stat-tile [label]="'report.governorates.col_approved'" [value]="d.grand_total.approved" />
            <app-stat-tile [label]="'report.governorates.col_pending'" [value]="d.grand_total.pending" />
            <app-stat-tile [label]="'report.governorates.col_rejected'" [value]="d.grand_total.rejected" />
            <app-stat-tile [label]="'report.governorates.col_spent'" [value]="d.grand_total.expenses_total" [money]="true" />
          </div>

          <p class="u-muted u-small u-mb-4">{{ 'report.governorates.click_hint' | translate }}</p>
          <app-data-table mode="static" [autoFilters]="true" [columns]="cols" [rows]="d.governorates" [emptyLabel]="'report.governorates.empty'">
            <ng-template appTableBody let-row>
              <tr class="ledger-table__row">
                <td appTableCol="label">
                  <a [routerLink]="['/cases']" [queryParams]="{ governorate: row.label }">{{ row.label }}</a>
                  @if (!row.standard) {
                    <span class="stamp">{{ 'report.governorates.non_standard' | translate }}</span>
                  }
                </td>
                <td appTableCol="total" class="u-bidi">{{ row.total }}</td>
                <td appTableCol="approved" class="u-bidi">{{ row.approved }}</td>
                <td appTableCol="pending" class="u-bidi">{{ row.pending }}</td>
                <td appTableCol="rejected" class="u-bidi">{{ row.rejected }}</td>
                <td appTableCol="expenses_total"><span class="u-bidi">{{ row.expenses_total | money }}</span></td>
                <td appTableCol="average"><span class="u-bidi">{{ row.average | money }}</span></td>
              </tr>
            </ng-template>
            <ng-template footer>
              <tr class="ledger-table__row">
                <th>{{ 'common.total' | translate }}</th>
                <td class="ledger-table__cell-num u-bidi">{{ d.grand_total.total }}</td>
                <td class="ledger-table__cell-num u-bidi">{{ d.grand_total.approved }}</td>
                <td class="ledger-table__cell-num u-bidi">{{ d.grand_total.pending }}</td>
                <td class="ledger-table__cell-num u-bidi">{{ d.grand_total.rejected }}</td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ d.grand_total.expenses_total | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ d.grand_total.average | money }}</span></td>
              </tr>
            </ng-template>
          </app-data-table>
        }
        <div empty>
          <app-empty-state [title]="'report.governorates.empty'" [text]="'common.no_data'" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GovernoratesPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  params = signal<{ direction: string | null; item: string | null; date_from: string; date_to: string }>({
    direction: null, item: null, date_from: '', date_to: '',
  });

  data = resource<GovData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<GovData>>('/reports/governorates', { params: cleanParams(this.params()) }),
      ).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.governorates ?? []).length === 0;

  readonly cols = [
    { field: 'label', header: 'report.governorates.col_governorate' },
    { field: 'total', header: 'report.governorates.col_total', numeric: true },
    { field: 'approved', header: 'report.governorates.col_approved', numeric: true },
    { field: 'pending', header: 'report.governorates.col_pending', numeric: true },
    { field: 'rejected', header: 'report.governorates.col_rejected', numeric: true },
    { field: 'expenses_total', header: 'report.governorates.col_spent', numeric: true },
    { field: 'average', header: 'report.governorates.col_average', numeric: true },
  ];

  /** reference data for the direction filter (cacheable endpoint) */
  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
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

  /** UI-only models for controls whose raw value isn't a query param */
  direction = signal('all');
  dateRange = signal<Date[]>([]);

  onDirection(value: string): void {
    this.direction.set(value);
    if (value === 'all') {
      this.setParams({ direction: null, item: null });
    } else if (value.startsWith('c:')) {
      this.setParams({ direction: value.slice(2), item: null });
    } else if (value.startsWith('i:')) {
      this.setParams({ direction: null, item: value.slice(2) });
    }
  }

  /** apply the picked range once both ends are chosen (or when cleared) */
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
    this.direction.set('all');
    this.dateRange.set([]);
    this.setParams({ direction: null, item: null, date_from: '', date_to: '' });
  }

  private setParams(patch: Partial<{ direction: string | null; item: string | null; date_from: string; date_to: string }>): void {
    this.params.update((p) => ({ ...p, ...patch }));
    this.data.reload();
  }

  printPage(): void {
    window.print();
  }
}

function cleanParams(params: Record<string, string | null>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== '') {
      result[key] = value;
    }
  }
  return result;
}

function toIsoDate(date: Date): string {
  const copy = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return copy.toISOString().slice(0, 10);
}
