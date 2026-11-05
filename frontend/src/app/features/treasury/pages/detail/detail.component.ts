import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { SelectModule } from 'primeng/select';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import type { ItemResponse, ListResponse } from '@shared/models/api.models';

interface TreasuryDetail {
  id: number;
  name: string;
  balance: string;
  notes: string | null;
  created_at: string | null;
  stats: { total_in: string; total_out: string; total_transactions: number };
  transactions: ListResponse<TransactionRow>;
}

interface TransactionRow {
  id: number;
  type: string;
  amount: string;
  description: string | null;
  user: string | null;
  date: string | null;
}

/** Treasury details and transactions (plan 20: replaces /treasury/{id}). */
@Component({
  selector: 'app-treasury-detail',
  standalone: true,
  imports: [MenuModule, 
    TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, PageHeaderComponent,
    AsyncPageComponent, StatTileComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, PrintSheetComponent, ConfirmDeleteComponent, MoneyPipe, DatetimePipe, EnumLabelPipe,
  ],
  template: `
    <app-print-sheet [title]="'treasury.detail.title' | translate">
      <app-page-header [title]="headerTitle()" [subtitle]="safeValue()?.notes ?? ''">
        <div actions class="u-flex u-gap-2 u-flex-wrap">
          <!-- T6-10: max 3 visible buttons; back lives on the async-page backLink -->
          <p-button [label]="'common.print' | translate" size="small" severity="secondary" [outlined]="true" (click)="print()" />
          <p-button [label]="'common.edit' | translate" size="small" severity="secondary" [outlined]="true" [routerLink]="['/treasury', id(), 'edit']" />
          <p-button [label]="'common.more' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="moreMenu.toggle($event)" />
          <p-menu #moreMenu appendTo="body" [model]="moreMenuItems()" popup="true" />
        </div>
      </app-page-header>

      <app-async-page [resource]="pageResource" variant="detail" [backLink]="['/treasury']">
        @if (safeValue(); as treasury) {
          <div class="kpi-grid">
            <app-stat-tile [label]="'treasury.list.balance'" [value]="treasury.balance" [money]="true" />
            <app-stat-tile [label]="'treasury.detail.total_in'" [value]="treasury.stats.total_in" [money]="true" />
            <app-stat-tile [label]="'treasury.detail.total_out'" [value]="treasury.stats.total_out" [money]="true" />
            <app-stat-tile [label]="'treasury.list.transactions_count'" [value]="treasury.stats.total_transactions" />
          </div>

          <app-data-table
            [title]="'treasury.detail.transactions' | translate"
            [columns]="columns"
            [rows]="treasury.transactions.data"
            [total]="treasury.transactions.meta.total"
            [perPage]="params().per_page"
            (pageChange)="onPage($event)"
          >
            <div toolbar class="u-flex u-gap-2">
              <p-select appendTo="body"
                [options]="typeOptions"
                optionLabel="label"
                optionValue="value"
                [(ngModel)]="typeFilter" name="typeFilter"
                (ngModelChange)="onTypeChange()"
                [placeholder]="'common.type' | translate"
                size="small"
              />
            </div>
            <ng-template appTableBody let-row>
              <tr class="ledger-table__row">
                <td appTableCol="date" class="u-bidi">{{ row.date | datetime }}</td>
                <td appTableCol="type">{{ row.type | enumLabel: 'transaction_type' }}</td>
                <td appTableCol="description">{{ row.description }}</td>
                <td appTableCol="amount" class="ledger-table__cell-num">{{ row.amount | money }}</td>
                <td appTableCol="user">{{ row.user?.name ?? '—' }}</td>
              </tr>
              <tr class="ledger-table__stacked">
                <div class="ledger-table__stacked-item">
                  <span class="ledger-table__stacked-label">{{ 'treasury.detail.col_date' | translate }}</span>
                  <span class="u-bidi">{{ row.date | datetime }}</span>
                </div>
                <div class="ledger-table__stacked-item">
                  <span class="ledger-table__stacked-label">{{ 'treasury.detail.col_type' | translate }}</span>
                  <span>{{ row.type | enumLabel: 'transaction_type' }}</span>
                </div>
                <div class="ledger-table__stacked-item">
                  <span class="ledger-table__stacked-label">{{ 'common.amount' | translate }}</span>
                  <span class="u-bidi">{{ row.amount | money }}</span>
                </div>
                <div class="ledger-table__stacked-item">
                  <span class="ledger-table__stacked-label">{{ 'treasury.detail.col_description' | translate }}</span>
                  <span>{{ row.description }}</span>
                </div>
              </tr>
            </ng-template>
            <div empty>
              <app-empty-state [title]="'treasury.detail.empty' | translate" [text]="'common.no_data' | translate" />
            </div>
          </app-data-table>

          <!-- T3-40: the server blocks deletion when the treasury has movements or balance — its message surfaces -->
          <app-confirm-delete
            #deleteDlg
            [path]="'/treasury/' + id() + '/delete'"
            [entity]="safeValue()?.name ?? ''"
            (deleted)="onDeleted()"
          />
        }
      </app-async-page>
    </app-print-sheet>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TreasuryDetailPage {
  /** T6-10: transfer + delete live in the header More menu */
  readonly moreMenuItems = computed<MenuItem[]>(() => [
    { label: this.translate.instant('treasury.list.transfer'), routerLink: '/treasury/transfer' },
    { label: this.translate.instant('common.delete'), command: () => this.deleteDlg()?.open() },
  ]);

  private http = inject(HttpClient);
  private router = inject(Router);
  private translate = inject(TranslateService);

  id = input.required<number>();
  typeFilter = signal('all');
  params = signal<{ page: number; per_page: number }>({ page: 1, per_page: 25 });

  /** the delete dialog lives inside the loaded branch — reach it via viewChild */
  deleteDlg = viewChild(ConfirmDeleteComponent);

  readonly tableKey = 'detail.component';

  readonly columns = [
    { field: 'date', header: 'treasury.detail.col_date', sortable: true },
    { field: 'type', header: 'treasury.detail.col_type', sortable: true },
    { field: 'description', header: 'treasury.detail.col_description', sortable: true },
    { field: 'amount', header: 'common.amount', numeric: true, sortable: true },
    { field: 'user', searchField: 'user.name', header: 'treasury.detail.col_user', sortable: true },
  ];

  /** type filter labels translated (T3-40: no hard-coded Arabic) */
  readonly typeOptions = [
    { label: this.translate.instant('treasury.detail.type_all'), value: 'all' },
    { label: this.translate.instant('enums.transaction_type.donation'), value: 'donation' },
    { label: this.translate.instant('enums.transaction_type.custody_out'), value: 'custody_out' },
    { label: this.translate.instant('enums.transaction_type.custody_return'), value: 'custody_return' },
    { label: this.translate.instant('enums.transaction_type.expense'), value: 'expense' },
    { label: this.translate.instant('enums.transaction_type.transfer_in'), value: 'transfer_in' },
    { label: this.translate.instant('enums.transaction_type.transfer_out'), value: 'transfer_out' },
  ];

  data = resource<TreasuryDetail, Record<string, unknown>>({
    loader: () =>
      firstValueFrom(
        this.http.get<ItemResponse<TreasuryDetail>>(`/treasury/${this.id()}`, {
          params: clean({ id: this.id(), ...this.params(), type: this.typeFilter() }),
        }),
      ).then((res: ItemResponse<TreasuryDetail>) => res.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** T4-20: a non-throwing resource view — raw ResourceRef.value() throws on a failed load */
  readonly pageResource: LoadableResource = {
    value: () => this.safeValue() ?? undefined,
    error: () => {
      try {
        return this.data.error();
      } catch {
        return undefined;
      }
    },
    isLoading: () => this.data.isLoading(),
    reload: () => {
      this.data.reload();
    },
  };

  /** header title outside the async states: the treasury's name once loaded, the generic key otherwise */
  headerTitle(): string {
    return this.safeValue()?.name ?? 'treasury.detail.title';
  }

  onTypeChange(): void {
    this.params.update((p) => ({ ...p, page: 1 }));
    this.reload();
  }

  reload(): void {
    this.data.reload();
  }

  onPage(event: { page: number; per_page: number }): void {
    this.params.update((p) => ({ ...p, page: event.page, per_page: event.per_page }));
    this.reload();
  }

  print(): void {
    window.print();
  }

  onDeleted(): void {
    void this.router.navigateByUrl('/treasury');
  }
}

function clean(params: Record<string, string | number>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== 'all' && value !== null && value !== undefined) {
      result[key] = String(value);
    }
  }
  return result;
}
