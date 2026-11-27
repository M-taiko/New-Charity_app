// The salary record of every employee — the page the "سجل مرتبات الموظف" tab opens
// (/hr/salary-history). The history of ONE employee stays at /hr/employees/:id/salaries.
// The table asks the server; its filter panel is built from the filters the endpoint declares.
import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { apiFilterDefs, filterParams, type TableQuery } from '@shared/lib/table-filters';
import { reloadList } from '@shared/lib/list-state';
import type { ListResponse } from '@shared/models/api.models';

interface SalaryRow {
  id: number;
  employee: string | null;
  employee_id: number;
  year: number;
  month: number;
  base_salary: string;
  final_salary: string;
  status: string;
}

/** the list's query: a value that is `undefined` was never set */
type Params = Record<string, string | number | undefined>;

@Component({
  selector: 'app-salary-history-list',
  standalone: true,
  imports: [
    SectionNavComponent,
    TranslatePipe,
    PageHeaderComponent,
    AsyncPageComponent,
    DataTableComponent,
    TableCellDirective,
    EmptyStateComponent,
    RowActionsComponent,
    StatusStampComponent,
    MoneyPipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'hr.salaries.history_title' | translate" accent="hr" iconName="briefcase" />

    <app-section-nav module="hr" />

    <app-async-page [resource]="data" variant="list" [empty]="isEmpty">
      @if (data.value(); as page) {
        <app-data-table [phoneFirst]="['employee', 'year', 'month', 'final_salary']"
          [exportQuery]="tableExportQuery()" exportPath="/hr/salary-history/export"
          [tableKey]="tableKey"
          [savedViews]="true"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [sortableKeys]="page.meta.sortable ?? null"
          [filters]="filterDefs()"
          [perPage]="pageSize()"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
          (queryChange)="onQuery($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appCell="employee" let-row>{{ row.employee ?? '—' }}</ng-template>
          <ng-template appCell="year" let-row><span class="u-bidi">{{ row.year }}</span></ng-template>
          <ng-template appCell="month" let-row><span class="u-bidi">{{ row.month }}</span></ng-template>
          <ng-template appCell="base_salary" let-row><span class="u-bidi">{{ row.base_salary | money: false }}</span></ng-template>
          <ng-template appCell="final_salary" let-row><span class="u-bidi">{{ row.final_salary | money: false }}</span></ng-template>
          <ng-template appCell="status" let-row><app-status-stamp [status]="row.status" namespace="salary" /></ng-template>
          <ng-template appCell="actions" let-row><app-row-actions [actions]="actionsFor(row)" /></ng-template>
          <div empty>
            <app-empty-state
              [title]="'hr.salaries.history_empty' | translate"
              [hint]="'hr.salaries.history_empty_hint' | translate"
              [actionLabel]="'hr.salaries.title' | translate"
              [actionRoute]="['/hr/salaries']"
              accent="hr" iconName="briefcase"
            />
          </div>
        </app-data-table>
      }
    </app-async-page>
  `,
})
export class SalaryHistoryListPage {
  private http = inject(HttpClient);

  readonly tableKey = 'salary-history-list.component';

  readonly columns = [
    { field: 'employee', header: 'hr.salaries.col_name', sortable: true },
    { field: 'year', header: 'hr.salaries.year', sortable: true, numeric: true },
    { field: 'month', header: 'hr.salaries.month', sortable: true, numeric: true },
    { field: 'base_salary', header: 'hr.salaries.col_base', sortable: false, numeric: true },
    { field: 'final_salary', header: 'hr.salaries.col_final', sortable: true, numeric: true },
    { field: 'status', header: 'hr.salaries.col_status', sortable: false },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  /** the list asks with exactly this: the page, the sort, the search and the table's own filters */
  params = signal<Params>({ page: 1, per_page: 25 });

  data = resource<ListResponse<SalaryRow>, Params>({
    loader: () => firstValueFrom(this.http.get<ListResponse<SalaryRow>>('/hr/salary-history', { params: cleanParams(this.params()) })),
  });

  /** the endpoint's declared filters drive the table's Filters control */
  readonly filterDefs = computed(() => apiFilterDefs(this.data.value()?.meta));

  /** the page size the table shows: a number, whatever its text in the params */
  readonly pageSize = computed(() => Number(this.params().per_page) || 25);

  /**
   * N-08: the page's empty slot is for a list that never had a record — empty AND
   * unfiltered. An empty answer under the search or a filter is "no results for
   * this filter", which the table itself says (with its "clear the filters"):
   * that state lives inside the table, so taking the table away here left the
   * page blank.
   */
  isEmpty = (): boolean => !this.isFiltered() && (this.data.value()?.data ?? []).length === 0;

  /** the search box or one of the table's filters is set */
  private isFiltered(): boolean {
    const params = this.params();
    if (String(params.q ?? '') !== '') {
      return true;
    }
    return Object.keys(params).some((key) => key.startsWith('filter[') && String(params[key] ?? '') !== '');
  }

  /** the export replays EXACTLY the params this list was fetched with (minus paging) */
  tableExportQuery(): Record<string, string> {
    const query = cleanParams(this.params());
    delete query.page;
    delete query.per_page;
    return query;
  }

  /** the record's page in "حسابات المرتبات" */
  actionsFor(row: SalaryRow): RowActionItem[] {
    return [{ icon: 'eye', label: 'common.view', route: ['/hr/salaries', row.id] }];
  }

  /** paging and sorting: the search and the filters stay as they were */
  onPage(event: { page: number; per_page: number; sort?: string }): void {
    const current = this.params();
    this.ask({ ...current, page: event.page, per_page: event.per_page, sort: event.sort ?? '' });
  }

  /** the table's whole query: paging, the sort, the search box and the filter panel — its values
   *  travel as filter[key]=…, the server contract's form. The table reports one change twice,
   *  once per event, so what is already asked is not asked again. */
  onQuery(query: TableQuery): void {
    const filters = filterParams(query.filters);
    for (const key of Object.keys(this.params())) {
      if (key.startsWith('filter[') && !(key in filters)) {
        filters[key] = '';
      }
    }
    this.ask({ page: query.page, per_page: query.perPage, sort: query.sort, q: query.q, ...filters });
  }

  private ask(next: Params): void {
    if (sameParams(next, this.params())) {
      return;
    }
    this.params.set(next);
    reloadList(this.data);
  }
}

function cleanParams(params: Params): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      result[key] = String(value);
    }
  }
  return result;
}

function sameParams(a: Params, b: Params): boolean {
  const keys = new Set([...Object.keys(a), ...Object.keys(b)]);
  for (const key of keys) {
    if (String(a[key] ?? '') !== String(b[key] ?? '')) {
      return false;
    }
  }
  return true;
}
