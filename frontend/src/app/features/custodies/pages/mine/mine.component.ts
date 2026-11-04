import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { DialogModule } from 'primeng/dialog';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { LedgerTableComponent, LedgerBodyDirective, LedgerColDirective } from '@shared/ui/ledger-table/ledger-table.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ReasonDialogComponent } from '@shared/ui/reason-dialog/reason-dialog.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import type { ItemResponse } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';

interface MineCustody {
  id: number; agent: string | null; treasury: string | null; amount: string;
  transferred_in: string; transferred_out: string; spent: string; returned: string;
  pending_return: string; frozen: string; remaining: string; spent_percent: number;
  status: string; status_detail: string; initiated_by: string; notes: string | null;
  created_at: string | null;
}

interface MineTransfer {
  id: number; custody_id: number; sender: string | null; receiver: string | null;
  amount: string; status: string; notes: string | null; created_at: string | null;
}

interface MyData {
  stats: Record<string, string | number>;
  custodies: MineCustody[];
  sent_transfers: MineTransfer[];
  received_transfers: MineTransfer[];
}

interface CustodyOption { id: number; label: string; remaining: string }

interface TxDetail {
  id: number;
  remaining: string;
  transactions: { id: number; type: string; amount: string; description: string | null; user: string | null; date: string | null }[];
}

type Tab = 'pending' | 'active' | 'transfers' | 'closed';

const OPEN_STATUSES = ['accepted', 'active', 'partially_returned', 'closed'];

/**
 * My custodies (plan 20, round 3 + T28 rev2 + T3-12): tabs for the lifecycle,
 * the Blade quick modals (donation, recovery, return to treasury, receive a
 * pending transfer), per-custody transactions drawer and the external receipt
 * dialog. Frozen shows only when > 0 (T26); the server re-checks every rule.
 */
