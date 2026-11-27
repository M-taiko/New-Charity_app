// notification.title (plan 20) — T4-20: async-page states.
// R4-06: a grouped TIMELINE, not a table — اليوم / أمس / أقدم, an unread dot
// and a bold title, the full message, and the whole row opening its record.
// N-06: a read shows at once and can be undone from the toast.
import { ChangeDetectionStrategy, Component, computed, effect, inject, resource, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { PaginatorModule } from 'primeng/paginator';
import type { PaginatorState } from 'primeng/paginator';
import { ToastService } from '@core/errors/toast.service';
import { NotificationReadService } from '@core/notifications/notification-read.service';
import { reloadList } from '@shared/lib/list-state';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import type { ListResponse } from '@shared/models/api.models';

interface Row {
  id: number;
  title: string;
  message: string;
  type: string;
  is_read: boolean;
  /** in-app route of the related record ("/custodies/1"), when there is one */
  link: string | null;
  created_at: string | null;
}

type NoticeGroupKey = 'today' | 'yesterday' | 'older';

interface NoticeGroup {
  key: NoticeGroupKey;
  rows: Row[];
}

const GROUP_LABELS: Record<NoticeGroupKey, string> = {
  today: 'notification.group_today',
  yesterday: 'notification.group_yesterday',
  older: 'notification.group_older',
};

const DAY_MS = 86_400_000;

/** local midnight that starts the viewer's current day */
function startOfToday(now: number): number {
  const date = new Date(now);
  date.setHours(0, 0, 0, 0);
  return date.getTime();
}

/**
 * Buckets newest-first rows under اليوم / أمس / أقدم. Rows arrive sorted, so
 * each group is one contiguous run; the run order follows the data.
 */
export function groupNotices(rows: readonly Row[], now = Date.now()): NoticeGroup[] {
  const today = startOfToday(now);
  const yesterday = today - DAY_MS;
  const groups: NoticeGroup[] = [];
  for (const row of rows) {
    const at = row.created_at ? new Date(row.created_at).getTime() : Number.NaN;
    const key: NoticeGroupKey = !Number.isNaN(at) && at >= today ? 'today' : !Number.isNaN(at) && at >= yesterday ? 'yesterday' : 'older';
    const last = groups[groups.length - 1];
    if (last && last.key === key) {
      last.rows.push(row);
    } else {
      groups.push({ key, rows: [row] });
    }
  }
  return groups;
}

/** notification.title (plan 20). */
@Component({
  selector: 'app-list-page',
  standalone: true,
  imports: [
    NgTemplateOutlet, TranslatePipe, RouterLink, PaginatorModule, DatetimePipe,
    PageHeaderComponent, AsyncPageComponent, ActionButtonComponent, EmptyStateComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'notification.title' | translate">
      <div actions>
        <!-- the ONE header action of this page -->
        <app-action-button kind="markRead" label="notifications.mark_all" size="small" [loading]="markingAll()" [disabled]="markingAll()" (action)="markAllRead()" />
      </div>
    </app-page-header>

    <app-async-page [resource]="pageResource" variant="list" [empty]="isEmpty">
      @if (data.value(); as page) {
        <!-- the body of one notification: shared by the link and the button -->
        <ng-template #body let-row>
          <span class="notice__dot" aria-hidden="true"></span>
          <span class="notice__main">
            <span class="notice__title">
              {{ row.title ?? '—' }}
              @if (!isRead(row)) {
                <span class="u-visually-hidden">{{ 'notification.unread' | translate }}</span>
              }
            </span>
            @if (row.message) {
              <span class="notice__text">{{ row.message }}</span>
            }
            <span class="notice__meta">
              <span class="u-bidi">{{ row.created_at | datetime }}</span>
            </span>
          </span>
        </ng-template>

        <section class="notices" [attr.aria-label]="'notification.title' | translate">
          @for (group of groups(); track group.key) {
            <div class="notices__group">
              <h2 class="notices__heading">{{ groupLabel(group.key) | translate }}</h2>
              <ul class="notices__list">
                @for (row of group.rows; track row.id) {
                  <li class="notice" [class.notice--unread]="!isRead(row)">
                    @if (row.link) {
                      <a class="notice__link" [routerLink]="row.link" (click)="markRead(row)">
                        <ng-container [ngTemplateOutlet]="body" [ngTemplateOutletContext]="{ $implicit: row }" />
                      </a>
                    } @else {
                      <button type="button" class="notice__link" (click)="markRead(row)">
                        <ng-container [ngTemplateOutlet]="body" [ngTemplateOutletContext]="{ $implicit: row }" />
                      </button>
                    }
                  </li>
                }
              </ul>
            </div>
          }
        </section>

        @if (page.meta.total > params().per_page) {
          <div class="notices notices__pager-wrap">
            <!-- the same pager as every table: controls, then the report -->
            <p-paginator
              [rows]="params().per_page"
              [totalRecords]="page.meta.total"
              [first]="first()"
              [rowsPerPageOptions]="[10, 25, 50, 100]"
              [dropdownAppendTo]="'body'"
              [showCurrentPageReport]="true"
              [currentPageReportTemplate]="'common.table_report' | translate"
              (onPageChange)="onPager($event)"
            />
          </div>
        }
      }
      <div empty>
        <app-empty-state [title]="'notification.empty' | translate" [text]="'common.no_data' | translate" />
      </div>
    </app-async-page>
  `,
})
export class NotificationsPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);

  markingAll = signal(false);

  readonly tableKey = 'list.component';

  /** the API lists oldest-first; a timeline reads newest-first */
  params = signal({ page: 1, per_page: 25, q: '', sort: '-created_at' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; sort: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/notifications', { params: this.params() })),
  });

  readonly groups = computed(() => groupNotices(this.safeValue()?.data ?? []));

  groupLabel(key: NoticeGroupKey): string {
    return GROUP_LABELS[key];
  }

  /** index of the first row on the current page (the paginator is 0-based) */
  first = computed(() => (this.params().page - 1) * this.params().per_page);

  onPager(state: PaginatorState): void {
    const page = (state.page ?? 0) + 1;
    const perPage = state.rows ?? this.params().per_page;
    const current = this.params();
    if (current.page === page && current.per_page === perPage) {
      return;
    }
    this.params.set({ ...current, page, per_page: perPage });
    this.data.reload();
  }

  /** T4-20: a non-throwing resource view — raw ResourceRef.value() throws on a failed load */
  readonly pageResource: LoadableResource = {
    value: () => this.safeValue(),
    error: () => {
      try {
        return this.data.error();
      } catch {
        return undefined;
      }
    },
    isLoading: () => this.data.isLoading(),
    reload: () => {
      this.data.reload();
    },
  };

  private safeValue(): ListResponse<Row> | undefined {
    try {
      return this.data.value();
    } catch {
      return undefined;
    }
  }

  isEmpty = (): boolean => (this.safeValue()?.data ?? []).length === 0;

  /**
   * N-06: rows shown as read before the server took it. They retire when the
   * reload brings the server's own rows.
   */
  private readIds = signal<ReadonlySet<number>>(new Set());

  private reads = inject(NotificationReadService);

  constructor() {
    effect(() => {
      try {
        this.data.value();
      } catch {
        // an errored resource has no rows to retire
      }
      this.readIds.set(new Set());
    });
  }

  /** the row's read state as shown */
  isRead(row: Row): boolean {
    return this.readIds().has(row.id) || row.is_read;
  }

  /** the row is a link to its record; opening it marks it read — shown at once, undone from the toast (N-06) */
  markRead(row: Row): void {
    if (this.isRead(row)) {
      return;
    }
    this.readIds.update((set) => new Set(set).add(row.id));
    void this.reads.read(row.id, { onUndone: () => { this.readIds.set(new Set()); reloadList(this.data); } }).then((ok) => {
      if (!ok) {
        this.readIds.update((set) => {
          const next = new Set(set);
          next.delete(row.id);
          return next;
        });
        this.toast.error('notification.mark_read_failed');
      }
    });
  }

  /** T3-102 + N-06: mark every notification as read; the toast puts exactly that batch back */
  markAllRead(): void {
    if (this.markingAll()) {
      return;
    }
    this.markingAll.set(true);
    const reload = (): void => { reloadList(this.data); };
    void this.reads.readAll({ onUndone: reload }).then((ok) => {
      this.markingAll.set(false);
      if (!ok) {
        this.toast.error('errors.generic');
      }
      reload();
    });
  }
}
