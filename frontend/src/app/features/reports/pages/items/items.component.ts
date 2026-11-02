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

/** Expense items report (plan 20). */
@Component({
  selector: 'app-items-report',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, PrintSheetComponent, StatTileComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.items.title' | translate">
        <app-page-header [title]="'report.items.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'common.total'" [value]="d.summary.total_amount" [money]="true" />
          <app-stat-tile [label]="'common.count'" [value]="d.summary.count" />
        </div>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.items.col_item' | translate }}</th>
              <th>{{ 'report.items.col_category' | translate }}</th>
              <th>{{ 'report.items.col_count' | translate }}</th>
              <th>{{ 'report.items.col_total' | translate }}</th>
              <th>{{ 'report.items.col_average' | translate }}</th>
              <th>{{ 'report.items.col_percent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.items; track row.item) {
                <tr class="ledger-table__row">
                  <td>{{ row.item }}</td>
                  <td>{{ row.category }}</td>
                  <td class="ledger-table__cell-num">{{ row.count }}</td>
                  <td class="ledger-table__cell-num">{{ row.total | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.average | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.percentage }}%</td>
                </tr>
              } @empty { <tr><td colspan="6" class="u-muted">{{ 'report.items.empty' | translate }}</td></tr> }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
})
export class ItemsReportPage {
  private http = inject(HttpClient);
  data = resource<{ summary: { total_amount: string; count: number }; items: { item: string; category: string | null; count: number; total: string; average: string; percentage: number }[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ summary: { total_amount: string; count: number }; items: { item: string; category: string | null; count: number; total: string; average: string; percentage: number }[] }>>('/reports/expense-items')).then((r) => r.data),
  });
  printPage(): void {
    window.print();
  }
}
