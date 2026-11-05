// Employee salary history (plan 20) — async-page states + the static ledger table (T4-20/21).
import { Component, inject, input, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableBodyDirective, TableColDirective } from '@shared/ui/data-table/data-table.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ListResponse } from '@shared/models/api.models';

interface SalaryRow { id: number; year: number; month: number; base_salary: string; final_salary: string; status: string }

@Component({
  selector: 'app-salary-history',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    StatusStampComponent, MoneyPipe,
  ],
  template: `
    <app-page-header [title]="'hr.salaries.history_title' | translate">
      <div actions><p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/hr/employees" /></div>
    </app-page-header>

    <app-section-nav module="hr" />

    <app-async-page [resource]="data" variant="list" [empty]="isEmpty" [backLink]="['/hr/employees']">
      @if (data.value(); as page) {
        <app-data-table mode="static" [autoFilters]="true" [columns]="columns" [rows]="page.data" [emptyLabel]="'hr.salaries.empty'">
          <ng-template appTableBody let-row>
            <tr class="ledger-table__row">
              <td appTableCol="year" class="u-bidi">{{ row.year }}</td>
              <td appTableCol="month" class="u-bidi">{{ row.month }}</td>
              <td appTableCol="base_salary"><span class="u-bidi">{{ row.base_salary | money }}</span></td>
              <td appTableCol="final_salary"><span class="u-bidi">{{ row.final_salary | money }}</span></td>
              <td appTableCol="status"><app-status-stamp [status]="row.status" namespace="salary" /></td>
              <td appTableCol="actions">
                <a [routerLink]="['/hr/salaries', row.id]">{{ 'common.view' | translate }}</a>
              </td>
            </tr>
          </ng-template>
        </app-data-table>
      }
    </app-async-page>
  `,
})
export class SalaryHistoryPage {
  private http = inject(HttpClient);
  id = input.required<number>();

  isEmpty = () => (this.data.value()?.data ?? []).length === 0;

  readonly columns = [
    { field: 'year', header: 'hr.salaries.year', numeric: true },
    { field: 'month', header: 'hr.salaries.month', numeric: true },
    { field: 'base_salary', header: 'hr.salaries.col_base', numeric: true },
    { field: 'final_salary', header: 'hr.salaries.col_final', numeric: true },
    { field: 'status', header: 'common.status' },
    { field: 'actions', header: 'common.actions' },
  ];

  data = resource<ListResponse<SalaryRow>, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) => firstValueFrom(this.http.get<ListResponse<SalaryRow>>('/hr/employees/' + params.id + '/salary-history')),
  });
}
