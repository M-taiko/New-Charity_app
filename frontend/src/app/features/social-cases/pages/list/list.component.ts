import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { ToastService } from '@core/errors/toast.service';
import { AuthService } from '@core/auth/auth.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import type { TableColumn } from '@shared/ui/data-table/data-table.component';
import { DataTableComponent, TableCellDirective } from '@shared/ui/data-table/data-table.component';
import type { BulkAction } from '@shared/ui/data-table/data-table.component';
import { ConfirmDialogComponent } from '@shared/ui/confirm-dialog/confirm-dialog.component';
import type { KeyValueItem } from '@shared/ui/key-value-list/key-value-list.component';
import { bulkFailure, bulkToastText, isBulkAnswer, type BulkAnswer } from '@shared/lib/bulk-result';
import type { ActionResponse } from '@shared/models/api.models';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import { RowActionsComponent, type RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { KeyValueListComponent } from '@shared/ui/key-value-list/key-value-list.component';
import { StatusStampComponent } from '@shared/ui/status-stamp/status-stamp.component';
import { QuickViewComponent } from '@shared/ui/quick-view/quick-view.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import { MoneyPipe } from '@shared/pipes/money.pipe';
import { warnMissingTranslation } from '@core/i18n/missing-key';
import type { ItemResponse, ListResponse } from '@shared/models/api.models';
import { DatetimeCellComponent } from '@shared/ui/datetime-cell/datetime-cell.component';
import type { TableQuery } from '@shared/lib/table-filters';
import { reloadList } from '@shared/lib/list-state';

/** list rows are the raw SocialCase model + researcher relation */
interface Row {
  id: number;
  name: string;
  phone: string | null;
  city: string | null;
  researcher: { id: number; name: string } | null;
  assistance_type: string | null;
  /** the amount the case asks for — the only amount a case holds (there is no approved one) */
  requested_amount: string | null;
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
 * Social cases list (T3-52): Blade modern parity — per-row family-members
 * modal and quick-details modal, row actions view / edit / toggle-active /
 * delete under the same @can('manage_social_cases') condition as Blade.
 */
@Component({
  selector: 'app-case-list',
  standalone: true,
  imports: [DatetimeCellComponent, TranslatePipe, RouterLink, ButtonModule,
    PageHeaderComponent, AsyncPageComponent,
    DataTableComponent, TableCellDirective,
    EmptyStateComponent, FormDialogComponent, ConfirmDeleteComponent,
    RowActionsComponent, KeyValueListComponent, StatusStampComponent, MoneyPipe,
    QuickViewComponent, ActionButtonComponent, ConfirmDialogComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'case.list.title' | translate" accent="cases" iconName="heart-handshake">
      <div actions class="u-flex u-gap-2">
        <!-- N-08: the same rule as the empty state's call to action -->
        @if (canCreate()) {
          <p-button [label]="'case.list.create' | translate" size="small" routerLink="/social-cases/new" />
        }
      </div>
    </app-page-header>

    <!-- N-08: "no data at all" is this page's empty slot; "no results for this
         filter or search" keeps the table, with its way back -->
    <app-async-page [resource]="pageResource" variant="list" [empty]="isFirstRun">
      @if (data.value(); as page) {
        <!-- P-07: the count chips carry the numbers AND filter — the four KPI
             cards showed the same counts a second time, so they are gone -->
        <div class="filter-chips" role="group" [attr.aria-label]="'common.filter' | translate">
          <span class="filter-chips__title">{{ 'case.list.registered' | translate }}</span>
          @for (chip of statusChips; track chip.value) {
            <button
              type="button"
              class="filter-chips__chip"
              [class.filter-chips__chip--active]="params().status === chip.value"
              [attr.aria-pressed]="params().status === chip.value"
              (click)="setStatusFilter(chip.value)"
            >
              {{ chip.label | translate }} <span class="u-bidi">({{ summaryValue(chip.countKey) }})</span>
            </button>
          }
        </div>

        <app-data-table #table [phoneFirst]="['name', 'status', 'requested_amount']"
        [exportQuery]="tableExportQuery()" listUrl="/social-cases"
          [savedViews]="true"
          [rowActivatable]="true"
          (rowActivate)="openQuickView($event.row, $event.element)"
          [selectable]="true"
          [bulkActions]="bulkActions()"
          (bulk)="askBulk($event.action, $event.rows)"
          [tableKey]="tableKey"
          [columns]="columns"
          [rows]="page.data"
          [total]="page.meta.total"
          [perPage]="params().per_page"
          [loading]="data.isLoading()"
          [filtered]="pageFiltered"
          (clearFilters)="clearPageFilters()"
          (pageChange)="onPage($event)"
          (queryChange)="onQuery($event)"
        >
          <div toolbar class="u-flex u-gap-2 u-flex-wrap"></div>
          <!-- R2-06: the phone card — beneficiary, status, approved amount,
               code + actions (the first table column is a link template, so
               the default card title came out empty) -->
          <ng-template appCell="code" let-row><a class="case-code u-bidi" [routerLink]="['/social-cases', row.id]">#{{ row.id }}</a></ng-template>
          <ng-template appCell="name" let-row>
            <a class="u-flex u-center-y u-gap-2 beneficiary" [routerLink]="['/social-cases', row.id]">
              <span class="beneficiary__avatar" aria-hidden="true">{{ initials(row.name) }}</span>
              <span>{{ row.name ?? '—' }}</span>
            </a>
          </ng-template>
          <ng-template appCell="assistance_type" let-row>{{ assistanceLabel(row.assistance_type) }}</ng-template>
          <ng-template appCell="requested_amount" let-row><span class="u-bidi">{{ row.requested_amount | money: false }}</span></ng-template>
          <ng-template appCell="status" let-row><app-status-stamp [status]="row.status" namespace="social_case" /></ng-template>
          <ng-template appCell="created_at" let-row><app-datetime-cell [value]="row.created_at" /></ng-template>
          <ng-template appCell="actions" let-row><app-row-actions [actions]="actionsFor(row)" /></ng-template>
        </app-data-table>
      }
      <div empty>
        <!-- N-08: no case at all yet — the first one is one click away for a
             role that may add one -->
        <app-empty-state
          [title]="'case.list.empty_first' | translate"
          [hint]="'case.list.empty_first_hint' | translate"
          accent="cases"
          iconName="heart-handshake"
          [actionLabel]="canCreate() ? 'case.list.create_first' : ''"
          [actionRoute]="canCreate() ? firstCaseRoute : null"
        />
      </div>
    </app-async-page>

    <app-form-dialog [header]="familyHeader()" [submitLabel]="'case.modal.close'" [(visible)]="familyOpen" [sending]="familyLoading()" (submit)="familyOpen.set(false)">
      <app-data-table mode="static" [autoFilters]="true" [phoneFirst]="['index', 'name', 'status']" [columns]="familyCols" [rows]="familyMembers()" [emptyLabel]="'case.modal.empty_family'">
        <ng-template appCell="index" let-row let-index="index"><span class="u-bidi">{{ index + 1 }}</span></ng-template>
        <ng-template appCell="name" let-row let-index="index">{{ row.name }}</ng-template>
        <ng-template appCell="relation" let-row let-index="index">{{ label('case.enums.relation.' + row.relationship) }}</ng-template>
        <ng-template appCell="gender" let-row let-index="index">{{ label('case.enums.gender.' + row.gender) }}</ng-template>
        <ng-template appCell="status" let-row let-index="index">{{ label('case.enums.member_status.' + row.status) }}</ng-template>
        <ng-template appCell="phone" let-row let-index="index"><span class="u-bidi">{{ row.phone ?? '—' }}</span></ng-template>
      </app-data-table>
    </app-form-dialog>

    <!-- N-03: the quick detail is the shared drawer — the list, its filters and the address stay -->
    <app-quick-view
      [title]="'case.detail.title' | translate"
      [subtitle]="quickSubtitle()"
      [status]="quickDetail()?.status ?? null"
      statusNamespace="social_case"
      [loading]="detailsLoading()"
      [fullRoute]="quickRoute()"
      [returnFocusTo]="quickReturnFocus()"
    >
      @if (quickDetail(); as q) {
        <app-key-value-list [items]="quickValues(q)" />
      }
      <div quick-actions>
        <!-- the row's own decisions, under the same abilities -->
        @if (quickRow(); as row) {
          @if (canManage()) {
            <app-action-button kind="edit" label="common.edit" size="small" [route]="['/social-cases', row.id, 'edit']" />
          }
          <app-action-button kind="family" label="case.actions.family" size="small" (action)="openFamilyFromQuick()" />
        }
      </div>
    </app-quick-view>

    <!-- N-04: one confirm for a bulk activate / deactivate — the count of what was selected -->
    <app-confirm-dialog
      #bulkDialog
      [header]="bulkHeader()"
      [message]="bulkMessage()"
      [context]="bulkContext()"
      [confirmLabel]="bulkHeader()"
      confirmKind="submit"
      [details]="bulkDetails()"
      (confirmed)="runBulk()"
    />

    <app-confirm-delete [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />
  `,
})
export class CaseListPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private auth = inject(AuthService);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);

  readonly tableKey = 'list.component';

  /** P-07 Stitch _3 columns: code · beneficiary (avatar) · category · approved
   *  amount · status · actions — phone/governorate/researcher moved to the
   *  quick-view drawer (openDetails) */
  readonly columns: TableColumn[] = [
    { field: 'code', header: 'case.list.col_code', sortable: false },
    { field: 'name', header: 'case.list.col_name', sortable: true },
    { field: 'assistance_type', header: 'case.list.col_category', sortable: true },
    // N-02: the field the API really sends (`requested_amount`; the column used to read one that
    // does not exist and was always blank) — the server still sorts it under the key "amount"
    { field: 'requested_amount', header: 'case.list.col_amount', numeric: true, sortable: true, sortKey: 'amount', minWidth: 110 },
    { field: 'status', header: 'case.list.col_status', sortable: true },
    { field: 'created_at', header: 'common.date', sortable: true },
    { field: 'actions', header: 'common.actions', sortable: false, pinned: 'end' },
  ];

  /** RD-4.3: chip filters over the status values the API filters on */
  readonly statusChips = [
    { value: 'all', label: 'case.list.tab_all', countKey: 'total' },
    { value: 'pending', label: 'case.list.pending', countKey: 'pending' },
    { value: 'approved', label: 'case.list.approved', countKey: 'approved' },
    { value: 'rejected', label: 'case.list.rejected', countKey: 'rejected' },
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


  /** the export replays EXACTLY the params this list was fetched with
   *  (minus paging) — the file always matches the table on screen */
  tableExportQuery(): Record<string, string> {
    const query = cleanParams(this.params());
    delete query.page;
    delete query.per_page;
    return query;
  }

  params = signal({ page: 1, per_page: 25, q: '', status: 'all' });

  data = resource<ListResponse<Row>, { page: number; per_page: number; q: string; status: string }>({
    loader: () =>
      firstValueFrom(this.http.get<ListResponse<Row>>('/social-cases', { params: cleanParams(this.params()) })),
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
      reloadList(this.data);
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

  /** N-08: where the empty state's call to action goes */
  readonly firstCaseRoute: readonly unknown[] = ['/social-cases', 'new'];

  /**
   * N-08: may this user add a case? The page offers it to the roles that
   * manage cases (`may_manage_social_cases`: accountant, manager, social
   * researcher). NOTE the server's store() checks nothing — see the changelog.
   */
  readonly canCreate = computed(() => this.auth.can('may_manage_social_cases'));

  /** N-08: the status chips filter outside the table — the table must know, to say "no results" and not "no data" */
  pageFiltered = (): boolean => this.params().status !== 'all';

  /** N-08: the table's "clear the filters" clears the chips too */
  clearPageFilters(): void {
    if (this.params().status === 'all') {
      return;
    }
    this.params.update((p) => ({ ...p, status: 'all', page: 1 }));
    reloadList(this.data);
  }

  /**
   * N-08: the table's search box reaches the server. It used to be typed into
   * nothing: the table emitted its query and nobody listened. Paging is
   * `onPage`'s; only the text is handled here.
   */
  onQuery(query: TableQuery): void {
    if (this.params().q === query.q) {
      return;
    }
    this.params.update((p) => ({ ...p, q: query.q, page: 1 }));
    reloadList(this.data);
  }

  /**
   * N-08: the page's empty slot is for a list that never had a record —
   * empty, unfiltered AND not reloading (while a reload runs after "clear the
   * filters" the old empty result is still held; that instant is not "no data")
   */
  isFirstRun = (): boolean => this.isEmpty() && this.params().status === 'all' && this.params().q === '' && !this.data.isLoading();

  familyOpen = signal(false);
  familyLoading = signal(false);
  familyMembers = signal<FamilyMember[]>([]);
  familyHeader = signal('case.modal.family');

  detailsLoading = signal(false);
  quickDetail = signal<QuickDetail | null>(null);

  private readonly quickView = viewChild.required(QuickViewComponent);
  /** the list row the quick view was opened from — its id decides the drawer's actions */
  protected readonly quickRow = signal<Row | null>(null);
  readonly quickReturnFocus = signal<HTMLElement | null>(null);

  readonly quickRoute = computed<readonly unknown[]>(() => {
    const row = this.quickRow();
    return row === null ? [] : ['/social-cases', row.id];
  });

  /** who it is, and the case's number — under the drawer's title */
  readonly quickSubtitle = computed(() => {
    const row = this.quickRow();
    return row === null ? '' : `#${row.id} · ${row.name ?? '—'}`;
  });

  acting = signal(false);
  deletePath = signal('');
  deleteEntity = signal('');
  private confirmDelete = viewChild(ConfirmDeleteComponent);

  readonly canManage = computed(() => this.auth.can('may_manage_social_cases'));

  /** T4-24 + P-07: view · edit inline; family / toggle-active / delete in "⋯" */
  actionsFor(row: Row): RowActionItem[] {
    return [
      { icon: 'eye', label: 'case.actions.details', handler: () => { this.openDetails(row); } },
      { icon: 'pencil', label: 'common.edit', route: ['/social-cases', row.id, 'edit'], visible: this.canManage() },
      { icon: 'users-group', label: 'case.actions.family', handler: () => { this.openFamily(row); } },
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

  /** P-07: avatar initials for Arabic names — first letter of the first two words */
  initials(name: string | null): string {
    return (name ?? '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((w) => w.charAt(0))
      .join('');
  }

  summaryValue(key: string): string {
    const value = this.safeValue()?.meta?.summary?.[key];
    return typeof value === 'number' || typeof value === 'string' ? String(value) : '0';
  }

  /** RD-4.3: chip-driven status filter — the API's `status` param, 'all' cleared */
  setStatusFilter(value: string): void {
    if (this.params().status === value) {
      return;
    }
    this.params.update((p) => ({ ...p, status: value, page: 1 }));
    reloadList(this.data);
  }

  /** localized assistance-type label with the raw-code fallback */
  assistanceLabel(value: string | null): string {
    if (!value) {
      return '—';
    }
    return this.label(`case.enums.assistance.${value}`);
  }

  onPage(event: { page: number; per_page: number; sort?: string }): void {
    const current = this.params();
    if (current.page === event.page && current.per_page === event.per_page && (current as { sort?: string }).sort === (event.sort ?? '')) {
      return;
    }
    this.params.update((p) => ({ ...p, page: event.page, per_page: event.per_page, sort: event.sort ?? '' }));
    reloadList(this.data);
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

  /** Blade loadFamilyMembers: GET social-cases/{id}/family-members into a modal */
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

  /** Blade viewCaseDetails: the case's summary in the shared quick-view drawer (N-03), built from the show endpoint */
  openDetails(row: Row, element: HTMLElement | null = null): void {
    // a double click is one drawer
    if (this.detailsLoading() || this.quickView().visible()) {
      return;
    }
    this.quickRow.set(row);
    this.quickReturnFocus.set(element);
    this.quickDetail.set(null);
    this.detailsLoading.set(true);
    this.quickView().open();
    void firstValueFrom(this.http.get<ItemResponse<QuickDetail>>(`/social-cases/${row.id}`))
      .then((res) => {
        this.detailsLoading.set(false);
        this.quickDetail.set(res.data);
      })
      .catch((err: unknown) => {
        this.detailsLoading.set(false);
        this.quickView().close();
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }

  /** N-03: a row was activated (a click on it, or Enter / Space) */
  openQuickView(row: unknown, element: HTMLElement | null): void {
    this.openDetails(row as Row, element);
  }

  /** the drawer's family action is the list's own; its dialog takes the focus, the drawer steps aside */
  openFamilyFromQuick(): void {
    const row = this.quickRow();
    if (row === null) {
      return;
    }
    this.quickView().close(false);
    this.openFamily(row);
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

  // ── N-04: bulk activate / deactivate ─────────────────────────────────────

  private readonly bulkDialog = viewChild.required<ConfirmDialogComponent>('bulkDialog');
  private readonly tableRef = viewChild.required(DataTableComponent);
  private bulkSending = false;

  /**
   * What the bulk bar offers: only what this role may do one by one —
   * activating and deactivating are for whoever manages cases.
   */
  readonly bulkActions = computed<readonly BulkAction[]>(() => [
    { value: 'activate', kind: 'activate', label: 'case.bulk.activate_selected', visible: this.canManage() },
    { value: 'deactivate', kind: 'deactivate', label: 'case.bulk.deactivate_selected', visible: this.canManage() },
  ]);

  /** what was asked for, waiting for the confirm */
  private readonly bulkTarget = signal<{ active: boolean; rows: readonly Row[] } | null>(null);

  readonly bulkHeader = computed(() => (this.bulkTarget()?.active ?? true) ? 'case.bulk.activate_selected' : 'case.bulk.deactivate_selected');

  readonly bulkMessage = computed(() => (this.bulkTarget()?.active ?? true) ? 'case.bulk.activate_confirm' : 'case.bulk.deactivate_confirm');

  readonly bulkContext = computed<Record<string, string>>(() => ({ count: String(this.bulkTarget()?.rows.length ?? 0) }));

  readonly bulkDetails = computed<readonly KeyValueItem[]>(() => (this.bulkTarget() === null ? [] : [{ label: 'case.bulk.count', value: this.bulkContext().count }]));

  /** N-04: the table's bulk bar was used — the confirm comes first, nothing is sent yet */
  askBulk(action: string, rows: readonly unknown[]): void {
    if (rows.length === 0 || this.bulkSending) {
      return;
    }
    this.bulkTarget.set({ active: action !== 'deactivate', rows: rows as Row[] });
    this.bulkDialog().open();
  }

  /** N-04: confirmed — one request, every id in it; the answer lists what was refused */
  runBulk(): void {
    const target = this.bulkTarget();
    if (target === null || this.bulkSending) {
      return;
    }
    this.bulkSending = true;
    void firstValueFrom(this.http.post<BulkAnswer>('/social-cases/bulk-active', { active: target.active, ids: target.rows.map((row) => row.id) }))
      .then((answer) => {
        this.bulkSending = false;
        this.tableRef().clearSelection();
        this.bulkTarget.set(null);
        if (isBulkAnswer(answer)) {
          const separator = this.translate.instant('common.list_separator');
          const text = bulkToastText(answer, separator, this.translate.instant('table.bulk.failed_list'));
          if (answer.data.refused > 0) {
            this.toast.info(text);
          } else {
            this.toast.success(text);
          }
        } else {
          this.toast.success((answer as ActionResponse).message);
        }
        reloadList(this.data);
      })
      .catch((err: unknown) => {
        this.bulkSending = false;
        const failure = bulkFailure(err);
        this.toast.apiError(failure.message, failure.code);
      });
  }

  toggleActive(row: Row): void {
    if (this.acting()) {
      return;
    }
    this.acting.set(true);
    void firstValueFrom(this.api.action(`/social-cases/${row.id}/toggle-active`)).then((res) => {
      this.acting.set(false);
      this.toast.success(res.message);
      reloadList(this.data);
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
