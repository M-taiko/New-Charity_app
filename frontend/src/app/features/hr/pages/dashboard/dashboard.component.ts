// HR dashboard (plan 20).
import { computed, ChangeDetectionStrategy, Component, inject, resource } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ItemResponse } from '@shared/models/api.models';

interface HrData {
  employees_count: number; active_employees: number; total_salaries: string;
  pending_salaries: number; approved_salaries: number; recorded_salaries: number; today_attendance: number;
}

@Component({
  selector: 'app-hr-dashboard',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent, StatTileComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-page-header [title]="'hr.dashboard.title' | translate">
        <div actions class="u-flex u-gap-2">
          <p-button [label]="'hr.dashboard.view_employees' | translate" size="small" [outlined]="true" routerLink="/hr/employees" />
          <p-button [label]="'hr.dashboard.view_attendance' | translate" size="small" [outlined]="true" routerLink="/hr/attendance" />
          <p-button [label]="'hr.dashboard.view_kpi' | translate" size="small" [outlined]="true" routerLink="/hr/kpi" />
        </div>
      </app-page-header>
      <div class="kpi-grid">
        <app-stat-tile [label]="'hr.dashboard.total_salaries'" [value]="d.total_salaries" [money]="true" />
        <app-stat-tile [label]="'hr.dashboard.employees'" [value]="d.employees_count" />
        <app-stat-tile [label]="'hr.dashboard.active_employees'" [value]="d.active_employees" />
      </div>
      <div class="kpi-grid">
        <app-stat-tile [label]="'hr.dashboard.pending_salaries'" [value]="d.pending_salaries" />
        <app-stat-tile [label]="'hr.dashboard.approved_salaries'" [value]="d.approved_salaries" />
        <app-stat-tile [label]="'hr.dashboard.recorded_salaries'" [value]="d.recorded_salaries" />
        <app-stat-tile [label]="'hr.dashboard.today_attendance'" [value]="d.today_attendance" />
      </div>
    }
  `,
})
export class HrDashboardPage {
  private http = inject(HttpClient);
  data = resource<HrData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<HrData>>('/hr/dashboard')).then((r) => r.data),
  });
}
