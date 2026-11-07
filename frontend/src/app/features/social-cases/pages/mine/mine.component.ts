import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { SectionNavComponent } from '@shared/ui/section-nav/section-nav.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import type { TableColumn } from '@shared/ui/data-table/data-table.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import { apiFilterDefs, filterParams, type TableQuery } from '@shared/lib/table-filters';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent, type RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { DatetimePipe } from '@shared/pipes/datetime.pipe';
import { warnMissingTranslation } from '@core/i18n/missing-key';
import type { ItemResponse, ListResponse } from '@shared/models/api.models';

/** my-cases rows are the raw SocialCase model (no researcher relation) */
interface Row {
  id: number;
  name: string;
  phone: string | null;
  assistance_type: string | null;
  status: string;
  is_active: boolean | null;
  created_at: string | null;
}

interface FamilyMember {
  id: number;
  name: string;
  relationship: string | null;
  gender: string | null;
  phone: string | null;
  status: string | null;
}

interface QuickDetail {
  id: number;
  name: string;
  phone: string | null;
  researcher: string | null;
  assistance_type: string | null;
  status: string;
  governorate: string | null;
  district: string | null;
  monthly_income: string;
  monthly_expenses: string;
  family_members_count: number;
  total_spent: string;
}

/**
 * My cases — the researcher's own list (T3-52): the researcher-cases Blade
 * page parity — family-members modal, quick-details modal and the shared row
 * actions; edit/toggle/delete remain hidden without manage_social_cases
 * because the API enforces that permission on update/toggle/delete.
 */
