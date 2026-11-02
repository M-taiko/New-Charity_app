import { computed, ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { ItemResponse } from '@shared/models/api.models';

/** Social case expenses report (plan 20). */
@Component({
  selector: 'app-case-expenses-report',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, PrintSheetComponent, StatTileComponent, MoneyPipe, DatetimePipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.case_expenses.title' | translate">
        <app-page-header [title]="'report.case_expenses.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'common.total'" [value]="d.summary.total_amount" [money]="true" />
          <app-stat-tile [label]="'common.count'" [value]="d.summary.count" />
        </div>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.case_expenses.col_category' | translate }}</th>
              <th>{{ 'report.case_expenses.col_count' | translate }}</th>
              <th>{{ 'report.case_expenses.col_total' | translate }}</th>
              <th>{{ 'report.case_expenses.col_percent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.by_category; track row.category) {
                <tr class="ledger-table__row">
                  <td>{{ row.category }}</td>
                  <td class="ledger-table__cell-num">{{ row.count }}</td>
                  <td class="ledger-table__cell-num">{{ row.total | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.percent }}%</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
})
export class CaseExpensesPage {
  private http = inject(HttpClient);
  data = resource<{ summary: { total_amount: string; count: number }; by_category: { category: string; count: number; total: string; percent: number }[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ summary: { total_amount: string; count: number }; by_category: { category: string; count: number; total: string; percent: number }[] }>>('/reports/social-case-expenses')).then((r) => r.data),
  });
  printPage(): void {
    window.print();
  }
}
