// broadcast.title (plan 20, T3-101): create with expiry + targeting, and the
// deactivate/reactivate row actions from the Blade history table.
// T4-20: async-page states; T4-23: app-field in the create dialog;
// T4-24: shared row-actions component.
import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableBodyDirective, TableColDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { ConfirmDialogComponent } from '@shared/ui/confirm-dialog/confirm-dialog.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import type { ActionResponse, ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  title: string;
  message: string;
  level: string;
  is_active: boolean;
  sender: string | null;
  target_type: string;
  target_user_id: number | null;
  expires_at: string | null;
  created_at: string | null;
}

interface SelectOption<T> {
  label: string;
  value: T;
}

/**
 * broadcast.title (plan 20, T3-101): create with expiry + targeting, and the
 * deactivate/reactivate row actions from the Blade history table.
 */
@Component({
  selector: 'app-list-page',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, InputTextModule, SelectModule, TextareaModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, DatetimePipe, StatusStampComponent,
    RowActionsComponent, ConfirmDialogComponent, FormDialogComponent, FieldComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'broadcast.title' | translate">
      <div actions><p-button [label]="'broadcast.create' | translate" size="small" (onClick)="openDialog()" /></div>
    </app-page-header>

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as page) {
        <app-data-table
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appTableBody let-row>
            <tr class="ledger-table__row">
              <td appTableCol="title">
                <strong class="u-small">{{ row.title ?? '—' }}</strong>
                <div class="u-muted u-small">{{ row.message ?? '—' }}</div>
              </td>
              <td appTableCol="level">{{ levelLabel(row.level) }}</td>
              <td appTableCol="sender">{{ row.sender ?? '—' }}</td>
              <td appTableCol="status">
                <app-status-stamp [status]="liveStatus(row)" namespace="broadcast" />
              </td>
              <td appTableCol="expires_at"><span class="u-bidi">{{ row.expires_at | datetime }}</span></td>
              <td appTableCol="created_at"><span class="u-bidi">{{ row.created_at | datetime }}</span></td>
              <td appTableCol="actions">
                <app-row-actions [actions]="actionsFor(row)" />
              </td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.col_title' | translate }}</span>
                {{ row.title ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.col_level' | translate }}</span>
                {{ levelLabel(row.level) }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.col_sender' | translate }}</span>
                {{ row.sender ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.col_status' | translate }}</span>
                <app-status-stamp [status]="liveStatus(row)" namespace="broadcast" />
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.expires' | translate }}</span>
                <span class="u-bidi">{{ row.expires_at | datetime }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'broadcast.col_date' | translate }}</span>
                <span class="u-bidi">{{ row.created_at | datetime }}</span>
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.actions' | translate }}</span>
                <app-row-actions [actions]="actionsFor(row)" />
              </div>
            </tr>
          </ng-template>
          <div empty>
            <app-empty-state [title]="'broadcast.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>

    <app-confirm-dialog
      [header]="confirmHeader()"
      [message]="confirmMessage()"
      [context]="{ name: confirmTarget()?.title ?? '' }"
      [confirmLabel]="confirmLabel()"
      (confirmed)="runToggle()"
    />

    <app-form-dialog
      [header]="'broadcast.create' | translate"
      [submitLabel]="'broadcast.create' | translate"
      [(visible)]="dialogOpen"
      [sending]="sending()"
      (submit)="submit()"
    >
      <div class="u-column u-gap-3">
        <app-field label="broadcast.broadcast_title" controlId="br-title" [required]="true">
          <input pInputText id="br-title" [(ngModel)]="form.title" name="title" class="u-full" />
        </app-field>
        <app-field label="broadcast.message" controlId="br-msg" [required]="true">
          <textarea pTextarea id="br-msg" rows="3" [(ngModel)]="form.message" name="message" class="u-full"></textarea>
        </app-field>
        <app-field label="broadcast.level" controlId="br-level" [required]="true">
          <p-select
            appendTo="body"
            inputId="br-level"
            [options]="levelOptions"
            optionLabel="label"
            optionValue="value"
            [(ngModel)]="form.level"
            name="level"
            styleClass="u-full"
          />
        </app-field>
        <app-field label="broadcast.targeting" controlId="br-target" [required]="true">
          <p-select
            appendTo="body"
            inputId="br-target"
            [options]="targetOptions"
            optionLabel="label"
            optionValue="value"
            [(ngModel)]="form.target_type"
            name="target_type"
            styleClass="u-full"
          />
        </app-field>
        @if (form.target_type === 'user') {
          <app-field label="broadcast.user" controlId="br-user" [required]="true">
            <p-select
              appendTo="body"
              inputId="br-user"
              [options]="userOptions()"
              optionLabel="label"
              optionValue="value"
              [(ngModel)]="form.target_user_id"
              name="target_user_id"
              [placeholder]="'common.choose' | translate"
              styleClass="u-full"
            />
            @if (formError() === 'broadcast.user') {
              <p class="field__error">{{ 'common.required_field' | translate }}</p>
            }
          </app-field>
        }
        <app-field label="broadcast.expires" controlId="br-expires" hint="broadcast.expires_hint">
          <input pInputText id="br-expires" type="datetime-local" [(ngModel)]="form.expires_at" name="expires_at" class="u-full" />
        </app-field>
        @if (formError() === 'required') {
          <p class="field__error">{{ 'common.required_field' | translate }}</p>
        }
      </div>
    </app-form-dialog>
  `,
})
export class BroadcastsPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private api = inject(ApiClient);
  private translate = inject(TranslateService);

  readonly tableKey = 'list.component';

  readonly columns = [
    { field: 'title', header: 'broadcast.col_title', sortable: true },
    { field: 'level', header: 'broadcast.col_level', sortable: true },
    { field: 'sender', header: 'broadcast.col_sender', sortable: true },
    { field: 'status', header: 'broadcast.col_status', sortable: false },
    { field: 'expires_at', header: 'broadcast.expires', sortable: true },
    { field: 'created_at', header: 'broadcast.col_date', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/broadcasts', { params: cleanParams(this.params()) })),
  });

  // ── create dialog (T3-101: expires_at + target_user_id) ──
  dialogOpen = signal(false);
  sending = signal(false);
  /** 'required' (title/message) or 'broadcast.user' (user target without a user) */
  readonly formError = signal<'' | 'required' | 'broadcast.user'>('');
  form = { title: '', message: '', level: 'warning', target_type: 'all', target_user_id: null as number | null, expires_at: '' };

  readonly levelOptions: SelectOption<string>[] = [
    { label: this.translate.instant('broadcast.level_info'), value: 'info' },
    { label: this.translate.instant('broadcast.level_warning'), value: 'warning' },
    { label: this.translate.instant('broadcast.level_danger'), value: 'danger' },
  ];

  readonly targetOptions: SelectOption<string>[] = [
    { label: this.translate.instant('broadcast.all'), value: 'all' },
    { label: this.translate.instant('broadcast.specific'), value: 'user' },
  ];

  /** user picker for the "specific user" target — from the shared lookups */
  readonly userOptions = computed<SelectOption<number>[]>(() =>
    (this.api.lookups()?.users ?? []).map((u) => ({ label: u.name, value: u.id })),
  );

  constructor() {
    this.api.loadLookups().subscribe({ error: () => undefined });
  }

  openDialog(): void {
    this.form = { title: '', message: '', level: 'warning', target_type: 'all', target_user_id: null, expires_at: '' };
    this.formError.set('');
    this.dialogOpen.set(true);
  }

  submit(): void {
    if (this.form.title.trim() === '' || this.form.message.trim() === '') {
      this.formError.set('required');
      return;
    }
    if (this.form.target_type === 'user' && !this.form.target_user_id) {
      this.formError.set('broadcast.user');
      return;
    }
    this.formError.set('');
    this.sending.set(true);
    const payload = {
      title: this.form.title.trim(),
      message: this.form.message.trim(),
      level: this.form.level,
      target_type: this.form.target_type,
      target_user_id: this.form.target_type === 'user' ? this.form.target_user_id : null,
      expires_at: this.form.expires_at || null,
    };
    void firstValueFrom(this.http.post<ActionResponse>('/broadcasts', payload))
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.dialogOpen.set(false);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.toast.apiError(this.errMsg(err));
      });
  }

  // ── deactivate / reactivate row actions (T3-101) ─────────
  private readonly confirmDialog = viewChild(ConfirmDialogComponent);
  private readonly toggleAction = signal<'deactivate' | 'reactivate' | null>(null);
  readonly confirmTarget = signal<Row | null>(null);

  readonly confirmHeader = computed(() =>
    this.toggleAction() === 'deactivate' ? 'broadcast.deactivate' : 'broadcast.reactivate',
  );
  readonly confirmLabel = this.confirmHeader;
  readonly confirmMessage = computed(() =>
    this.toggleAction() === 'deactivate' ? 'broadcast.confirm_deactivate' : 'broadcast.confirm_reactivate',
  );

  /** T3-101: one toggle action per row depending on its live state */
  actionsFor(row: Row): RowActionItem[] {
    return row.is_active
      ? [{ icon: 'x', label: 'broadcast.deactivate', handler: () => { this.askToggle(row, 'deactivate'); } }]
      : [{ icon: 'check', label: 'broadcast.reactivate', handler: () => { this.askToggle(row, 'reactivate'); } }];
  }

  askToggle(row: Row, action: 'deactivate' | 'reactivate'): void {
    this.confirmTarget.set(row);
    this.toggleAction.set(action);
    this.confirmDialog()?.open();
  }

  runToggle(): void {
    const row = this.confirmTarget();
    const action = this.toggleAction();
    if (!row || !action) {
      return;
    }
    void firstValueFrom(this.http.post<ActionResponse>(`/broadcasts/${row.id}/${action}`, {}))
      .then((res) => {
        this.toast.success(res.message ?? (action === 'deactivate' ? 'broadcast.deactivated' : 'broadcast.reactivated'));
        this.confirmTarget.set(null);
        this.toggleAction.set(null);
        this.data.reload();
      })
      .catch((err: unknown) => { this.toast.apiError(this.errMsg(err)); });
  }

  /** Blade rule: active means is_active AND not past expires_at */
  liveStatus(row: Row): string {
    const expired = row.expires_at !== null && new Date(row.expires_at).getTime() <= Date.now();
    return row.is_active && !expired ? 'active' : 'inactive';
  }

  /** level code → Arabic label (same fallback-to-code behavior as enumLabel) */
  levelLabel(level: string | null): string {
    if (!level) {
      return '—';
    }
    const key = `broadcast.level_${level}`;
    const label = this.translate.instant(key);
    return label !== key ? label : level;
  }

  onPage(event: { page: number; per_page: number; sort?: string }): void {
    // p-table re-emits onLazyLoad on every render — only reload on real changes
    const current = this.params();
    const sort = event.sort ?? '';
    if (current.page === event.page && current.per_page === event.per_page && (current as { sort?: string }).sort === sort) {
      return;
    }
    this.params.update((p) => ({ ...p, page: event.page, per_page: event.per_page, sort }));
    this.data.reload();
  }

  private errMsg(err: unknown): string {
    const body = (err as HttpErrorResponse).error as { message?: string; error?: string } | null;
    return body?.message ?? body?.error ?? 'errors.generic';
  }
}

function cleanParams(params: Record<string, string | number | null | undefined>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '' && value !== 'all') {
      result[key] = String(value);
    }
  }
  return result;
}
