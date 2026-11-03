// Researcher statistics (plan 20, T3-110: rejected column from Blade).
import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ItemResponse } from '@shared/models/api.models';

interface ResearchersData {
  summary: { researchers_count: number; total_cases: number; approved_cases: number; total_spent: string };
  researchers: { researcher: { id: number; name: string }; total_cases: number; approved: number; rejected: number; pending: number; total_spent: string; approval_rate: number }[];
}

@Component({
  selector: 'app-researchers-report',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, StatTileComponent, PrintSheetComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.researchers.title' | translate">
        <app-page-header [title]="'report.researchers.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (onClick)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'report.researchers.researchers_count'" [value]="d.summary.researchers_count" />
          <app-stat-tile [label]="'report.researchers.total_cases'" [value]="d.summary.total_cases" />
          <app-stat-tile [label]="'report.researchers.approved_cases'" [value]="d.summary.approved_cases" />
          <app-stat-tile [label]="'report.researchers.total_spent'" [value]="d.summary.total_spent" [money]="true" />
        </div>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.researchers.col_name' | translate }}</th>
              <th>{{ 'report.researchers.col_cases' | translate }}</th>
              <th>{{ 'report.researchers.col_approved' | translate }}</th>
              <th>{{ 'report.researchers.col_rejected' | translate }}</th>
              <th>{{ 'report.researchers.col_pending' | translate }}</th>
              <th>{{ 'report.researchers.col_spent' | translate }}</th>
              <th>{{ 'report.researchers.col_rate' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.researchers; track row.researcher.id) {
                <tr class="ledger-table__row">
                  <td>{{ row.researcher.name }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_cases }}</td>
                  <td class="ledger-table__cell-num">{{ row.approved }}</td>
                  <td class="ledger-table__cell-num">{{ row.rejected }}</td>
                  <td class="ledger-table__cell-num">{{ row.pending }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_spent | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.approval_rate }}%</td>
                </tr>
              } @empty { <tr><td colspan="7" class="u-muted">{{ 'report.researchers.empty' | translate }}</td></tr> }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResearchersPage {
  private http = inject(HttpClient);
  data = resource<ResearchersData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ResearchersData>>('/analytics/researchers')).then((r) => r.data),
  });
  printPage(): void {
    window.print();
  }
}
