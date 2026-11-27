import { ChangeDetectionStrategy, Component, computed, inject, input, resource, signal, viewChild } from '@angular/core';
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
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { journalMoneyClass, journalStampClass } from '@shared/lib/journal-style';
import { entityColor } from '@shared/lib/chart-colors';
import type { ItemResponse, ListResponse } from '@shared/models/api.models';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { Router } from '@angular/router';
import { DatetimeCellComponent } from '@shared/ui/datetime-cell/datetime-cell.component';

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
  imports: [DatetimeCellComponent, MenuModule,
    TranslatePipe, FormsModule, ButtonModule, SelectModule, PageHeaderComponent,
    AsyncPageComponent, StatTileComponent, DataTableComponent, TableCellDirective,
    EmptyStateComponent, PrintSheetComponent, ConfirmDeleteComponent, MoneyPipe, DatetimePipe, EnumLabelPipe, ActionButtonComponent,
    IconComponent,
  ],
  template: `
    <app-print-sheet [title]="'treasury.detail.title' | translate">
      <app-page-header [title]="headerTitle()" [subtitle]="safeValue()?.notes ?? ''" accent="treasury" iconName="building-bank">
        <div actions class="u-flex u-gap-2 u-flex-wrap">
          <!-- T6-10: max 3 visible buttons; back lives on the async-page backLink -->
          <app-action-button kind="print" label="common.print" size="small" (action)="print()" />
          <app-action-button kind="edit" label="common.edit" size="small" [route]="['/treasury', id(), 'edit']" />
          <app-action-button kind="more" label="common.more" size="small" (action)="moreMenu.toggle($event)" />
          <p-menu #moreMenu appendTo="body" [model]="moreMenuItems()" popup="true" />
        </div>
      </app-page-header>

      <app-async-page [resource]="pageResource" variant="detail" [backLink]="['/treasury']">
        @if (safeValue(); as treasury) {
          <!-- RD-5.2: the detail pattern — card-hero facts + tinted money cards -->
          <div class="detail-grid u-mb-4">
            <section class="card-hero">
              <header class="card-hero__band">
                <span>{{ 'treasury.detail.facts' | translate }}</span>
                <app-icon name="info" [size]="18" />
              </header>
              <div class="card-hero__body">
                <div class="key-value-list">
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'treasury.list.name' | translate }}</span>
                    <span class="key-value-list__value u-flex u-center-y u-gap-1">
                      <!-- N-07: the treasury's own colour — the same dot beside its row in the list -->
                      <span class="entity-dot" aria-hidden="true" [style.background]="entityColor(treasury.id)"></span>
                      <span>{{ treasury.name }}</span>
                    </span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'treasury.list.balance' | translate }}</span>
                    <span class="key-value-list__value money money--link u-bidi u-fw-bold">{{ treasury.balance | money }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'common.notes' | translate }}</span>
                    <span class="key-value-list__value">{{ treasury.notes ?? '—' }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'common.date' | translate }}</span>
                    <span class="key-value-list__value u-bidi">{{ treasury.created_at | datetime: false }}</span>
                  </div>
                </div>
              </div>
            </section>

            <section class="chart-card">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'treasury.detail.stats' | translate }}</span></div>
              <div class="kpi-grid">
                <app-stat-tile [label]="'treasury.detail.total_in'" [value]="treasury.stats.total_in" [money]="true" tone="success" iconName="received" accent="treasury" />
                <app-stat-tile [label]="'treasury.detail.total_out'" [value]="treasury.stats.total_out" [money]="true" tone="danger" iconName="sent" accent="treasury" />
                <app-stat-tile [label]="'treasury.list.transactions_count'" [value]="treasury.stats.total_transactions" iconName="history" accent="treasury" />
              </div>
            </section>
          </div>

          <app-data-table [phoneFirst]="['date', 'type', 'amount']"
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
            <ng-template appCell="date" let-row><app-datetime-cell [value]="row.date" /></ng-template>
            <ng-template appCell="type" let-row><span class="stamp" [class]="stampClass(row.type)">{{ row.type | enumLabel: 'transaction_type' }}</span></ng-template>
            <ng-template appCell="description" let-row>{{ row.description }}</ng-template>
            <ng-template appCell="amount" let-row><span class="u-bidi" [class]="moneyClass(row.type)">{{ row.amount | money: false }}</span></ng-template>
            <ng-template appCell="user" let-row>{{ row.user?.name ?? '—' }}</ng-template>
            <div empty>
              <app-empty-state [title]="'treasury.detail.empty' | translate" [text]="'common.no_data' | translate" accent="treasury" iconName="building-bank" />
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

  /** RD-5.2: journal-ledger pill + signed money colors for the transactions table */
  readonly stampClass = journalStampClass;

  /** N-07: this treasury's own colour for the dot beside its name */
  entityColor = entityColor;
  readonly moneyClass = journalMoneyClass;

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
