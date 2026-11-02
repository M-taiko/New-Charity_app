import { ChangeDetectionStrategy, Component, computed, inject, resource, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { SelectModule } from 'primeng/select';
import { InputNumberModule } from 'primeng/inputnumber';
import { TextareaModule } from 'primeng/textarea';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { HttpErrorResponse } from '@angular/common/http';
import { ActionResponse, ListResponse } from '@shared/models/api.models';

interface TreasuryRow {
  id: number;
  name: string;
  balance: string;
  transactions_count?: number;
  created_at: string | null;
  notes: string | null;
}

/** Treasuries list (plan 20: replaces /treasury). T36: no average-balance card. */
@Component({
  selector: 'app-treasury-list',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, FormsModule, ButtonModule, InputTextModule, SelectModule,
    InputNumberModule, TextareaModule, PageHeaderComponent, StatTileComponent,
    DataTableComponent, TableBodyDirective, TableColDirective, EmptyStateComponent, FormDialogComponent,
    IconComponent, MoneyPipe, DatetimePipe,
  ],
  template: `
    <app-page-header [title]="'treasury.list.title' | translate">
      <div actions class="u-flex u-gap-2">
        <p-button [label]="'treasury.list.transfer' | translate" [outlined]="true" size="small" routerLink="/treasury/transfer" />
        <p-button [label]="'treasury.list.receive_donation' | translate" size="small" (onClick)="donationDialog.open()" />
        <p-button [label]="'treasury.list.add' | translate" size="small" routerLink="/treasury/new" />
      </div>
    </app-page-header>

    @if (data.isLoading() && !data.value()) {
      <div class="kpi-grid">
        @for (s of [1, 2]; track s) {
          <div class="skeleton skeleton--tile"></div>
        }
      </div>
    } @else if (data.value(); as page) {
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
            <td appTableCol="actions">
              <div class="ledger-table__actions">
                <p-button [text]="true" size="small" [routerLink]="['/treasury', row.id, 'edit']" [ariaLabel]="'common.edit' | translate">
                  <ng-template pTemplate="icon"><app-icon name="pencil" [size]="14" /></ng-template>
                </p-button>
              </div>
            </td>
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
          </tr>
        </ng-template>
        <div empty>
          <app-empty-state [title]="'treasury.list.empty' | translate" [text]="'treasury.list.empty_text' | translate">
            <p-button [label]="'treasury.list.add' | translate" size="small" routerLink="/treasury/new" />
          </app-empty-state>
        </div>
      </app-data-table>
    }

    <app-form-dialog
      #donationDialog
      [header]="'treasury.donation.title' | translate"
      [submitLabel]="'treasury.donation.title' | translate"
      [sending]="sending()"
      [error]="actionError()"
      (submit)="submitDonation()"
    >
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="don-treasury">{{ 'custody.create.treasury' | translate }}</label>
          <p-select appendTo="body"
            inputId="don-treasury"
            [options]="treasuries() ?? []"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="donation.treasury_id" name="treasury_id"
            styleClass="u-full"
          />
        </div>
        <div class="field">
          <label class="field__label" for="don-amount">{{ 'treasury.donation.amount' | translate }}</label>
          <p-inputnumber inputId="don-amount" [(ngModel)]="donation.amount" name="amount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label">{{ 'treasury.donation.source' | translate }}</label>
          <p-select appendTo="body"
            inputId="don-source"
            [options]="sources ?? []"
            optionLabel="label"
            optionValue="value"
            [(ngModel)]="donation.source" name="source"
          />
          @if (donation.source === 'external') {
            <input pInputText id="don-external" [(ngModel)]="donation.external_name" name="external_name" [placeholder]="'treasury.donation.external_name' | translate" style="width: 100%; margin-block-start: 8px" />
          }
        </div>
        <div class="field">
          <label class="field__label" for="don-notes">{{ 'treasury.donation.description' | translate }}</label>
          <textarea pTextarea id="don-notes" rows="2" [(ngModel)]="donation.description" name="description" style="width: 100%"></textarea>
        </div>
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

  readonly tableKey = 'list.component';

  readonly columns = [
    { field: 'name', header: 'treasury.list.name', sortable: true },
    { field: 'balance', header: 'treasury.list.balance', numeric: true, sortable: true },
    { field: 'transactions_count', header: 'treasury.list.transactions_count', numeric: true },
    { field: 'created_at', header: 'treasury.list.created_at', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: true },
  ];

  readonly sources = [
    { label: 'من الشركة', value: 'company' },
    { label: 'من جهة خارجية', value: 'external' },
    { label: 'مردود/مرتجع', value: 'returnings' },
  ];

  params = signal<{ page: number; per_page: number; q: string; sort?: string }>({ page: 1, per_page: 25, q: '' });
  sending = signal(false);
  actionError = signal<HttpErrorResponse | null>(null);

  donation = { treasury_id: null as number | null, amount: null as number | null, source: 'company', external_name: '', description: '' };

  data = resource<ListResponse<TreasuryRow>, { page: number; per_page: number; q: string }>({
    loader: () =>
      firstValueFrom(
        this.http.get<ListResponse<TreasuryRow>>('/treasury', {
          params: cleanParams(this.params()),
        }),
      ),
  });

  treasuries = computed(() => this.data.value()?.data ?? []);

  summaryCount(): number {
    return Number(this.data.value()?.meta.summary?.treasuries_count ?? 0);
  }

  summaryBalance(): string {
    return String(this.data.value()?.meta.summary?.total_balance ?? '0');
  }

  balanceAfter(): string {
    const treasury = this.treasuries().find((t) => t.id === this.donation.treasury_id);
    const base = parseFloat(treasury?.balance ?? '0') || 0;
    return (base + (this.donation.amount ?? 0)).toFixed(2);
  }

  onSearch(term: string): void {
    this.params.update((p) => ({ ...p, q: term, page: 1 }));
    this.data.reload();
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
    this.http
      .post<ActionResponse>(`/treasury/${this.donation.treasury_id}/add-donation`, {
        amount: this.donation.amount,
        source: this.donation.source,
        external_source: this.donation.external_name || undefined,
        description: this.donation.description,
      })
      .subscribe({
        next: (res) => {
          this.sending.set(false);
          this.toast.success(res.message);
          this.donation = { treasury_id: null, amount: null, source: 'company', external_name: '', description: '' };
          this.data.reload();
        },
        error: (err: HttpErrorResponse) => {
          this.sending.set(false);
          this.actionError.set(err);
        },
      });
  }


}

function cleanParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '') {
      result[key] = String(value);
    }
  }
  return result;
}
