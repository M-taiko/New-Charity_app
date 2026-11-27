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
import { PageActionsComponent } from '@shared/ui/page-actions/page-actions.component';
import type { PageAction } from '@shared/ui/page-actions/page-actions.component';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ReasonDialogComponent } from '@shared/ui/reason-dialog/reason-dialog.component';
import { AppTabsComponent } from '@shared/ui/tabs/tabs.component';
import type { AppTab } from '@shared/ui/tabs/tabs.component';
import type { RibbonStep } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import { FlowRibbonComponent } from '@shared/ui/flow-ribbon/flow-ribbon.component';
import type { RingSegment } from '@shared/ui/balance-ring/balance-ring.component';
import { BalanceRingComponent } from '@shared/ui/balance-ring/balance-ring.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { KeyValueItem } from '@shared/ui/key-value-list/key-value-list.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { EnumLabelPipe } from '@shared/pipes/enum-label.pipe';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';
import { FieldComponent } from '@shared/ui/field/field.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { DatetimeCellComponent } from '@shared/ui/datetime-cell/datetime-cell.component';

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
  imports: [DatetimeCellComponent, FieldComponent, TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, InputTextModule, TextareaModule, SelectModule, PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableCellDirective, StatusStampComponent, PrintSheetComponent, FormDialogComponent, ReasonDialogComponent, AppTabsComponent, FlowRibbonComponent, PageActionsComponent, BalanceRingComponent, MoneyPipe, DatetimePipe, EnumLabelPipe, ActionButtonComponent, IconComponent, StatTileComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-print-sheet [title]="'custody.detail.title' | translate">
      @if (safeValue(); as c) {
        <app-page-header [title]="'#' + c.id" [subtitle]="c.agent ?? ''" accent="custodies" iconName="wallet">
          <!-- T7-10 + R5-01: ONE primary + ONE secondary as buttons, the rest
               (print, transfer when offered, donation) in the More menu;
               status decisions stay in the sticky bar -->
          <app-page-actions actions [pageActions]="headerActions(c)" />
        </app-page-header>
      }

      <app-async-page [resource]="data" variant="detail" [backLink]="['/custodies']">
        @if (safeValue(); as c) {
          <!-- P-04: the status pill rides with the header — #id + agent are
               already in the page header; no separate status row below -->
          <div class="u-flex u-gap-3 u-flex-wrap u-mb-3">
            <app-status-stamp [status]="c.status" namespace="custody_status" [large]="true" />
            <span class="u-small u-muted">{{ c.status_detail }}</span>
          </div>

          <!-- T6-10: one sticky action bar — primary decision first, destructive
               last. R5-01: not rendered at all when it would hold no action -->
          @if (hasStickyAction(c)) {
            <div class="sticky-actions u-flex u-gap-2 u-flex-wrap">
              @if (showApproveRequest(c)) {
                <app-action-button kind="accept" label="custody.detail.approve_request" size="small" (action)="acceptDlg.open(); openAccept()" />
              }
              @if (showAgentAccept(c)) {
                <app-action-button kind="accept" label="custody.detail.agent_accept" size="small" (action)="agentAcceptDlg.open()" />
              }
              @if (showReceive(c)) {
                <app-action-button kind="receive" label="custody.detail.receive" size="small" (action)="receiveDlg.open()" />
              }
              @if (showAgentReturn(c)) {
                <app-action-button kind="returnCustody" label="custody.detail.return" size="small" (action)="returnOpen.set(!returnOpen())" />
              }
              @if (showRequestReturn(c) && !hasPendingReturnRequest(c)) {
                <app-action-button kind="requestReturn" label="custody.detail.request_return" size="small" (action)="reqReturnDlg.open(); openReqReturn()" />
              }
              @if (showRequestReturn(c) && hasPendingReturnRequest(c)) {
                <span class="u-small u-muted">{{ 'custody.detail.pending_return_note' | translate }}</span>
              }
              @if (showAgentAccept(c)) {
                <app-action-button kind="reject" label="custody.detail.reject" size="small" (action)="agentRejectDlg.open()" />
              }
              @if (showApproveRequest(c)) {
                <app-action-button kind="reject" label="custody.detail.reject_request" size="small" (action)="rejectDlg.open()" />
              }
              @if (showCancel(c)) {
                <app-action-button kind="cancel" label="custody.detail.cancel_custody" size="small" (action)="cancelDlg.open()" />
              }
            </div>
          }

          @if (showReturnDisabledHint(c)) {
            <p class="field__hint u-mb-2">{{ 'custody.detail.return_disabled_hint' | translate }}</p>
          }

          <app-flow-ribbon [steps]="flowSteps(c)" />

          <!-- P-04: ONE facts card (clean 2-col key/value, brand band kept) —
               the duplicate status_info card is gone; the spent ratio moved
               under the ring -->
          <div class="u-mt-4">
            <section class="card-hero">
              <header class="card-hero__band">
                <span>{{ 'custody.detail.facts' | translate }}</span>
                <app-icon name="info" [size]="18" />
              </header>
              <div class="card-hero__body">
                <div class="key-value-list">
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'custody.detail.agent' | translate }}</span>
                    <span class="key-value-list__value">{{ c.agent ?? '—' }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'glossary.treasury' | translate }}</span>
                    <span class="key-value-list__value"><span class="stamp stamp--info">{{ c.treasury ?? '—' }}</span></span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'glossary.accountant' | translate }}</span>
                    <span class="key-value-list__value">{{ c.accountant ?? '—' }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'common.date' | translate }}</span>
                    <span class="key-value-list__value u-bidi">{{ c.created_at | datetime: false }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'custody.detail.breakdown.amount' | translate }}</span>
                    <span class="key-value-list__value money money--link u-bidi u-fw-bold">{{ c.amount | money }}</span>
                  </div>
                  <div class="key-value-list__item">
                    <span class="key-value-list__key">{{ 'common.notes' | translate }}</span>
                    <span class="key-value-list__value">{{ c.notes ?? '—' }}</span>
                  </div>
                </div>
                <div class="card-hero__foot u-flex u-gap-2 u-flex-wrap">
                  <app-action-button kind="back" label="common.back" size="small" [route]="['/custodies']" />
                  <app-action-button kind="approve" label="custody.detail.new_request" size="small" [route]="['/custodies', 'new']" />
                </div>
              </div>
            </section>
          </div>

          <!-- P-04: ONE summary strip of 5 tinted mini-cards -->
          <div class="kpi-grid u-mt-4">
            <app-stat-tile [label]="'custody.detail.breakdown.received_transfer'" [value]="c.transferred_in" [money]="true" [tinted]="true" tone="info" />
            <app-stat-tile [label]="'custody.detail.breakdown.sent_transfer'" [value]="c.transferred_out" [money]="true" [tinted]="true" tone="danger" />
            <app-stat-tile [label]="'custody.detail.breakdown.remaining'" [value]="c.remaining" [money]="true" [tinted]="true" tone="success" />
            <app-stat-tile [label]="'custody.detail.breakdown.spent'" [value]="c.spent" [money]="true" [tinted]="true" tone="primary" />
            <app-stat-tile [label]="'custody.detail.breakdown.returned'" [value]="c.returned" [money]="true" [tinted]="true" tone="warning" />
          </div>

          <!-- P-04: ONE balance ring with a 3-item legend + the spent ratio —
               the duplicate "توزيع رصيد العهدة" list is deleted -->
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.breakdown.title' | translate }}</span></div>
            <div class="u-flex u-gap-4 u-flex-wrap u-center-y">
              <app-balance-ring [segments]="ringSegments(c)" [total]="c.remaining" />
              <div class="u-flex-1">
                <div class="u-flex u-justify-between u-small u-mb-1">
                  <span class="u-muted">{{ 'custody.list.col_spent_percent' | translate }}</span>
                  <span class="u-bidi">{{ spentRatio(c) }}%</span>
                </div>
                <div class="progress-cell__bar">
                  <div class="progress-cell__fill" [style.width.%]="spentRatio(c)"></div>
                </div>
              </div>
            </div>
          </section>

          <app-tabs class="u-mt-4" id="custody-detail" [tabs]="tabs()" [(active)]="detailTab" />

          @if (detailTab() === 'returns') {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.returns' | translate }}</span></div>
            <app-data-table [phoneFirst]="['requested_at', 'status', 'amount']" mode="static" [autoFilters]="true" [staticPaginated]="false" [columns]="returnRequestCols" [rows]="c.return_requests">
              <ng-template appCell="requested_at" let-row><app-datetime-cell [value]="row.requested_at" /></ng-template>
              <ng-template appCell="amount" let-row>{{ row.amount | money: false }}</ng-template>
              <ng-template appCell="reason" let-row>{{ row.reason || '—' }}
@if (row.rejection_reason) { <span class="u-small u-muted">{{ row.rejection_reason }}</span> }</ng-template>
              <ng-template appCell="status" let-row><app-status-stamp [status]="row.status" namespace="return_request" /></ng-template>
              <ng-template appCell="requested_by" let-row>{{ row.requested_by ?? '—' }}</ng-template>
              <ng-template appCell="decided" let-row>@if (row.approved_by) {
  <span class="u-bidi">{{ row.approved_by }} — {{ row.approved_at | datetime: false }}</span>
} @else { <span class="u-muted">—</span> }</ng-template>
              <ng-template appCell="actions" let-row>@if (row.status === 'pending') {
  @if (canManageTreasury()) {
    <app-action-button kind="approve" label="custody.detail.approve_rr" size="small" (action)="rrDlg.open(); openRrApprove(row)" />
  } @else if (c.initiated_by === 'agent' && meId() === c.agent_id) {
    <app-action-button kind="approve" label="custody.detail.approve_rr" size="small" (action)="rrOwnerDlg.open(); openRrOwnerApprove(row)" />
  }
}</ng-template>
            </app-data-table>
          </section>

          }

          @if (detailTab() === 'transactions') {
          <!-- P-04: the journal ledger — datetime · type pill · in (green) ·
               out (red) · running balance · description, with footer totals -->
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.transactions' | translate }}</span></div>
            <app-data-table mode="static" [staticPaginated]="false" [phoneFirst]="['date', 'in', 'out']" [columns]="ledgerCols" [rows]="ledgerRows(c)">
              <ng-template appCell="date" let-row><app-datetime-cell [value]="row.date" /></ng-template>
              <ng-template appCell="type" let-row><span class="stamp" [class]="txStampClass(row.type)">{{ row.type | enumLabel: 'transaction_type' }}</span></ng-template>
              <ng-template appCell="in" let-row>@if (row.delta >= 0) { <span class="money money--pos u-bidi">{{ row.amount | money: false }}</span> } @else { <span class="u-muted">—</span> }</ng-template>
              <ng-template appCell="out" let-row>@if (row.delta < 0) { <span class="money money--neg u-bidi">{{ row.amount | money: false }}</span> } @else { <span class="u-muted">—</span> }</ng-template>
              <ng-template appCell="balance" let-row><span class="u-bidi u-fw-semibold">{{ row.balance | money: false }}</span></ng-template>
              <ng-template appCell="description" let-row>{{ row.description }}</ng-template>
              <ng-template appFooterCell="description">
                <span class="u-fw-semibold">{{ 'common.total' | translate }}</span>
              </ng-template>
              <ng-template appFooterCell="in"><span class="money money--pos u-bidi u-fw-semibold">{{ ledgerTotals(c).in | money: false }}</span></ng-template>
              <ng-template appFooterCell="out"><span class="money money--neg u-bidi u-fw-semibold">{{ ledgerTotals(c).out | money: false }}</span></ng-template>
            </app-data-table>
          </section>

          }

          @if (detailTab() === 'expenses') {
          <section class="chart-card u-mt-4">
            <div class="chart-card__head"><span class="chart-card__title">{{ 'custody.detail.expenses' | translate }}</span></div>
            <app-data-table mode="static" [autoFilters]="true" [staticPaginated]="false" [phoneFirst]="['date', 'amount']" [columns]="expenseCols" [rows]="c.expenses">
              <ng-template appCell="date" let-row><span class="u-bidi">{{ row.date | datetime: false }}</span></ng-template>
              <ng-template appCell="description" let-row><a [routerLink]="['/expenses', row.id]">{{ row.description || '—' }}</a>
@if (row.split.length > 1) { <span class="stamp stamp--info">{{ 'expense.detail.custody_split_note' | translate }}</span> }</ng-template>
              <ng-template appCell="direction" let-row><span class="u-small">{{ row.direction || ('expense.list.filter.no_direction' | translate) }}</span></ng-template>
              <ng-template appCell="amount" let-row>{{ row.amount | money: false }}</ng-template>
            </app-data-table>
          </section>

          }

          @if (returnOpen()) {
            <section class="form-section u-mt-4">
              <h3 class="form-section__title">{{ 'custody.mine.return_dialog.title' | translate }}</h3>
              <div class="form-section__grid">
                <app-field label="custody.detail.return_amount" controlId="ret-amount" [required]="true">
                  <p-inputnumber inputId="ret-amount" [(ngModel)]="returnAmount" name="returnAmount" mode="decimal"
                                 [minFractionDigits]="2" [min]="0.01" [max]="parseFloat(c.remaining)" styleClass="u-full" />
                  <span class="field__hint u-bidi">{{ 'glossary.remaining' | translate }}: {{ c.remaining | money }}</span>
                </app-field>
                <app-field label="custody.detail.return_reason" controlId="ret-reason" [required]="true">
                  <input pInputText id="ret-reason" [(ngModel)]="reason" name="reason" class="u-full" />
                </app-field>
              </div>
              <div class="u-flex u-justify-end">
                <p-button [label]="'common.cancel' | translate" [text]="true" (onClick)="returnOpen.set(false)" />
                <p-button [label]="'custody.mine.return_to_treasury' | translate" (onClick)="submitReturn(c.id)" [loading]="sending()" />
              </div>
            </section>
          }
        }
      </app-async-page>

      <!-- approve the agent's request: choose the treasury that funds the custody -->
      <app-form-dialog #acceptDlg [header]="'custody.detail.accept_dialog' | translate"
                       [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                       (submit)="submitAccept()">
        <div class="u-column u-gap-3">
          @if (safeValue(); as c) {
            <app-field label="common.amount">
              <p class="u-bidi u-m-0 u-fw-bold">{{ c.amount | money }}</p>
            </app-field>
          }
          <app-field label="custody.create.treasury" controlId="accept-treasury" [required]="true">
            <p-select appendTo="body" inputId="accept-treasury" [options]="treasuryOptions()" optionLabel="name" optionValue="id"
                      [(ngModel)]="acceptTreasuryId" name="acceptTreasuryId" styleClass="u-full">
              <ng-template let-option #item>
                <div class="u-flex-between">
                  <span>{{ option.name }}</span>
                  <span class="u-bidi u-small">{{ option.balance | money }}</span>
                </div>
              </ng-template>
            </p-select>
          </app-field>
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

      <!-- manager rejects the agent's request — N-06: every decision below shows the money it decides about -->
      <app-reason-dialog #rejectDlg [header]="'custody.detail.reject' | translate"
                         [label]="'custody.detail.reject_reason' | translate" [submitLabel]="'common.confirm' | translate"
                         [details]="custodyDetails()" [sending]="sending()" [error]="actionError()" (submit)="submitReject($event)" />

      <!-- accountant/manager cancels their own pending custody -->
      <app-reason-dialog #cancelDlg [header]="'custody.detail.cancel_custody' | translate"
                         [label]="'custody.detail.cancel_reason' | translate" [submitLabel]="'common.confirm' | translate"
                         [details]="custodyDetails()" [minLength]="1" [sending]="sending()" [error]="actionError()" (submit)="submitCancel($event)" />

      <!-- agent rejects the custody the accountant created -->
      <app-reason-dialog #agentRejectDlg [header]="'custody.detail.reject' | translate"
                         [label]="'custody.detail.reject_reason' | translate" [submitLabel]="'common.confirm' | translate"
                         [details]="custodyDetails()" [minLength]="1" [sending]="sending()" [error]="actionError()" (submit)="submitAgentReject($event)" />

      <!-- agent confirms receiving the money -->
      <app-form-dialog #receiveDlg [header]="'custody.detail.receive' | translate"
                       [submitLabel]="'common.confirm' | translate" [details]="custodyDetails()" [sending]="sending()" [error]="actionError()"
                       (submit)="submitReceive()">
        <p>{{ 'custody.detail.receive_confirm' | translate }}</p>
      </app-form-dialog>

      <!-- agent accepts the custody the accountant sent -->
      <app-form-dialog #agentAcceptDlg [header]="'custody.detail.agent_accept' | translate"
                       [submitLabel]="'common.confirm' | translate" [details]="custodyDetails()" [sending]="sending()" [error]="actionError()"
                       (submit)="submitAgentAccept()">
        <p>{{ 'custody.detail.agent_accept_confirm' | translate }}</p>
      </app-form-dialog>

      <!-- non-agent asks the custody owner to return money to a treasury -->
      <app-form-dialog #reqReturnDlg [header]="'custody.detail.request_return_dialog' | translate"
                       [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                       (submit)="submitReqReturn()">
        <div class="u-column u-gap-3">
          @if (safeValue(); as c) {
            <app-field label="custody.detail.return_amount" controlId="rrq-amount" [required]="true">
              <p-inputnumber inputId="rrq-amount" [(ngModel)]="reqReturnAmount" name="reqReturnAmount" mode="decimal"
                             [minFractionDigits]="2" [min]="0.01" [max]="parseFloat(c.remaining)" styleClass="u-full" />
              <span class="field__hint u-bidi">{{ 'glossary.remaining' | translate }}: {{ c.remaining | money }}</span>
            </app-field>
            <app-field label="common.reason" controlId="rrq-reason">
              <textarea pTextarea id="rrq-reason" rows="2" [(ngModel)]="reqReturnReason" name="reqReturnReason" class="u-full"></textarea>
            </app-field>
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
            <app-field label="common.amount">
              <p class="u-bidi u-m-0 u-fw-bold">{{ r.amount | money }}</p>
              <span class="u-small u-muted">{{ 'custody.detail.rr_requester' | translate }}: {{ r.requested_by ?? '—' }}</span>
            </app-field>
          }
          <app-field label="custody.create.treasury" controlId="rr-treasury" [required]="true">
            <p-select appendTo="body" inputId="rr-treasury" [options]="treasuryOptions()" optionLabel="name" optionValue="id"
                      [(ngModel)]="rrTreasuryId" name="rrTreasuryId" styleClass="u-full">
              <ng-template let-option #item>
                <div class="u-flex-between">
                  <span>{{ option.name }}</span>
                  <span class="u-bidi u-small">{{ option.balance | money }}</span>
                </div>
              </ng-template>
            </p-select>
          </app-field>
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
                       [submitLabel]="'common.confirm' | translate" [details]="returnDetails(rrOwnerApprove())" [sending]="sending()" [error]="actionError()"
                       (submit)="submitRrOwnerApprove()">
        <p>{{ 'custody.detail.rr_owner_note' | translate }}</p>
      </app-form-dialog>

      <!-- external donation (new standalone custody) / expense refund (back into this custody) -->
      <app-form-dialog #donationDlg [header]="'custody.detail.donation_add' | translate"
                       [submitLabel]="'common.confirm' | translate" [sending]="sending()" [error]="actionError()"
                       (submit)="submitDonation()">
        <div class="u-column u-gap-3">
          <app-field label="custody.detail.donation_type" controlId="don-type" [required]="true">
            <p-select appendTo="body" inputId="don-type" [options]="donationTypeOptions" optionLabel="label" optionValue="value"
                      [(ngModel)]="donationType" name="donationType" styleClass="u-full" />
          </app-field>
          <app-field label="common.amount" controlId="don-amount" [required]="true">
            <p-inputnumber inputId="don-amount" [(ngModel)]="donationAmount" name="donationAmount" mode="decimal"
                           [minFractionDigits]="2" [min]="0.01" styleClass="u-full" />
          </app-field>
          <app-field label="common.description" controlId="don-desc" [required]="true">
            <input pInputText id="don-desc" [(ngModel)]="donationDescription" name="donationDescription" class="u-full" />
          </app-field>
          <p class="field__hint">{{ 'custody.detail.donation_hint' | translate }}</p>
        </div>
      </app-form-dialog>
    </app-print-sheet>
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

  /** P-04: three tabs — expenses / return requests / the journal ledger
   *  (the always-visible overview content is not a tab any more) */
  detailTab = signal<string>('expenses');
  readonly tabs = computed<AppTab[]>(() => [
    { id: 'expenses', label: 'custody.detail.expenses' },
    { id: 'returns', label: 'custody.detail.returns' },
    { id: 'transactions', label: 'custody.detail.transactions' },
  ]);
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

  private moneyPipe = new MoneyPipe();

  /** N-06: what a decision on this custody is about — its amount, who holds it, which treasury funds it */
  readonly custodyDetails = computed<readonly KeyValueItem[]>(() => {
    const c = this.safeValue();
    if (c === null) {
      return [];
    }
    return [
      { label: 'common.amount_egp', value: this.moneyPipe.transform(c.amount, false) },
      { label: 'custody.list.col_agent', value: c.agent ?? '—' },
      { label: 'glossary.treasury', value: c.treasury ?? '—' },
    ];
  });

  /** N-06: what approving a return request returns */
  returnDetails(r: ReturnRequestRow | null): readonly KeyValueItem[] {
    if (r === null) {
      return [];
    }
    return [
      { label: 'common.amount_egp', value: this.moneyPipe.transform(r.amount, false) },
      { label: 'custody.return_requests.col_requested_by', value: r.requested_by ?? '—' },
    ];
  }
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

  readonly returnRequestCols = [
    { field: 'requested_at', header: 'common.date' },
    { field: 'amount', header: 'common.amount', numeric: true },
    { field: 'reason', header: 'custody.detail.return_reason' },
    { field: 'status', header: 'common.status' },
    { field: 'requested_by', header: 'custody.detail.rr_requester' },
    { field: 'decided', header: 'custody.detail.rr_decided' },
    { field: 'actions', header: 'common.actions' },
  ];

  /** P-04: the journal ledger columns — in/out split + running balance */
  readonly ledgerCols = [
    { field: 'date', header: 'common.date', minWidth: 110 },
    { field: 'type', header: 'common.type' },
    { field: 'in', header: 'custody.detail.ledger_in', numeric: true, minWidth: 110 },
    { field: 'out', header: 'custody.detail.ledger_out', numeric: true, minWidth: 110 },
    { field: 'balance', header: 'custody.detail.ledger_balance', numeric: true, minWidth: 110 },
    { field: 'description', header: 'common.description' },
  ];

  readonly expenseCols = [
    { field: 'date', header: 'common.date' },
    { field: 'description', header: 'common.description' },
    { field: 'direction', header: 'expense.list.col_direction' },
    { field: 'amount', header: 'common.amount', numeric: true },
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

  /** secondary actions live in the header More menu (T6-10) */
  /** T7-10: the header action list — the component enforces visibility + More */
  /**
   * R5-01: the primary first, one secondary beside it, everything else in
   * the More menu. `print` is pinned to the menu: it is a FILLED brand button
   * by kind (it is the one primary on the report pages), and beside
   * "طلب عهدة جديدة" that made two primaries in one header.
   */
  headerActions(c: CustodyDetail): PageAction[] {
    return [
      { kind: 'create', label: 'custody.mine.request_new', route: ['/custodies/new'], visible: this.isAgent() },
      { kind: 'edit', label: 'common.edit', route: ['/custodies', c.id, 'edit'], visible: this.canManageTreasury() },
      { kind: 'transfer', label: 'custody.detail.transfer_to_agent', route: ['/transfers/new'], visible: this.showTransferShortcut(c) },
      { kind: 'donate', label: 'custody.detail.donation_add', run: () => { this.donationDlg().open(); this.openDonation(); }, visible: this.showDonation(c) },
      { kind: 'print', label: 'common.print', run: () => { this.printPage(); }, menu: true },
    ];
  }

  /**
   * R5-01: the sticky bar is rendered only when it holds an action. A closed
   * custody has none, and the unconditional bar showed as an empty 25px strip
   * under the status line.
   */
  hasStickyAction(c: CustodyDetail): boolean {
    return this.showApproveRequest(c) || this.showAgentAccept(c) || this.showReceive(c) || this.showAgentReturn(c) || this.showRequestReturn(c) || this.showCancel(c);
  }

  isAgent(): boolean {
    return this.auth.can('is_agent');
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

  /** RD-4.10 journal ledger: movement-type pill color per transaction type */
  txStampClass(type: string): string {
    if (type.includes('return')) {
      return 'stamp--success';
    }
    if (type.includes('transfer_in') || type === 'custody_out') {
      return 'stamp--info';
    }
    if (type.includes('transfer_out')) {
      return 'stamp--danger';
    }
    return 'stamp--neutral';
  }

  /** RD-4.10 journal ledger: signed money color (in green / out rose) */
  txMoneyClass(type: string): string {
    if (type.includes('return') || type.includes('transfer_in') || type === 'custody_out') {
      return 'money money--pos';
    }
    if (type.includes('transfer_out') || type.includes('expense')) {
      return 'money money--neg';
    }
    return '';
  }

  /** RD-4.10: (spent + returned) / (amount + transfers-in), pinned to 0..100 */
  spentRatio(c: CustodyDetail): number {
    const base = this.parseFloat(c.amount) + this.parseFloat(c.transferred_in);
    if (base <= 0) {
      return 0;
    }
    return Math.max(0, Math.min(100, Math.round(((this.parseFloat(c.spent) + this.parseFloat(c.returned)) / base) * 100)));
  }

  /** P-04: THREE ring segments — spent / returned / remaining (the 6-way
   *  split repeated the mini-cards and the old breakdown list) */
  ringSegments(c: CustodyDetail): RingSegment[] {
    return [
      { label: 'custody.detail.breakdown.spent', amount: c.spent },
      { label: 'custody.detail.breakdown.returned', amount: c.returned },
      { label: 'custody.detail.breakdown.remaining', amount: c.remaining },
    ];
  }

  /** P-04: the journal ledger — transactions in chronological order with a
   *  running balance (in-green/out-red semantics from txMoneyClass) */
  ledgerRows(c: CustodyDetail): { id: number; type: string; amount: string; delta: number; balance: string; description: string | null; user: string | null; date: string | null }[] {
    const chronological = [...c.transactions].reverse();
    let balance = 0;
    return chronological.map((tx) => {
      const amount = this.parseFloat(tx.amount);
      // the running balance must end at c.remaining — sanity-check in dev
      const delta = this.txMoneyClass(tx.type).includes('pos') ? amount : -amount;
      balance += delta;
      return { ...tx, delta, balance: balance.toFixed(2) };
    });
  }

  /** P-04: footer totals for the ledger's in/out columns */
  ledgerTotals(c: CustodyDetail): { in: string; out: string } {
    let totalIn = 0;
    let totalOut = 0;
    for (const row of this.ledgerRows(c)) {
      if (row.delta >= 0) {
        totalIn += row.delta;
      } else {
        totalOut += -row.delta;
      }
    }
    return { in: totalIn.toFixed(2), out: totalOut.toFixed(2) };
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
    this.act('accept', body, () => { this.acceptDlg().close(); });
  }

  submitReject(reason: string): void {
    this.act('reject', { reason }, () => { this.rejectDlg().done(); });
  }

  submitCancel(reason: string): void {
    this.act('cancel', { cancel_reason: reason }, () => { this.cancelDlg().done(); });
  }

  submitAgentAccept(): void {
    this.act('agent-accept', {}, () => { this.agentAcceptDlg().close(); });
  }

  submitAgentReject(reason: string): void {
    this.act('agent-reject', { reason }, () => { this.agentRejectDlg().done(); });
  }

  submitReceive(): void {
    this.act('receive', {}, () => { this.receiveDlg().close(); });
  }

  submitReqReturn(): void {
    if (this.reqReturnAmount() === null) {
      return;
    }
    this.act('request-return', { return_amount: this.reqReturnAmount(), reason: this.reqReturnReason() }, () => { this.reqReturnDlg().close(); });
  }

  submitDonation(): void {
    if (this.donationAmount() === null || !this.donationDescription().trim()) {
      return;
    }
    this.act('external-donation', {
      type: this.donationType(),
      amount: this.donationAmount(),
      description: this.donationDescription(),
    }, () => { this.donationDlg().close(); });
  }

  submitRrApprove(): void {
    const request = this.rrApprove();
    if (!request || this.rrTreasuryId() === null) {
      return;
    }
    this.post(`/custody-return-requests/${request.id}/approve-with-treasury`, { treasury_id: this.rrTreasuryId() }, () => { this.rrDlg().close(); });
  }

  submitRrOwnerApprove(): void {
    const request = this.rrOwnerApprove();
    if (!request) {
      return;
    }
    this.post(`/custody-return-requests/${request.id}/approve`, {}, () => { this.rrOwnerDlg().close(); });
  }

  submitReturn(custodyId: number): void {
    if (this.sending()) {
      return;
    }
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
