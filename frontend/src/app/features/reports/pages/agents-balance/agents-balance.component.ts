// Agents balance report (plan 20, T32/T10b, T3-110: full Blade column set).
// T4-20/21/27: async-page states + the static ledger table in the shared report layout.
import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DataTableComponent, TableBodyDirective, TableColDirective } from '@shared/ui/data-table/data-table.component';
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
  imports: [
    TranslatePipe, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableBodyDirective, TableColDirective,
    StatTileComponent, PrintSheetComponent, MoneyPipe,
  ],
  template: `
    <app-print-sheet [title]="'report.agents_balance.title' | translate">
      <app-page-header [title]="'report.agents_balance.title' | translate">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (onClick)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="kpi-grid">
            <app-stat-tile [label]="'report.agents_balance.total_balance'" [value]="total('total_balance')" [money]="true" />
            <app-stat-tile [label]="'report.agents_balance.total_agents'" [value]="total('total_agents')" />
            <app-stat-tile [label]="'report.agents_balance.total_received'" [value]="total('total_received')" [money]="true" />
            <app-stat-tile [label]="'report.agents_balance.total_spent'" [value]="total('total_spent')" [money]="true" />
            <app-stat-tile [label]="'report.agents_balance.total_returned'" [value]="total('total_returned')" [money]="true" />
          </div>

          <app-data-table mode="static" [autoFilters]="true" [columns]="cols" [rows]="d.agents" [emptyLabel]="'report.agents_balance.empty'">
            <ng-template appTableBody let-row>
              <tr class="ledger-table__row">
                <td appTableCol="agent">{{ row.agent.name }}</td>
                <td appTableCol="total_custodies" class="u-bidi">{{ row.total_custodies }}</td>
                <td appTableCol="active_custodies" class="u-bidi">{{ row.active_custodies }}</td>
                <td appTableCol="closed_custodies" class="u-bidi">{{ row.closed_custodies }}</td>
                <td appTableCol="total_received"><span class="u-bidi">{{ row.total_received | money }}</span></td>
                <td appTableCol="transferred_in"><span class="u-bidi">{{ row.transferred_in | money }}</span></td>
                <td appTableCol="transferred_out"><span class="u-bidi">{{ row.transferred_out | money }}</span></td>
                <td appTableCol="total_spent"><span class="u-bidi">{{ row.total_spent | money }}</span></td>
                <td appTableCol="total_returned"><span class="u-bidi">{{ row.total_returned | money }}</span></td>
                <td appTableCol="current_balance"><span class="u-bidi">{{ row.current_balance | money }}</span></td>
              </tr>
            </ng-template>
          </app-data-table>
        }
        <div empty>
          <app-empty-state [title]="'report.agents_balance.empty'" [text]="'common.no_data'" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AgentsBalancePage {
  private http = inject(HttpClient);
  data = resource<AgentsData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<AgentsData>>('/reports/agents-balance')).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.agents ?? []).length === 0;

  readonly cols = [
    { field: 'agent', header: 'report.agents_balance.col_name' },
    { field: 'total_custodies', header: 'report.agents_balance.col_custodies', numeric: true },
    { field: 'active_custodies', header: 'report.agents_balance.col_active', numeric: true },
    { field: 'closed_custodies', header: 'report.agents_balance.col_closed', numeric: true },
    { field: 'total_received', header: 'report.agents_balance.col_received', numeric: true },
    { field: 'transferred_in', header: 'report.agents_balance.col_transferred_in', numeric: true },
    { field: 'transferred_out', header: 'report.agents_balance.col_transferred_out', numeric: true },
    { field: 'total_spent', header: 'report.agents_balance.col_spent', numeric: true },
    { field: 'total_returned', header: 'report.agents_balance.col_returned', numeric: true },
    { field: 'current_balance', header: 'report.agents_balance.col_balance', numeric: true },
  ];

  total(key: string): string {
    return String(this.data.value()?.grand_totals[key] ?? '0');
  }
  printPage(): void {
    window.print();
  }
}
