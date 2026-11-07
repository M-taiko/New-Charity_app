// hr.kpi.title (plan 20). T4-20: async-page states; T4-23: dialog fields via
// app-field.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ListResponse, ActionResponse } from '@shared/models/api.models';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';

interface Row {
  id: number;
  employee: string | null;
  name: string;
  target: string;
  actual: string;
  percentage: number;
  period_start: string | null;
}

/** hr.kpi.title (plan 20). */
@Component({
  selector: 'app-kpi-page',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe, FormsModule, ButtonModule,
    InputTextModule, SelectModule, InputNumberModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableCellDirective, 
    EmptyStateComponent, MoneyPipe, FormDialogComponent, FieldComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'hr.kpi.title' | translate" accent="hr" iconName="briefcase">
      <div actions><p-button [label]="'hr.kpi.add' | translate" size="small" (onClick)="dialogOpen.set(true)" /></div>
    </app-page-header>

    <app-section-nav module="hr" />

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as page) {
        <app-data-table listUrl="/hr/kpi"
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appCell="employee" let-row>{{ row.employee ?? '—' }}</ng-template>
          <ng-template appCell="name" let-row>{{ row.name ?? '—' }}</ng-template>
          <ng-template appCell="target" let-row><span class="u-bidi">{{ row.target | money }}</span></ng-template>
          <ng-template appCell="actual" let-row><span class="u-bidi">{{ row.actual | money }}</span></ng-template>
          <ng-template appCell="percentage" let-row>{{ row.percentage ?? '—' }}</ng-template>
          <div empty>
            <app-empty-state [title]="'hr.kpi.empty' | translate" [text]="'common.no_data' | translate" accent="hr" iconName="briefcase" />
          </div>
        </app-data-table>
      }
    </app-async-page>

    <app-form-dialog [header]="'hr.kpi.add' | translate" [submitLabel]="'common.save' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <app-field label="hr.employees.user" controlId="kpi-emp" [required]="true">
          <p-select appendTo="body" inputId="kpi-emp" [options]="employees" optionLabel="name" optionValue="id" [(ngModel)]="form.employee_id" name="employee_id" styleClass="u-full" />
        </app-field>
        <app-field label="hr.kpi.name" controlId="kpi-name" [required]="true">
          <input pInputText id="kpi-name" [(ngModel)]="form.metric_name" name="metric_name" class="u-full" />
        </app-field>
        <app-field label="hr.kpi.target" controlId="kpi-target" [required]="true">
          <p-inputnumber inputId="kpi-target" [(ngModel)]="form.target_value" name="target_value" mode="decimal" styleClass="u-full" />
        </app-field>
        <app-field label="hr.kpi.actual" controlId="kpi-actual" [required]="true">
          <p-inputnumber inputId="kpi-actual" [(ngModel)]="form.actual_value" name="actual_value" mode="decimal" styleClass="u-full" />
        </app-field>
      </div>
    </app-form-dialog>
  `,
})
export class KpiPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);

  readonly tableKey = 'kpi.component';

  readonly columns = [
    { field: 'employee', header: 'hr.kpi.col_employee', sortable: true },
    { field: 'name', header: 'hr.kpi.col_name', sortable: true },
    { field: 'target', header: 'hr.kpi.col_target', sortable: true, numeric: true },
    { field: 'actual', header: 'hr.kpi.col_actual', sortable: true, numeric: true },
    { field: 'percentage', header: 'hr.kpi.col_percent', sortable: true }
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/hr/kpi', { params: cleanParams(this.params()) })),
  });

  summaryValue(key: string): string {
    const value = this.data.value()?.meta.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  setParam(key: string, value: string): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }


  dialogOpen = signal(false);
  sending = signal(false);
  form = { employee_id: null as number | null, metric_name: '', target_value: null as number | null, actual_value: null as number | null, period_start: new Date().toISOString().slice(0, 10), period_end: new Date().toISOString().slice(0, 10) };

  employees: { id: number; name: string }[] = [];
  constructor() {
    void firstValueFrom(this.http.get<ListResponse<{ id: number; name: string | null }>>('/hr/employees', { params: { per_page: '100' } }))
      .then((page) => (this.employees = page.data.map((e) => ({ id: e.id, name: e.name ?? '#' + e.id }))));
  }

  submit(): void {
    if (this.form.employee_id === null || this.form.metric_name === '') { return; }
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/hr/kpi', this.form)).then((res) => {
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
