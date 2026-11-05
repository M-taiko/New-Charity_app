// Employee list (plan 20) — async-page states (T4-20) + row actions linking
// every employee to their salary history and the edit form (T4-04.4). No
// delete action: no employee-delete endpoint exists in the API.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  name: string | null;
  department: string | null;
  job_title: string | null;
  hire_date: string | null;
  salary: string;
  status: string;
}

/** hr.employees.title (plan 20). */
@Component({
  selector: 'app-employees-page',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, MoneyPipe, RowActionsComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'hr.employees.title' | translate" />

    <app-section-nav module="hr" />

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as page) {
        <app-data-table
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appTableBody let-row>
            <tr class="ledger-table__row">
              <td appTableCol="name">{{ row.name ?? '—' }}</td>
              <td appTableCol="department">{{ row.department ?? '—' }}</td>
              <td appTableCol="job_title">{{ row.job_title ?? '—' }}</td>
              <td appTableCol="salary" class="ledger-table__cell-num"><span class="u-bidi">{{ row.salary | money }}</span></td>
              <td appTableCol="status">{{ row.status ?? '—' }}</td>
              <td appTableCol="actions">
                <app-row-actions [actions]="actionsFor(row)" />
              </td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.employees.col_name' | translate }}</span>
                {{ row.name ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.employees.col_department' | translate }}</span>
                {{ row.department ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.employees.col_title' | translate }}</span>
                {{ row.job_title ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.salaries.detail.base' | translate }}</span>
                <span class="u-bidi">{{ row.salary | money }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.status' | translate }}</span>
                {{ row.status ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.actions' | translate }}</span>
                <app-row-actions [actions]="actionsFor(row)" />
              </div>
            </tr>
          </ng-template>
          <div empty>
            <app-empty-state [title]="'hr.employees.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>
  `,
})
export class EmployeesPage {
  private http = inject(HttpClient);

  readonly tableKey = 'employees.component';

  readonly columns = [
    { field: 'name', header: 'hr.employees.col_name', sortable: true },
    { field: 'department', header: 'hr.employees.col_department', sortable: true },
    { field: 'job_title', header: 'hr.employees.col_title', sortable: true },
    { field: 'salary', header: 'hr.salaries.detail.base', sortable: true },
    { field: 'status', header: 'common.status', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/hr/employees', { params: cleanParams(this.params()) })),
  });

  /** T4-04.4: salary history (real route /hr/employees/:id/salaries) + edit form */
  actionsFor(row: Row): RowActionItem[] {
    return [
      { icon: 'history', label: 'hr.salaries.history_title', route: ['/hr/employees', row.id, 'salaries'] },
      { icon: 'pencil', label: 'common.edit', route: ['/hr/employees', row.id, 'edit'] },
    ];
  }

  summaryValue(key: string): string {
    const value = this.data.value()?.meta.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  setParam(key: string, value: string): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }

  onPage(event: { page: number; per_page: number; sort?: string }): void {
    // p-table re-emits onLazyLoad on every render — only reload on real changes
    const current = this.params();
    const sort = event.sort ?? '';
    if (current.page === event.page && current.per_page === event.per_page && (current as { sort?: string }).sort === sort) {
      return;
    }
    this.params.update((p) => ({ ...p, page: event.page, per_page: event.per_page, sort }));
    this.data.reload();
  }
}

function cleanParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '' && value !== 'all') {
      result[key] = String(value);
    }
  }
  return result;
}