@Component({
  selector: 'app-my-cases',
  standalone: true,
  imports: [SectionNavComponent, 
    TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent, StatTileComponent,
    DataTableComponent, TableCellDirective, 
    DataTableComponent, TableCellDirective, 
    EmptyStateComponent, FormDialogComponent, ConfirmDeleteComponent,
    RowActionsComponent, KeyValueListComponent, StatusStampComponent, DatetimePipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'case.mine.title' | translate" accent="cases" iconName="heart-handshake">
      <div actions class="u-flex u-gap-2">
        <p-button [label]="'case.mine.create' | translate" size="small" routerLink="/cases/new" />
      </div>
    </app-page-header>

    <app-section-nav module="cases" />

    <app-async-page [resource]="pageResource" variant="list" [empty]="isEmpty">
      @if (data.value(); as page) {
        <div class="kpi-grid">
          <app-stat-tile [label]="'case.list.total'" [value]="summaryValue('total')" accent="cases" iconName="heart-handshake" />
          <app-stat-tile [label]="'case.list.pending'" [value]="summaryValue('pending')" />
          <app-stat-tile [label]="'case.list.approved'" [value]="summaryValue('approved')" />
          <app-stat-tile [label]="'case.list.rejected'" [value]="summaryValue('rejected')" />
        </div>

        <app-data-table listUrl="/my-cases"
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          [filters]="metaFilters()"
          (queryChange)="onQuery($event)"

          (pageChange)="onPage($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <ng-template appCell="name" let-row><a [routerLink]="['/cases', row.id]">{{ row.name ?? '—' }}</a></ng-template>
          <ng-template appCell="phone" let-row><span class="u-bidi">{{ row.phone ?? '—' }}</span></ng-template>
          <ng-template appCell="assistance_type" let-row>{{ label('case.enums.assistance.' + row.assistance_type) }}</ng-template>
          <ng-template appCell="status" let-row><app-status-stamp [status]="row.status" namespace="social_case" /></ng-template>
          <ng-template appCell="created_at" let-row><span class="u-bidi">{{ row.created_at | datetime }}</span></ng-template>
          <ng-template appCell="actions" let-row><app-row-actions [actions]="actionsFor(row)" /></ng-template>
        </app-data-table>
      }
      <div empty>
        <app-empty-state [title]="'case.mine.empty' | translate" [text]="'common.no_data' | translate" accent="cases" iconName="heart-handshake" />
      </div>
    </app-async-page>

    <app-form-dialog [header]="familyHeader()" [submitLabel]="'case.modal.close'" [(visible)]="familyOpen" [sending]="familyLoading()">
      <app-data-table mode="static" [autoFilters]="true" [columns]="familyCols" [rows]="familyMembers()" [emptyLabel]="'case.modal.empty_family'">
        <ng-template appCell="index" let-row let-index="index"><span class="u-bidi">{{ index + 1 }}</span></ng-template>
        <ng-template appCell="name" let-row let-index="index">{{ row.name }}</ng-template>
        <ng-template appCell="relation" let-row let-index="index">{{ label('case.enums.relation.' + row.relationship) }}</ng-template>
        <ng-template appCell="gender" let-row let-index="index">{{ label('case.enums.gender.' + row.gender) }}</ng-template>
        <ng-template appCell="status" let-row let-index="index">{{ label('case.enums.member_status.' + row.status) }}</ng-template>
        <ng-template appCell="phone" let-row let-index="index"><span class="u-bidi">{{ row.phone ?? '—' }}</span></ng-template>
      </app-data-table>
    </app-form-dialog>

    <app-form-dialog [header]="'case.detail.title' | translate" [submitLabel]="'common.open_page'" [(visible)]="detailsOpen" [sending]="detailsLoading()" (submit)="openDetailPage()">
      @if (quickDetail(); as q) {
        <div class="u-mb-4"><app-status-stamp [status]="q.status" namespace="social_case" /></div>
        <app-key-value-list [items]="quickValues(q)" />
      }
    </app-form-dialog>

    <app-confirm-delete [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />
  `,
})
export class MyCasesPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private auth = inject(AuthService);
  private router = inject(Router);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  readonly tableKey = 'mine.component';

  readonly columns = [
    { field: 'name', header: 'case.mine.col_name', sortable: true },
    { field: 'phone', header: 'case.mine.col_phone', sortable: true },
    { field: 'assistance_type', header: 'case.mine.col_assistance', sortable: true },
    { field: 'status', header: 'case.mine.col_status', sortable: true },
    { field: 'created_at', header: 'case.mine.col_date', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false },
  ];

  /** the family-members modal table (T4-21) */
  readonly familyCols: TableColumn[] = [
    { field: 'index', header: 'case.detail.col_index' },
    { field: 'name', header: 'case.form.member_name' },
    { field: 'relation', header: 'case.form.relation' },
    { field: 'gender', header: 'case.form.member_gender' },
    { field: 'status', header: 'case.form.member_status' },
    { field: 'phone', header: 'case.form.member_phone' },
  ];

  params = signal<{ page: number; per_page: number; q: string; sort?: string; status: string }>({ page: 1, per_page: 25, q: '', sort: '', status: 'all' });

  /** the active filter[...] values — forwarded verbatim (T7-07 server contract) */
  filterBag: Record<string, unknown> = {};

  /** T7-07: the endpoint's declared allowlist drives the table's Filters control */
  readonly metaFilters = computed(() => apiFilterDefs(this.data.value()?.meta));

  /** the table emits the full query — filters reach the server (T7-07) */
  onQuery(query: TableQuery): void {
    this.filterBag = query.filters;
    this.params.update((p) => ({
      ...p,
      page: query.page,
      per_page: query.perPage,
      sort: query.sort !== '' ? query.sort : p.sort,
      q: query.q,
    }));
    this.data.reload();
  }

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/my-cases', { params: { ...cleanParams(this.params()), ...filterParams(this.filterBag) } })),
  });

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

  familyOpen = signal(false);
  familyLoading = signal(false);
  familyMembers = signal<FamilyMember[]>([]);
  familyHeader = signal('case.modal.family');

  detailsOpen = signal(false);
  detailsLoading = signal(false);
  quickDetail = signal<QuickDetail | null>(null);

  acting = signal(false);
  deletePath = signal('');
  deleteEntity = signal('');
  private confirmDelete = viewChild(ConfirmDeleteComponent);

  readonly canManage = computed(() => this.auth.can('may_manage_social_cases'));

  /** T4-24: family / details / edit / toggle-active / delete (Blade actions) */
  actionsFor(row: Row): RowActionItem[] {
    return [
      { icon: 'users-group', label: 'case.actions.family', handler: () => { this.openFamily(row); } },
      { icon: 'eye', label: 'case.actions.details', handler: () => { this.openDetails(row); } },
      { icon: 'pencil', label: 'common.edit', route: ['/cases', row.id, 'edit'], visible: this.canManage() },
      {
        icon: row.is_active ? 'check' : 'x',
        label: row.is_active ? 'case.actions.deactivate' : 'case.actions.activate',
        handler: () => { this.toggleActive(row); },
        disabled: this.acting(),
        visible: this.canManage(),
      },
      { icon: 'trash', label: 'common.delete', severity: 'danger', handler: () => { this.askDelete(row); }, visible: this.canManage() },
    ];
  }

  summaryValue(key: string): string {
    const value = this.safeValue()?.meta?.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  onPage(event: { page: number; per_page: number; sort?: string }): void {
    const current = this.params();
    if (current.page === event.page && current.per_page === event.per_page && (current as { sort?: string }).sort === (event.sort ?? '')) {
      return;
    }
    this.params.update((p) => ({ ...p, page: event.page, per_page: event.per_page, sort: event.sort ?? '' }));
    this.data.reload();
  }

  /** localized enum label with the raw-code fallback (never blank) */
  label(key: string): string {
    const value = key.slice(key.lastIndexOf('.') + 1);
    if (!value || value === 'null') {
      return '—';
    }
    const translated = this.translate.instant(key);
    if (translated === key) {
      warnMissingTranslation(key); // dev only — a raw code in the UI means a key was lost (T5-01)
    }
    return translated !== key ? translated : value;
  }

  openFamily(row: Row): void {
    this.familyMembers.set([]);
    this.familyHeader.set(this.translate.instant('case.modal.family_of', { name: row.name }));
    this.familyOpen.set(true);
    this.familyLoading.set(true);
    void firstValueFrom(this.http.get<{ family_members: FamilyMember[] | null }>(`/social-cases/${row.id}/family-members`))
      .then((res) => {
        this.familyLoading.set(false);
        this.familyMembers.set(res.family_members ?? []);
      })
      .catch((err: unknown) => {
        this.familyLoading.set(false);
        this.familyOpen.set(false);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }

  openDetails(row: Row): void {
    this.quickDetail.set(null);
    this.detailsOpen.set(true);
    this.detailsLoading.set(true);
    void firstValueFrom(this.http.get<ItemResponse<QuickDetail>>(`/social-cases/${row.id}`))
      .then((res) => {
        this.detailsLoading.set(false);
        this.quickDetail.set(res.data);
      })
      .catch((err: unknown) => {
        this.detailsLoading.set(false);
        this.detailsOpen.set(false);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }

  openDetailPage(): void {
    const q = this.quickDetail();
    if (q) {
      void this.router.navigate(['/cases', q.id]);
    }
  }

  quickValues(q: QuickDetail): { label: string; value: string }[] {
    return [
      { label: 'case.detail.name', value: q.name },
      { label: 'case.detail.phone', value: q.phone ?? '—' },
      { label: 'case.detail.researcher', value: q.researcher ?? '—' },
      { label: 'case.detail.assistance', value: this.label('case.enums.assistance.' + q.assistance_type) },
      { label: 'case.detail.governorate', value: q.governorate ?? '—' },
      { label: 'case.detail.district', value: q.district ?? '—' },
      { label: 'case.form.monthly_income', value: q.monthly_income },
      { label: 'case.form.monthly_expenses', value: q.monthly_expenses },
      { label: 'case.form.family_members_count', value: String(q.family_members_count) },
      { label: 'case.detail.total_spent', value: q.total_spent },
    ];
  }

  toggleActive(row: Row): void {
    this.acting.set(true);
    void firstValueFrom(this.api.action(`/social-cases/${row.id}/toggle-active`)).then((res) => {
      this.acting.set(false);
      this.toast.success(res.message);
      this.data.reload();
    }).catch((err: unknown) => {
      this.acting.set(false);
      this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
    });
  }

  askDelete(row: Row): void {
    this.deletePath.set(`/social-cases/${row.id}/delete`);
    this.deleteEntity.set(row.name);
    this.confirmDelete()?.open();
  }
}

function cleanParams(params: Record<string, unknown>): Record<string, string> {
  const result: Record<string, string> = {};
  for (const [key, value] of Object.entries(params)) {
    if (value !== null && value !== undefined && value !== '' && value !== 'all') {
      result[key] = String(value);
    }
  }
  return result;
}
