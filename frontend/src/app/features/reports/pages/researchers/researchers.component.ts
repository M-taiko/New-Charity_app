// Researcher statistics (plan 20, T3-110: rejected column from Blade).
// T4-20/21/27: async-page states + the static ledger table in the shared layout.
import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
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
  imports: [
    TranslatePipe, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent,
    DataTableComponent, TableCellDirective, 
    StatTileComponent, PrintSheetComponent, MoneyPipe,
  ],
  template: `
    <app-print-sheet orientation="landscape" [title]="'report.researchers.title' | translate">
      <app-page-header [title]="'report.researchers.title' | translate" accent="reports" iconName="report-analytics">
        <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (onClick)="printPage()" /></div>
      </app-page-header>

      <app-async-page [resource]="data" variant="report" [empty]="isEmpty" [backLink]="['/reports']">
        @if (data.value(); as d) {
          <div class="kpi-grid">
            <app-stat-tile [label]="'report.researchers.researchers_count'" [value]="d.summary.researchers_count" accent="reports" iconName="report-analytics" />
            <app-stat-tile [label]="'report.researchers.total_cases'" [value]="d.summary.total_cases" />
            <app-stat-tile [label]="'report.researchers.approved_cases'" [value]="d.summary.approved_cases" />
            <app-stat-tile [label]="'report.researchers.total_spent'" [value]="d.summary.total_spent" [money]="true" />
          </div>

          <app-data-table listUrl="/analytics/researchers" mode="static" [autoFilters]="true" [columns]="cols" [rows]="d.researchers" [emptyLabel]="'report.researchers.empty'">
            <ng-template appCell="researcher" let-row>{{ row.researcher.name }}</ng-template>
            <ng-template appCell="total_cases" let-row><span class="u-bidi">{{ row.total_cases }}</span></ng-template>
            <ng-template appCell="approved" let-row><span class="u-bidi">{{ row.approved }}</span></ng-template>
            <ng-template appCell="rejected" let-row><span class="u-bidi">{{ row.rejected }}</span></ng-template>
            <ng-template appCell="pending" let-row><span class="u-bidi">{{ row.pending }}</span></ng-template>
            <ng-template appCell="total_spent" let-row><span class="u-bidi">{{ row.total_spent | money }}</span></ng-template>
            <ng-template appCell="approval_rate" let-row><span class="u-bidi">{{ row.approval_rate }}%</span></ng-template>
          </app-data-table>
        }
        <div empty>
          <app-empty-state [title]="'report.researchers.empty'" [text]="'common.no_data'" accent="reports" iconName="report-analytics" />
        </div>
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResearchersPage {
  private http = inject(HttpClient);
  data = resource<ResearchersData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<ResearchersData>>('/analytics/researchers')).then((r) => r.data),
  });

  isEmpty = (): boolean => (this.data.value()?.researchers ?? []).length === 0;

  readonly cols = [
    { field: 'researcher', header: 'report.researchers.col_name' },
    { field: 'total_cases', header: 'report.researchers.col_cases', numeric: true },
    { field: 'approved', header: 'report.researchers.col_approved', numeric: true },
    { field: 'rejected', header: 'report.researchers.col_rejected', numeric: true },
    { field: 'pending', header: 'report.researchers.col_pending', numeric: true },
    { field: 'total_spent', header: 'report.researchers.col_spent', numeric: true },
    { field: 'approval_rate', header: 'report.researchers.col_rate', numeric: true },
  ];

  printPage(): void {
    window.print();
  }
}
