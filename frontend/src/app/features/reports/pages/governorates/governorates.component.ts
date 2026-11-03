import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { DatePickerModule } from 'primeng/datepicker';
import { SelectModule } from 'primeng/select';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { ApiClient } from '@core/http/api-client';
import type { DirectionNode, ItemResponse, Lookups } from '@shared/models/api.models';

interface GovRow {
  label: string;
  standard: boolean;
  total: number; approved: number; pending: number; rejected: number;
  expenses_total: string;
  average: string;
}

interface GovData {
  governorates: GovRow[];
  grand_total: { total: number; approved: number; pending: number; rejected: number; expenses_total: string; average: string };
}

/**
 * Governorates report (T34, plan 20 — new page): one row per governorate.
 * Date range + direction filters (T3-110) mirror reports/governorates.blade.php —
 * they affect the expense amounts only, never the case counts.
 */
@Component({
  selector: 'app-governorates-report',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, DatePickerModule, SelectModule,
    PageHeaderComponent, PrintSheetComponent, MoneyPipe,
  ],
  template: `
    @if (data.value(); as d) {
      <app-print-sheet [title]="'report.governorates.title' | translate">
        <app-page-header [title]="'report.governorates.title' | translate">
          <div actions><p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" /></div>
        </app-page-header>

        <div class="filter-bar">
          <div class="filter-bar__row">
            <div class="field">
              <label class="field__label" for="gov-filter-direction">{{ 'report.filters.direction' | translate }}</label>
              <p-select appendTo="body" inputId="gov-filter-direction" name="direction"
                [options]="directionOptions()" optionLabel="label" optionValue="value"
                [ngModel]="direction()" (ngModelChange)="onDirection($event)"
                [ariaLabel]="'report.filters.direction' | translate"
              />
            </div>
            <div class="field">
              <label class="field__label" for="gov-filter-date">{{ 'report.filters.range' | translate }}</label>
              <p-datepicker appendTo="body" inputId="gov-filter-date" name="date" selectionMode="range"
                [showIcon]="true"
                [ngModel]="dateRange()" (ngModelChange)="onDateRange($event)"
                [ariaLabel]="'report.filters.range' | translate"
              />
            </div>
            <div class="filter-bar__spacer"></div>
            <p-button [label]="'common.reset' | translate" size="small" [outlined]="true" (click)="resetFilters()" />
          </div>
          <p class="u-muted u-small">{{ 'report.governorates.filter_note' | translate }}</p>
        </div>

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
              <th>{{ 'report.governorates.col_average' | translate }}</th>
            </tr></thead>
            <tbody>
              @for (row of d.governorates; track row.label) {
                <tr class="ledger-table__row">
                  <td>
                    <a [routerLink]="['/cases']" [queryParams]="{ governorate: row.label }">{{ row.label }}</a>
                    @if (!row.standard) {
                      <span class="stamp">{{ 'report.governorates.non_standard' | translate }}</span>
                    }
                  </td>
                  <td class="ledger-table__cell-num">{{ row.total }}</td>
                  <td class="ledger-table__cell-num">{{ row.approved }}</td>
                  <td class="ledger-table__cell-num">{{ row.pending }}</td>
                  <td class="ledger-table__cell-num">{{ row.rejected }}</td>
                  <td class="ledger-table__cell-num">{{ row.expenses_total | money }}</td>
                  <td class="ledger-table__cell-num">{{ row.average | money }}</td>
                </tr>
              } @empty {
                <tr><td colspan="7" class="u-muted">{{ 'report.governorates.empty' | translate }}</td></tr>
              }
            </tbody>
            <tfoot>
              <tr class="ledger-table__row">
                <th>{{ 'common.total' | translate }}</th>
                <td class="ledger-table__cell-num">{{ d.grand_total.total }}</td>
                <td class="ledger-table__cell-num">{{ d.grand_total.approved }}</td>
                <td class="ledger-table__cell-num">{{ d.grand_total.pending }}</td>
                <td class="ledger-table__cell-num">{{ d.grand_total.rejected }}</td>
                <td class="ledger-table__cell-num">{{ d.grand_total.expenses_total | money }}</td>
                <td class="ledger-table__cell-num">{{ d.grand_total.average | money }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </app-print-sheet>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class GovernoratesPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  params = signal<{ direction: string | null; item: string | null; date_from: string; date_to: string }>({
    direction: null, item: null, date_from: '', date_to: '',
  });

  data = resource<GovData, unknown>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<GovData>>('/reports/governorates', { params: cleanParams(this.params()) }),
      ).then((r) => r.data),
  });

  /** reference data for the direction filter (cacheable endpoint) */
  lookups = resource<Lookups, unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()),
  });

  directionOptions = computed(() => {
    const options: { label: string; value: string }[] = [
      { label: this.translate.instant('report.filters.all'), value: 'all' },
    ];
    const walk = (nodes: DirectionNode[], depth: number): void => {
      for (const node of nodes) {
        options.push({ label: `${'— '.repeat(depth)}${node.name}`, value: `c:${node.id}` });
        walk(node.children, depth + 1);
        for (const item of node.items) {
          options.push({ label: `${'— '.repeat(depth + 1)}${item.name}`, value: `i:${item.id}` });
        }
      }
    };
    walk(this.lookups.value()?.direction_tree ?? [], 0);
    return options;
  });

  /** UI-only models for controls whose raw value isn't a query param */
  direction = signal('all');
  dateRange = signal<Date[]>([]);

  onDirection(value: string): void {
    this.direction.set(value);
    if (value === 'all') {
      this.setParams({ direction: null, item: null });
    } else if (value.startsWith('c:')) {
      this.setParams({ direction: value.slice(2), item: null });
    } else if (value.startsWith('i:')) {
      this.setParams({ direction: null, item: value.slice(2) });
    }
  }

  /** apply the picked range once both ends are chosen (or when cleared) */
  onDateRange(value: Date[] | undefined): void {
    const range = value ?? [];
    this.dateRange.set(range);
    if (range.length === 2) {
      this.setParams({ date_from: toIsoDate(range[0]), date_to: toIsoDate(range[1]) });
    } else if (range.length === 0) {
      this.setParams({ date_from: '', date_to: '' });
    }
  }

  resetFilters(): void {
    this.direction.set('all');
    this.dateRange.set([]);
    this.setParams({ direction: null, item: null, date_from: '', date_to: '' });
  }

  private setParams(patch: Partial<{ direction: string | null; item: string | null; date_from: string; date_to: string }>): void {
    this.params.update((p) => ({ ...p, ...patch }));
    this.data.reload();
  }

  printPage(): void {
    window.print();
  }
}

function cleanParams(params: Record<string, string | null>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== '') {
      result[key] = value;
    }
  }
  return result;
}

function toIsoDate(date: Date): string {
  const copy = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return copy.toISOString().slice(0, 10);
}
