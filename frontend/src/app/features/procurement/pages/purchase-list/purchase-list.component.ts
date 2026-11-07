// Purchase requests list (plan 20, T3-70 row actions).
// T4-20: async-page states; T4-24: shared row-actions component.
import { ChangeDetectionStrategy, Component, inject, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { AuthService } from '@core/auth/auth.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent } from '@shared/ui/row-actions/row-actions.component';
import type { RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import type { ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  title: string;
  category: string | null;
  priority: string;
  estimated_cost: string;
  status: string;
  requested_by: string | null;
  created_at: string | null;
}

/** procurement.purchase.list_title (plan 20, T3-70 row actions). */
@Component({
  selector: 'app-purchase-list-page',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, StatTileComponent, DataTableComponent, TableCellDirective, 
    EmptyStateComponent, ConfirmDeleteComponent, MoneyPipe, RowActionsComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'procurement.purchase.list_title' | translate" accent="procurement" iconName="shopping-cart">
      <div actions class="u-flex u-gap-2">
        <p-button [label]="'procurement.purchase.create' | translate" size="small" routerLink="/procurement/purchase/new" />
      </div>
    </app-page-header>

    <app-section-nav module="procurement" />

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as page) {
        <div class="kpi-grid">
          <app-stat-tile [label]="'procurement.purchase.pending'" [value]="summaryValue('pending')" accent="procurement" iconName="shopping-cart" />
          <app-stat-tile [label]="'procurement.purchase.approved'" [value]="summaryValue('approved')" />
          <app-stat-tile [label]="'procurement.purchase.purchased'" [value]="summaryValue('purchased')" />
        </div>

        <app-data-table listUrl="/purchase-requests"
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appCell="title" let-row>{{ row.title ?? '—' }}</ng-template>
          <ng-template appCell="category" let-row>{{ row.category ?? '—' }}</ng-template>
          <ng-template appCell="priority" let-row>{{ row.priority ?? '—' }}</ng-template>
          <ng-template appCell="estimated_cost" let-row><span class="u-bidi">{{ row.estimated_cost | money }}</span></ng-template>
          <ng-template appCell="status" let-row>{{ row.status ?? '—' }}</ng-template>
          <ng-template appCell="requested_by" let-row>{{ row.requested_by ?? '—' }}</ng-template>
          <ng-template appCell="actions" let-row><app-row-actions [actions]="actionsFor(row)" /></ng-template>
          <div empty>
            <app-empty-state [title]="'procurement.purchase.empty' | translate" [text]="'common.no_data' | translate" accent="procurement" iconName="shopping-cart" />
          </div>
        </app-data-table>
      }
    </app-async-page>

    <app-confirm-delete #deleteDlg [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />
  `,
})
export class PurchaseListPage {
  private http = inject(HttpClient);
  private auth = inject(AuthService);

  readonly deleteDlg = viewChild.required(ConfirmDeleteComponent);

  readonly tableKey = 'purchase-list.component';

  readonly columns = [
    { field: 'title', header: 'procurement.purchase.col_title', sortable: true },
    { field: 'category', header: 'procurement.purchase.col_category', sortable: true },
    { field: 'priority', header: 'procurement.purchase.col_priority', sortable: true },
    { field: 'estimated_cost', header: 'procurement.purchase.col_cost', sortable: true, numeric: true },
    { field: 'status', header: 'common.status', sortable: true },
    { field: 'requested_by', header: 'procurement.purchase.col_requester', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  deletePath = signal('');
  deleteEntity = signal('');

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/purchase-requests', { params: cleanParams(this.params()) })),
  });

  /**
   * Delete mirrors the detail page rule (requester while pending; managers at
   * any status per PurchaseRequestController::destroy). Rows expose the
   * requester as a name only, so pending-or-manager is the enforceable UI
   * approximation — the server still authorizes.
   */
  canDelete(row: Row): boolean {
    return this.auth.can('is_manager') || row.status === 'pending';
  }

  actionsFor(row: Row): RowActionItem[] {
    return [
      { icon: 'eye', label: 'common.view', route: ['/procurement/purchase', row.id] },
      {
        icon: 'trash',
        label: 'common.delete',
        severity: 'danger',
        visible: this.canDelete(row),
        handler: () => { this.openDelete(row); },
      },
    ];
  }

  openDelete(row: Row): void {
    this.deletePath.set(`/purchase-requests/${row.id}/delete`);
    this.deleteEntity.set(row.title);
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
