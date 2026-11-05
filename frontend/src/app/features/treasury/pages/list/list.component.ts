import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent, type RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { HttpErrorResponse } from '@angular/common/http';
import type { ActionResponse, ListResponse } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface TreasuryRow {
  id: number;
  name: string;
  balance: string;
  transactions_count?: number;
  created_at: string | null;
  notes: string | null;
}

/**
 * Treasuries list (plan 20: replaces /treasury, T3-40): row actions
 * (view/edit/delete/donation/transfer) and the donation dialog with the
 * three sources the API accepts (company/external/returnings).
 */
@Component({
  selector: 'app-treasury-list',
  standalone: true,
  imports: [SectionNavComponent, 
    FieldComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, SelectModule,
    InputNumberModule, TextareaModule, PageHeaderComponent, AsyncPageComponent, StatTileComponent,
    DataTableComponent, TableBodyDirective, TableColDirective, EmptyStateComponent, FormDialogComponent,
    ConfirmDeleteComponent, RowActionsComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    <app-page-header [title]="'treasury.list.title' | translate">
      <div actions class="u-flex u-gap-2 u-flex-wrap">
        <p-button [label]="'treasury.list.transfer' | translate" [outlined]="true" size="small" routerLink="/treasury/transfer" />
        <p-button [label]="'treasury.list.receive_donation' | translate" size="small" (onClick)="openDonation(null)" />
        <p-button [label]="'treasury.list.add' | translate" size="small" routerLink="/treasury/new" />
      </div>
    </app-page-header>

    <app-section-nav module="treasury" />

    <app-async-page [resource]="pageResource" variant="list" [empty]="isEmpty">
      @if (data.value(); as page) {
        <div class="kpi-grid">
          <app-stat-tile [label]="'treasury.list.count'" [value]="summaryCount()" />
          <app-stat-tile [label]="'treasury.list.total_balance'" [value]="summaryBalance()" [money]="true" />
        </div>

        <app-data-table
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2">
          </div>
          <ng-template appTableBody let-row>
            <tr class="ledger-table__row">
              <td appTableCol="name">
                <a [routerLink]="['/treasury', row.id]">{{ row.name }}</a>
                @if (row.notes) {
                  <div class="u-muted u-small u-truncate">{{ row.notes }}</div>
                }
              </td>
              <td appTableCol="balance" class="ledger-table__cell-num">{{ row.balance | money }}</td>
              <td appTableCol="transactions_count" class="ledger-table__cell-num">{{ row.transactions_count ?? '—' }}</td>
              <td appTableCol="created_at" class="u-bidi">{{ row.created_at | datetime: false }}</td>
              <td appTableCol="actions"><app-row-actions [actions]="actionsFor(row)" /></td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'treasury.list.name' | translate }}</span>
                <a [routerLink]="['/treasury', row.id]">{{ row.name }}</a>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'treasury.list.balance' | translate }}</span>
                <span class="u-bidi">{{ row.balance | money }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <app-row-actions [actions]="actionsFor(row)" />
              </div>
            </tr>
          </ng-template>
        </app-data-table>
      }
      <div empty>
        <app-empty-state [title]="'treasury.list.empty' | translate" [text]="'treasury.list.empty_text' | translate">
          <p-button [label]="'treasury.list.add' | translate" size="small" routerLink="/treasury/new" />
        </app-empty-state>
      </div>
    </app-async-page>

    <!-- T3-40: the server blocks deletion of a treasury with movements/balance — its message surfaces -->
    <app-confirm-delete #deleteDlg [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />

    <app-form-dialog
      [header]="'treasury.donation.title' | translate"
      [submitLabel]="'treasury.donation.title' | translate"
      [sending]="sending()"
      [error]="actionError()"
      [(visible)]="donationOpen"
      (submit)="submitDonation()"
    >
      <div class="u-column u-gap-3">
        <app-field label="custody.create.treasury" controlId="don-treasury" [required]="true">
          <p-select appendTo="body"
            inputId="don-treasury"
            [options]="treasuries()"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="donation.treasury_id" name="treasury_id"
            styleClass="u-full"
          />
        </app-field>
        <app-field label="treasury.donation.amount" controlId="don-amount" [required]="true">
          <p-inputnumber inputId="don-amount" [(ngModel)]="donation.amount" name="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </app-field>
        <app-field label="treasury.donation.source" controlId="don-source" [required]="true">
          <p-select appendTo="body"
            inputId="don-source"
            [options]="sources"
            optionLabel="label"
            optionValue="value"
            [(ngModel)]="donation.source" name="source"
          />
          @if (donation.source === 'external') {
            <input pInputText id="don-external" class="u-full u-mt-2" [(ngModel)]="donation.external_name" name="external_name" [placeholder]="'treasury.donation.external_name' | translate" />
          }
        </app-field>
        <app-field label="treasury.donation.description" controlId="don-notes" [required]="true">
          <textarea pTextarea id="don-notes" rows="2" class="u-full" [(ngModel)]="donation.description" name="description"></textarea>
        </app-field>
        @if (donation.treasury_id && donation.amount) {
          <p class="field__hint">
            {{ 'treasury.donation.balance_after' | translate }}:
            {{ balanceAfter() | money }}
          </p>
        }
      </div>
    </app-form-dialog>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TreasuryListPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  readonly deleteDlg = viewChild.required(ConfirmDeleteComponent);

  readonly tableKey = 'list.component';

  readonly columns = [
    { field: 'name', header: 'treasury.list.name', sortable: true },
    { field: 'balance', header: 'treasury.list.balance', numeric: true, sortable: true },
    { field: 'transactions_count', header: 'treasury.list.transactions_count', numeric: true },
    { field: 'created_at', header: 'treasury.list.created_at', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  /** the three sources TreasuryController@addDonation accepts (T3-40) */
  readonly sources = [
    { label: this.translate.instant('treasury.donation.source_company'), value: 'company' },
    { label: this.translate.instant('treasury.donation.source_external'), value: 'external' },
    { label: this.translate.instant('treasury.donation.source_return'), value: 'returnings' },
  ];

  params = signal<{ page: number; per_page: number; q: string; sort?: string }>({ page: 1, per_page: 25, q: '' });
  sending = signal(false);
  donationOpen = signal(false);
  actionError = signal<HttpErrorResponse | null>(null);

  deletePath = signal('');
  deleteEntity = signal('');

  donation = { treasury_id: null as number | null, amount: null as number | null, source: 'company', external_name: '', description: '' };

  data = resource<ListResponse<TreasuryRow>, { page: number; per_page: number; q: string }>({
    loader: () =>
      firstValueFrom(
        this.http.get<ListResponse<TreasuryRow>>('/treasury', {
          params: cleanParams(this.params()),
        }),
      ),
  });

  /** T4-20: a non-throwing resource view — raw ResourceRef.value() throws on a failed load */
  readonly pageResource: LoadableResource = {
    value: () => this.safeValue(),
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

  private safeValue(): ListResponse<TreasuryRow> | undefined {
    try {
      return this.data.value();
    } catch {
      return undefined;
    }
  }

  isEmpty = (): boolean => (this.safeValue()?.data ?? []).length === 0;

  treasuries = computed(() => this.safeValue()?.data ?? []);

  summaryCount(): number {
    return Number(this.safeValue()?.meta.summary?.treasuries_count ?? 0);
  }

  summaryBalance(): string {
    return String(this.safeValue()?.meta.summary?.total_balance ?? '0');
  }

  balanceAfter(): string {
    const treasury = this.treasuries().find((t) => t.id === this.donation.treasury_id);
    const base = parseFloat(treasury?.balance ?? '0') || 0;
    return (base + (this.donation.amount ?? 0)).toFixed(2);
  }

  /** T4-24: view / donation / edit / transfer / delete (Blade actions) */
  actionsFor(row: TreasuryRow): RowActionItem[] {
    return [
      { icon: 'eye', label: 'common.view', route: ['/treasury', row.id] },
      { icon: 'wallet', label: 'treasury.list.receive_donation', handler: () => { this.openDonation(row); } },
      { icon: 'pencil', label: 'common.edit', route: ['/treasury', row.id, 'edit'] },
      { icon: 'arrows-exchange', label: 'treasury.list.transfer', route: ['/treasury', 'transfer'] },
      { icon: 'trash', label: 'common.delete', severity: 'danger', handler: () => { this.openDelete(row); } },
    ];
  }

  /** open the donation dialog — prefilled with the row's treasury when opened from a row */
  openDonation(row: TreasuryRow | null): void {
    this.actionError.set(null);
    this.donation = { treasury_id: row?.id ?? null, amount: null, source: 'company', external_name: '', description: '' };
    this.donationOpen.set(true);
  }

  /** T3-40: treasury delete — the server refuses when it has movements or balance */
  openDelete(row: TreasuryRow): void {
    this.deletePath.set(`/treasury/${row.id}/delete`);
    this.deleteEntity.set(row.name);
    this.deleteDlg().open();
  }

  onPage(event: { page: number; per_page: number; sort: string }): void {
    const current = this.params();
    if (current.page === event.page && current.per_page === event.per_page && (current as { sort?: string }).sort === event.sort) {
      return;
    }
    this.params.set({ ...current, page: event.page, per_page: event.per_page, sort: event.sort });
    this.data.reload();
  }

  submitDonation(): void {
    if (!this.donation.treasury_id || !this.donation.amount) {
      return;
    }
    this.sending.set(true);
    this.actionError.set(null);
    void firstValueFrom(this.http.post<ActionResponse>(`/treasury/${this.donation.treasury_id}/add-donation`, {
      amount: this.donation.amount,
      source: this.donation.source,
      external_source: this.donation.external_name || undefined,
      description: this.donation.description,
    }))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.donationOpen.set(false);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.actionError.set((err as HttpErrorResponse));
      });
  }
}

function cleanParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (typeof value === 'string' || typeof value === 'number') {
      if (value !== '') {
        result[key] = String(value);
      }
    }
  }
  return result;
}
