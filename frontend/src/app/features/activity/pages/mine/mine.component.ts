// activity.mine_title (plan 20) — T4-20: async-page states.
import { ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent } from '@shared/ui/async-page/async-page.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  user: string | null;
  event: string;
  description: string | null;
  created_at: string | null;
}

/** activity.mine_title (plan 20). */
@Component({
  selector: 'app-mine-page',
  standalone: true,
  imports: [
    TranslatePipe,
    PageHeaderComponent, AsyncPageComponent, DataTableComponent, TableCellDirective, 
    EmptyStateComponent, DatetimePipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'activity.mine_title' | translate" />

    <app-async-page [resource]="data" variant="list">
      @if (data.value(); as page) {
        <app-data-table listUrl="/activity-logs"
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appCell="created_at" let-row><span class="u-bidi">{{ row.created_at | datetime }}</span></ng-template>
          <ng-template appCell="event" let-row>{{ row.event ?? '—' }}</ng-template>
          <ng-template appCell="description" let-row>{{ row.description ?? '—' }}</ng-template>
          <div empty>
            <app-empty-state [title]="'activity.empty' | translate" [text]="'common.no_data' | translate" />
          </div>
        </app-data-table>
      }
    </app-async-page>
  `,
})
export class MyActivityPage {
  private http = inject(HttpClient);

  readonly tableKey = 'mine.component';

  readonly columns = [
    { field: 'created_at', header: 'activity.col_time', sortable: true },
    { field: 'event', header: 'activity.col_event', sortable: true },
    { field: 'description', header: 'activity.col_description', sortable: true }
  ];

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/my-activity', { params: cleanParams(this.params()) })),
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

function cleanParams(params: Record<string, string | number>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== '' && value !== 'all') {
      result[key] = String(value);
    }
  }
  return result;
}
