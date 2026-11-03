import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ReasonDialogComponent } from '@shared/ui/reason-dialog/reason-dialog.component';
import type { RibbonStep } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import { FlowRibbonComponent } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import type { RingSegment } from '@shared/ui/balance-ring/balance-ring.component';
import { BalanceRingComponent } from '@shared/ui/balance-ring/balance-ring.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface CustodyExpense {
  id: number; amount: string; type: string; description: string | null;
  date: string | null; reviewed: boolean; direction: string | null;
  split: { id: number; amount: string }[];
}

interface ReturnRequestRow {
  id: number; amount: string; reason: string | null; status: string;
  requested_by: string | null; requested_at: string | null;
  approved_by: string | null; approved_at: string | null; rejection_reason: string | null;
}

interface CustodyDetail {
  id: number; agent: string | null; agent_id: number; treasury: string | null;
  accountant: string | null; amount: string; transferred_in: string; transferred_out: string;
  spent: string; returned: string; pending_return: string; frozen: string; remaining: string;
  status: string; status_detail: string; initiated_by: string; notes: string | null;
  created_at: string | null;
  expenses: CustodyExpense[];
  return_requests: ReturnRequestRow[];
  transactions: { id: number; type: string; amount: string; description: string | null; user: string | null; date: string | null }[];
}

type DonationType = 'external_donation' | 'expense_refund';

/**
 * Custody details (plan 20 + T3-10/T3-11): every action the Blade view had,
 * under the same role/status conditions. The server re-checks authorization —
 * `can()` only hides buttons. Balances come from the API only.
 */
