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
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import type { TableColumn } from '@shared/ui/data-table/data-table.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { entityColor } from '@shared/lib/chart-colors';
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
    DataTableComponent, TableCellDirective,
    StatTileComponent, PrintSheetComponent, MoneyPipe, ActionButtonComponent,
  ],
  template: `
    <app-print-sheet [title]="'report.agents_balance.title' | translate">
      <app-page-header [title]="'report.agents_balance.title' | translate">
        <div actions><app-action-button kind="print" label="common.print" size="small" (action)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <!-- RD-4.8: 4 summary cards. R4-04: no top bars; the in/out figures keep
               the signed-flow colours of the table below (blue in, red out) and
               the balance is coloured only when it is negative -->
          <div class="kpi-grid">
            <app-stat-tile [label]="'report.agents_balance.total_agents'" [value]="total('total_agents')" accent="reports" iconName="users" />
            <app-stat-tile [label]="'report.agents_balance.total_received'" [value]="total('total_received')" [money]="true" tone="info" accent="reports" iconName="received" />
            <app-stat-tile [label]="'report.agents_balance.total_spent'" [value]="total('total_spent')" [money]="true" tone="danger" accent="reports" iconName="expense" />
            <app-stat-tile [label]="'report.agents_balance.total_balance'" [value]="total('total_balance')" [money]="true" [tone]="isNegative(total('total_balance')) ? 'danger' : ''" accent="reports" iconName="money" />
          </div>

          <app-data-table listUrl="/reports/agents-balance" mode="static" [autoFilters]="true" [phoneFirst]="['agent', 'current_balance']" [columns]="cols" [rows]="d.agents" [emptyLabel]="'report.agents_balance.empty'">
            <ng-template appCell="agent" let-row>
              <span class="u-flex u-center-y u-gap-2 beneficiary">
                <!-- N-07: the ring is the agent's own colour — the same one as the
                     agent's bar on the dashboard ranking (entityColor) -->
                <span class="beneficiary__avatar beneficiary__avatar--entity" aria-hidden="true" [style.borderColor]="entityColor(row.agent.id)">{{ initials(row.agent.name) }}</span>
                <span class="u-fw-semibold">{{ row.agent.name }}</span>
              </span>
            </ng-template>
            <ng-template appCell="total_custodies" let-row><span class="u-bidi">{{ row.total_custodies }}</span></ng-template>
            <ng-template appCell="active_custodies" let-row><span class="stamp stamp--success u-bidi">{{ row.active_custodies }}</span></ng-template>
            <ng-template appCell="closed_custodies" let-row><span class="stamp stamp--neutral u-bidi">{{ row.closed_custodies }}</span></ng-template>
            <ng-template appCell="total_received" let-row><span class="money money--link u-bidi">{{ row.total_received | money: false }}</span></ng-template>
            <ng-template appCell="transferred_in" let-row><span class="money money--pos u-bidi">{{ row.transferred_in | money: false }}</span></ng-template>
            <ng-template appCell="transferred_out" let-row><span class="money money--neg u-bidi">{{ row.transferred_out | money: false }}</span></ng-template>
            <ng-template appCell="total_spent" let-row><span class="money money--neg u-bidi">{{ row.total_spent | money: false }}</span></ng-template>
            <ng-template appCell="total_returned" let-row><span class="money money--pos u-bidi">{{ row.total_returned | money: false }}</span></ng-template>
            <!-- P-08: plain bold balance — red only when negative (the filled
                 dark-brown pill shouted on every row) -->
            <ng-template appCell="current_balance" let-row>
              <span class="u-fw-semibold u-bidi" [class.money--neg]="isNegative(row.current_balance)">{{ row.current_balance | money: false }}</span>
            </ng-template>
          </app-data-table>
        }
        <div empty>
          <app-empty-state [title]="'report.agents_balance.empty'" [text]="'common.no_data'" accent="reports" iconName="report-analytics" />
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

  /** P-08: min-widths stop the header overlap and the clipped currency;
   *  headers carry (ج.م), cells stay bare tabular numbers */
  readonly cols: TableColumn[] = [
    { field: 'agent', header: 'report.agents_balance.col_name', minWidth: 160 },
    { field: 'total_custodies', header: 'report.agents_balance.col_custodies', numeric: true },
    { field: 'active_custodies', header: 'report.agents_balance.col_active', numeric: true },
    { field: 'closed_custodies', header: 'report.agents_balance.col_closed', numeric: true },
    { field: 'total_received', header: 'report.agents_balance.col_received', numeric: true, minWidth: 150 },
    { field: 'transferred_in', header: 'report.agents_balance.col_transferred_in', numeric: true, minWidth: 150 },
    { field: 'transferred_out', header: 'report.agents_balance.col_transferred_out', numeric: true, minWidth: 150 },
    { field: 'total_spent', header: 'report.agents_balance.col_spent', numeric: true, minWidth: 150 },
    { field: 'total_returned', header: 'report.agents_balance.col_returned', numeric: true, minWidth: 150 },
    { field: 'current_balance', header: 'report.agents_balance.col_balance', numeric: true, minWidth: 150 },
  ];

  total(key: string): string {
    return String(this.data.value()?.grand_totals[key] ?? '0');
  }

  /** red only for negative balances — colour carries meaning, not decoration */
  isNegative(value: string): boolean {
    return Number.parseFloat(value) < 0;
  }

  /** N-07: the agent's own colour for the avatar ring — the template binds it */
  entityColor = entityColor;

  /** avatar initials for Arabic names (the Stitch _8 agent column) */
  initials(name: string): string {
    return name.trim().split(/\s+/).slice(0, 2).map((w) => w.charAt(0)).join('');
  }

  printPage(): void {
    window.print();
  }
}
