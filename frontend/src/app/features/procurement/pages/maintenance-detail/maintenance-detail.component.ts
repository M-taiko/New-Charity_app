// Maintenance request details (plan 20, T3-71): assign to a user, reject with
// a reason, resolve with notes (the shared reason dialog replaces the previous
// hard-coded text), delete. T4-20: async-page states; T4-23: app-field for the
// assign dialog.
import type { WritableSignal } from '@angular/core';
import { computed, ChangeDetectionStrategy, Component, inject, input, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
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
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';

interface MaintenanceDetail { id: number; [key: string]: string | number | boolean | null | undefined }

/**
 * Maintenance request details (plan 20, T3-71): assign to a user, reject with
 * a reason, resolve with notes (the shared reason dialog replaces the previous
 * hard-coded text), delete.
 */
@Component({
  selector: 'app-maintenance-detail',
  standalone: true,
  imports: [TranslatePipe, RouterLink, FormsModule, ButtonModule, SelectModule, PageHeaderComponent, AsyncPageComponent, KeyValueListComponent, StatusStampComponent, PrintSheetComponent, FormDialogComponent, FieldComponent, ReasonDialogComponent, ConfirmDeleteComponent, ActionButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-async-page [resource]="data" variant="detail" [backLink]="['/procurement/maintenance']">
      @if (safeValue(); as item) {
        <app-print-sheet [title]="'procurement.maintenance.detail.title' | translate">
          <app-page-header [title]="itemTitle(item)" accent="procurement" iconName="shopping-cart">
            <div actions class="u-flex u-gap-2 u-flex-wrap">
              <!-- T6-10: decisions live in the sticky bar; back is the async-page backLink -->
              <app-action-button kind="back" label="common.back_to_list" size="small" [route]="['/procurement/maintenance']" />
            </div>
          </app-page-header>
          <!-- T6-10: one sticky action bar — primary decision first, destructive last -->
          <div class="sticky-actions u-flex u-gap-2 u-flex-wrap">
            @if (canReview() && statusOf(item) === 'pending') {
              <app-action-button kind="assign" label="procurement.maintenance.detail.assign" size="small" (action)="openAssign()" />
            }
            @if (canResolve(item)) {
              <app-action-button kind="resolve" label="procurement.maintenance.detail.resolve" size="small" (action)="resolveDlg.open()" />
            }
            @if (canReview() && statusOf(item) === 'pending') {
              <app-action-button kind="reject" label="procurement.maintenance.detail.reject" size="small" (action)="rejectDlg.open()" />
            }
            @if (canDelete()) {
              <app-action-button kind="delete" label="common.delete" size="small" (action)="deleteDlg.open()" />
            }
          </div>

          <div class="u-mb-4"><app-status-stamp [status]="statusOf(item)" namespace="maintenance_request" [large]="true" /></div>
          <app-key-value-list [items]="keyValues(item)" />
          <ng-content />
        </app-print-sheet>

        <!-- Assign: user picker → POST assign with assigned_to (T3-71) -->
        <app-form-dialog
          [header]="'procurement.maintenance.detail.assign' | translate"
          [submitLabel]="'procurement.maintenance.detail.assign' | translate"
          [sending]="sending()"
          [error]="assignError()"
          [(visible)]="assignOpen"
          (submit)="submitAssign()"
        >
          <app-field label="procurement.maintenance.detail.assignee" controlId="assign-user" [required]="true">
            <p-select appendTo="body"
              inputId="assign-user"
              [options]="users()"
              optionLabel="name"
              optionValue="id"
              [(ngModel)]="assignTo"
              name="assigned_to"
              [placeholder]="'common.choose' | translate"
              styleClass="u-full"
            />
          </app-field>
        </app-form-dialog>

        <!-- Reject with a reason (T3-02) -->
        <app-reason-dialog
          #rejectDlg
          [header]="'procurement.maintenance.detail.reject' | translate"
          [label]="'common.reason' | translate"
          [submitLabel]="'common.confirm' | translate"
          [sending]="sending()"
          [error]="rejectError()"
          (submit)="submitReject($event)"
        />

        <!-- Resolve with notes (T3-02: no more hard-coded text) -->
        <app-reason-dialog
          #resolveDlg
          [header]="'procurement.maintenance.detail.resolve' | translate"
          [label]="'procurement.maintenance.detail.resolution_notes' | translate"
          [submitLabel]="'procurement.maintenance.detail.resolve' | translate"
          [sending]="sending()"
          [error]="resolveError()"
          [maxLength]="1000"
          (submit)="submitResolve($event)"
        />

        <app-confirm-delete
          #deleteDlg
          [path]="'/maintenance-requests/' + id() + '/delete'"
          [entity]="itemTitle(item)"
          (deleted)="onDeleted()"
        />
      }
    </app-async-page>
  `,
})
export class MaintenanceDetailPage {
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private auth = inject(AuthService);
  private router = inject(Router);

  id = input.required<number>();
  sending = signal(false);

  readonly rejectDlg = viewChild.required('rejectDlg', { read: ReasonDialogComponent });
  readonly resolveDlg = viewChild.required('resolveDlg', { read: ReasonDialogComponent });

  assignError = signal<HttpErrorResponse | null>(null);
  rejectError = signal<HttpErrorResponse | null>(null);
  resolveError = signal<HttpErrorResponse | null>(null);

  assignOpen = signal(false);
  assignTo: number | null = null;
  users = signal<{ id: number; name: string }[]>([]);

  data = resource<MaintenanceDetail, { id: number }>({
    params: () => ({ id: this.id() }),
    loader: ({ params }) =>
      firstValueFrom(this.api.get<MaintenanceDetail>(`/maintenance-requests/${params.id}`)),
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

  /**
   * Blade allows the assignee, managers and accountants while the request is
   * pending/in-progress. The payload exposes the assignee as a name only, so
   * the status gate is what the UI can enforce — the server still authorizes.
   */
  canResolve(item: MaintenanceDetail): boolean {
    return ['pending', 'in_progress'].includes(this.statusOf(item));
  }

  /** mirrors the sibling purchase-request rule: managers may remove requests */
  canDelete(): boolean {
    return this.auth.can('is_manager');
  }

  statusOf(item: MaintenanceDetail): string {
    return String(item.status ?? '');
  }

  itemTitle(item: MaintenanceDetail): string {
    return `${String(item.title)} #${item.id}`;
  }

  keyValues(item: MaintenanceDetail): { label: string; value: string }[] {
    return [
      { label: 'procurement.maintenance.col_reporter', value: String(item.reported_by ?? '—') },
      { label: 'procurement.maintenance.col_assignee', value: String(item.assigned_to ?? '—') },
      { label: 'procurement.maintenance.form.location', value: String(item.location ?? '—') },
      { label: 'procurement.maintenance.form.priority', value: String(item.priority ?? '—') },
      { label: 'common.description', value: String(item.description ?? '—') },
      { label: 'procurement.maintenance.detail.resolution_notes', value: String(item.resolution_notes ?? '—') },
      { label: 'procurement.maintenance.detail.rejected_by', value: String(item.rejected_by ?? '—') },
      { label: 'transfer.list.col_rejection', value: String(item.rejection_reason ?? '—') },
    ];
  }

  openAssign(): void {
    this.assignError.set(null);
    this.assignTo = null;
    if (this.users().length === 0) {
      void firstValueFrom(this.api.loadLookups()).then((lookups) => {
        this.users.set(lookups.users);
      });
    }
    this.assignOpen.set(true);
  }

  submitAssign(): void {
    if (this.assignTo === null) {
      return;
    }
    this.sending.set(true);
    this.assignError.set(null);
    void firstValueFrom(this.api.action(`/maintenance-requests/${this.id()}/assign`, { assigned_to: this.assignTo }))
      .then((res) => { this.onActionDone(res.message, () => { this.assignOpen.set(false); }, this.assignError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.assignError); });
  }

  submitReject(reason: string): void {
    this.sending.set(true);
    this.rejectError.set(null);
    // the controller currently ignores the reason field; it is sent for parity
    // with the other reject flows and forward-compatibility
    void firstValueFrom(this.api.action(`/maintenance-requests/${this.id()}/reject`, { rejection_reason: reason }))
      .then((res) => { this.onActionDone(res.message, () => { this.rejectDlg().done(); }, this.rejectError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.rejectError); });
  }

  submitResolve(notes: string): void {
    this.sending.set(true);
    this.resolveError.set(null);
    void firstValueFrom(this.api.action(`/maintenance-requests/${this.id()}/resolve`, { resolution_notes: notes }))
      .then((res) => { this.onActionDone(res.message, () => { this.resolveDlg().done(); }, this.resolveError); })
      .catch((err: unknown) => { this.onActionFailed((err as HttpErrorResponse), this.resolveError); });
  }

  onDeleted(): void {
    void this.router.navigateByUrl('/procurement/maintenance');
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