@Component({
  selector: 'app-custody-detail',
  standalone: true,
  imports: [EmptyStateComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, PageHeaderComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, FormDialogComponent, ReasonDialogComponent, FlowRibbonComponent, BalanceRingComponent, MoneyPipe, DatetimePipe, EnumLabelPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (safeValue(); as c) {
      <app-print-sheet [title]="'custody.detail.title' | translate">
        <app-page-header [title]="'#' + c.id" [subtitle]="c.agent ?? ''">
          <div actions class="u-flex u-gap-2 u-flex-wrap">
            <p-button [label]="'common.print' | translate" size="small" [outlined]="true" (click)="printPage()" />
            @if (canManageTreasury()) {
              <p-button [label]="'common.edit' | translate" size="small" [outlined]="true" routerLink="/custodies/{{ c.id }}/edit" />
            }

            @if (showApproveRequest(c)) {
              <p-button [label]="'custody.detail.approve_request' | translate" size="small" severity="success" (onClick)="acceptDlg.open(); openAccept()" />
              <p-button [label]="'custody.detail.reject_request' | translate" size="small" severity="danger" [outlined]="true" (onClick)="rejectDlg.open()" />
            }
            @if (showCancel(c)) {
              <p-button [label]="'custody.detail.cancel_custody' | translate" size="small" severity="danger" [outlined]="true" (onClick)="cancelDlg.open()" />
            }

            @if (showAgentAccept(c)) {
              <p-button [label]="'custody.detail.agent_accept' | translate" size="small" severity="success" (onClick)="agentAcceptDlg.open()" />
              <p-button [label]="'custody.detail.reject' | translate" size="small" severity="danger" [outlined]="true" (onClick)="agentRejectDlg.open()" />
            }
            @if (showReceive(c)) {
              <p-button [label]="'custody.detail.receive' | translate" size="small" (onClick)="receiveDlg.open()" />
            }

            @if (showTransferShortcut(c)) {
              <p-button [label]="'custody.detail.transfer_to_agent' | translate" size="small" severity="info" [outlined]="true" routerLink="/transfers/new" />
            }
            @if (showAgentReturn(c)) {
              <p-button [label]="'custody.detail.return' | translate" size="small" severity="warn" [outlined]="true" (onClick)="returnOpen.set(!returnOpen())" />
            }
            @if (isAgent()) {
              <p-button [label]="'custody.mine.request_new' | translate" size="small" severity="success" [outlined]="true" routerLink="/custodies/new" />
            }

            @if (showRequestReturn(c)) {
              @if (hasPendingReturnRequest(c)) {
                <span class="u-small u-muted">{{ 'custody.detail.pending_return_note' | translate }}</span>
              } @else {
                <p-button [label]="'custody.detail.request_return' | translate" size="small" severity="warn" [outlined]="true" (onClick)="reqReturnDlg.open(); openReqReturn()" />
              }
            }

            @if (showDonation(c)) {
              <p-button [label]="'custody.detail.donation_add' | translate" size="small" severity="success" [outlined]="true" (onClick)="donationDlg.open(); openDonation()" />
            }
          </div>
        </app-page-header>

        @if (showReturnDisabledHint(c)) {
          <p class="field__hint u-mb-2">{{ 'custody.detail.return_disabled_hint' | translate }}</p>
        }

        <div class="u-flex u-gap-3 u-flex-wrap u-mb-4">
          <app-status-stamp [status]="c.status" namespace="custody_status" [large]="true" />
          <span class="u-small u-muted">{{ c.status_detail }}</span>
        </div>

        <app-flow-ribbon [steps]="flowSteps(c)" />

        <div class="kpi-grid u-mt-4">
          <app-balance-ring [segments]="ringSegments(c)" [total]="c.remaining" />
          <div class="chart-card">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.breakdown.title' | translate }}</span></div>
            <app-key-value-list [items]="breakdown(c)" />
          </div>
        </div>

        <app-key-value-list [items]="keyValues(c)" />

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.returns' | translate }}</span></div>
          <div style="overflow-x: auto">
            <table class="ledger-table" style="border: 0">
              <thead><tr>
                <th>{{ 'common.date' | translate }}</th>
                <th>{{ 'common.amount' | translate }}</th>
                <th>{{ 'custody.detail.return_reason' | translate }}</th>
                <th>{{ 'common.status' | translate }}</th>
                <th>{{ 'custody.detail.rr_requester' | translate }}</th>
                <th>{{ 'custody.detail.rr_decided' | translate }}</th>
                <th>{{ 'common.actions' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (r of c.return_requests; track r.id) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ r.requested_at | datetime }}</td>
                    <td class="ledger-table__cell-num">{{ r.amount | money }}</td>
                    <td>
                      {{ r.reason || '—' }}
                      @if (r.rejection_reason) { <span class="u-small u-muted">{{ r.rejection_reason }}</span> }
                    </td>
                    <td><app-status-stamp [status]="r.status" namespace="return_request" /></td>
                    <td>{{ r.requested_by ?? '—' }}</td>
                    <td>
                      @if (r.approved_by) {
                        <span class="u-bidi">{{ r.approved_by }} — {{ r.approved_at | datetime: false }}</span>
                      } @else { <span class="u-muted">—</span> }
                    </td>
                    <td>
                      @if (r.status === 'pending') {
                        @if (canManageTreasury()) {
                          <p-button [label]="'custody.detail.approve_rr' | translate" size="small" severity="success" [outlined]="true" (onClick)="rrDlg.open(); openRrApprove(r)" />
                        } @else if (c.initiated_by === 'agent' && meId() === c.agent_id) {
                          <p-button [label]="'custody.detail.approve_rr' | translate" size="small" severity="success" [outlined]="true" (onClick)="rrOwnerDlg.open(); openRrOwnerApprove(r)" />
                        }
                      }
                    </td>
                  </tr>
                } @empty {
                  <tr><td colspan="7" class="u-muted">{{ 'common.no_data' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        </section>

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.transactions' | translate }}</span></div>
          <div style="overflow-x: auto">
            <table class="ledger-table" style="border: 0">
              <thead><tr>
                <th>{{ 'common.date' | translate }}</th>
                <th>{{ 'common.type' | translate }}</th>
                <th>{{ 'common.description' | translate }}</th>
                <th>{{ 'common.amount' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (t of c.transactions; track t.id) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ t.date | datetime }}</td>
                    <td>{{ t.type | enumLabel: 'transaction_type' }}</td>
                    <td>{{ t.description }}</td>
                    <td class="ledger-table__cell-num">{{ t.amount | money }}</td>
                  </tr>
                } @empty {
                  <tr><td colspan="4" class="u-muted">{{ 'common.no_data' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        </section>

        <section class="chart-card u-mt-4">
          <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.expenses' | translate }}</span></div>
          <div style="overflow-x: auto">
            <table class="ledger-table" style="border: 0">
              <thead><tr>
                <th>{{ 'common.date' | translate }}</th><th>{{ 'common.description' | translate }}</th>
                <th>{{ 'expense.list.col_direction' | translate }}</th><th>{{ 'common.amount' | translate }}</th>
              </tr></thead>
              <tbody>
                @for (e of c.expenses; track e.id) {
                  <tr class="ledger-table__row">
                    <td class="u-bidi">{{ e.date | datetime: false }}</td>
                    <td><a [routerLink]="['/expenses', e.id]">{{ e.description || '—' }}</a>
                      @if (e.split.length > 1) { <span class="stamp stamp--info">{{ 'expense.detail.custody_split_note' | translate }}</span> }
                    </td>
                    <td class="u-small">{{ e.direction || ('expense.list.filter.no_direction' | translate) }}</td>
                    <td class="ledger-table__cell-num">{{ e.amount | money }}</td>
                  </tr>
                } @empty {
                  <tr><td colspan="4" class="u-muted">{{ 'common.no_data' | translate }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        </section>

        @if (returnOpen()) {
          <section class="form-section u-mt-4">
            <h3 class="form-section__title">{{ 'custody.mine.return_dialog.title' | translate }}</h3>
            <div class="form-section__grid">
              <div class="field">
                <label class="field__label" for="ret-amount">{{ 'custody.detail.return_amount' | translate }} *</label>
                <p-inputnumber inputId="ret-amount" [(ngModel)]="returnAmount" name="returnAmount" mode="decimal"
                               [minFractionDigits]="2" [min]="0.01" [max]="parseFloat(c.remaining)" styleClass="u-full" />
                <span class="field__hint u-bidi">{{ 'glossary.remaining' | translate }}: {{ c.remaining | money }}</span>
              </div>
              <div class="field">
                <label class="field__label" for="ret-reason">{{ 'custody.detail.return_reason' | translate }} *</label>
                <input pInputText id="ret-reason" [(ngModel)]="reason" name="reason" style="width: 100%" />
              </div>
            </div>
            <div class="u-flex" style="justify-content: flex-end">
              <p-button [label]="'common.cancel' | translate" [text]="true" (onClick)="returnOpen.set(false)" />
              <p-button [label]="'custody.mine.return_to_treasury' | translate" (onClick)="submitReturn(c.id)" [loading]="sending()" />
            </div>
          </section>
        }
      </app-print-sheet>
    } @else if (data.isLoading()) {
      <div class="skeleton skeleton--title u-mb-4"></div>
      <div class="skeleton skeleton--tile"></div>
    } @else {
      <app-empty-state [title]="'common.not_found' | translate" [text]="'common.not_found_text' | translate" />

    }

    <!-- approve the agent's request: choose the treasury that funds the custody -->
    <app-form-dialog #acceptDlg [header]="'custody.detail.accept_dialog' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitAccept()">
      <div class="u-column u-gap-3">
        @if (safeValue(); as c) {
          <div class="field">
            <span class="field__label">{{ 'common.amount' | translate }}</span>
            <p class="u-bidi" style="margin: 0; font-weight: 700">{{ c.amount | money }}</p>
          </div>
        }
        <div class="field">
          <label class="field__label" for="accept-treasury">{{ 'custody.create.treasury' | translate }} *</label>
          <p-select appendTo="body" inputId="accept-treasury" [options]="treasuryOptions()" optionLabel="name" optionValue="id"
                    [(ngModel)]="acceptTreasuryId" name="acceptTreasuryId" styleClass="u-full">
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.name }}</span>
                <span class="u-bidi u-small">{{ option.balance | money }}</span>
              </div>
            </ng-template>
          </p-select>
        </div>
        @if (acceptTreasuryId() !== null) {
          <p class="field__hint u-bidi">{{ 'custody.detail.treasury_balance' | translate }}: {{ acceptBalance() | money }}
            — {{ 'custody.detail.balance_after' | translate }}: {{ acceptBalanceAfter() | money }}</p>
          @if (acceptInsufficient()) {
            <p class="field__error" role="alert">{{ 'custody.detail.insufficient_treasury' | translate }}</p>
          }
        }
        <p class="field__hint">{{ 'custody.detail.accept_note' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- manager rejects the agent's request -->
    <app-reason-dialog #rejectDlg [header]="'custody.detail.reject' | translate"
                       [label]="'custody.detail.reject_reason' | translate" [submitLabel]="'common.confirm' | translate"
                       [sending]="sending()" [error]="actionError()" (submit)="submitReject($event)" />

    <!-- accountant/manager cancels their own pending custody -->
    <app-reason-dialog #cancelDlg [header]="'custody.detail.cancel_custody' | translate"
                       [label]="'custody.detail.cancel_reason' | translate" [submitLabel]="'common.confirm' | translate"
                       [minLength]="1" [sending]="sending()" [error]="actionError()" (submit)="submitCancel($event)" />

    <!-- agent rejects the custody the accountant created -->
    <app-reason-dialog #agentRejectDlg [header]="'custody.detail.reject' | translate"
                       [label]="'custody.detail.reject_reason' | translate" [submitLabel]="'common.confirm' | translate"
                       [minLength]="1" [sending]="sending()" [error]="actionError()" (submit)="submitAgentReject($event)" />

    <!-- agent confirms receiving the money -->
    <app-form-dialog #receiveDlg [header]="'custody.detail.receive' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitReceive()">
      <p>{{ 'custody.detail.receive_confirm' | translate }}</p>
    </app-form-dialog>

    <!-- agent accepts the custody the accountant sent -->
    <app-form-dialog #agentAcceptDlg [header]="'custody.detail.agent_accept' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitAgentAccept()">
      <p>{{ 'custody.detail.agent_accept_confirm' | translate }}</p>
    </app-form-dialog>

    <!-- non-agent asks the custody owner to return money to a treasury -->
    <app-form-dialog #reqReturnDlg [header]="'custody.detail.request_return_dialog' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitReqReturn()">
      <div class="u-column u-gap-3">
        @if (safeValue(); as c) {
          <div class="field">
            <label class="field__label" for="rrq-amount">{{ 'custody.detail.return_amount' | translate }} *</label>
            <p-inputnumber inputId="rrq-amount" [(ngModel)]="reqReturnAmount" name="reqReturnAmount" mode="decimal"
                           [minFractionDigits]="2" [min]="0.01" [max]="parseFloat(c.remaining)" styleClass="u-full" />
            <span class="field__hint u-bidi">{{ 'glossary.remaining' | translate }}: {{ c.remaining | money }}</span>
          </div>
          <div class="field">
            <label class="field__label" for="rrq-reason">{{ 'common.reason' | translate }}</label>
            <textarea pTextarea id="rrq-reason" rows="2" [(ngModel)]="reqReturnReason" name="reqReturnReason" style="width: 100%"></textarea>
          </div>
        }
        <p class="field__hint">{{ 'custody.detail.request_return_note' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- treasury holder approves a pending return request and picks the treasury -->
    <app-form-dialog #rrDlg [header]="'custody.detail.approve_rr' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitRrApprove()">
      <div class="u-column u-gap-3">
        @if (rrApprove(); as r) {
          <div class="field">
            <span class="field__label">{{ 'common.amount' | translate }}</span>
            <p class="u-bidi" style="margin: 0; font-weight: 700">{{ r.amount | money }}</p>
            <span class="u-small u-muted">{{ 'custody.detail.rr_requester' | translate }}: {{ r.requested_by ?? '—' }}</span>
          </div>
        }
        <div class="field">
          <label class="field__label" for="rr-treasury">{{ 'custody.create.treasury' | translate }} *</label>
          <p-select appendTo="body" inputId="rr-treasury" [options]="treasuryOptions()" optionLabel="name" optionValue="id"
                    [(ngModel)]="rrTreasuryId" name="rrTreasuryId" styleClass="u-full">
            <ng-template let-option #item>
              <div class="u-flex-between">
                <span>{{ option.name }}</span>
                <span class="u-bidi u-small">{{ option.balance | money }}</span>
              </div>
            </ng-template>
          </p-select>
        </div>
        @if (rrApprove(); as r) {
          @if (rrTreasuryId() !== null) {
            <p class="field__hint u-bidi">{{ 'custody.detail.treasury_balance' | translate }}: {{ rrTreasuryBalance() | money }}
              — {{ 'custody.detail.balance_after' | translate }}: {{ rrBalanceAfter(r) | money }}</p>
          }
        }
        <p class="field__hint">{{ 'custody.detail.rr_treasury_note' | translate }}</p>
      </div>
    </app-form-dialog>

    <!-- custody owner approves a pending return request (executed immediately) -->
    <app-form-dialog #rrOwnerDlg [header]="'custody.detail.approve_rr' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitRrOwnerApprove()">
      <p>{{ 'custody.detail.rr_owner_note' | translate }}</p>
    </app-form-dialog>

    <!-- external donation (new standalone custody) / expense refund (back into this custody) -->
    <app-form-dialog #donationDlg [header]="'custody.detail.donation_add' | translate"
                     [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                     (submit)="submitDonation()">
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="don-type">{{ 'custody.detail.donation_type' | translate }} *</label>
          <p-select appendTo="body" inputId="don-type" [options]="donationTypeOptions" optionLabel="label" optionValue="value"
                    [(ngModel)]="donationType" name="donationType" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="don-amount">{{ 'common.amount' | translate }} *</label>
          <p-inputnumber inputId="don-amount" [(ngModel)]="donationAmount" name="donationAmount" mode="decimal"
                         [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
        </div>
        <div class="field">
          <label class="field__label" for="don-desc">{{ 'common.description' | translate }} *</label>
          <input pInputText id="don-desc" [(ngModel)]="donationDescription" name="donationDescription" style="width: 100%" />
        </div>
        <p class="field__hint">{{ 'custody.detail.donation_hint' | translate }}</p>
      </div>
    </app-form-dialog>
  `,
})
export class CustodyDetailPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);
  readonly auth = inject(AuthService);

  id = input.required<number>();
  sending = signal(false);
  actionError = signal<HttpErrorResponse | null>(null);

  returnOpen = signal(false);
  returnAmount = signal<number | null>(null);
  reason = signal('');

  acceptTreasuryId = signal<number | null>(null);
  reqReturnAmount = signal<number | null>(null);
  reqReturnReason = signal('');
  rrApprove = signal<ReturnRequestRow | null>(null);
  rrTreasuryId = signal<number | null>(null);
  rrOwnerApprove = signal<ReturnRequestRow | null>(null);
  donationType = signal<DonationType>('external_donation');
  donationAmount = signal<number | null>(null);
  donationDescription = signal('');

  readonly acceptDlg = viewChild.required<FormDialogComponent>('acceptDlg');
  readonly reqReturnDlg = viewChild.required<FormDialogComponent>('reqReturnDlg');
  readonly rrDlg = viewChild.required<FormDialogComponent>('rrDlg');
  readonly rrOwnerDlg = viewChild.required<FormDialogComponent>('rrOwnerDlg');
  readonly donationDlg = viewChild.required<FormDialogComponent>('donationDlg');
  readonly receiveDlg = viewChild.required<FormDialogComponent>('receiveDlg');
  readonly agentAcceptDlg = viewChild.required<FormDialogComponent>('agentAcceptDlg');
  readonly rejectDlg = viewChild.required<ReasonDialogComponent>('rejectDlg');
  readonly cancelDlg = viewChild.required<ReasonDialogComponent>('cancelDlg');
  readonly agentRejectDlg = viewChild.required<ReasonDialogComponent>('agentRejectDlg');

  readonly donationTypeOptions: { value: DonationType; label: string }[] = [
    { value: 'external_donation', label: this.translate.instant('custody.detail.donation_type_external') },
    { value: 'expense_refund', label: this.translate.instant('custody.detail.donation_type_refund') },
  ];

  data = resource<CustodyDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.http.get<ItemResponse<CustodyDetail>>(`/custodies/${params.id}`)).then((r) => r.data),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  treasuries = resource<{ id: number; name: string; balance: string }[], unknown>({
    loader: () => firstValueFrom(this.api.loadLookups()).then((l) => l.treasuries),
  });

  readonly treasuryOptions = computed(() => {
    try {
      return this.treasuries.value() ?? [];
    } catch {
      return [];
    }
  });

  meId(): number {
    return this.auth.me()?.id ?? 0;
  }

  isAgent(): boolean {
    return this.auth.hasRole('مندوب');
  }

  canManageTreasury(): boolean {
    return this.auth.can('manage_treasury');
  }

  parseFloat(v: string): number {
    return parseFloat(v) || 0;
  }

  // ── visibility (copied from custodies/modern-show.blade.php) ──

  showApproveRequest(c: CustodyDetail): boolean {
    return this.auth.can('may_approve_custody') && c.status === 'pending' && c.initiated_by === 'agent';
  }

  showCancel(c: CustodyDetail): boolean {
    return this.auth.can('may_approve_custody') && c.status === 'pending' && c.initiated_by === 'accountant';
  }

  showAgentAccept(c: CustodyDetail): boolean {
    return this.isAgent() && this.meId() === c.agent_id && c.status === 'pending' && c.initiated_by === 'accountant';
  }

  showReceive(c: CustodyDetail): boolean {
    return this.isAgent() && this.meId() === c.agent_id && c.status === 'accepted' && c.initiated_by === 'agent';
  }

  showTransferShortcut(c: CustodyDetail): boolean {
    return this.isAgent() && this.meId() === c.agent_id
      && (c.status === 'active' || (c.status === 'accepted' && c.initiated_by === 'accountant'));
  }

  showAgentReturn(c: CustodyDetail): boolean {
    return this.isAgent() && this.meId() === c.agent_id
      && ['accepted', 'active', 'partially_returned'].includes(c.status)
      && this.parseFloat(c.remaining) > 0;
  }

  showReturnDisabledHint(c: CustodyDetail): boolean {
    return this.isAgent() && this.meId() === c.agent_id
      && ['accepted', 'active', 'partially_returned'].includes(c.status)
      && this.parseFloat(c.remaining) <= 0;
  }

  showRequestReturn(c: CustodyDetail): boolean {
    return this.auth.can('may_approve_custody') && !this.isAgent()
      && ['active', 'accepted', 'partially_returned'].includes(c.status)
      && this.parseFloat(c.remaining) > 0;
  }

  showDonation(c: CustodyDetail): boolean {
    return ['active', 'accepted', 'partially_returned'].includes(c.status)
      && (this.canManageTreasury() || (this.isAgent() && this.meId() === c.agent_id));
  }

  hasPendingReturnRequest(c: CustodyDetail): boolean {
    return c.return_requests.some((r) => r.status === 'pending');
  }

  // ── treasury pickers ──

  acceptTreasury(): { id: number; name: string; balance: string } | null {
    return this.treasuryOptions().find((t) => t.id === this.acceptTreasuryId()) ?? null;
  }

  acceptBalance(): number {
    return this.parseFloat(this.acceptTreasury()?.balance ?? '0');
  }

  acceptBalanceAfter(): string {
    const custody = this.safeValue();
    const amount = custody ? this.parseFloat(custody.amount) : 0;
    return (this.acceptBalance() - amount).toFixed(2);
  }

  acceptInsufficient(): boolean {
    const custody = this.safeValue();
    if (!custody || this.acceptTreasuryId() === null) {
      return false;
    }
    return this.acceptBalance() < this.parseFloat(custody.amount);
  }

  rrTreasuryBalance(): number {
    const treasury = this.treasuryOptions().find((t) => t.id === this.rrTreasuryId());
    return this.parseFloat(treasury?.balance ?? '0');
  }

  rrBalanceAfter(r: ReturnRequestRow): string {
    return (this.rrTreasuryBalance() + this.parseFloat(r.amount)).toFixed(2);
  }

  // ── existing display helpers ──

  flowSteps(c: CustodyDetail): RibbonStep[] {
    const spent = this.parseFloat(c.spent) > 0;
    const transferred = this.parseFloat(c.transferred_out) > 0;
    const returned = this.parseFloat(c.returned) > 0 || c.status === 'pending_return';
    const done = ['active', 'partially_returned', 'pending_return', 'closed'].includes(c.status);
    return [
      { label: 'custody.detail.flow.treasury', state: c.status === 'pending' ? 'current' : 'done' },
      { label: 'custody.detail.flow.custody', state: c.status === 'accepted' ? 'current' : done ? 'done' : 'todo' },
      { label: 'custody.detail.flow.spend', state: spent ? 'done' : done ? 'current' : 'todo' },
      { label: 'custody.detail.flow.transfer', state: transferred ? 'done' : 'todo' },
      { label: 'custody.detail.flow.return', state: c.status === 'closed' ? 'done' : returned ? 'current' : 'todo' },
    ];
  }

  ringSegments(c: CustodyDetail): RingSegment[] {
    return [
      { label: 'custody.detail.breakdown.spent', amount: c.spent },
      { label: 'custody.detail.breakdown.sent_transfer', amount: c.transferred_out },
      { label: 'custody.detail.breakdown.returned', amount: c.returned },
      { label: 'custody.detail.breakdown.pending_return', amount: c.pending_return },
      { label: 'custody.detail.breakdown.frozen', amount: c.frozen },
      { label: 'custody.detail.breakdown.remaining', amount: c.remaining },
    ];
  }

  breakdown(c: CustodyDetail): { label: string; value: string }[] {
    return [
      { label: 'custody.detail.breakdown.amount', value: c.amount },
      { label: 'custody.detail.breakdown.received_transfer', value: c.transferred_in },
      { label: 'custody.detail.breakdown.spent', value: c.spent },
      { label: 'custody.detail.breakdown.sent_transfer', value: c.transferred_out },
      { label: 'custody.detail.breakdown.returned', value: c.returned },
      { label: 'custody.detail.breakdown.pending_return', value: c.pending_return },
      { label: 'custody.detail.breakdown.frozen', value: c.frozen },
      { label: 'custody.detail.breakdown.remaining', value: c.remaining },
    ];
  }

  keyValues(c: CustodyDetail): { label: string; value: string }[] {
    return [
      { label: 'custody.detail.agent', value: c.agent ?? '—' },
      { label: 'glossary.treasury', value: c.treasury ?? '—' },
      { label: 'glossary.accountant', value: c.accountant ?? '—' },
      { label: 'common.notes', value: c.notes ?? '—' },
      { label: 'common.date', value: c.created_at ?? '—' },
    ];
  }

  printPage(): void {
    window.print();
  }

  // ── actions ──

  openAccept(): void {
    this.actionError.set(null);
    this.acceptTreasuryId.set(null);
  }

  openReqReturn(): void {
    this.actionError.set(null);
    this.reqReturnAmount.set(null);
    this.reqReturnReason.set('');
  }

  openDonation(): void {
    this.actionError.set(null);
    this.donationType.set('external_donation');
    this.donationAmount.set(null);
    this.donationDescription.set('');
  }

  openRrApprove(r: ReturnRequestRow): void {
    this.actionError.set(null);
    this.rrApprove.set(r);
    this.rrTreasuryId.set(null);
  }

  openRrOwnerApprove(r: ReturnRequestRow): void {
    this.actionError.set(null);
    this.rrOwnerApprove.set(r);
  }

  submitAccept(): void {
    const custody = this.safeValue();
    const treasuryId = this.acceptTreasuryId();
    if (!custody || treasuryId === null || this.acceptInsufficient()) {
      return;
    }
    // personal/agent-requested custody (no treasury yet): single treasury_id;
    // regular custody: the controller requires a treasury_amounts distribution
    // that sums up to the custody amount — one treasury covers it fully here.
    const body = custody.treasury === null
      ? { treasury_id: treasuryId }
      : { treasury_amounts: { [String(treasuryId)]: custody.amount } };
    this.act('accept', body, () => this.acceptDlg().close());
  }

  submitReject(reason: string): void {
    this.act('reject', { reason }, () => this.rejectDlg().done());
  }

  submitCancel(reason: string): void {
    this.act('cancel', { cancel_reason: reason }, () => this.cancelDlg().done());
  }

  submitAgentAccept(): void {
    this.act('agent-accept', {}, () => this.agentAcceptDlg().close());
  }

  submitAgentReject(reason: string): void {
    this.act('agent-reject', { reason }, () => this.agentRejectDlg().done());
  }

  submitReceive(): void {
    this.act('receive', {}, () => this.receiveDlg().close());
  }

  submitReqReturn(): void {
    if (this.reqReturnAmount() === null) {
      return;
    }
    this.act('request-return', { return_amount: this.reqReturnAmount(), reason: this.reqReturnReason() }, () => this.reqReturnDlg().close());
  }

  submitDonation(): void {
    if (this.donationAmount() === null || !this.donationDescription().trim()) {
      return;
    }
    this.act('external-donation', {
      type: this.donationType(),
      amount: this.donationAmount(),
      description: this.donationDescription(),
    }, () => this.donationDlg().close());
  }

  submitRrApprove(): void {
    const request = this.rrApprove();
    if (!request || this.rrTreasuryId() === null) {
      return;
    }
    this.post(`/custody-return-requests/${request.id}/approve-with-treasury`, { treasury_id: this.rrTreasuryId() }, () => this.rrDlg().close());
  }

  submitRrOwnerApprove(): void {
    const request = this.rrOwnerApprove();
    if (!request) {
      return;
    }
    this.post(`/custody-return-requests/${request.id}/approve`, {}, () => this.rrOwnerDlg().close());
  }

  submitReturn(custodyId: number): void {
    if (this.returnAmount() === null) {
      return;
    }
    this.sending.set(true);
    void firstValueFrom(
      this.http.post<ActionResponse>('/custodies/request-return', {
        custody_id: custodyId,
        amount: this.returnAmount(),
        description: this.reason(),
      }),
    )
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.returnOpen.set(false);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.actionError.set((err as HttpErrorResponse));
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }

  act(path: string, body: Record<string, unknown> = {}, onDone?: () => void): void {
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>(`/custodies/${this.id()}/${path}`, body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        onDone?.();
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.actionError.set((err as HttpErrorResponse));
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }

  private post(path: string, body: Record<string, unknown>, onDone?: () => void): void {
    this.sending.set(true);
    void firstValueFrom(this.api.action(path, body))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        onDone?.();
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.actionError.set((err as HttpErrorResponse));
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }
}
