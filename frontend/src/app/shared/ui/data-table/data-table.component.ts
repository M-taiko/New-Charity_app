import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  Directive,
  contentChildren,
  TemplateRef,
  computed,
  contentChild,
  effect,
  inject,
  input,
  output,
  signal,
  untracked,
  viewChild,
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TableModule, Table } from 'primeng/table';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { firstValueFrom } from 'rxjs';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { ApiClient } from '@core/http/api-client';
import { AuthService } from '@core/auth/auth.service';
import { ToastService } from '@core/errors/toast.service';
import { TableFiltersComponent, FilterCustomDirective } from '../table-filters/table-filters.component';
import { TableViewsComponent, type ViewColumn } from '../table-views/table-views.component';
import {
  deleteView,
  emptyViews,
  findView,
  parseViews,
  renameView,
  saveView,
  setActiveView,
  setDefaultView,
  viewsStorageKey,
  type SavedViews,
  type ViewSnapshot,
} from '@shared/lib/saved-views';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { ScrollEdgesDirective } from './scroll-edges.directive';
import { ActionButtonComponent } from '../actions/action-button.component';
import type { ActionKind } from '../actions/action-kind';
import {
  defaultFilterValues,
  deriveFilters,
  filterParams,
  filterRows,
  fieldValue,
  isFilterActive,
  type TableFilterDef,
  type TableFilterValue,
  type TableQuery,
} from '@shared/lib/table-filters';

export interface TableColumn {
  field: string;
  header: string; // i18n key
  numeric?: boolean;
  width?: string;
  sortable?: boolean;
  /** server sort key when it differs from the column field (T6-21) */
  sortKey?: string;
  /** dot-path used for client-side search when the displayed value is nested (e.g. "agent.name") */
  searchField?: string;
  /** pinned columns (e.g. actions) stay in place: last, not draggable/resizable (T6-22) */
  pinned?: 'start' | 'end' | null;
  /** minimum width in px for resize (T6-22, default 80) */
  minWidth?: number;
}

export interface TablePageEvent {
  page: number;
  per_page: number;
  sort: string;
  q?: string;
}

/** N-04: one action a page offers for the selected rows; the table draws the button and hands the rows back */
export interface BulkAction {
  /** what the page receives back with the rows */
  readonly value: string;
  /** i18n key of its label */
  readonly label: string;
  readonly kind: ActionKind;
  /** false hides it (a role that may not do it); the page decides, by the same rule as elsewhere */
  readonly visible?: boolean;
}

/** what tells one row from another: its id */
export function rowIdKey(row: unknown): string | number {
  const id = (row as { id?: unknown } | null)?.id;
  return typeof id === 'string' || typeof id === 'number' ? id : '';
}

/** the only two export formats — Excel and PDF (CSV was a duplicate of Excel) */
export type ExportFormat = 'xlsx' | 'pdf';

/** Per-user table state persisted in localStorage (plan 19.4, 21.2). */
interface TableState {
  columnOrder?: string[];
  columnWidths?: Record<string, string>;
  tableWidth?: string;
  sortField?: string;
  sortDir?: number;
  rows?: number;
  /** N-02: columns the user hid (pinned ones can never be hidden) */
  hiddenColumns?: string[];
}

/** Marks the projected per-row template: <ng-template appTableBody let-row> */
@Directive({ selector: '[appTableBody]' })
export class TableBodyDirective {
  template = inject<TemplateRef<unknown>>(TemplateRef);
}

/**
 * T6-22: per-column cell template — <ng-template appCell="amount" let-row>.
 * When a table has cell templates, the table renders its <td>s itself from
 * the ordered column list, so header, data and totals always move together.
 */
@Directive({ selector: '[appCell]' })
export class TableCellDirective {
  appCell = input.required<string>();
  template = inject<TemplateRef<unknown>>(TemplateRef);
}

/**
 * T7-04: per-column totals/footer cell — <ng-template appFooterCell="amount">.
 * When present, the table renders its footer row from the ordered column
 * list, so totals follow column reordering exactly like header and body.
 */
@Directive({ selector: '[appFooterCell]' })
export class TableFooterCellDirective {
  appFooterCell = input.required<string>();
  template = inject<TemplateRef<unknown>>(TemplateRef);
}


/**
 * The only table in the app (plan 16.2). Server-side pagination + sorting,
 * ALL columns sortable by default, resizable, reorderable (drag-and-drop),
 * and per-user state (column order, widths, sort, page size) saved to
 * localStorage keyed by the PAGE ROUTE + table key (no collisions between
 * pages that reuse the same component key).
 *
 * Search is client-side: the table filters the rows the parent component
 * provides, matching the query against every column's field value
 * (any value type — string/number/boolean — via dot-path lookup).
 */
