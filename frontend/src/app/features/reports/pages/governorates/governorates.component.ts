import { computed, ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ItemResponse } from '@shared/models/api.models';

interface GovRow { name: string; total: number; approved: number; pending: number; rejected: number; expenses_total: string; average?: string }

/** Governorates report (T34, plan 20 — new page): one row per governorate. */
@Component({
  selector: 'app-governorates-report',
  standalone: true,
  imports: [TranslatePipe, RouterLink, ButtonModule, PageHeaderComponent, PrintSheetComponent, StatTileComponent, MoneyPipe],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.governorates.title' | translate">
        <app-page-header [title]="'report.governorates.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>
        <p class="u-muted u-small u-mb-4">{{ 'report.governorates.click_hint' | translate }}</p>
        <div class="ledger-table">
          <table>
            <thead><tr>
              <th>{{ 'report.governorates.col_governorate' | translate }}</th>
              <th>{{ 'report.governorates.col_total' | translate }}</th>
              <th>{{ 'report.governorates.col_approved' | translate }}</th>
              <th>{{ 'report.governorates.col_pending' | translate }}</th>
              <th>{{ 'report.governorates.col_rejected' | translate }}</th>
              <th>{{ 'report.governorates.col_spent' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.governorates; track row.name) {
                <tr class="ledger-table__row">
                  <td><a [routerLink]="['/cases']" [queryParams]="{ governorate: row.name }">{{ row.name }}</a></td>
                  <td class="ledger-table__cell-num">{{ row.total }}</td>
                  <td class="ledger-table__cell-num">{{ row.approved }}</td>
                  <td class="ledger-table__cell-num">{{ row.pending }}</td>
                  <td class="ledger-table__cell-num">{{ row.rejected }}</td>
                  <td class="ledger-table__cell-num">{{ row.expenses_total | money }}</td>
                </tr>
              } @empty {
                <tr><td colspan="6" class="u-muted">{{ 'report.governorates.empty' | translate }}</td></tr>
              }
            </tbody>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
})
export class GovernoratesPage {
  private http = inject(HttpClient);
  data = resource<{ governorates: GovRow[]; grand_total: Record<string, number> }, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<{ governorates: GovRow[]; grand_total: Record<string, number> }>>('/reports/governorates')).then((r) => r.data),
  });
  printPage(): void {
    window.print();
  }
}
