// Purchase request details (plan 20, T3-70): approve dialog with accounting
// category + treasury distribution (the API requires expense_category_id,
// treasury_id and treasury_amounts summing to the estimated cost), reject
// with a required reason, mark purchased with actual cost, delete.
// T4-20: async-page states; T4-23: app-field in the approve/purchase dialogs.
import type { WritableSignal } from '@angular/core';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { SelectModule } from 'primeng/select';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { PrintSheetComponent } from '@shared/ui/print-sheet/print-sheet.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { ReasonDialogComponent } from '@shared/ui/reason-dialog/reason-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { DirectionNode } from '@shared/models/api.models';

interface PurchaseDetail { id: number; [key: string]: string | number | boolean | null | undefined }

/**
 * Purchase request details (plan 20, T3-70): approve dialog with accounting
 * category + treasury distribution (the API requires expense_category_id,
 * treasury_id and treasury_amounts summing to the estimated cost), reject
 * with a required reason, mark purchased with actual cost, delete.
 */
@Component({
  selector: 'app-purchase-detail',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, InputNumberModule, SelectModule, PageHeaderComponent, AsyncPageComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, FormDialogComponent, FieldComponent, ReasonDialogComponent, ConfirmDeleteComponent, MoneyPipe],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-async-page [resource]="data" variant="detail" [backLink]="['/procurement/purchase']">
      @if (safeValue(); as item) {
        <app-print-sheet [title]="'procurement.purchase.detail.title' | translate">
          <app-page-header [title]="itemTitle(item)">
            <div actions class="u-flex u-gap-2 u-flex-wrap">
              <!-- T6-10: decisions live in the sticky bar; back is the async-page backLink -->
              <p-button [label]="'common.back_to_list' | translate" size="small" severity="secondary" [outlined]="true" routerLink="/procurement/purchase" />
            </div>
          </app-page-header>
          <!-- T6-10: one sticky action bar — primary decision first, destructive last -->
          <div class="sticky-actions u-flex u-gap-2 u-flex-wrap">
            @if (canReview() && statusOf(item) === 'pending') {
              <p-button [label]="'procurement.purchase.detail.approve' | translate" size="small" severity="success" (onClick)="openApprove()" />
            }
            @if (canReview() && statusOf(item) === 'approved') {
              <p-button [label]="'procurement.purchase.detail.purchase' | translate" size="small" severity="success" (onClick)="openPurchase()" />
            }
            @if (canReview() && statusOf(item) === 'pending') {
              <p-button [label]="'procurement.purchase.detail.reject' | translate" size="small" severity="danger" [outlined]="true" (onClick)="rejectDlg.open()" />
            }
            @if (canDelete(item)) {
              <p-button [label]="'procurement.purchase.detail.delete' | translate" size="small" severity="danger" [outlined]="true" (onClick)="deleteDlg.open()" />
            }
          </div>

          <div class="u-mb-4"><app-status-stamp [status]="statusOf(item)" namespace="purchase_request" [large]="true" /></div>
          <app-key-value-list [items]="keyValues(item)" />
          <ng-content />
        </app-print-sheet>

        <!-- Approve: category + treasury + amount covering the estimated cost (T3-70 bug fix) -->
        <app-form-dialog
          [header]="'procurement.purchase.detail.approve' | translate"
          [submitLabel]="'procurement.purchase.detail.approve_and_spend' | translate"
          [sending]="sending()"
          [error]="approveError()"
          [(visible)]="approveOpen"
          (submit)="submitApprove()"
        >
          <div class="u-column u-gap-3">
            <app-field label="procurement.purchase.detail.accounting_category" controlId="approve-category" [required]="true">
              <p-select appendTo="body"
                inputId="approve-category"
                [options]="categories()"
                optionLabel="name"
                optionValue="id"
                [(ngModel)]="approveCategoryId"
                name="expense_category_id"
                [placeholder]="'common.choose' | translate"
                styleClass="u-full"
              />
            </app-field>
            <app-field label="procurement.purchase.detail.main_treasury" controlId="approve-treasury" [required]="true">
              <p-select appendTo="body"
                inputId="approve-treasury"
                [options]="treasuries()"
                optionLabel="name"
                optionValue="id"
                [(ngModel)]="approveTreasuryId"
                name="treasury_id"
                (ngModelChange)="onTreasuryChange()"
                [placeholder]="'common.choose' | translate"
                styleClass="u-full"
              />
              @if (selectedTreasury(); as t) {
                <p class="field__hint u-bidi">{{ 'treasury.list.balance' | translate }}: {{ t.balance | money }}</p>
              }
            </app-field>
            <app-field label="procurement.purchase.detail.distribution_amount" controlId="approve-amount" [required]="true">
              <p-inputnumber inputId="approve-amount"
                [ngModel]="approveAmount"
                (ngModelChange)="approveAmount = $event"
                [maxFractionDigits]="2" [min]="0.01" mode="decimal" name="treasury_amount"
                styleClass="u-full"
              />
              @if (selectedTreasury(); as t) {
                <p class="field__hint u-bidi">{{ 'procurement.purchase.detail.balance_after' | translate }}: {{ balanceAfter(t.balance) | money }}</p>
              }
            </app-field>
            <div class="u-flex u-gap-3 u-flex-wrap">
              <span class="field__hint u-bidi">{{ 'procurement.purchase.detail.amount_required' | translate }}: {{ estimated() | money }}</span>
              <span class="field__hint u-bidi">{{ 'procurement.purchase.detail.amount_entered' | translate }}: {{ enteredAmount() | money }}</span>
            </div>
            @if (!amountMatches()) {
              <p class="field__error">{{ 'procurement.purchase.detail.amount_mismatch' | translate }}</p>
            }
          </div>
        </app-form-dialog>

        <!-- Reject: required reason (T3-02) -->
        <app-reason-dialog
          #rejectDlg
          [header]="'procurement.purchase.detail.reject' | translate"
          [label]="'procurement.purchase.detail.reject_reason' | translate"
          [submitLabel]="'procurement.purchase.detail.confirm_reject' | translate"
          [sending]="sending()"
          [error]="rejectError()"
          (submit)="submitReject($event)"
        />

        <!-- Mark purchased: actual cost -->
        <app-form-dialog
          [header]="'procurement.purchase.detail.purchase' | translate"
          [submitLabel]="'procurement.purchase.detail.confirm_purchase' | translate"
          [sending]="sending()"
          [error]="purchaseError()"
          [(visible)]="purchaseOpen"
          (submit)="submitPurchase()"
        >
          <app-field label="procurement.purchase.detail.actual_cost" controlId="purchase-cost">
            <p-inputnumber inputId="purchase-cost"
              [ngModel]="purchaseCost"
              (ngModelChange)="purchaseCost = $event"
              [minFractionDigits]="2" [min]="0" mode="decimal" name="actual_cost"
              styleClass="u-full"
            />
            <p class="field__hint u-bidi">{{ 'procurement.purchase.detail.amount_required' | translate }}: {{ estimated() | money }}</p>
          </app-field>
        </app-form-dialog>

        <app-confirm-delete
          #deleteDlg
          [path]="'/purchase-requests/' + id() + '/delete'"
          [entity]="itemTitle(item)"
          (deleted)="onDeleted()"
        />
      }
    </app-async-page>
  `,
})
export class PurchaseDetailPage {
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private router = inject(Router);

  id = input.required<number>();
  sending = signal(false);

  readonly rejectDlg = viewChild.required(ReasonDialogComponent);

  approveError = signal<HttpErrorResponse | null>(null);
  rejectError = signal<HttpErrorResponse | null>(null);
  purchaseError = signal<HttpErrorResponse | null>(null);

  approveOpen = signal(false);
  purchaseOpen = signal(false);

  approveCategoryId = signal<number | null>(null);
  approveTreasuryId = signal<number | null>(null);
  approveAmount: number | null = null;
  purchaseCost: number | null = null;

  treasuries = signal<{ id: number; name: string; balance: string }[]>([]);
  categories = signal<DirectionNode[]>([]);

  data = resource<PurchaseDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<PurchaseDetail>(`/purchase-requests/${params.id}`)),
  });

  /** data.value() throws on an errored resource (e.g. 404) — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** Blade: @can('approve_custody') — managers/accountants review */
  readonly canReview = computed(() => this.auth.can('may_approve_custody'));

  readonly estimated = computed(() => parseFloat(String(this.safeValue()?.estimated_cost ?? '0')) || 0);

  enteredAmount(): number {
    return this.approveAmount ?? 0;
  }

  /** the server requires the distribution to equal the estimated cost (±0.01) */
  amountMatches(): boolean {
    return this.approveAmount !== null && Math.abs(this.enteredAmount() - this.estimated()) <= 0.01;
  }

  selectedTreasury(): { id: number; name: string; balance: string } | null {
    return this.treasuries().find((t) => t.id === this.approveTreasuryId()) ?? null;
  }

  balanceAfter(balance: string): string {
    return ((parseFloat(balance) || 0) - this.enteredAmount()).toFixed(2);
  }

  /**
   * Blade shows delete for the requester while pending; the controller also
   * allows managers at any status. The API payload carries the requester name
   * only, so pending-or-manager is the closest enforceable rule (server 403s).
   */
  canDelete(item: PurchaseDetail): boolean {
    return this.auth.can('is_manager') || this.statusOf(item) === 'pending';
  }

  statusOf(item: PurchaseDetail): string {
    return String(item.status ?? '');
  }

  itemTitle(item: PurchaseDetail): string {
    return `${String(item.title)} #${item.id}`;
  }

  keyValues(item: PurchaseDetail): { label: string; value: string }[] {
    return [
      { label: 'procurement.purchase.col_requester', value: String(item.requested_by ?? '—') },
      { label: 'procurement.purchase.form.supplier', value: String(item.supplier ?? '—') },
      { label: 'procurement.purchase.form.category', value: String(item.category ?? '—') },
      { label: 'procurement.purchase.form.priority', value: String(item.priority ?? '—') },
      { label: 'procurement.purchase.form.estimated_cost', value: String(item.estimated_cost ?? '—') },
      { label: 'procurement.purchase.detail.actual_cost', value: String(item.actual_cost ?? '—') },
      { label: 'common.description', value: String(item.description ?? '—') },
      { label: 'procurement.purchase.form.needed_by', value: String(item.needed_by ?? '—') },
      { label: 'transfer.list.col_rejection', value: String(item.rejection_reason ?? '—') },
    ];
  }

  openApprove(): void {
    this.approveError.set(null);
    this.approveCategoryId.set(null);
    this.approveTreasuryId.set(null);
    this.approveAmount = this.estimated() > 0 ? this.estimated() : null;
    if (this.treasuries().length === 0 || this.categories().length === 0) {
      void firstValueFrom(this.api.loadLookups()).then((lookups) => {
        this.treasuries.set(lookups.treasuries);
        this.categories.set(lookups.direction_tree);
      });
    }
    this.approveOpen.set(true);
  }

  onTreasuryChange(): void {
    if (this.approveAmount === null && this.estimated() > 0) {
      this.approveAmount = this.estimated();
    }
  }

  submitApprove(): void {
    const treasuryId = this.approveTreasuryId();
    if (treasuryId === null || this.approveCategoryId() === null || this.approveAmount === null || !this.amountMatches()) {
      return;
    }
    this.sending.set(true);
    this.approveError.set(null);
    void firstValueFrom(this.api.action(`/purchase-requests/${this.id()}/approve`, {
      expense_category_id: this.approveCategoryId(),
      treasury_id: treasuryId,
      treasury_amounts: { [treasuryId]: this.approveAmount },
    }))
      .then((res) => { this.onActionDone(res.message, () => { this.approveOpen.set(false); }, this.approveError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.approveError); });
  }

  submitReject(reason: string): void {
    this.sending.set(true);
    this.rejectError.set(null);
    void firstValueFrom(this.api.action(`/purchase-requests/${this.id()}/reject`, { rejection_reason: reason }))
      .then((res) => { this.onActionDone(res.message, () => { this.rejectDlg().done(); }, this.rejectError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.rejectError); });
  }

  openPurchase(): void {
    this.purchaseError.set(null);
    this.purchaseCost = this.estimated() > 0 ? this.estimated() : null;
    this.purchaseOpen.set(true);
  }

  submitPurchase(): void {
    this.sending.set(true);
    this.purchaseError.set(null);
    void firstValueFrom(this.api.action(`/purchase-requests/${this.id()}/purchased`, {
      actual_cost: this.purchaseCost ?? undefined,
    }))
      .then((res) => { this.onActionDone(res.message, () => { this.purchaseOpen.set(false); }, this.purchaseError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.purchaseError); });
  }

  onDeleted(): void {
    void this.router.navigateByUrl('/procurement/purchase');
  }

  private onActionDone(message: string, close: () => void, error: WritableSignal<HttpErrorResponse | null>): void {
    this.sending.set(false);
    error.set(null);
    close();
    this.toast.success(message);
    this.data.reload();
  }

  private onActionFailed(err: HttpErrorResponse, error: WritableSignal<HttpErrorResponse | null>): void {
    this.sending.set(false);
    error.set(err);
    this.toast.apiError(err.error?.message ?? 'errors.generic', err.error?.code);
  }
}
