import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ItemResponse } from '@shared/models/api.models';

interface TreasuryRow {
  treasury: { id: number; name: string }; donations: string; custodies_issued: string;
  custodies_returned: string; direct_expenses: string; transfers_in: string; transfers_out: string;
  actual_balance: string; expected_balance: string; difference: string; is_reconciled: boolean;
}

interface ReconciliationData {
  treasuries: TreasuryRow[];
  totals: { actual_balance: string; expected_balance: string; difference: string; is_reconciled: boolean };
}

/**
 * Reconciliation report (plan 20, T3-110): expected vs actual per treasury —
 * Blade parity adds the net-transfers column, the per-row status badge and
 * the grand-total footer row.
 */
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
              <th>{{ 'report.reconciliation.col_transfers' | translate }}</th>
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
                  <td class="ledger-table__cell-num">{{ netTransfers(row) | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.expected_balance | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.actual_balance | money }}</td>
                  <td class="ledger-table__cell-num">
                    @if (row.is_reconciled) {
                      <span class="stamp">{{ 'report.reconciliation.reconciled' | translate }}</span>
                    } @else {
                      <span class="u-bidi">{{ row.difference | money }}</span>
                    }
                  </td>
                </tr>
              }
            </tbody>
            <tfoot>
              <tr class="ledger-table__row">
                <th>{{ 'common.total' | translate }}</th>
                <td class="ledger-table__cell-num">{{ columnSum('donations') | money }}</td>
                <td class="ledger-table__cell-num">{{ columnSum('custodies_issued') | money }}</td>
                <td class="ledger-table__cell-num">{{ columnSum('custodies_returned') | money }}</td>
                <td class="ledger-table__cell-num">{{ columnSum('direct_expenses') | money }}</td>
                <td class="ledger-table__cell-num">{{ netTransfersSum() | money }}</td>
                <td class="ledger-table__cell-num">{{ d.totals.expected_balance | money }}</td>
                <td class="ledger-table__cell-num">{{ d.totals.actual_balance | money }}</td>
                <td class="ledger-table__cell-num">{{ d.totals.difference | money }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="field__hint u-mt-2">{{ 'report.reconciliation.expected_formula' | translate }}</p>
      </app-print-sheet>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReconciliationPage {
  private http = inject(HttpClient);
  data = resource<ReconciliationData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ReconciliationData>>('/reports/reconciliation')).then((r) => r.data),
  });

  /** net transfers for one treasury (display-only derivation from the two API columns) */
  netTransfers(row: TreasuryRow): string {
    return ((parseFloat(row.transfers_in) || 0) - (parseFloat(row.transfers_out) || 0)).toFixed(2);
  }

  netTransfersSum(): string {
    const rows = this.data.value()?.treasuries ?? [];
    return rows
      .reduce((sum, row) => sum + (parseFloat(row.transfers_in) || 0) - (parseFloat(row.transfers_out) || 0), 0)
      .toFixed(2);
  }

  columnSum(key: 'donations' | 'custodies_issued' | 'custodies_returned' | 'direct_expenses'): string {
    const rows = this.data.value()?.treasuries ?? [];
    return rows.reduce((sum, row) => sum + (parseFloat(row[key]) || 0), 0).toFixed(2);
  }

  printPage(): void {
    window.print();
  }
}