@Component({
  selector: 'app-my-custodies',
  standalone: true,
  imports: [FieldComponent, TranslatePipe, RouterLink, FormsModule, DialogModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, PageHeaderComponent, AsyncPageComponent, LedgerTableComponent, LedgerBodyDirective, LedgerColDirective, StatTileComponent, EmptyStateComponent, FormDialogComponent, ReasonDialogComponent, StatusStampComponent, MoneyPipe, DatetimePipe, EnumLabelPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'custody.mine.title' | translate">
      <div actions class="u-flex u-gap-2 u-flex-wrap">
        @if (showQuickActions()) {
          <p-button [label]="'custody.mine.quick_donation' | translate" size="small" severity="success" [outlined]="true" (onClick)="donationDlg.open(); openDonation()" />
          @if (isAgent()) {
            <p-button [label]="'custody.mine.recover' | translate" size="small" severity="info" [outlined]="true" (onClick)="recoveryDlg.open(); openRecovery()" />
          }
          <p-button [label]="'custody.mine.return_to_treasury' | translate" size="small" severity="warn" [outlined]="true" (onClick)="refundDlg.open(); openRefund()" />
        }
        @if (showExternalReceipt()) {
          <p-button [label]="'custody.mine.external_receipt' | translate" size="small" (onClick)="externalDialog.open()" />
        }
        <p-button [label]="'custody.mine.request_new' | translate" size="small" routerLink="/custodies/new" />
      </div>
    </app-page-header>

    <app-async-page [resource]="data" variant="list" [empty]="isEmpty">
      @if (data.value(); as d) {
        <div class="kpi-grid">
          <app-stat-tile [label]="'custody.mine.remaining'" [value]="stat('total_remaining')" [money]="true" />
          <app-stat-tile [label]="'custody.mine.amount'" [value]="stat('total_amount')" [money]="true" />
          <app-stat-tile [label]="'custody.mine.spent'" [value]="stat('total_spent')" [money]="true" />
          <app-stat-tile [label]="'custody.mine.total'" [value]="stat('total_custodies')" />
          @if (parseFloatStat('frozen') > 0) {
            <app-stat-tile [label]="'dashboard.agent.frozen'" [value]="stat('frozen')" [money]="true" [inset]="true" />
          }
        </div>

        <div class="u-flex u-gap-2 u-flex-wrap u-mt-4" role="tablist">
          <p-button [label]="('custody.mine.tab_awaiting' | translate) + ' (' + pendingCount() + ')'" size="small"
                    [outlined]="tab() !== 'pending'" [text]="tab() === 'pending'" (onClick)="tab.set('pending')" />
          <p-button [label]="('custody.mine.active' | translate) + ' (' + activeCount() + ')'" size="small"
                    [outlined]="tab() !== 'active'" [text]="tab() === 'active'" (onClick)="tab.set('active')" />
          <p-button [label]="('custody.mine.tab_transfers' | translate) + ' (' + pendingTransferCount() + ')'" size="small"
                    [outlined]="tab() !== 'transfers'" [text]="tab() === 'transfers'" (onClick)="tab.set('transfers')" />
          <p-button [label]="('custody.mine.tab_closed' | translate) + ' (' + closedCount() + ')'" size="small"
                    [outlined]="tab() !== 'closed'" [text]="tab() === 'closed'" (onClick)="tab.set('closed')" />
        </div>

        @if (tab() !== 'transfers') {
          <app-ledger-table class="u-mt-4" [columns]="custodyCols" [rows]="rows()"
                            [title]="'custody.mine.title'" [emptyLabel]="'custody.mine.empty'">
            <ng-template appLedgerBody let-row>
              <tr class="ledger-table__row">
                <td appLedgerCol="created_at" class="u-bidi">{{ row.created_at | datetime: false }}</td>
                <td appLedgerCol="amount">{{ row.amount | money }}</td>
                <td appLedgerCol="transferred_in">
                  @if (parseFloat(row.transferred_in) > 0) { {{ row.transferred_in | money }} } @else { — }
                </td>
                <td appLedgerCol="spent">{{ row.spent | money }}</td>
                <td appLedgerCol="returned">{{ row.returned | money }}</td>
                <td appLedgerCol="remaining">
                  {{ row.remaining | money }}
                  @if (parseFloat(row.frozen) > 0) {
                    <span class="u-small u-muted">{{ 'custody.detail.breakdown.frozen' | translate }}: {{ row.frozen | money }}</span>
                  }
                </td>
                <td appLedgerCol="status">
                  <app-status-stamp [status]="row.status" namespace="custody_status" />
                  @if (row.status === 'pending') {
                    @if (row.initiated_by === 'accountant') {
                      <span class="u-small u-muted">{{ 'custody.mine.awaiting_acceptance' | translate }}</span>
                    } @else {
                      <span class="u-small u-muted">{{ 'custody.mine.awaiting_approval' | translate }}</span>
                    }
                  }
                </td>
                <td appLedgerCol="actions">
                  <div class="u-flex u-gap-1 u-flex-wrap">
                    <a [routerLink]="['/custodies', row.id]">{{ 'common.view' | translate }}</a>
                    <p-button [label]="'custody.mine.tx_button' | translate" size="small" [text]="true" (onClick)="openTransactions(row.id)" />
                    @if (row.status === 'accepted' || row.status === 'active') {
                      <p-button [label]="'custody.mine.quick_donation' | translate" size="small" [text]="true" (onClick)="donationDlg.open(); openDonation(row.id)" />
                      <p-button [label]="'custody.mine.return_to_treasury' | translate" size="small" [text]="true" (onClick)="refundDlg.open(); openRefund(row.id)" />
                    }
                  </div>
                </td>
              </tr>
            </ng-template>
          </app-ledger-table>
        } @else {
          <div class="kpi-grid u-mt-4">
            <section class="chart-card">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.mine.sent_transfers' | translate }}</span></div>
              @for (t of d.sent_transfers; track t.id) {
                <div class="u-flex-between u-small">
                  <span>{{ 'glossary.receiver' | translate }}: {{ t.receiver }}</span>
                  <span class="u-bidi">{{ t.amount | money }}</span>
                  <app-status-stamp [status]="t.status" namespace="transfer_status" />
                  <a [routerLink]="['/transfers', t.id]">{{ 'common.view' | translate }}</a>
                </div>
              } @empty { <p class="u-muted u-small">{{ 'common.no_data' | translate }}</p> }
            </section>
            <section class="chart-card">
              <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.mine.received_transfers' | translate }}</span></div>
              @for (t of d.received_transfers; track t.id) {
                <div class="u-flex-between u-small u-gap-2">
                  <span>{{ 'glossary.sender' | translate }}: {{ t.sender }}</span>
                  <span class="u-bidi">{{ t.amount | money }}</span>
                  <app-status-stamp [status]="t.status" namespace="transfer_status" />
                  @if (t.status === 'pending') {
                    <p-button [label]="'custody.mine.receive_transfer' | translate" size="small" severity="success" [outlined]="true" (onClick)="receiveDlg.open(); openReceive(t)" />
                    <p-button [label]="'custody.mine.reject_btn' | translate" size="small" severity="danger" [text]="true" (onClick)="transferRejectDlg.open(); rejectTransfer.set(t)" />
                  } @else {
                    <a [routerLink]="['/transfers', t.id]">{{ 'common.view' | translate }}</a>
                  }
                </div>
              } @empty { <p class="u-muted u-small">{{ 'common.no_data' | translate }}</p> }
            </section>
          </div>
        }
      }
      <div empty>
        <app-empty-state [title]="'custody.mine.empty' | translate" [text]="'common.no_data' | translate" />
      </div>
    </app-async-page>

    <!-- external receipt: standalone custody for a user who owns none (T28) -->
    <app-form-dialog #externalDialog [header]="'custody.mine.external_receipt_dialog.title' | translate"
                     [submitLabel]="'common.save' | translate" [sending]="sending()" [error]="actionError()" (submit)="submitExternal()">
      <div class="u-column u-gap-3">
        <app-field label="custody.mine.external_receipt_dialog.amount" controlId="ext-amount" [required]="true">
          <p-inputnumber inputId="ext-amount" [(ngModel)]="extAmount" name="extAmount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </app-field>
        <app-field label="custody.mine.external_receipt_dialog.source" controlId="ext-source" [required]="true">
          <input pInputText id="ext-source" [(ngModel)]="extSource" name="extSource" class="u-full" />
        </app-field>
        <app-field label="common.description" controlId="ext-desc" [required]="true">
          <textarea pTextarea id="ext-desc" rows="2" [(ngModel)]="extDescription" name="extDescription" class="u-full"></textarea>
        </app-field>
        <p class="field__hint">{{ 'custody.mine.external_receipt_dialog.hint' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- quick external donation: a NEW standalone custody; the chosen custody only picks the treasury -->
    <app-form-dialog #donationDlg [header]="'custody.mine.donation_dialog.title' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()" (submit)="submitDonation()">
      <div class="u-column u-gap-3">
        <app-field label="custody.mine.donation_dialog.custody" controlId="don-custody" [required]="true">
          <p-select appendTo="body" inputId="don-custody" [options]="donationOptions()" optionLabel="label" optionValue="id"
                    [(ngModel)]="donationCustodyId" name="donationCustodyId" styleClass="u-full">
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.label }}</span>
                <span class="u-bidi u-small">{{ option.remaining | money }}</span>
              </div>
            </ng-template>
          </p-select>
        </app-field>
        <app-field label="common.amount" controlId="don-amount" [required]="true">
          <p-inputnumber inputId="don-amount" [(ngModel)]="donationAmount" name="donationAmount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </app-field>
        <app-field label="common.description" controlId="don-desc" [required]="true">
          <textarea pTextarea id="don-desc" rows="2" [(ngModel)]="donationDescription" name="donationDescription" class="u-full"></textarea>
        </app-field>
        <p class="field__hint">{{ 'custody.mine.donation_dialog.hint' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- quick recovery of purchase money: into an existing custody or a new one -->
    <app-form-dialog #recoveryDlg [header]="'custody.mine.recover_dialog.title' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()" (submit)="submitRecovery()">
      <div class="u-column u-gap-3">
        <app-field label="custody.mine.recover_dialog.custody" controlId="rec-custody">
          <p-select appendTo="body" inputId="rec-custody" [options]="recoveryOptions()" optionLabel="label" optionValue="id"
                    [(ngModel)]="recoveryCustodyId" name="recoveryCustodyId" [showClear]="true" styleClass="u-full">
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.label }}</span>
                <span class="u-bidi u-small">{{ option.remaining | money }}</span>
              </div>
            </ng-template>
          </p-select>
          <span class="field__hint">{{ 'custody.mine.recover_dialog.hint' | translate }}</span>
        </app-field>
        <app-field label="custody.mine.recover_dialog.amount" controlId="rec-amount" [required]="true">
          <p-inputnumber inputId="rec-amount" [(ngModel)]="recoveryAmount" name="recoveryAmount" mode="decimal" [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </app-field>
        <app-field label="custody.mine.recover_dialog.reason" controlId="rec-desc" [required]="true">
          <textarea pTextarea id="rec-desc" rows="2" [(ngModel)]="recoveryDescription" name="recoveryDescription" class="u-full"></textarea>
        </app-field>
      </div>
    </app-form-dialog>

    <!-- quick return to treasury: accountant reviews and picks the treasury -->
    <app-form-dialog #refundDlg [header]="'custody.mine.return_dialog.title' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()" (submit)="submitRefund()">
      <div class="u-column u-gap-3">
        <app-field label="custody.mine.refund_dialog.custody" controlId="ref-custody" [required]="true">
          <p-select appendTo="body" inputId="ref-custody" [options]="refundOptions()" optionLabel="label" optionValue="id"
                    [(ngModel)]="refundCustodyId" name="refundCustodyId" styleClass="u-full">
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.label }}</span>
                <span class="u-bidi u-small">{{ option.remaining | money }}</span>
              </div>
            </ng-template>
          </p-select>
        </app-field>
        <app-field label="custody.mine.return_dialog.amount" controlId="ref-amount" [required]="true">
          <p-inputnumber inputId="ref-amount" [(ngModel)]="refundAmount" name="refundAmount" mode="decimal"
                         [minFractionDigits]="2" [min]="0.01" [max]="refundMax()" styleClass="u-full" />
          <span class="field__hint u-bidi">{{ 'custody.mine.refund_dialog.max' | translate }}: {{ refundMax() | money }}</span>
        </app-field>
        <app-field label="common.description" controlId="ref-desc" [required]="true">
          <textarea pTextarea id="ref-desc" rows="2" [(ngModel)]="refundDescription" name="refundDescription" class="u-full"></textarea>
        </app-field>
        <p class="field__hint">{{ 'custody.mine.return_dialog.created' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- receive a pending incoming transfer: pick which of my open custodies absorbs it -->
    <app-form-dialog #receiveDlg [header]="'custody.mine.receive_transfer' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()" (submit)="submitReceive()">
      <div class="u-column u-gap-3">
        @if (receiveTransfer(); as t) {
          <p class="u-small u-muted">
            {{ 'glossary.sender' | translate }}: {{ t.sender }} —
            <span class="u-bidi">{{ t.amount | money }}</span>
          </p>
        }
        <app-field label="custody.mine.receive_transfer_dialog.custody" controlId="rcv-custody">
          <p-select appendTo="body" inputId="rcv-custody" [options]="receiveCustodyOptions()" optionLabel="label" optionValue="id"
                    [(ngModel)]="receiveCustodyId" name="receiveCustodyId" styleClass="u-full" />
          <span class="field__hint">{{ 'custody.mine.receive_transfer_dialog.hint' | translate }}</span>
        </app-field>
      </div>
    </app-form-dialog>

    <!-- reject an incoming transfer with a reason -->
    <app-reason-dialog #transferRejectDlg [header]="'custody.mine.reject_transfer' | translate"
                       [label]="'common.reason'" [submitLabel]="'common.confirm' | translate"
                       [sending]="sending()" [error]="actionError()" (submit)="submitTransferReject($event)" />

    <!-- per-custody transactions drawer -->
    <p-dialog appendTo="body" [visible]="txCustodyId() !== null" (visibleChange)="txCustodyId.set($event ? txCustodyId() : null)"
              [header]="'custody.detail.transactions' | translate" [modal]="true" [draggable]="false" [resizable]="false"
              [style]="{ width: 'min(680px, 94vw)' }">
      @if (txDetail.value(); as tx) {
        <app-ledger-table [columns]="transactionCols" [rows]="tx.transactions">
          <ng-template appLedgerBody let-row>
            <tr class="ledger-table__row">
              <td appLedgerCol="date" class="u-bidi">{{ row.date | datetime }}</td>
              <td appLedgerCol="type">{{ row.type | enumLabel: 'transaction_type' }}</td>
              <td appLedgerCol="description">{{ row.description }}</td>
              <td appLedgerCol="amount">{{ row.amount | money }}</td>
            </tr>
          </ng-template>
        </app-ledger-table>
      } @else if (txDetail.isLoading()) {
        <div class="skeleton skeleton--tile"></div>
      }
    </p-dialog>
  `,
})
export class MyCustodiesPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private translate = inject(TranslateService);

  tab = signal<Tab>('active');
  sending = signal(false);
  actionError = signal<HttpErrorResponse | null>(null);

  extAmount = signal<number | null>(null);
  extSource = signal('');
  extDescription = signal('');

  donationCustodyId = signal<number | null>(null);
  donationAmount = signal<number | null>(null);
  donationDescription = signal('');
  recoveryCustodyId = signal<number | null>(null);
  recoveryAmount = signal<number | null>(null);
  recoveryDescription = signal('');
  refundCustodyId = signal<number | null>(null);
  refundAmount = signal<number | null>(null);
  refundDescription = signal('');
  receiveTransfer = signal<MineTransfer | null>(null);
  receiveCustodyId = signal<number | null>(null);
  rejectTransfer = signal<MineTransfer | null>(null);
  txCustodyId = signal<number | null>(null);

  readonly externalDialog = viewChild.required<FormDialogComponent>('externalDialog');
  readonly donationDlg = viewChild.required<FormDialogComponent>('donationDlg');
  readonly recoveryDlg = viewChild.required<FormDialogComponent>('recoveryDlg');
  readonly refundDlg = viewChild.required<FormDialogComponent>('refundDlg');
  readonly receiveDlg = viewChild.required<FormDialogComponent>('receiveDlg');
  readonly transferRejectDlg = viewChild.required<ReasonDialogComponent>('transferRejectDlg');

  /** the auto option for the receive dialog: let the server use my oldest custody (or create one) */
  private readonly autoOption = { id: -1, label: this.translate.instant('custody.mine.receive_transfer_dialog.auto'), remaining: '0' };

  data = resource<MyData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<MyData>>('/custodies/mine')).then((r) => r.data),
  });

  txDetail = resource<TxDetail | null, { id: number | null }>({
    params: () => ({ id: this.txCustodyId() }),
    loader: ({ params }) => {
      if (params.id === null) {
        return Promise.resolve(null);
      }
      return firstValueFrom(this.http.get<ItemResponse<TxDetail>>(`/custodies/${params.id}`)).then((r) => r.data);
    },
  });

  readonly custodyCols = [
    { field: 'created_at', header: 'common.date' },
    { field: 'amount', header: 'custody.list.col_amount', numeric: true },
    { field: 'transferred_in', header: 'custody.list.col_transferred_in', numeric: true },
    { field: 'spent', header: 'custody.list.col_spent', numeric: true },
    { field: 'returned', header: 'custody.list.col_returned', numeric: true },
    { field: 'remaining', header: 'custody.list.col_remaining', numeric: true },
    { field: 'status', header: 'common.status' },
    { field: 'actions', header: 'common.actions' },
  ];

  readonly transactionCols = [
    { field: 'date', header: 'common.date' },
    { field: 'type', header: 'common.type' },
    { field: 'description', header: 'common.description' },
    { field: 'amount', header: 'common.amount', numeric: true },
  ];

  readonly custodies = computed(() => this.data.value()?.custodies ?? []);

  readonly rows = computed(() => {
    const rows = this.custodies();
    switch (this.tab()) {
      case 'pending':
        return rows.filter((c) => c.status === 'pending');
      case 'active':
        return rows.filter((c) => ['accepted', 'active', 'partially_returned'].includes(c.status));
      case 'closed':
        return rows.filter((c) => ['closed', 'cancelled', 'rejected'].includes(c.status));
      default:
        return rows;
    }
  });

  readonly pendingCount = computed(() => this.custodies().filter((c) => c.status === 'pending').length);
  readonly activeCount = computed(() => this.custodies().filter((c) => ['accepted', 'active', 'partially_returned'].includes(c.status)).length);
  readonly closedCount = computed(() => this.custodies().filter((c) => ['closed', 'cancelled', 'rejected'].includes(c.status)).length);
  readonly pendingTransferCount = computed(() => (this.data.value()?.received_transfers ?? []).filter((t) => t.status === 'pending').length);

  /** the page is empty only with no custodies and no transfers in either direction */
  isEmpty = (): boolean => {
    try {
      const d = this.data.value();
      return d !== undefined && d.custodies.length === 0
        && d.sent_transfers.length === 0 && d.received_transfers.length === 0;
    } catch {
      return false;
    }
  };

  /** open custodies (Blade quick-action condition: not a supervisor and at least one open custody) */
  readonly openCustodies = computed(() => this.custodies().filter((c) => OPEN_STATUSES.includes(c.status)));

  readonly showQuickActions = computed(() => !this.auth.can('is_supervisor') && this.openCustodies().length > 0);

  readonly showExternalReceipt = computed(() => {
    const owns = this.auth.can('owns_custody');
    const supervisor = this.auth.can('is_supervisor');
    return !supervisor && !owns;
  });

  isAgent(): boolean {
    return this.auth.can('is_agent');
  }

  private optionLabel(c: MineCustody): string {
    return '#' + String(c.id) + (c.treasury ? ' — ' + c.treasury : '');
  }

  readonly donationOptions = computed<CustodyOption[]>(() =>
    this.openCustodies().map((c) => ({ id: c.id, label: this.optionLabel(c), remaining: c.remaining })));

  readonly recoveryOptions = computed<CustodyOption[]>(() =>
    this.openCustodies().map((c) => ({ id: c.id, label: this.optionLabel(c), remaining: c.remaining })));

  readonly refundOptions = computed<CustodyOption[]>(() =>
    this.openCustodies()
      .filter((c) => ['accepted', 'partially_returned', 'closed'].includes(c.status) && this.parseFloat(c.remaining) > 0)
      .map((c) => ({ id: c.id, label: this.optionLabel(c), remaining: c.remaining })));

  readonly receiveCustodyOptions = computed<CustodyOption[]>(() => [
    this.autoOption,
    ...this.custodies()
      .filter((c) => ['accepted', 'active'].includes(c.status))
      .map((c) => ({ id: c.id, label: this.optionLabel(c), remaining: c.remaining })),
  ]);

  refundMax(): number {
    const selected = this.refundOptions().find((o) => o.id === this.refundCustodyId());
    return this.parseFloat(selected?.remaining ?? '0');
  }

  stat(key: string): string {
    return String(this.data.value()?.stats[key] ?? '0');
  }

  parseFloatStat(key: string): number {
    return parseFloat(this.stat(key)) || 0;
  }

  parseFloat(v: string): number {
    return parseFloat(v) || 0;
  }

  // ── dialog openers (reset stale state before showing) ──

  openDonation(custodyId?: number): void {
    this.actionError.set(null);
    this.donationCustodyId.set(custodyId ?? null);
    this.donationAmount.set(null);
    this.donationDescription.set('');
  }

  openRecovery(): void {
    this.actionError.set(null);
    this.recoveryCustodyId.set(null);
    this.recoveryAmount.set(null);
    this.recoveryDescription.set('');
  }

  openRefund(custodyId?: number): void {
    this.actionError.set(null);
    this.refundCustodyId.set(custodyId ?? null);
    this.refundAmount.set(null);
    this.refundDescription.set('');
  }

  openReceive(transfer: MineTransfer): void {
    this.actionError.set(null);
    this.receiveTransfer.set(transfer);
    this.receiveCustodyId.set(this.autoOption.id);
  }

  openTransactions(custodyId: number): void {
    this.txCustodyId.set(custodyId);
  }

  // ── submissions ──

  submitExternal(): void {
    if (this.extAmount() === null || !this.extSource()) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.api.action('/my-external-receipt', {
        amount: this.extAmount(),
        external_source: this.extSource(),
        description: this.extDescription(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.externalDialog().close();
        this.data.reload();
        this.auth.loadMe().subscribe();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  submitDonation(): void {
    const custodyId = this.donationCustodyId();
    if (custodyId === null || this.donationAmount() === null || !this.donationDescription().trim()) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.api.action(`/custodies/${custodyId}/external-donation`, {
        custody_id: custodyId,
        amount: this.donationAmount(),
        description: this.donationDescription(),
        type: 'external_donation',
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.donationDlg().close();
        this.data.reload();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  submitRecovery(): void {
    if (this.recoveryAmount() === null || !this.recoveryDescription().trim()) {
      return;
    }
    const custodyId = this.recoveryCustodyId();
    this.sending.set(true);
    void firstValueFrom(
      this.api.action('/custodies/add-recovery', {
        ...(custodyId === null ? {} : { custody_id: custodyId }),
        amount: this.recoveryAmount(),
        description: this.recoveryDescription(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.recoveryDlg().close();
        this.data.reload();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  submitRefund(): void {
    const amount = this.refundAmount();
    if (this.refundCustodyId() === null || amount === null || !this.refundDescription().trim()) {
      return;
    }
    if (amount > this.refundMax()) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.api.action('/custodies/request-return', {
        custody_id: this.refundCustodyId(),
        amount,
        description: this.refundDescription(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.refundDlg().close();
        this.data.reload();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  submitReceive(): void {
    const transfer = this.receiveTransfer();
    if (!transfer) {
      return;
    }
    const custodyId = this.receiveCustodyId();
    this.sending.set(true);
    void firstValueFrom(
      this.api.action(`/custody-transfers/${transfer.id}/approve`, {
        ...(custodyId === null || custodyId === this.autoOption.id ? {} : { custody_id: custodyId }),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.receiveDlg().close();
        this.data.reload();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  submitTransferReject(reason: string): void {
    const transfer = this.rejectTransfer();
    if (!transfer) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.api.action(`/custody-transfers/${transfer.id}/reject`, { rejection_reason: reason }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.transferRejectDlg().done();
        this.data.reload();
      })
      .catch((err: unknown) => { this.fail((err as HttpErrorResponse)); });
  }

  private fail(err: HttpErrorResponse): void {
    this.sending.set(false);
    this.actionError.set(err);
    this.toast.apiError(err.error?.message ?? 'errors.generic', err.error?.code);
  }
}
