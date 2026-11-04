// HR dashboard (plan 20). T4-20: async-page states; every tile deep-links the
// list it summarizes (the HR lists read no query params, so links are plain).
import { ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import type { ItemResponse } from '@shared/models/api.models';

interface HrData {
  employees_count: number; active_employees: number; total_salaries: string;
  pending_salaries: number; approved_salaries: number; recorded_salaries: number; today_attendance: number;
}

@Component({
  selector: 'app-hr-dashboard',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, EmptyStateComponent, StatTileComponent,
  ],
  template: `
    <app-page-header [title]="'hr.dashboard.title' | translate">
      <div actions class="u-flex u-gap-2">
        <p-button [label]="'hr.dashboard.view_employees' | translate" size="small" [outlined]="true" routerLink="/hr/employees" />
        <p-button [label]="'hr.dashboard.view_attendance' | translate" size="small" [outlined]="true" routerLink="/hr/attendance" />
        <p-button [label]="'hr.dashboard.view_kpi' | translate" size="small" [outlined]="true" routerLink="/hr/kpi" />
      </div>
    </app-page-header>

    <app-async-page [resource]="data" variant="list" [empty]="isEmpty" [backLink]="['/dashboard']">
      @if (data.value(); as d) {
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/hr/salaries" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.total_salaries'" [value]="d.total_salaries" [money]="true" />
          </a>
          <a class="tile-link" routerLink="/hr/employees" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.employees'" [value]="d.employees_count" />
          </a>
          <a class="tile-link" routerLink="/hr/employees" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.active_employees'" [value]="d.active_employees" />
          </a>
        </div>
        <div class="kpi-grid">
          <a class="tile-link" routerLink="/hr/salaries" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.pending_salaries'" [value]="d.pending_salaries" />
          </a>
          <a class="tile-link" routerLink="/hr/salaries" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.approved_salaries'" [value]="d.approved_salaries" />
          </a>
          <a class="tile-link" routerLink="/hr/salaries" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.recorded_salaries'" [value]="d.recorded_salaries" />
          </a>
          <a class="tile-link" routerLink="/hr/attendance" [title]="'common.open_page' | translate">
            <app-stat-tile [label]="'hr.dashboard.today_attendance'" [value]="d.today_attendance" />
          </a>
        </div>
      }
      <div empty>
        <app-empty-state [title]="'hr.dashboard.empty'" [text]="'common.no_data'" />
      </div>
    </app-async-page>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HrDashboardPage {
  private http = inject(HttpClient);
  data = resource<HrData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<HrData>>('/hr/dashboard')).then((r) => r.data),
  });

  /** every figure derives from registered employees — none means nothing to summarize */
  isEmpty = (): boolean => (this.data.value()?.employees_count ?? 0) === 0;
}
