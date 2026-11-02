// Employee salary history (plan 20).
import { computed, ChangeDetectionStrategy, Component, inject, input, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ListResponse } from '@shared/models/api.models';

interface SalaryRow { id: number; year: number; month: number; base_salary: string; final_salary: string; status: string }

@Component({
  selector: 'app-salary-history',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent, StatusStampComponent, MoneyPipe],
  template: `
    <app-page-header [title]="'hr.salaries.history_title' | translate">
      <div actions><p-button [label]="'common.back_to_list' | translate" size="small" [text]="true" routerLink="/hr/employees" /></div>
    </app-page-header>
    @if (data.value(); as page) {
      <div class="ledger-table">
        <table>
          <thead><tr>
            <th>{{ 'hr.salaries.year' | translate }}</th><th>{{ 'hr.salaries.month' | translate }}</th>
            <th>{{ 'hr.salaries.col_base' | translate }}</th><th>{{ 'hr.salaries.col_final' | translate }}</th>
            <th>{{ 'common.status' | translate }}</th><th>{{ 'common.actions' | translate }}</th>
          </tr></thead>
          <tbody>
            @for (row of page.data; track row.id) {
              <tr class="ledger-table__row">
                <td class="u-bidi">{{ row.year }}</td>
                <td class="u-bidi">{{ row.month }}</td>
                <td class="ledger-table__cell-num">{{ row.base_salary | money }}</td>
                <td class="ledger-table__cell-num">{{ row.final_salary | money }}</td>
                <td><app-status-stamp [status]="row.status" namespace="salary" /></td>
                <td><a [routerLink]="['/hr/salaries', row.id]">{{ 'common.view' | translate }}</a></td>
              </tr>
            } @empty { <tr><td colspan="6" class="u-muted">{{ 'hr.salaries.empty' | translate }}</td></tr> }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class SalaryHistoryPage {
  private http = inject(HttpClient);
  id = input.required<number>();
  data = resource<ListResponse<SalaryRow>, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) => firstValueFrom(this.http.get<ListResponse<SalaryRow>>('/hr/employees/' + params.id + '/salary-history')),
  });
}
