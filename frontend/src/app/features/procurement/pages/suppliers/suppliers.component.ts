// Suppliers (plan 20, T3-72: deactivate/delete row action).
// T4-20: async-page states; T4-23: app-field in the create dialog;
// T4-24: shared row-actions component.
import { ChangeDetectionStrategy, Component, inject, resource, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import type { HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { TextareaModule } from 'primeng/textarea';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import type { ListResponse, ActionResponse } from '@shared/models/api.models';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { FieldComponent } from '@shared/ui/field/field.component';

interface Row {
  id: number;
  name: string;
  phone: string | null;
  email: string | null;
  purchase_requests_count: number;
  is_active: boolean;
}

/** procurement.suppliers.title (plan 20, T3-72: deactivate/delete row action). */
@Component({
  selector: 'app-suppliers-page',
  standalone: true,
  imports: [FormsModule, 
    TranslatePipe, ButtonModule, InputTextModule, TextareaModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, ConfirmDeleteComponent, FormDialogComponent, FieldComponent, RowActionsComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'procurement.suppliers.title' | translate">
      <div actions class="u-flex u-gap-2">
        <p-button [label]="'procurement.suppliers.add' | translate" size="small" (onClick)="dialogOpen.set(true)" />
      </div>
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
              <td appTableCol="name">{{ row.name ?? '—' }}</td>
              <td appTableCol="phone">{{ row.phone ?? '—' }}</td>
              <td appTableCol="email">{{ row.email ?? '—' }}</td>
              <td appTableCol="purchase_requests_count">{{ row.purchase_requests_count ?? '—' }}</td>
              <td appTableCol="actions">
                <app-row-actions [actions]="actionsFor(row)" />
              </td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'procurement.suppliers.col_name' | translate }}</span>
                {{ row.name ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'procurement.suppliers.col_phone' | translate }}</span>
                {{ row.phone ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'procurement.suppliers.col_email' | translate }}</span>
                {{ row.email ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'procurement.suppliers.col_requests' | translate }}</span>
                {{ row.purchase_requests_count ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.actions' | translate }}</span>
                <app-row-actions [actions]="actionsFor(row)" />
              </div>
            </tr>
          </ng-template>
          <div empty>
            <app-empty-state [title]="'procurement.suppliers.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>

    <app-confirm-delete #deleteDlg [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />

    <app-form-dialog [header]="'procurement.suppliers.add' | translate" [submitLabel]="'common.save' | translate" [(visible)]="dialogOpen" [sending]="sending()" (submit)="submit()">
      <div class="u-column u-gap-3">
        <app-field label="procurement.suppliers.name" controlId="sup-name" [required]="true">
          <input pInputText id="sup-name" [(ngModel)]="form.name" name="name" class="u-full" />
        </app-field>
        <app-field label="procurement.suppliers.phone" controlId="sup-phone">
          <input pInputText id="sup-phone" [(ngModel)]="form.phone" name="phone" class="u-full" />
        </app-field>
        <app-field label="procurement.suppliers.email" controlId="sup-email">
          <input pInputText id="sup-email" type="email" [(ngModel)]="form.email" name="email" class="u-full" />
        </app-field>
        <app-field label="common.notes" controlId="sup-notes">
          <textarea pTextarea id="sup-notes" rows="2" [(ngModel)]="form.notes" name="notes" class="u-full"></textarea>
        </app-field>
      </div>
    </app-form-dialog>
  `,
})
export class SuppliersPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);

  readonly deleteDlg = viewChild.required(ConfirmDeleteComponent);

  readonly tableKey = 'suppliers.component';

  readonly columns = [
    { field: 'name', header: 'procurement.suppliers.col_name', sortable: true },
    { field: 'phone', header: 'procurement.suppliers.col_phone', sortable: true },
    { field: 'email', header: 'procurement.suppliers.col_email', sortable: true },
    { field: 'purchase_requests_count', header: 'procurement.suppliers.col_requests', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  deletePath = signal('');
  deleteEntity = signal('');

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/suppliers', { params: cleanParams(this.params()) })),
  });

  /**
   * T3-72: the API delete deactivates the supplier (SupplierController::destroy
   * sets is_active = false) — Blade offered it for active suppliers only.
   */
  actionsFor(row: Row): RowActionItem[] {
    return [
      {
        icon: 'trash',
        label: 'procurement.suppliers.deactivate',
        severity: 'danger',
        visible: row.is_active,
        handler: () => { this.openDelete(row); },
      },
    ];
  }

  openDelete(row: Row): void {
    this.deletePath.set(`/suppliers/${row.id}/delete`);
    this.deleteEntity.set(row.name);
    this.deleteDlg().open();
  }

  summaryValue(key: string): string {
    const value = this.data.value()?.meta.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  setParam(key: string, value: string): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }


  dialogOpen = signal(false);
  sending = signal(false);
  form = { name: '', phone: '', email: '', address: '', notes: '' };

  submit(): void {
    if (this.form.name === '') { return; }
    this.sending.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/suppliers', this.form)).then((res) => {
      this.sending.set(false);
      this.toast.success(res.message);
      this.dialogOpen.set(false);
      this.data.reload();
    }).catch((err: unknown) => {
      this.sending.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
    });
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
}

function cleanParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (typeof value === 'string' || typeof value === 'number') {
      if (value !== '' && value !== 'all') {
        result[key] = String(value);
      }
    }
  }
  return result;
}
