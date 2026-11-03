// hr.salaries.title (plan 20). T3-90: month/year list filters (as in
// hr/salaries/index.blade.php) + the salaries-report CSV export button.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import {HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import type { ListResponse, ActionResponse } from '@shared/models/api.models';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { InputNumberModule } from 'primeng/inputnumber';

interface Row {
  id: number;
  employee: string | null;
  year: number;
  month: number;
  base_salary: string;
  final_salary: string;
  status: string;
}

/** hr.salaries.title (plan 20). */
@Component({
  selector: 'app-salaries-page',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, SelectModule,
    PageHeaderComponent, StatTileComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, MoneyPipe, StatusStampComponent,
    FormDialogComponent, InputNumberModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'hr.salaries.title' | translate">
      <div actions class="u-flex u-gap-2 u-flex-wrap">
        <p-button [label]="'hr.salaries.export' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="exportReport()" />
        <p-button [label]="'hr.salaries.calculate' | translate" size="small" (onClick)="dialogOpen.set(true)" />
      </div>
    </app-page-header>

    @if (data.isLoading() && !data.value()) {
      <div class="kpi-grid">@for (s of [1, 2, 3, 4]; track s) {<div class="skeleton skeleton--tile"></div>}</div>
    } @else if (data.value(); as page) {
        <div class="kpi-grid">
        <app-stat-tile [label]="'hr.salaries.detail.title'" [value]="summaryValue('total_final')" [money]="true" />
        </div>


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
          <div class="field" style="margin: 0">
            <label class="field__label" for="sal-filter-month">{{ 'hr.salaries.month' | translate }}</label>
            <p-select inputId="sal-filter-month" [options]="monthOptions" optionLabel="label" optionValue="value" [ngModel]="params().month" name="month" (ngModelChange)="setParam('month', $event)" styleClass="u-full" />
          </div>
          <div class="field" style="margin: 0">
            <label class="field__label" for="sal-filter-year">{{ 'hr.salaries.year' | translate }}</label>
            <p-select inputId="sal-filter-year" [options]="yearOptions" optionLabel="label" optionValue="value" [ngModel]="params().year" name="year" (ngModelChange)="setParam('year', $event)" styleClass="u-full" />
          </div>
        </div>
        <ng-template appTableBody let-row>
          <tr class="ledger-table__row">
            <td appTableCol="employee">{{ row.employee ?? '—' }}</td>
            <td appTableCol="year">{{ row.year ?? '—' }}</td>
            <td appTableCol="month">{{ row.month ?? '—' }}</td>
            <td appTableCol="base_salary" class="ledger-table__cell-num"><span class="u-bidi">{{ row.base_salary | money }}</span></td>
            <td appTableCol="final_salary" class="ledger-table__cell-num"><span class="u-bidi">{{ row.final_salary | money }}</span></td>
            <td appTableCol="status"><app-status-stamp [status]="row.status ?? ''" namespace="salary" /></td>
          </tr>
          <tr class="ledger-table__stacked">
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.col_name' | translate }}</span>
              {{ row.employee ?? '—' }}
            </div>
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.year' | translate }}</span>
              {{ row.year ?? '—' }}
            </div>
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.month' | translate }}</span>
              {{ row.month ?? '—' }}
            </div>
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.col_base' | translate }}</span>
              <span class="u-bidi">{{ row.base_salary | money }}</span>
            </div>
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.col_final' | translate }}</span>
              <span class="u-bidi">{{ row.final_salary | money }}</span>
            </div>
            <div class="ledger-table__stacked-item">
              <span class="ledger-table__stacked-label">{{ 'hr.salaries.col_status' | translate }}</span>
              <app-status-stamp [status]="row.status ?? ''" namespace="salary" />
            </div>
          </tr>
        </ng-template>
        <div empty>
          <app-empty-state [title]="'hr.salaries.empty' | translate" [text]="'common.no_data' | translate" />
        </div>
      </app-data-table>
    }

    <app-form-dialog [header]="'hr.salaries.calculate' | translate" [submitLabel]="'hr.salaries.calculate' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <div class="field"><label class="field__label" for="sal-year">{{ 'hr.salaries.year' | translate }} *</label>
          <p-inputnumber inputId="sal-year" [(ngModel)]="form.year" name="year" [useGrouping]="false" styleClass="u-full" /></div>
        <div class="field"><label class="field__label" for="sal-month">{{ 'hr.salaries.month' | translate }} *</label>
          <p-inputnumber inputId="sal-month" [(ngModel)]="form.month" name="month" [min]="1" [max]="12" [useGrouping]="false" styleClass="u-full" /></div>
        <div class="field"><label class="field__label">{{ 'hr.salaries.method' | translate }} *</label>
          <p-select appendTo="body" [options]="methodOptions" optionLabel="label" optionValue="value" [(ngModel)]="form.calculation_method" name="calculation_method" styleClass="u-full" /></div>
      </div>
    </app-form-dialog>
  `,
})
export class SalariesPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  readonly tableKey = 'salaries.component';

  readonly columns = [
    { field: 'employee', header: 'hr.salaries.col_name', sortable: true },
    { field: 'year', header: 'hr.salaries.year', sortable: true },
    { field: 'month', header: 'hr.salaries.month', sortable: true },
    { field: 'base_salary', header: 'hr.salaries.col_base', sortable: true },
    { field: 'final_salary', header: 'hr.salaries.col_final', sortable: true },
    { field: 'status', header: 'hr.salaries.col_status', sortable: true }
  ];

  /** T3-90: month/year filters (defaults = the current month), also feed the export */
  params = signal({ page: 1, per_page: 25, q: '', status: 'all', month: new Date().getMonth() + 1, year: new Date().getFullYear() });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string; month: number; year: number }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/hr/salaries', { params: cleanParams(this.params()) })),
  });

  readonly monthOptions = Array.from({ length: 12 }, (_, i) => ({ label: String(i + 1), value: i + 1 }));
  /** same range as the Blade page: two years back through next year */
  readonly yearOptions = Array.from({ length: 4 }, (_, i) => ({ label: String(new Date().getFullYear() - 2 + i), value: new Date().getFullYear() - 2 + i }));

  /** the export downloads the CSV for the filtered month/year (SalaryController@exportReport) */
  exportReport(): void {
    window.open(`/api/v1/hr/salaries-report/export?year=${this.params().year}&month=${this.params().month}`, '_blank');
  }

  summaryValue(key: string): string {
    const value = this.data.value()?.meta?.summary?.[key];
    return value === undefined || value === null ? '0' : String(value);
  }

  setParam(key: string, value: string | number): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }


  dialogOpen = signal(false);
  sending = signal(false);
  form = { year: new Date().getFullYear(), month: new Date().getMonth() + 1, calculation_method: 'daily_rate' };

  methodOptions = [
    { label: this.translate.instant('hr.salaries.method_daily'), value: 'daily_rate' },
    { label: this.translate.instant('hr.salaries.method_deduction'), value: 'deduction_method' },
  ];

  submit(): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/hr/salaries/calculate', this.form)).then((res) => {
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
