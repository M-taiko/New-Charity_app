import { computed, ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ItemResponse } from '@shared/models/api.models';

interface TreasuryRow {
  treasury: { id: number; name: string }; donations: string; custodies_issued: string;
  custodies_returned: string; direct_expenses: string; transfers_in: string; transfers_out: string;
  actual_balance: string; expected_balance: string; difference: string; is_reconciled: boolean;
}

/** Reconciliation report (plan 20): expected vs actual per treasury. */
@Component({
  selector: 'app-reconciliation-report',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, PrintSheetComponent, StatTileComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.reconciliation.title' | translate">
        <app-page-header [title]="'report.reconciliation.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'report.reconciliation.col_actual'" [value]="d.totals.actual_balance" [money]="true" />
          <app-stat-tile [label]="'report.reconciliation.col_expected'" [value]="d.totals.expected_balance" [money]="true" />
          <app-stat-tile [label]="'report.reconciliation.col_difference'" [value]="d.totals.difference" [money]="true" [inset]="true" />
          <app-stat-tile [label]="'common.status'" [value]="d.totals.is_reconciled ? ('report.reconciliation.reconciled' | translate) : ('report.reconciliation.not_reconciled' | translate)" />
        </div>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.reconciliation.col_treasury' | translate }}</th>
              <th>{{ 'report.reconciliation.col_donations' | translate }}</th>
              <th>{{ 'report.reconciliation.col_issued' | translate }}</th>
              <th>{{ 'report.reconciliation.col_returned' | translate }}</th>
              <th>{{ 'report.reconciliation.col_direct' | translate }}</th>
              <th>{{ 'report.reconciliation.col_expected' | translate }}</th>
              <th>{{ 'report.reconciliation.col_actual' | translate }}</th>
              <th>{{ 'report.reconciliation.col_difference' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.treasuries; track row.treasury.id) {
                <tr class="ledger-table__row">
                  <td>{{ row.treasury.name }}</td>
                  <td class="ledger-table__cell-num">{{ row.donations | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.custodies_issued | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.custodies_returned | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.direct_expenses | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.expected_balance | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.actual_balance | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.difference | money }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        <p class="field__hint u-mt-2">{{ 'report.reconciliation.expected_formula' | translate }}</p>
      </app-print-sheet>
    }
  `,
})
export class ReconciliationPage {
  private http = inject(HttpClient);
  data = resource<{ treasuries: TreasuryRow[]; totals: { actual_balance: string; expected_balance: string; difference: string; is_reconciled: boolean } }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ treasuries: TreasuryRow[]; totals: { actual_balance: string; expected_balance: string; difference: string; is_reconciled: boolean } }>>('/reports/reconciliation')).then((r) => r.data),
  });
  printPage(): void {
    window.print();
  }
}
