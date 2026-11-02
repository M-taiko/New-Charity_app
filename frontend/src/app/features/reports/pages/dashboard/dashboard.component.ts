import { ChangeDetectionStrategy, Component, computed, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ChartCardComponent } from '@shared/ui/chart-card/chart-card.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { ItemResponse } from '@shared/models/api.models';

/** Reports dashboard (plan 20, T34): every tile and chart links to its data page. */
@Component({
  selector: 'app-reports-dashboard',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent, StatTileComponent, ChartCardComponent, PrintSheetComponent],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.dashboard.title' | translate">
        <app-page-header [title]="'report.dashboard.title' | translate">
          <div actions>
            <p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" />
          </div>
        </app-page-header>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/treasury" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'report.dashboard.treasury_balance'" [value]="d.treasury_balance" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/expenses" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'report.dashboard.total_expenses'" [value]="d.total_spent" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/custodies" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'report.dashboard.active_custodies'" [value]="d.active_custodies" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'report.dashboard.social_cases'" [value]="d.total_social_cases" />
          </a>
          <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'case.list.approved'" [value]="d.approved_cases" />
          </a>
          <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'case.list.pending'" [value]="d.pending_cases" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/reports/directions" [title]="'common.open_page' | translate">
            <app-chart-card [title]="'report.dashboard.charts.expenses_by_category'" [series]="categorySeries()" />
          </a>
          <a class="tile-link" routerLink="/cases" [title]="'common.open_page' | translate">
            <app-chart-card [title]="'report.dashboard.charts.cases'" [series]="casesSeries()" chartType="bar" />
          </a>
        </div>
        <div class="u-flex u-gap-2 u-mt-4 u-flex-wrap">
          <p-button [label]="'report.dashboard.governorates_card' | translate" size="small" [outlined]="true" routerLink="/reports/governorates" />
          <p-button [label]="'report.dashboard.view_custodies' | translate" size="small" [outlined]="true" routerLink="/custodies/tracking" />
          <p-button [label]="'report.dashboard.view_cases' | translate" size="small" [outlined]="true" routerLink="/cases" />
        </div>
      </app-print-sheet>
    }
  `,
})
export class ReportsDashboardPage {
  private http = inject(HttpClient);

  data = resource<ReportsData, void>({
    loader: () =>
      firstValueFrom(this.http.get<ItemResponse<ReportsData>>('/reports')).then((r) => r.data),
  });

  categorySeries = computed(() =>
    (this.data.value()?.expenses_by_category ?? [])
      .slice(0, 6)
      .map((c) => ({ label: c.name, value: Number(c.amount) })),
  );

  casesSeries = computed(() => {
    const d = this.data.value();
    return [
      { label: 'موافق عليها', value: d?.approved_cases ?? 0 },
      { label: 'معلقة', value: d?.pending_cases ?? 0 },
      { label: 'مرفوضة', value: d?.rejected_cases ?? 0 },
    ];
  });

  printPage(): void {
    window.print();
  }
}

interface ReportsData {
  treasury_balance: string; total_spent: string; active_custodies: number;
  total_social_cases: number; approved_cases: number; pending_cases: number; rejected_cases: number;
  expenses_by_category: { id: number; name: string; amount: string }[];
}
