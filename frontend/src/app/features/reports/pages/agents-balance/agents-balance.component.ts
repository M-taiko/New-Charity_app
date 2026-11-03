// Agents balance report (plan 20, T32/T10b, T3-110: full Blade column set).
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

interface AgentsData {
  grand_totals: Record<string, string | number>;
  agents: {
    agent: { id: number; name: string };
    total_custodies: number; active_custodies: number; closed_custodies: number;
    total_received: string; transferred_in: string; transferred_out: string;
    total_spent: string; total_returned: string; current_balance: string;
  }[];
}

@Component({
  selector: 'app-agents-balance',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, PageHeaderComponent, StatTileComponent, PrintSheetComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.agents_balance.title' | translate">
        <app-page-header [title]="'report.agents_balance.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (onClick)="printPage()" /></div>
        </app-page-header>
        <div class="kpi-grid">
          <app-stat-tile [label]="'report.agents_balance.total_balance'" [value]="total('total_balance')" [money]="true" />
          <app-stat-tile [label]="'report.agents_balance.total_agents'" [value]="total('total_agents')" />
          <app-stat-tile [label]="'report.agents_balance.total_received'" [value]="total('total_received')" [money]="true" />
          <app-stat-tile [label]="'report.agents_balance.total_spent'" [value]="total('total_spent')" [money]="true" />
          <app-stat-tile [label]="'report.agents_balance.total_returned'" [value]="total('total_returned')" [money]="true" />
        </div>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.agents_balance.col_name' | translate }}</th>
              <th>{{ 'report.agents_balance.col_custodies' | translate }}</th>
              <th>{{ 'report.agents_balance.col_active' | translate }}</th>
              <th>{{ 'report.agents_balance.col_closed' | translate }}</th>
              <th>{{ 'report.agents_balance.col_received' | translate }}</th>
              <th>{{ 'report.agents_balance.col_transferred_in' | translate }}</th>
              <th>{{ 'report.agents_balance.col_transferred_out' | translate }}</th>
              <th>{{ 'report.agents_balance.col_spent' | translate }}</th>
              <th>{{ 'report.agents_balance.col_returned' | translate }}</th>
              <th>{{ 'report.agents_balance.col_balance' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.agents; track row.agent.id) {
                <tr class="ledger-table__row">
                  <td>{{ row.agent.name }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_custodies }}</td>
                  <td class="ledger-table__cell-num">{{ row.active_custodies }}</td>
                  <td class="ledger-table__cell-num">{{ row.closed_custodies }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_received | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.transferred_in | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.transferred_out | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_spent | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.total_returned | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.current_balance | money }}</td>
                </tr>
              } @empty { <tr><td colspan="10" class="u-muted">{{ 'report.agents_balance.empty' | translate }}</td></tr> }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AgentsBalancePage {
  private http = inject(HttpClient);
  data = resource<AgentsData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<AgentsData>>('/reports/agents-balance')).then((r) => r.data),
  });
  total(key: string): string {
    return String(this.data.value()?.grand_totals[key] ?? '0');
  }
  printPage(): void {
    window.print();
  }
}