@Component({
  selector: 'app-data-table',
  standalone: true,
  imports: [TranslatePipe, TableModule, NgTemplateOutlet, FormsModule, InputTextModule, ButtonModule, TableFiltersComponent, TableViewsComponent, MenuModule, IconComponent, ScrollEdgesDirective, EmptyStateComponent, ActionButtonComponent],
  template: `
    <div class="ledger-table" appScrollEdges [class.print-no-totals]="!printIncludeTotals()" [class.print-landscape]="printOrientation() === 'landscape'"
         [class.ledger-table--compact]="density() === 'compact'" [attr.aria-busy]="busy() || null"
         [attr.data-columns]="renderedFields()"
         [attr.data-phone-columns]="phoneFirst().length > 0 ? phoneFirst().join(',') : null"
         [attr.data-phone-first]="phoneLead().length > 0 ? 'true' : null"
         [attr.data-selectable]="selectable() ? 'true' : null">
      @if (printCapNotice() !== '') {
        <p class="print-cap-notice" style="display: none">{{ printCapNotice() }}</p>
      }
      @if (title() !== '' || true) {
        <div class="ledger-table__header">
          @if (title() !== '') {
            <span class="ledger-table__title">{{ title() | translate }}</span>
          }
          <input
            pInputText
            type="search"
            class="ledger-table__filter"
            [placeholder]="'common.search' | translate"
            [attr.aria-label]="'common.search' | translate"
            [ngModel]="searchQuery()" name="searchQuery()"
            (ngModelChange)="onSearch($event)"
          />
          <!-- T6-30: the table owns filtering — one Filters control everywhere -->
          @if (effectiveFilters().length > 0) {
            <app-table-filters #filtersPanel [defs]="effectiveFilters()" [customTemplates]="customFilterTemplates()" [rows]="rows()"
                               [(values)]="filterValues" (applied)="onFiltersApplied($event)"
                               (opened)="closeViewsPanel()" />
          }
          <!-- N-02: saved views — filters, search, sort and visible columns under a name -->
          @if (savedViews()) {
            <app-table-views [views]="viewsState()" [current]="viewSnapshot()" [columns]="hideableColumns()"
                             (opened)="closeFiltersPanel()"
                             (apply)="applyView($event)"
                             (saveAs)="saveViewAs($event)"
                             (renameTo)="renameSavedView($event.id, $event.name)"
                             (remove)="deleteSavedView($event)"
                             (setDefault)="setDefaultSavedView($event)"
                             (leave)="leaveView()"
                             (columnVisibility)="setColumnVisible($event.field, $event.visible)" />
          }
          <span class="ledger-table__spacer"></span>
          <!-- D-06: ONE trailing "⋯" group — export, print, density and the
               reset that used to wear a confusing funnel icon all live here -->
          <button
            type="button"
            class="topbar__icon-btn"
            (click)="settingsMenu.toggle($event)"
            [attr.aria-label]="'common.more_actions' | translate"
            [title]="'common.more_actions' | translate"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use href="assets/icons/sprite.svg#tabler-dots-vertical" />
            </svg>
          </button>
          <p-menu #settingsMenu appendTo="body" [model]="settingsItems()" popup="true" />
          <ng-content select="[toolbar]" />
        </div>
      }

      <!-- R5-03: shown by the stylesheet on phones, only while the table is
           wider than its card (data-scroll). The arrow sits at the END of the
           line, pointing the way — toward the columns still to the side -->
      <p class="ledger-table__scroll-hint" aria-hidden="true">
        {{ 'common.scroll_columns' | translate }}
        <app-icon name="chevron" [size]="14" />
      </p>

      <!-- N-04: the bulk bar — on screen while at least one row is selected, stuck under the top bar -->
      @if (selectable() && selectedCount() > 0) {
        <div class="ledger-table__bulk" role="region" [attr.aria-label]="'table.bulk.bar_label' | translate">
          <span class="ledger-table__bulk-count" role="status">{{ 'table.bulk.selected' | translate: { count: selectedCount() } }}</span>
          @for (action of visibleBulkActions(); track action.value) {
            <app-action-button [kind]="action.kind" [label]="action.label" size="small" (action)="runBulk(action.value)" />
          }
          <app-action-button kind="clearSelection" label="table.bulk.clear" size="small" (action)="clearSelection()" />
        </div>
      }

      <p-table #table
        [value]="displayRows()"
        [lazy]="pagedHere()"
        (onLazyLoad)="onLazyLoad($event)"
        (onColReorder)="onColReorder($event)"
        (onColResize)="onColResize($event)"
        [rows]="perPage()"
        [totalRecords]="mode() === 'static' ? staticSorted().length : total()"
        [paginator]="true"
        [rowsPerPageOptions]="pageSizeOptions()"
        [paginatorDropdownAppendTo]="'body'"
        [showCurrentPageReport]="true"
        [currentPageReportTemplate]="'common.table_report' | translate"
        [(first)]="primeFirst"
        [styleClass]="density() === 'compact' ? 'p-datatable-sm' : ''"
        [resizableColumns]="true"
        [columnResizeMode]="'expand'"
        [reorderableColumns]="true"
        [sortMode]="'single'"
        [sortField]="initialState.sortField || ''"
        [sortOrder]="initialState.sortDir || 1"
        [tableStyle]="tableStyle()"
        [scrollable]="false"
        [loading]="queryUnanswered()"
        [showLoader]="false"
      >
        <ng-template pTemplate="header">
          <tr>
            <!-- N-04: the selection column — on a wide screen its own column (a cell of this
                 table, never sorted, moved or resized); on a phone the stylesheet hides it and
                 the checkbox moves into the first header cell, which stays pinned -->
            @if (selectable()) {
              <th class="ledger-table__cell-select" scope="col">
                <input #pageBox type="checkbox" class="ledger-table__checkbox"
                       [checked]="allOnPageSelected()" [indeterminate]="someOnPageSelected()"
                       (change)="togglePage(pageBox.checked)"
                       [attr.aria-label]="'table.bulk.select_all' | translate" />
              </th>
            }
            @for (col of headerColumns(); track col.field; let i = $index) {
              <!-- R3-05: actions columns get an explicit WIDTH, not just
                   min-width — table-layout:fixed ignores min-width when the
                   column sum exceeds the container -->
              <th
                (dblclick)="resetColumnWidth(col.field)"
                [pSortableColumn]="isSortable(col) ? sortFieldOf(col) : undefined"
                pResizableColumn
                pReorderableColumn
                [pResizableColumnDisabled]="col.pinned != null"
                [pReorderableColumnDisabled]="col.pinned != null"
                [style.width]="columnWidth(col.field) ?? actionsWidth(col)"
                [style.minWidth.px]="col.minWidth ?? (col.numeric ? 96 : col.field === 'actions' ? 150 : 80)"
                [class.ledger-table__cell-num]="col.numeric"
                [attr.data-field]="col.field"
                scope="col"
                [attr.aria-sort]="ariaSort(col.field)"
              >
                <!-- ONE unbreakable row: the select-all box (a selectable table, on a phone), the name
                     and the sort arrow. It is a single inline box, as wide as what it holds on one line —
                     so a column is never narrower than its own header, the name never breaks, and the
                     arrow always sits beside it. (As two boxes, a line could break between them: at
                     1280px 26 arrows sat under their name.) -->
                <span class="ledger-table__header-label">
                  <!-- on a phone the box sits inside a header cell that sorts on click: the click is its own,
                       or it would select the page and sort the column — and a sort clears the selection -->
                  @if (selectable() && i === 0) {
                    <input #pageBoxInline type="checkbox" class="ledger-table__checkbox ledger-table__checkbox--inline"
                           [checked]="allOnPageSelected()" [indeterminate]="someOnPageSelected()"
                           (change)="togglePage(pageBoxInline.checked)"
                           (click)="$event.stopPropagation()" (dblclick)="$event.stopPropagation()"
                           [attr.aria-label]="'table.bulk.select_all' | translate" />
                  }
                  <span class="ledger-table__header-text">{{ col.header | translate }}</span>
                  @if (isSortable(col)) {
                    <p-sortIcon [field]="sortFieldOf(col)" />
                  }
                </span>
              </th>
            }
          </tr>
        </ng-template>

        <ng-template pTemplate="body" let-row let-index="rowIndex">
          @if (cellTemplates().size > 0) {
            <!-- T6-22: cells rendered from the ordered column list — reordering
                 always carries header + data + totals together.
                 The <tr> wrapper is REQUIRED: p-table projects this template
                 verbatim, and without it the loose <td>s fall out of the table
                 row structure and the browser stacks them into anonymous
                 rows (the "empty broken table" bug). -->
            <!-- N-03: an activatable row is focusable; a click on it (not on a link or a button
                 inside) or Enter / Space on it opens the page's quick view -->
            <tr
              [class.ledger-table__row--activatable]="rowActivatable()"
              [class.ledger-table__row--selected]="selectable() && selection().has(rowKey()(row))"
              [tabindex]="rowActivatable() ? 0 : null"
              [attr.aria-haspopup]="rowActivatable() ? 'dialog' : null"
              [attr.aria-selected]="selectable() ? selection().has(rowKey()(row)) : null"
              (click)="onRowClick($event, row)"
              (keydown)="onRowKeydown($event, row)"
            >
              <!-- N-04: this row's checkbox (wide screens); on a phone it sits in the first cell -->
              @if (selectable()) {
                <td class="ledger-table__cell-select">
                  <input #rowBox type="checkbox" class="ledger-table__checkbox"
                         [checked]="selection().has(rowKey()(row))"
                         (change)="toggleRow(rowKey()(row), rowBox.checked)"
                         [attr.aria-label]="'table.bulk.select_row' | translate" />
                </td>
              }
              @for (col of headerColumns(); track col.field; let i = $index) {
                <td [class.ledger-table__cell-num]="col.numeric" [class.ledger-table__cell-actions]="col.field === 'actions'">
                  @if (selectable() && i === 0) {
                    <input #rowBoxInline type="checkbox" class="ledger-table__checkbox ledger-table__checkbox--inline"
                           [checked]="selection().has(rowKey()(row))"
                           (change)="toggleRow(rowKey()(row), rowBoxInline.checked)"
                           [attr.aria-label]="'table.bulk.select_row' | translate" />
                  }
                  <ng-container
                    [ngTemplateOutlet]="cellTemplates().get(col.field) ?? null"
                    [ngTemplateOutletContext]="{ $implicit: row, index: index }"
                  />
                </td>
              }
            </tr>
          } @else {
            <ng-container
              [ngTemplateOutlet]="body()?.template ?? null"
              [ngTemplateOutletContext]="{ $implicit: row, index: index, columns: orderedColumns() }"
            />
          }
        </ng-template>

        <ng-template pTemplate="emptymessage">
          <tr>
            <td [colSpan]="tableColumnCount()">
              <!-- N-08: two different states. "No results for this filter"
                   keeps the toolbar and offers one way back — clearing what
                   filtered everything out. "No data at all" is the page's own
                   onboarding (the empty slot), with its call to action -->
              @if (noResultsForFilter()) {
                <app-empty-state
                  [compact]="true"
                  [title]="'common.no_results_for_filter'"
                  [hint]="'common.no_results_hint'"
                  [actionLabel]="'common.clear_filters'"
                  actionKind="reset"
                  (action)="clearAllFilters()"
                />
              } @else {
                <ng-content select="[empty]" />
                @if (emptyLabel() !== '') {
                  <span class="u-muted">{{ emptyLabel() | translate }}</span>
                }
              }
            </td>
          </tr>
        </ng-template>

        <ng-template pTemplate="footer">
          <!-- T6-20: totals as a real table footer — repeats with the header in print.
               T7-04: when per-column footer cells exist they render from the ordered
               column list, so totals follow column reordering like header and body -->
          @if (footerCellTemplates().size > 0) {
            <tr class="ledger-table__row">
              @if (selectable()) {
                <td class="ledger-table__cell-select"></td>
              }
              @for (col of headerColumns(); track col.field; let i = $index) {
                @if (i === 0) {
                  <th scope="row">
                    <ng-container [ngTemplateOutlet]="footerCellTemplates().get(col.field) ?? null" />
                  </th>
                } @else {
                  <td [class.ledger-table__cell-num]="col.numeric">
                    <ng-container [ngTemplateOutlet]="footerCellTemplates().get(col.field) ?? null" />
                  </td>
                }
              }
            </tr>
          } @else {
            <ng-content select="[footer]" />
          }
        </ng-template>

        <ng-template pTemplate="loadingbody">
          <tr>
            <td [colSpan]="tableColumnCount()">
              @for (s of [1, 2, 3, 4, 5]; track s) {
                <div class="skeleton skeleton--row"></div>
              }
            </td>
          </tr>
        </ng-template>
      </p-table>

      @if (summaryEntries().length > 0) {
        <div class="ledger-table__summary">
          @for (entry of summaryEntries(); track entry.label) {
            <span>
              {{ entry.label | translate }}:
              <strong class="u-bidi">{{ entry.value }}</strong>
            </span>
          }
        </div>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DataTableComponent {
  private storage = inject(LocalStorageService);
  private api = inject(ApiClient);
  private toast = inject(ToastService);
  private translate = inject(TranslateService);
  private auth = inject(AuthService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  readonly body = contentChild(TableBodyDirective);
  readonly cells = contentChildren(TableCellDirective);
  readonly footerCells = contentChildren(TableFooterCellDirective);
  readonly filterCustoms = contentChildren(FilterCustomDirective);
  /** PrimeNG's table, for the one thing its inputs cannot do at run time: change the sort */
  private readonly tableRef = viewChild(Table);
  /** the saved-views control (N-02), to close it when the filters panel opens */
  private readonly viewsPanelRef = viewChild(TableViewsComponent);

  /** per-column footer templates by column field (T7-04) */
  readonly footerCellTemplates = computed(() => {
    const map = new Map<string, TemplateRef<unknown>>();
    for (const cell of this.footerCells()) {
      map.set(cell.appFooterCell(), cell.template);
    }
    return map;
  });

  /** custom filter renderers by filter key (T6-30) */
  readonly customFilterTemplates = computed(() => {
    const map: Record<string, TemplateRef<unknown> | null> = {};
    for (const custom of this.filterCustoms()) {
      map[custom.appFilterCustom()] = custom.template;
    }
    return map;
  });

  /** per-column cell templates by column field (T6-22) */
  readonly cellTemplates = computed(() => {
    const map = new Map<string, TemplateRef<unknown>>();
    for (const cell of this.cells()) {
      map.set(cell.appCell(), cell.template);
    }
    return map;
  });

  /** per-component key; the storage key is prefixed with the page route */
  tableKey = input('');
  columns = input.required<TableColumn[]>();
  rows = input.required<unknown[]>();
  total = input(0);
  perPage = input(25);
  first = input(0);

  /**
   * PrimeNG's own page offset, two-way bound to its grid. The pager is
   * PrimeNG's: a table that goes back to its first page has to move PrimeNG's
   * too, and PrimeNG only does that when this value CHANGES.
   */
  protected readonly primeFirst = signal(0);

  /**
   * True when this table hands PrimeNG exactly the rows of ONE page and keeps
   * the paging itself: server mode, and static mode with its own paging. The
   * grid must then be lazy. A grid that is not lazy slices its value by the
   * page offset — so page 2 onward was a slice of page 1's rows: empty, under
   * a pager that still offered every page. That was every static table with
   * more than one page (the directions list: pages 2–8 showed nothing).
   */
  readonly pagedHere = computed(
    () => this.mode() === 'server' || (this.mode() === 'static' && this.staticPaginated() && !this.printingAllRows()),
  );
  loading = input(false);
  title = input('');
  summary = input<Record<string, string>>({});
  /** i18n key shown in the empty message (ledger-table parity, T6-20); '' hides it */
  emptyLabel = input('');

  /** T6-20: "server" (default — page loads pages) | "static" (all rows provided; the table sorts, paginates, searches and filters client-side) */
  mode = input<'server' | 'static'>('server');
  /** accepted server sort keys from meta.sortable (T6-21) — arrows on other columns are disabled */
  sortableKeys = input<string[] | null>(null);
  /** filter definitions (T6-30) — from the page or from the API's meta.filters */
  filters = input<TableFilterDef[]>([]);
  /** T6-30: derive filter definitions from columns + row values (static tables without definitions) */
  autoFilters = input(false);
  /** static mode: optional client-side pagination toggle (default on) */
  staticPaginated = input(true);
  /** T6-71: API export path, e.g. '/expenses/export' - shows the Export menu (xlsx/pdf/csv) */
  exportPath = input('');
  /**
   * T7-06: the list endpoint this table loads from, e.g. '/expenses'. The
   * Export menu is DERIVED (GET {listUrl}/export) so pages never declare a
   * separate export path — export is part of the list contract.
   */
  listUrl = input('');
  /** extra page-level params the export must replay (report period filters, scope…) */
  exportQuery = input<Record<string, string>>({});
  /** T6-70: show the toolbar Print button (default true) */
  printable = input(true);
  /**
   * R5-03: column fields that come first on a phone, in this order — the
   * title, the status, the amount; the rest follow in their declared order.
   * A phone card shows about two columns without scrolling, so the declared
   * order (e.g. amount, five more figures, then status) buried the status
   * and the actions far to the side. Wider screens are not affected.
   */
  phoneFirst = input<string[]>([]);

  /**
   * N-02: offer saved views on this table — the toolbar's "العروض المحفوظة"
   * control (save, apply, rename, default, delete) and the visible columns.
   * Off by default; a page opts in.
   */
  savedViews = input(false);

  /**
   * N-03: the rows can be activated — a click on the row (outside any link,
   * button or field inside it), or Enter / Space on the focused row — emits
   * `rowActivate` for the page to open its quick view. The rows become
   * focusable. Off by default; a page opts in.
   */
  rowActivatable = input(false);
  /** N-03: `element` is the row, for the quick view to bring the focus back to */
  rowActivate = output<{ row: unknown; element: HTMLElement | null }>();

  /**
   * N-04: the rows can be selected — a checkbox a row, a select-all for the
   * page, a bulk bar with the page's actions while at least one is selected.
   * Off by default; a page opts in.
   */
  selectable = input(false);
  /** N-04: what the page offers for the selected rows */
  bulkActions = input<readonly BulkAction[]>([]);
  /** N-04: what tells one row from another (its id): what the selection keeps */
  rowKey = input<(row: unknown) => string | number>(rowIdKey);
  /** N-04: a bulk action was asked for, with the rows selected on this page */
  bulk = output<{ action: string; rows: readonly unknown[] }>();

  /** true below 768px — where a table no longer fits its card */
  readonly phone = signal(typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches);

  pageChange = output<TablePageEvent>();
  /** T6-30: the full query in server mode — page forwards it to the API as page/per_page/sort/q/filter[key] */
  queryChange = output<TableQuery>();
  /**
   * N-08: the table's "no results for this filter" action was used — it has
   * already cleared its own filters and search; this is for the filters a
   * page keeps outside the table (status chips, a period), which the page
   * clears and then reloads with.
   */
  clearFilters = output();

  /**
   * N-08: whether the PAGE filters the rows by something the table cannot
   * see (chips above it, a period). null = the table decides by its own
   * search and filters; a function is read reactively.
   */
  filtered = input<boolean | (() => boolean) | null>(null);

  searchQuery = signal('');
  state = signal<TableState>({});
  density = signal<'comfortable' | 'compact'>(this.storage.get('table.density', 'comfortable'));

  /** page route path (e.g. "users", "custodies/mine") — scopes saved state per page */
  private readonly routePath = this.route.pathFromRoot
    .map((r) => r.routeConfig?.path)
    .filter((p): p is string => !!p && !p.includes(':'))
    .join('/');

  private readonly storageKey = computed(() => {
    const key = this.tableKey() || this.title() || 'table';
    return this.routePath ? `${this.routePath}/${key}` : key;
  });

  private readSavedState(): TableState {
    return this.storage.get<TableState>(`table.state.${this.storageKey()}`, {});
  }

  /**
   * Saved sort restored ONCE — p-table's [sortField]/[sortOrder] inputs are
   * constant after init. Re-binding them on every save made PrimeNG toggle
   * its internal order twice (input feedback), so descending never fired.
   */
  readonly initialState = this.readSavedState();

  constructor() {
    // R5-03: follow the viewport across the phone boundary (rotation, resize).
    // The query outlives the table, so the listener is taken off again on destroy.
    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      const narrow = window.matchMedia('(max-width: 767px)');
      const follow = (event: MediaQueryListEvent): void => {
        this.phone.set(event.matches);
      };
      narrow.addEventListener('change', follow);
      inject(DestroyRef).onDestroy(() => {
        narrow.removeEventListener('change', follow);
      });
    }
    effect(() => {
      const key = this.storageKey();
      const saved = this.storage.get<TableState>(`table.state.${key}`, {});
      this.state.set(saved);

        // seed the first data fetch with the saved sort so header and rows agree
        const savedSortField = typeof saved.sortField === 'string' ? saved.sortField : '';
        if (savedSortField !== '') {
          this.pageChange.emit({
            page: 1,
            per_page: saved.rows ?? 25,
            sort: (saved.sortDir === -1 ? '-' : '') + String(saved.sortField),
          });
        }
      });
      // N-04: the rows changed (another page came, or the list was reloaded): a row that left is no longer selected
      effect(() => {
        const keys = new Set(this.pageKeys());
        this.selection.update((current) => {
          const next = new Set([...current].filter((key) => keys.has(key)));
          return next.size === current.size ? current : next;
        });
      });
      // N-04: a filter, the search or the sort changed: what was selected was selected in another list.
      // The selection is written, never read, here: read, it would be a dependency, and the
      // effect would run again the moment a row was selected — and wipe it.
      effect(() => {
        void this.filterValues();
        void this.searchQuery();
        void this.currentSort();
        this.selection.set(new Set());
      });
      // N-08: the search or a filter changed in a list the server filters. From here until a
      // request that began AFTER the change has ended, the rows on screen answer the previous query
      effect(() => {
        const query = JSON.stringify([this.searchQuery(), this.filterValues()]);
        const first = this.seenQuery === null;
        this.seenQuery = query;
        if (first || this.mode() === 'static') {
          return;
        }
        // a request in flight began before the change: its answer is not the one waited for
        this.answeringChange = false;
        this.queryUnanswered.set(true);
        // a page that asks nothing for this query is answered by the rows it has
        this.giveUpWaiting(0);
      });
      effect(() => {
        const loading = this.loading();
        if (loading === this.seenLoading) {
          return;
        }
        this.seenLoading = loading;
        if (loading) {
          this.cancelGiveUp();
          // read untracked: whether a change is waiting is not what this effect follows
          this.answeringChange = untracked(() => this.queryUnanswered());
          return;
        }
        if (this.answeringChange) {
          this.answeringChange = false;
          this.queryUnanswered.set(false);
        } else if (untracked(() => this.queryUnanswered())) {
          // a request that began before the change ended: the page may still be about to ask
          // again for the new query (`reloadList` does, within 30ms of this moment)
          this.giveUpWaiting(DataTableComponent.ASK_AGAIN_WITHIN_MS);
        }
      });
      inject(DestroyRef).onDestroy(() => {
        this.cancelGiveUp();
      });
      // a page may send the table to other rows through the public `[first]` input: the pager
      // follows it. Only THAT is followed here — read on the pager's own page changes, this
      // effect pushed the bound offset straight back to 0 after every page click, and the
      // pager could then never be moved by `firstPage()`
      let firstFromPage = this.first();
      effect(() => {
        const wanted = this.first();
        if (wanted !== firstFromPage) {
          firstFromPage = wanted;
          this.primeFirst.set(wanted);
        }
      });
    }

  /**
   * `reloadList` asks a refused reload again at most SETTLE_POLL_MS (30) after the
   * request in flight has settled. A request that began after a change is waited
   * for this long past the end of an older one; none comes, the rows there are
   * the answer to the query as it is.
   */
  private static readonly ASK_AGAIN_WITHIN_MS = 90;
  private giveUpTimer: ReturnType<typeof setTimeout> | null = null;

  /** nothing may be waited for past this delay: still no request, the rows there are the answer */
  private giveUpWaiting(delayMs: number): void {
    this.cancelGiveUp();
    this.giveUpTimer = setTimeout(() => {
      this.giveUpTimer = null;
      if (!this.loading()) {
        this.queryUnanswered.set(false);
      }
    }, delayMs);
  }

  private cancelGiveUp(): void {
    if (this.giveUpTimer !== null) {
      clearTimeout(this.giveUpTimer);
      this.giveUpTimer = null;
    }
  }

  /** rows after the active table filters (T6-30 static engine) */
  staticRows = computed<unknown[]>(() => {
    const rows = this.rows();
    if (!Array.isArray(rows)) {
      return [];
    }
    return filterRows(rows, this.filterValues(), this.effectiveFilters());
  });

  /** rows filtered client-side — matches what the user SEES: raw values,
   *  comma-formatted money, dd/mm/yyyy dates, and translated enum labels */
  filteredRows = computed(() => {
    const q = this.searchQuery().trim().toLowerCase();
    const rows = this.staticRows();
    if (!q || !Array.isArray(rows)) return rows;
    const qPlain = q.replace(/,/g, '');
    const fields = this.orderedColumns().map((c) => c.searchField ?? c.field);
    return rows.filter((row) =>
      fields.some((field) => this.cellMatches(this.fieldValue(row, field), q, qPlain)),
    );
  });

  /** T6-20 static mode: client-side sort + pagination over filteredRows */
  staticPage = signal(0);
  currentSort = signal('');

  staticSorted = computed(() => {
    const rows = [...this.filteredRows()];
    const sort = this.currentSort();
    if (this.mode() !== 'static' || sort === '') {
      return rows;
    }
    const dir = sort.startsWith('-') ? -1 : 1;
    const field = sort.replace(/^-/, '');
    const col = this.orderedColumns().find((c) => (c.sortKey ?? c.field) === field || c.field === field);
    const key = col ? (col.sortKey ?? col.field) : field;
    return rows.sort((a, b) => {
      const av = fieldValue(a, key);
      const bv = fieldValue(b, key);
      const an = typeof av === 'number' ? av : Number.parseFloat(String(av ?? ''));
      const bn = typeof bv === 'number' ? bv : Number.parseFloat(String(bv ?? ''));
      if (Number.isFinite(an) && Number.isFinite(bn) && /^-?[\d.,]+$/.test(String(av ?? '')) && /^-?[\d.,]+$/.test(String(bv ?? ''))) {
        return (an - bn) * dir;
      }
      return String(av ?? '').localeCompare(String(bv ?? ''), 'ar', { numeric: true }) * dir;
    });
  });

  /** page-size choices: the standard steps plus the page's own perPage when
      it sits outside them (e.g. tracking's show-all 1000) so the paginator
      dropdown always shows the active value */
  pageSizeOptions = computed<number[]>(() => {
    const steps = [10, 25, 50, 100];
    const current = Math.max(this.perPage(), 1);
    return [...new Set(current > 100 ? [...steps, current] : steps)].sort((a, b) => a - b);
  });

  /** what p-table renders: the current static page, or the server-provided rows */
  displayRows = computed(() => {
    if (this.mode() !== 'static' || !this.staticPaginated() || this.printingAllRows()) {
      return this.staticSorted();
    }
    const start = this.staticPage();
    const size = Math.max(this.perPage(), 1);
    return this.staticSorted().slice(start, start + size);
  });

  /** one cell's value in every form the interface might display it */
  private cellMatches(value: unknown, q: string, qPlain: string): boolean {
    // objects can never match a text query (and previously stringified to
    // "[object Object]", which matched nothing either)
    if (value === null || value === undefined || typeof value === 'object') return false;
    const raw = String(value as string | number | bigint | boolean | symbol);
    if (raw.toLowerCase().includes(q)) return true;

    // money: stored "5000.00", displayed "5,000.00"
    if (qPlain && /^-?[\d.]+$/.test(raw) && raw.includes(qPlain)) return true;

    // dates: stored ISO "2026-10-27T…", displayed "27/10/2026"
    const d = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    if (d && `${d[3]}/${d[2]}/${d[1]}`.includes(q)) return true;

    // enums: stored code ("closed"), displayed label ("مغلقة")
    return this.enumLabels(raw).some((label) => label.toLowerCase().includes(q));
  }

  /** every translated label for a value across enum namespaces (cached) */
  private readonly labelCache = new Map<string, string[]>();
  private enumLabels(value: string): string[] {
    const cached = this.labelCache.get(value);
    if (cached) return cached;
    const labels: string[] = [];
    for (const ns of ENUM_NAMESPACES) {
      const key = `enums.${ns}.${value}`;
      const label = this.translate.instant(key);
      if (label && label !== key) labels.push(label);
    }
    this.labelCache.set(value, labels);
    return labels;
  }

  /** dot-path lookup: "agent.name" → row.agent?.name; any value type allowed */
  private fieldValue(row: unknown, field: string): unknown {
    return field.split('.').reduce<unknown>((acc, part) => {
      if (acc !== null && acc !== undefined && typeof acc === 'object') {
        return (acc as Record<string, unknown>)[part];
      }
      return undefined;
    }, row);
  }




  /** Columns reordered + widths restored from saved state; hidden ones (N-02) left out unless pinned */
  orderedColumns = computed(() => {
    const input = this.columns();
    if (!Array.isArray(input) || input.length === 0) return [];
    const hidden = this.state().hiddenColumns;
    const cols = hidden === undefined || hidden.length === 0 ? [...input] : input.filter((c) => c.pinned != null || !hidden.includes(c.field));
    const order = this.state().columnOrder;
    if (!order || order.length === 0) return cols;

    const fieldSet = new Set(cols.map((c) => c.field));
    const ordered: TableColumn[] = [];
    for (const field of order) {
      const col = cols.find((c) => c.field === field);
      if (col && fieldSet.has(field)) {
        ordered.push(col);
        fieldSet.delete(field);
      }
    }
    // append any new columns not in the saved order
    for (const col of cols) {
      if (fieldSet.has(col.field)) {
        ordered.push(col);
      }
    }
    return ordered;
  });

  /** columns with pinned ones (actions) always last and not draggable (T6-22) */
  headerColumns = computed(() => {
    const cols = this.orderedColumns();
    const pinned = cols.filter((c) => c.pinned);
    const rest = cols.filter((c) => !c.pinned);
    // R5-03: on a phone the declared key columns come first, in that order
    const lead = this.phoneLead();
    if (lead.length === 0) {
      return [...rest, ...pinned];
    }
    const front: TableColumn[] = [];
    for (const field of lead) {
      const col = rest.find((c) => c.field === field);
      if (col) {
        front.push(col);
      }
    }
    return [...front, ...rest.filter((c) => !front.includes(c)), ...pinned];
  });

  /**
   * R5-03: the key columns that come first ON A PHONE, in this order — the
   * title, the status, the amount. Empty on wider screens, so a desktop
   * table keeps exactly its declared (or user-dragged) order.
   */
  phoneLead = computed<string[]>(() => (this.phone() ? this.phoneFirst() : []));

  /** the rendered column order, for the visual matrix (`data-columns`) */
  renderedFields = computed(() => this.headerColumns().map((c) => c.field).join(','));

  /** the server sort key of a column — field unless sortKey is set (T6-21) */
  sortFieldOf(col: TableColumn): string {
    return col.sortKey ?? col.field;
  }

  /**
   * T6-21: a column shows a sort arrow only when the page did not opt out
   * AND the server declared it sortable (when meta.sortable is known).
   * Dev-time warning catches columns that look sortable but are not.
   */
  isSortable(col: TableColumn): boolean {
    if (col.sortable === false || col.field === 'actions') {
      return false;
    }
    const allowed = this.sortableKeys();
    if (allowed === null) {
      return true; // server contract unknown — keep the arrow
    }
    const key = this.sortFieldOf(col);
    const ok = allowed.includes(key) || allowed.includes(col.field);
    if (!ok && typeof ngDevMode !== 'undefined' && ngDevMode) {
      // eslint-disable-next-line no-console -- the dev-time warning T6-21 asks for
      console.warn(
        `[data-table] column "${col.field}" is marked sortable but missing from meta.sortable — arrow disabled (T6-21)`,
      );
    }
    return ok;
  }

  /**
   * aria-sort for the focused column header. The saved sort holds the
   * server's key, which can differ from the field (`requested_amount` sorts
   * as `amount`): compared as the key, or those headers never said "sorted".
   */
  ariaSort(field: string): 'ascending' | 'descending' | 'none' {
    const column = this.columns().find((col) => col.field === field);
    const key = column === undefined ? field : this.sortFieldOf(column);
    if (this.state().sortField !== key) {
      return 'none';
    }
    return this.state().sortDir === -1 ? 'descending' : 'ascending';
  }

  columnWidth(field: string): string | null {
    return this.state().columnWidths?.[field] ?? null;
  }

  /**
   * R3-05 / actions fill: actions columns get an explicit WIDTH (not just
   * min-width — `table-layout: fixed` ignores min-width when the column sum
   * exceeds the container). The default is 160px, but a page that declares a
   * wider `minWidth` (e.g. expenses: a text workflow button beside the icon
   * slots) keeps its own value — the default must never shrink a column
   * below what its content needs.
   */
  actionsWidth(col: TableColumn): string | null {
    if (col.field !== 'actions') {
      return null;
    }
    return `${Math.max(160, col.minWidth ?? 0)}px`;
  }

  /** restored table width (expand resize mode grows the table past 100%) */
  tableStyle(): { width: string } | undefined {
    const w = this.state().tableWidth;
    return w ? { width: w } : undefined;
  }

  summaryEntries(): { label: string; value: string }[] {
    return Object.entries(this.summary()).map(([label, value]) => ({ label, value }));
  }

  /** T7-06: GET {listUrl}/export unless the page set an explicit exportPath */
  readonly effectiveExportPath = computed(() => {
    const explicit = this.exportPath();
    if (explicit !== '') {
      return explicit;
    }
    const list = this.listUrl();
    return list !== '' ? list.replace(/\/+$/, '') + '/export' : '';
  });

  /** D-06: the ONE toolbar group — Excel, PDF, print (portrait), density
   *  toggle and reset in a single quiet "⋯" menu */
  readonly settingsItems = computed<MenuItem[]>(() => {
    const items: MenuItem[] = [];
    if (this.effectiveExportPath() !== '') {
      items.push(
        { label: this.translate.instant('common.export_excel'), command: () => { void this.downloadExport('xlsx'); } },
        { label: this.translate.instant('common.export_pdf'), command: () => { void this.downloadExport('pdf'); } },
      );
    }
    if (this.printable()) {
      // ONE print action, always portrait
      items.push({ label: this.translate.instant('common.print'), command: () => { this.printTable(); } });
    }
    if (items.length > 0) {
      items.push({ separator: true });
    }
    items.push(
      {
        label: this.translate.instant(this.density() === 'compact' ? 'common.density_comfortable' : 'common.density_compact'),
        command: () => { this.toggleDensity(); },
      },
      { label: this.translate.instant('common.reset_layout'), command: () => { this.resetState(); } },
    );
    return items;
  });

  /**
   * T7-06: the export replays the table's CURRENT query — format + the page's
   * own params + (server mode) the live sort/search/filter[…] values, so the
   * file always matches what is on screen.
   */
  private exportParams(format: ExportFormat): Record<string, string> {
    const params: Record<string, string> = { format, ...this.exportQuery() };
    if (this.mode() === 'server') {
      if (this.currentSort() !== '') {
        params.sort = this.currentSort();
      }
      if (this.searchQuery().trim() !== '') {
        params.q = this.searchQuery().trim();
      }
      Object.assign(params, filterParams(this.filterValues()));
    }
    return params;
  }

  /** `<table title or route>-<yyyy-mm-dd>.<ext>` — never a bare "export.xlsx" */
  private exportFileName(format: ExportFormat): string {
    const base = (this.title() || '').trim() !== '' ? this.translate.instant(this.title()) : this.router.url.split('?')[0].split('/').filter(Boolean).pop() || 'export';
    const safe = base.replace(/[\\/:*?"<>|\s]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60) || 'export';
    const day = new Date().toISOString().slice(0, 10);
    return `${safe}-${day}.${format}`;
  }

  async downloadExport(format: ExportFormat): Promise<void> {
    const path = this.effectiveExportPath();
    if (path === '') {
      return;
    }
    const query = new URLSearchParams(this.exportParams(format)).toString();
    try {
      const blob = await firstValueFrom(this.api.getBlob(path + '?' + query));
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = this.exportFileName(format);
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      this.toast.success(this.translate.instant('common.export_done'));
    } catch (err) {
      this.toast.apiError((err as { error?: { message?: string } })?.error?.message ?? 'errors.generic');
    }
  }

  /**
   * T6-70: print the table — always PORTRAIT. Static tables print every
   * filtered row (the whole table, not just the visible page).
   */
  printTable(): void {
    this.printOrientation.set('portrait');
    this.printingAllRows.set(this.mode() === 'static');
    // let the row expansion apply before the print dialog opens
    setTimeout(() => { window.print(); }, 50);
  }

  readonly printOrientation = signal<'portrait' | 'landscape'>('portrait');
  readonly printingAllRows = signal(false);

  /** T6-70: include/exclude sections — org header (body-wide) and the totals */
  readonly printIncludeHeader = signal(true);
  readonly printIncludeTotals = signal(true);

  /** static mode: printing all rows shows every filtered row (capped notice past the export cap) */
  readonly printCapNotice = computed(() => {
    if (!this.printingAllRows() || this.mode() !== 'static') {
      return '';
    }
    return this.staticSorted().length > 2000
      ? this.translate.instant('common.print_cap_notice', { count: 2000 })
      : '';
  });

  /** back to the first page: this table's offset and PrimeNG's — the pager is PrimeNG's */
  private firstPage(): void {
    this.staticPage.set(0);
    this.primeFirst.set(0);
  }

  onSearch(term: string): void {
    this.searchQuery.set(term);
    this.firstPage();
    if (this.mode() !== 'static') {
      this.emitQuery();
    }
  }

  // ── T6-30: table-driven filters ────────────────────────────────

  /** effective definitions: page-provided, or derived from rows (autoFilters) */
  effectiveFilters = computed<TableFilterDef[]>(() => {
    const explicit = this.filters();
    if (explicit.length > 0) {
      return explicit;
    }
    return this.autoFilters() ? deriveFilters(this.columns(), this.rows()) : [];
  });

  filterValues = signal<Record<string, TableFilterValue | undefined>>({});
  private filtersInitialised = false;
  /** N-02: true once the filter values were restored (from the URL) or defaulted */
  private readonly filtersReady = signal(false);
  /** N-02: the filters came from ?filter[…] in the address — a shared link wins over the default view */
  private filtersFromUrl = false;

  private readonly filterDefsEffect = effect(() => {
    const defs = this.effectiveFilters();
    if (defs.length === 0 || this.filtersInitialised) {
      return;
    }
    this.filtersInitialised = true;
    // restore from ?filter[...]=… — links and refresh reproduce the view
    const restored = this.readFiltersFromUrl(defs);
    this.filtersFromUrl = restored !== null;
    this.filterValues.set(restored ?? defaultFilterValues(defs));
    this.filtersReady.set(true);
  });

  private readFiltersFromUrl(defs: TableFilterDef[]): Record<string, TableFilterValue | undefined> | null {
    const params = this.router.parseUrl(this.router.url).queryParams;
    const keys = defs.map((d) => d.key);
    const hasAny = keys.some((k) => params[`filter[${k}]`] !== undefined);
    if (!hasAny) {
      return null;
    }
    const out: Record<string, TableFilterValue | undefined> = {};
    for (const def of defs) {
      const flat = params[`filter[${def.key}]`];
      if (flat !== undefined) {
        const value = String(flat);
        if (def.type === 'multiselect') {
          out[def.key] = value.split(',').filter(Boolean);
        } else if (def.type === 'boolean') {
          out[def.key] = value === 'true' || value === '1';
        } else {
          out[def.key] = value;
        }
        continue;
      }
      const min = params[`filter[${def.key}][min]`];
      const max = params[`filter[${def.key}][max]`];
      const from = params[`filter[${def.key}][from]`];
      const to = params[`filter[${def.key}][to]`];
      if (min !== undefined || max !== undefined) {
        out[def.key] = { min: min !== undefined ? Number(min) : undefined, max: max !== undefined ? Number(max) : undefined };
      }
      if (from !== undefined || to !== undefined) {
        out[def.key] = { from: from !== undefined ? String(from) : undefined, to: to !== undefined ? String(to) : undefined };
      }
    }
    return out;
  }

  onFiltersApplied(values: Record<string, TableFilterValue | undefined>): void {
    this.filterValues.set(values);
    this.firstPage();
    if (this.mode() !== 'static') {
      this.emitQuery();
    }
  }

  // ── N-08: two empty states ─────────────────────────────────────

  private readonly filtersPanel = viewChild(TableFiltersComponent);

  /**
   * N-08: the search box or a filter changed, and no request that BEGAN after
   * that change has ended yet. The rows on screen are then the previous answer
   * — which `filteredRows` narrows by the new text, often to nothing — and no
   * "empty" may be read from them: the server has not said "nothing" yet. The
   * table shows its loading body instead (`loadingbody`).
   *
   * The gap this closes is of round 6's own making: a reload asked while a
   * request is in flight is refused by Angular and asked again by `reloadList`
   * (shared/lib/list-state.ts) up to 30ms after that request settles. In
   * between the page is "not loading" with the OLD rows under the NEW search,
   * and "no results for this filter" — with its "clear the filters" — showed
   * before the search had even been asked (measured: 73ms before its answer).
   * `n08-empty-states.spec.ts` reproduces it on demand.
   */
  protected readonly queryUnanswered = signal(false);
  /** a request began after the search or a filter changed: its end is the answer to it */
  private answeringChange = false;
  /** the query as last seen by the effect below; null before its first run */
  private seenQuery: string | null = null;
  /** what `loading` was at the effect's last run */
  private seenLoading = false;

  /** the page's request is in flight, or the rows on screen do not answer the search yet */
  readonly busy = computed(() => this.loading() || this.queryUnanswered());

  /** no row is on screen */
  private readonly noRows = computed(() => this.displayRows().length === 0);

  /** a filter the user changed away from its default (a default is not a filter) */
  private ownFiltersChanged(): boolean {
    const values = this.filterValues();
    return this.effectiveFilters().some((def) => {
      const value = values[def.key];
      return isFilterActive(value) && JSON.stringify(value) !== JSON.stringify(def.default ?? undefined);
    });
  }

  /**
   * Nothing on screen AND something filtered it out — the page's own flag,
   * the search box, or a filter changed from its default. This is the state
   * that offers "clear the filters"; "no data at all" is the other one.
   */
  readonly noResultsForFilter = computed(() => {
    // Not while the page's request is in flight: the rows on screen are then
    // the OLD ones, which the search box filters client-side — the server has
    // not said "nothing" yet. Showing this state (and its "clear" button)
    // before the answer invited a click that could not be honoured.
    if (this.loading() || this.queryUnanswered() || !this.noRows()) {
      return false;
    }
    const page = this.filtered();
    const pageFiltered = typeof page === 'function' ? page() : page === true;
    return pageFiltered || this.searchQuery().trim() !== '' || this.ownFiltersChanged();
  });

  /** the table's filters and search back to nothing; the page's own filters through `clearFilters` */
  clearAllFilters(): void {
    const panel = this.filtersPanel();
    if (panel && this.ownFiltersChanged()) {
      // values + `applied` + the ?filter[…] URL params, in one place
      panel.clearAll();
    }
    this.searchQuery.set('');
    this.firstPage();
    this.clearFilters.emit();
    if (this.mode() !== 'static') {
      this.emitQuery();
    }
  }

  /** server mode: forward the whole query (search + filters reach the server, T6-30) */
  private emitQuery(): void {
    const filters: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(this.filterValues())) {
      if (value !== undefined && value !== null && value !== '') {
        filters[key] = value;
      }
    }
    this.queryChange.emit({
      page: this.staticPage() / Math.max(this.perPage(), 1) + 1,
      perPage: this.perPage(),
      sort: this.currentSort(),
      q: this.searchQuery(),
      filters,
    });
  }

  toggleDensity(): void {
    const next = this.density() === 'comfortable' ? 'compact' : 'comfortable';
    this.density.set(next);
    this.storage.set('table.density', next);
  }

  resetState(): void {
    this.storage.remove(`table.state.${this.storageKey()}`);
    this.state.set({});
    this.searchQuery.set('');
  }

  onColReorder(event: { dragIndex?: number | null; dropIndex?: number | null }): void {
    const cols = [...this.orderedColumns()];
    const dragIdx = event.dragIndex ?? 0;
    const dropIdx = event.dropIndex ?? 0;
    const moved = cols.splice(dragIdx, 1)[0];
    cols.splice(dropIdx, 0, moved);
    this.saveState({ columnOrder: cols.map((c) => c.field) });
  }

  /** T6-22: double-click on a header resets that column's saved width */
  resetColumnWidth(field: string): void {
    const widths = { ...(this.state().columnWidths ?? {}) };
    if (field in widths) {
      const { [field]: _removed, ...rest } = widths;
      this.saveState({ columnWidths: rest });
    }
  }

  onColResize(event: { element?: HTMLElement | null; delta?: number | null }): void {
    const element = event.element ?? null;
    const field = element?.getAttribute('data-field');
    if (!element || !field) return;
    const offsetWidth = element.offsetWidth > 0 ? element.offsetWidth : 100;
    const width = element.style.width !== '' ? element.style.width : `${offsetWidth}px`;
    // PrimeNG (expand mode) also grew the table element — persist that too,
    // otherwise restored column widths can exceed the 100% container
    const table = element.closest('table');
    const tableWidth = table !== null && table.style.width !== '' ? table.style.width : undefined;
    const widths = { ...(this.state().columnWidths ?? {}), [field]: width };
    this.saveState({ columnWidths: widths, ...(tableWidth ? { tableWidth } : {}) });
  }

  onLazyLoad(event: {
    first?: number | null;
    rows?: number | null;
    sortField?: string | string[] | null;
    sortOrder?: number | null;
  }): void {
    const first = event.first ?? 0;
    const rows = event.rows ?? 25;
    const rawField = Array.isArray(event.sortField) ? event.sortField[0] : event.sortField;
    let sort = '';
    if (rawField) {
      sort = (event.sortOrder === -1 ? '-' : '') + rawField;
    }

    // persist sort + page size
    this.saveState({
      sortField: rawField ?? '',
      sortDir: event.sortOrder ?? 0,
      rows,
    });

    if (this.mode() === 'static') {
      // T6-20: client-side sort + pagination — nothing to ask the server for
      this.staticPage.set(first);
      this.currentSort.set(sort);
      return;
    }

    this.pageChange.emit({
      page: Math.floor(first / Math.max(rows, 1)) + 1,
      per_page: rows,
      sort,
    });
    this.currentSort.set(sort);
    this.emitQuery();
  }

  private saveState(partial: Partial<TableState>): void {
    const current = this.state();
    const next = { ...current, ...partial };
    this.state.set(next);
    this.storage.set(`table.state.${this.storageKey()}`, next);
  }

  // ── N-02: saved views ─────────────────────────────────────────────────────

  /** the table's own key (page route + table key) scopes the views, then the signed-in user */
  private readonly viewsKey = computed(() => viewsStorageKey(this.storageKey(), this.auth.me()?.id ?? null));

  /** what is saved for this table and user (N-02) — exposed to the template */
  readonly viewsState = signal<SavedViews>(emptyViews());
  /** the key whose default view was already applied on this visit */
  private startedViewsKey: string | null = null;

  /** the table as it is now: what a view saves, and what it is compared with */
  readonly viewSnapshot = computed<ViewSnapshot>(() => ({
    filters: this.filterValues(),
    q: this.searchQuery(),
    sort: this.currentSort(),
    hiddenColumns: this.state().hiddenColumns ?? [],
  }));

  /** the columns a view may hide: everything but the pinned ones (actions stay) */
  readonly hideableColumns = computed<ViewColumn[]>(() =>
    this.columns()
      .filter((column) => column.pinned == null && column.field !== 'actions')
      .map((column) => ({ field: column.field, header: column.header })),
  );

  /** N-02: the saved views of this table and user are read when either is known */
  private readonly viewsLoadEffect = effect(() => {
    if (!this.savedViews()) {
      return;
    }
    const key = this.viewsKey();
    this.viewsState.set(parseViews(this.storage.get<unknown>(key, null)));
  });

  /**
   * N-02: a fresh visit starts in the default view — once the filters were
   * restored, and unless the address already names filters (a shared link
   * shows what it says). Repeated for a new key: the user may sign in after
   * the table was built.
   */
  private urlSearchApplied = false;

  /**
   * N-01: the list was opened with ?q=… (the palette's "see all results") —
   * the search box starts from it, and a server list asks with it. Once,
   * after the filters were restored: the address is read, not written.
   */
  private readonly urlSearchEffect = effect(() => {
    if (this.urlSearchApplied) {
      return;
    }
    // wait for the filters the same address may carry
    if (this.effectiveFilters().length > 0 && !this.filtersReady()) {
      return;
    }
    this.urlSearchApplied = true;
    const q = this.router.parseUrl(this.router.url).queryParams.q;
    if (typeof q !== 'string' || q.trim() === '') {
      return;
    }
    this.searchQuery.set(q.trim());
    if (this.mode() !== 'static') {
      this.emitQuery();
    }
  });

  private readonly viewsStartEffect = effect(() => {
    if (!this.savedViews()) {
      return;
    }
    const key = this.viewsKey();
    if (this.startedViewsKey === key) {
      return;
    }
    if (this.effectiveFilters().length > 0 && !this.filtersReady()) {
      return;
    }
    this.startedViewsKey = key;
    const views = this.viewsState();
    const start = views.defaultId === null ? null : findView(views, views.defaultId);
    if (start !== null && !this.filtersFromUrl) {
      // outside change detection: applying a view navigates (the address follows) and reloads the page's list
      setTimeout(() => { this.applyView(start.id); }, 0);
    }
  });

  private persistViews(): void {
    this.storage.set(this.viewsKey(), this.viewsState());
  }

  /** N-02: one toolbar panel at a time — they would lie on top of each other */
  closeViewsPanel(): void {
    this.viewsPanelRef()?.close(false);
  }

  closeFiltersPanel(): void {
    const panel = this.filtersPanel();
    if (panel?.panelOpen()) {
      panel.panelOpen.set(false);
    }
  }

  /**
   * N-02: put the table in a saved view — filters, search, sort and the
   * visible columns. Everything is set first, the announcements follow.
   */
  applyView(id: string): void {
    const view = findView(this.viewsState(), id);
    if (view === null) {
      return;
    }
    this.firstPage();
    this.searchQuery.set(view.q);
    this.filterValues.set({ ...view.filters });
    this.applySort(view.sort);
    this.saveState({ hiddenColumns: [...view.hiddenColumns] });
    this.viewsState.set(setActiveView(this.viewsState(), id));
    this.persistViews();
    // the address follows, so a refresh reproduces the view
    this.filtersPanel()?.syncUrl({ ...view.filters });
    if (this.mode() !== 'static') {
      // the same two announcements a header click makes: pages take the sort from the first, the rest from the second
      this.pageChange.emit({ page: 1, per_page: this.perPage(), sort: view.sort });
      this.emitQuery();
    }
  }

  /**
   * The sort of a view. PrimeNG's sort inputs are write-once by design (see
   * `initialState`), so its state is set here directly — the header arrow
   * follows; the request is left to the caller.
   */
  private applySort(sort: string): void {
    const field = sort.replace(/^-/, '');
    const descending = sort.startsWith('-');
    this.currentSort.set(sort);
    this.saveState({ sortField: field, sortDir: field === '' ? 0 : descending ? -1 : 1 });
    const table = this.tableRef();
    if (table !== undefined && this.mode() !== 'static') {
      table.sortField = field;
      table.sortOrder = descending ? -1 : 1;
    }
  }

  /** N-02: the table as it is, saved under a name (the control has already checked the name) */
  saveViewAs(name: string): void {
    const result = saveView(this.viewsState(), this.viewSnapshot(), name);
    if (!result.ok) {
      return;
    }
    this.viewsState.set(result.state);
    this.persistViews();
    this.toast.success(this.translate.instant('table.views.saved', { name: result.view.name }));
  }

  renameSavedView(id: string, name: string): void {
    const next = renameView(this.viewsState(), id, name);
    if (next === this.viewsState()) {
      return;
    }
    this.viewsState.set(next);
    this.persistViews();
  }

  deleteSavedView(id: string): void {
    const view = findView(this.viewsState(), id);
    if (view === null) {
      return;
    }
    this.viewsState.set(deleteView(this.viewsState(), id));
    this.persistViews();
    this.toast.info(this.translate.instant('table.views.deleted', { name: view.name }));
  }

  /** N-02: the view a fresh visit starts with; null = the plain table */
  setDefaultSavedView(id: string | null): void {
    this.viewsState.set(setDefaultView(this.viewsState(), id));
    this.persistViews();
  }

  /** N-02: the chip's close — the view is no longer the one on screen; the table stays as it is */
  leaveView(): void {
    this.viewsState.set(setActiveView(this.viewsState(), null));
    this.persistViews();
  }

  /** N-02: a column shown or hidden (kept with the table's layout, so it survives a reload) */
  setColumnVisible(field: string, visible: boolean): void {
    const hidden = new Set(this.state().hiddenColumns ?? []);
    if (visible) {
      hidden.delete(field);
    } else {
      hidden.add(field);
    }
    this.saveState({ hiddenColumns: [...hidden] });
  }

  // ── N-03: activatable rows ────────────────────────────────────────────────

  /** what a click inside a row must not trigger the row's own action */
  private static readonly CONTROLS_IN_ROW = 'a, button, input, select, textarea, label, [role="button"], [role="menuitem"], [role="option"], [role="checkbox"], [contenteditable], p-select, p-checkbox, p-radiobutton, p-toggleswitch';

  /** N-03: a click on the row itself — not on a control inside it */
  onRowClick(event: MouseEvent, row: unknown): void {
    if (!this.rowActivatable()) {
      return;
    }
    const origin = event.target as Element | null;
    const rowElement = event.currentTarget as HTMLElement | null;
    // a click that started in a control inside the row belongs to that control
    if (rowElement === null || origin?.closest(DataTableComponent.CONTROLS_IN_ROW) != null) {
      return;
    }
    // the row takes the focus: the quick view brings it back here when it closes
    rowElement.focus();
    this.rowActivate.emit({ row, element: rowElement });
  }

  // ── N-04: selection ───────────────────────────────────────────────────────

  /** the keys of the selected rows (on this page: a row that leaves the page leaves the selection) */
  readonly selection = signal<ReadonlySet<string | number>>(new Set());

  private readonly pageRows = computed<unknown[]>(() => (Array.isArray(this.rows()) ? this.rows() : []));

  /** the number of cells a full row has: the columns, and the selection column when there is one */
  readonly tableColumnCount = computed(() => this.orderedColumns().length + (this.selectable() ? 1 : 0));

  private readonly pageKeys = computed<(string | number)[]>(() => this.pageRows().map((row) => this.rowKey()(row)));

  readonly selectedCount = computed(() => this.selection().size);

  private readonly selectedOnPage = computed(() => this.pageKeys().filter((key) => this.selection().has(key)).length);

  /** every row on the page is selected */
  readonly allOnPageSelected = computed(() => this.pageKeys().length > 0 && this.selectedOnPage() === this.pageKeys().length);
  /** some, not all: the select-all box is then in its mixed state */
  readonly someOnPageSelected = computed(() => this.selectedOnPage() > 0 && !this.allOnPageSelected());

  /** the selected rows, in the order the page lists them */
  readonly selectedRows = computed<readonly unknown[]>(() => this.pageRows().filter((row) => this.selection().has(this.rowKey()(row))));

  readonly visibleBulkActions = computed(() => this.bulkActions().filter((action) => action.visible !== false));

  toggleRow(key: string | number, checked: boolean): void {
    this.selection.update((current) => {
      const next = new Set(current);
      if (checked) {
        next.add(key);
      } else {
        next.delete(key);
      }
      return next;
    });
  }

  /** the select-all box of the page: every row on it, or none */
  togglePage(checked: boolean): void {
    this.selection.update((current) => {
      const next = new Set(current);
      for (const key of this.pageKeys()) {
        if (checked) {
          next.add(key);
        } else {
          next.delete(key);
        }
      }
      return next;
    });
  }

  clearSelection(): void {
    this.selection.set(new Set());
  }

  /** N-04: one of the page's actions, for the rows selected now; the page confirms before anything is sent */
  runBulk(action: string): void {
    const rows = this.selectedRows();
    if (rows.length === 0) {
      return;
    }
    this.bulk.emit({ action, rows });
  }

  /** N-03: Enter or Space on the focused row; a control inside the row keeps its own keys */
  onRowKeydown(event: KeyboardEvent, row: unknown): void {
    if (!this.rowActivatable() || (event.key !== 'Enter' && event.key !== ' ')) {
      return;
    }
    if (event.target !== event.currentTarget) {
      return;
    }
    // Space would scroll the page
    event.preventDefault();
    this.rowActivate.emit({ row, element: event.currentTarget as HTMLElement | null });
  }
}

/** enum namespaces used across the app — for search label matching */
const ENUM_NAMESPACES = [
  'custody_status', 'transfer_status', 'return_request', 'review_state', 'social_case',
  'purchase_request', 'maintenance_request', 'task', 'salary', 'edit_request',
  'broadcast', 'transaction_type', 'expense_type', 'priority',
] as const;
