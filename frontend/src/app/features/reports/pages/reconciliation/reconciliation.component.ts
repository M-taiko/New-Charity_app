import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DataTableComponent, TableBodyDirective, TableColDirective } from '@shared/ui/data-table/data-table.component';
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
 * T4-20/21/27: async-page states + static ledger table in the shared layout.
 */
@Component({
  selector: 'app-reconciliation-report',
  standalone: true,
  imports: [
    TranslatePipe, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableBodyDirective, TableColDirective,
    PrintSheetComponent, StatTileComponent, MoneyPipe,
  ],
  template: `
    <app-print-sheet [title]="'report.reconciliation.title' | translate">
      <app-page-header [title]="'report.reconciliation.title' | translate">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="kpi-grid">
            <app-stat-tile [label]="'report.reconciliation.col_actual'" [value]="d.totals.actual_balance" [money]="true" />
            <app-stat-tile [label]="'report.reconciliation.col_expected'" [value]="d.totals.expected_balance" [money]="true" />
            <app-stat-tile [label]="'report.reconciliation.col_difference'" [value]="d.totals.difference" [money]="true" [inset]="true" />
            <app-stat-tile [label]="'common.status'" [value]="d.totals.is_reconciled ? ('report.reconciliation.reconciled' | translate) : ('report.reconciliation.not_reconciled' | translate)" />
          </div>

          <app-data-table mode="static" [autoFilters]="true" [columns]="cols" [rows]="d.treasuries" [emptyLabel]="'report.reconciliation.empty'">
            <ng-template appTableBody let-row>
              <tr class="ledger-table__row">
                <td appTableCol="treasury">{{ row.treasury.name }}</td>
                <td appTableCol="donations"><span class="u-bidi">{{ row.donations | money }}</span></td>
                <td appTableCol="custodies_issued"><span class="u-bidi">{{ row.custodies_issued | money }}</span></td>
                <td appTableCol="custodies_returned"><span class="u-bidi">{{ row.custodies_returned | money }}</span></td>
                <td appTableCol="direct_expenses"><span class="u-bidi">{{ row.direct_expenses | money }}</span></td>
                <td appTableCol="net_transfers"><span class="u-bidi">{{ netTransfers(row) | money }}</span></td>
                <td appTableCol="expected_balance"><span class="u-bidi">{{ row.expected_balance | money }}</span></td>
                <td appTableCol="actual_balance"><span class="u-bidi">{{ row.actual_balance | money }}</span></td>
                <td appTableCol="difference">
                  @if (row.is_reconciled) {
                    <span class="stamp">{{ 'report.reconciliation.reconciled' | translate }}</span>
                  } @else {
                    <span class="u-bidi">{{ row.difference | money }}</span>
                  }
                </td>
              </tr>
            </ng-template>
            <ng-template footer>
              <tr class="ledger-table__row">
                <th>{{ 'common.total' | translate }}</th>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ columnSum('donations') | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ columnSum('custodies_issued') | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ columnSum('custodies_returned') | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ columnSum('direct_expenses') | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ netTransfersSum() | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ d.totals.expected_balance | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ d.totals.actual_balance | money }}</span></td>
                <td class="ledger-table__cell-num"><span class="u-bidi">{{ d.totals.difference | money }}</span></td>
              </tr>
            </ng-template>
          </app-data-table>
          <p class="field__hint u-mt-2">{{ 'report.reconciliation.expected_formula' | translate }}</p>
        }
        <div empty>
          <app-empty-state [title]="'report.reconciliation.empty'" [text]="'common.no_data'" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReconciliationPage {
  private http = inject(HttpClient);
  data = resource<ReconciliationData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ReconciliationData>>('/reports/reconciliation')).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.treasuries ?? []).length === 0;

  readonly cols = [
    { field: 'treasury', header: 'report.reconciliation.col_treasury' },
    { field: 'donations', header: 'report.reconciliation.col_donations', numeric: true },
    { field: 'custodies_issued', header: 'report.reconciliation.col_issued', numeric: true },
    { field: 'custodies_returned', header: 'report.reconciliation.col_returned', numeric: true },
    { field: 'direct_expenses', header: 'report.reconciliation.col_direct', numeric: true },
    { field: 'net_transfers', header: 'report.reconciliation.col_transfers', numeric: true },
    { field: 'expected_balance', header: 'report.reconciliation.col_expected', numeric: true },
    { field: 'actual_balance', header: 'report.reconciliation.col_actual', numeric: true },
    { field: 'difference', header: 'report.reconciliation.col_difference', numeric: true },
  ];

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
