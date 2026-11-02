import { computed, ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ItemResponse } from '@shared/models/api.models';

/** Direction analytics (plan 20, T33): opened from dashboards with the same selection. */
@Component({
  selector: 'app-directions-report',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, PrintSheetComponent, StatTileComponent, ChartCardComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.directions.title' | translate">
        <app-page-header [title]="'report.directions.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'common.total'" [value]="d.grand_total" [money]="true" />
        </div>
        <div class="kpi-grid">
          <app-chart-card [title]="'report.directions.charts'" [series]="rootsSeries()" />
        </div>
        <div class="ledger-table">
          <div class="ledger-table__header"><span class="ledger-table__title">{{ 'report.directions.details' | translate }}</span></div>
          <table>
            <thead><tr>
              <th>{{ 'report.directions.col_level' | translate }}</th>
              <th>{{ 'report.directions.col_item' | translate }}</th>
              <th>{{ 'report.directions.col_code' | translate }}</th>
              <th>{{ 'report.directions.col_total' | translate }}</th>
              <th>{{ 'report.directions.col_count' | translate }}</th>
              <th>{{ 'report.directions.col_percent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.rows; track row.id) {
                <tr class="ledger-table__row">
                  <td>{{ row.level }}</td>
                  <td>{{ row.name }}</td>
                  <td class="u-bidi">{{ row.code ?? '—' }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_amount | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.expense_count }}</td>
                  <td class="ledger-table__cell-num">{{ row.percentage }}%</td>
                </tr>
              } @empty { <tr><td colspan="6" class="u-muted">{{ 'report.directions.empty' | translate }}</td></tr> }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
})
export class DirectionsReportPage {
  private http = inject(HttpClient);
  data = resource<{ grand_total: string; rows: { id: number; name: string; level: number | string; code: string | null; total_amount: string; expense_count: number; percentage: number }[] }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ grand_total: string; rows: { id: number; name: string; level: number | string; code: string | null; total_amount: string; expense_count: number; percentage: number }[] }>>('/reports/categories-analytics')).then((r) => r.data),
  });
  rootsSeries() {
    return (this.data.value()?.rows ?? []).filter((r) => r.level === 1).slice(0, 6).map((r) => ({ label: r.name, value: parseFloat(r.total_amount) || 0 }));
  }
  printPage(): void {
    window.print();
  }
}
