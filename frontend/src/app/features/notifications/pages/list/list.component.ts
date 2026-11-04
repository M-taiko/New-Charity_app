// notification.title (plan 20) — T4-20: async-page states.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { ToastService } from '@core/errors/toast.service';
import { PollingService } from '@core/polling/polling.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { TableColDirective, DataTableComponent, TableBodyDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ActionResponse, ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  title: string;
  message: string;
  type: string;
  is_read: boolean;
  link: string | null;
  created_at: string | null;
}

/** notification.title (plan 20). */
@Component({
  selector: 'app-list-page',
  standalone: true,
  imports: [
    TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableBodyDirective, TableColDirective,
    EmptyStateComponent, DatetimePipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'notification.title' | translate">
      <div actions class="u-flex u-gap-2">
        <p-button
          [label]="'notifications.mark_all' | translate"
          size="small"
          [loading]="markingAll()"
          [disabled]="markingAll()"
          (onClick)="markAllRead()"
        />
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
            <tr
              class="ledger-table__row"
              [class.ledger-table__row--link]="row.link"
              (click)="openNotification(row)"
            >
              <td appTableCol="title">
                @if (row.link) {
                  <a [routerLink]="row.link" (click)="$event.stopPropagation()">{{ row.title ?? '—' }}</a>
                } @else {
                  {{ row.title ?? '—' }}
                }
              </td>
              <td appTableCol="message">{{ row.message ?? '—' }}</td>
              <td appTableCol="created_at"><span class="u-bidi">{{ row.created_at | datetime }}</span></td>
            </tr>
            <tr class="ledger-table__stacked">
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.name' | translate }}</span>
                @if (row.link) {
                  <a [routerLink]="row.link">{{ row.title ?? '—' }}</a>
                } @else {
                  {{ row.title ?? '—' }}
                }
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.description' | translate }}</span>
                {{ row.message ?? '—' }}
              </div>
              <div class="ledger-table__stacked-item">
                <span class="ledger-table__stacked-label">{{ 'common.date' | translate }}</span>
                <span class="u-bidi">{{ row.created_at | datetime }}</span>
              </div>
            </tr>
          </ng-template>
          <div empty>
            <app-empty-state [title]="'notification.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>
  `,
})
export class NotificationsPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);
  private router = inject(Router);
  private polling = inject(PollingService);

  markingAll = signal(false);

  readonly tableKey = 'list.component';

  readonly columns = [
    { field: 'title', header: 'common.name', sortable: true },
    { field: 'message', header: 'common.description', sortable: true },
    { field: 'created_at', header: 'common.date', sortable: true }
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/notifications', { params: cleanParams(this.params()) })),
  });

  summaryValue(key: string): string {
    const value = this.data.value()?.meta.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  setParam(key: string, value: string): void {
    this.params.update((p) => ({ ...p, [key]: value, page: 1 }));
    this.data.reload();
  }

  onSearch(term: string): void { this.setParam('q', term); }

  /** clicking a notification opens its related page and marks it read */
  openNotification(row: Row): void {
    this.markRead(row);
    if (row.link) {
      void this.router.navigateByUrl(row.link);
    }
  }

  markRead(row: Row): void {
    if (row.is_read) return;
    row.is_read = true; // optimistic
    this.http.post(`/notifications/${row.id}/read`, {}).subscribe({
      error: () => { this.toast.error('notification.mark_read_failed'); },
    });
  }

  /** T3-102: mark every notification as read from the page, not just the bell */
  markAllRead(): void {
    this.markingAll.set(true);
    void firstValueFrom(this.http.post<ActionResponse>('/notifications/mark-all-read', {}))
      .then((res) => {
        this.markingAll.set(false);
        this.toast.success(res.message);
        this.data.reload();
        this.polling.refreshNow();
      })
      .catch((err: unknown) => {
        this.markingAll.set(false);
        this.toast.apiError(this.errMsg(err));
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
