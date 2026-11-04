// hr.attendance.title (plan 20). T3-91: month/year filter (defaults = the
// current month/year) reloading the list through the API's month & year query
// params (Api\V1\HrController@attendance). The Blade "delete employee" button
// is a stub with no route and is intentionally NOT implemented.
// T4-20: async-page states; T4-23: dialog fields via app-field.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import type { ListResponse, ActionResponse } from '@shared/models/api.models';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';

interface Row {
  id: number;
  employee: string | null;
  date: string | null;
  check_in: string | null;
  check_out: string | null;
  status: string;
}

/** hr.attendance.title (plan 20). */
@Component({
  selector: 'app-attendance-page',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, SelectModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, DatetimePipe, EnumLabelPipe,
    FormDialogComponent, FieldComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'hr.attendance.title' | translate">
      <div actions><p-button [label]="'hr.attendance.add' | translate" size="small" (onClick)="dialogOpen.set(true)" /></div>
    </app-page-header>

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
          <div toolbar class="u-flex u-gap-2 u-flex-wrap">
            <app-field label="hr.attendance.month" controlId="att-filter-month" class="u-m-0">
              <p-select inputId="att-filter-month" [options]="monthOptions" optionLabel="label" optionValue="value" [ngModel]="params().month" name="month" (ngModelChange)="setParam('month', $event)" styleClass="u-full" />
            </app-field>
            <app-field label="hr.attendance.year" controlId="att-filter-year" class="u-m-0">
              <p-select inputId="att-filter-year" [options]="yearOptions" optionLabel="label" optionValue="value" [ngModel]="params().year" name="year" (ngModelChange)="setParam('year', $event)" styleClass="u-full" />
            </app-field>
          </div>
          <ng-template appTableBody let-row>
            <tr class="ledger-table__row">
              <td appTableCol="employee">{{ row.employee ?? '—' }}</td>
              <td appTableCol="date"><span class="u-bidi">{{ row.date | datetime }}</span></td>
              <td appTableCol="check_in"><span class="u-bidi">{{ row.check_in ?? '—' }}</span></td>
              <td appTableCol="check_out"><span class="u-bidi">{{ row.check_out ?? '—' }}</span></td>
              <td appTableCol="status"><span class="stamp">{{ row.status | enumLabel: 'attendance' }}</span></td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.employees.col_name' | translate }}</span>
                {{ row.employee ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.date' | translate }}</span>
                <span class="u-bidi">{{ row.date | datetime }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.attendance.check_in' | translate }}</span>
                <span class="u-bidi">{{ row.check_in ?? '—' }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'hr.attendance.check_out' | translate }}</span>
                <span class="u-bidi">{{ row.check_out ?? '—' }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.status' | translate }}</span>
                {{ row.status | enumLabel: 'attendance' }}
              </div>
            </tr>
          </ng-template>
          <div empty>
            <app-empty-state [title]="'hr.attendance.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>

    <app-form-dialog [header]="'hr.attendance.add' | translate" [submitLabel]="'common.save' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <app-field label="hr.employees.user" controlId="att-emp" [required]="true">
          <p-select appendTo="body" inputId="att-emp" [options]="employees" optionLabel="name" optionValue="id" [(ngModel)]="form.employee_id" name="employee_id" styleClass="u-full" />
        </app-field>
        <app-field label="hr.attendance.status" controlId="att-status" [required]="true">
          <p-select appendTo="body" inputId="att-status" [options]="statusOptions" optionLabel="label" optionValue="value" [(ngModel)]="form.status" name="status" styleClass="u-full" />
        </app-field>
      </div>
    </app-form-dialog>
  `,
})
export class AttendancePage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  readonly tableKey = 'attendance.component';

  readonly columns = [
    { field: 'employee', header: 'hr.employees.col_name', sortable: true },
    { field: 'date', header: 'common.date', sortable: true },
    { field: 'check_in', header: 'hr.attendance.check_in', sortable: true },
    { field: 'check_out', header: 'hr.attendance.check_out', sortable: true },
    { field: 'status', header: 'common.status', sortable: true }
  ];

  /** T3-91: month/year filter — defaults to the current month, reloads on change */
  params = signal({ page: 1, per_page: 25, q: '', status: 'all', month: new Date().getMonth() + 1, year: new Date().getFullYear() });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string; month: number; year: number }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/hr/attendance', { params: cleanParams(this.params()) })),
  });

  readonly monthOptions = Array.from({ length: 12 }, (_, i) => ({ label: String(i + 1), value: i + 1 }));
  readonly yearOptions = Array.from({ length: 4 }, (_, i) => ({ label: String(new Date().getFullYear() - 2 + i), value: new Date().getFullYear() - 2 + i }));

  summaryValue(key: string): string {
    const value = this.data.value()?.meta.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  setParam(key: string, value: string | number): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }


  dialogOpen = signal(false);
  sending = signal(false);
  form = { employee_id: null as number | null, date: new Date().toISOString().slice(0, 10), status: 'present' };

  statusOptions = (['present', 'absent', 'late', 'leave'] as const).map((value) => ({
    label: this.translate.instant(`enums.attendance.${value}`),
    value,
  }));
  employees: { id: number; name: string }[] = [];
  constructor() {
    void firstValueFrom(this.http.get<ListResponse<{ id: number; name: string | null }>>('/hr/employees', { params: { per_page: '100' } }))
      .then((page) => (this.employees = page.data.map((e) => ({ id: e.id, name: e.name ?? '#' + e.id }))));
  }

  submit(): void {
    if (this.form.employee_id === null) { return; }
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/hr/attendance', this.form)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.dialogOpen.set(false);
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
    });
  }
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
