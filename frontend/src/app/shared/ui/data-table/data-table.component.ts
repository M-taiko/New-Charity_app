import {
  ChangeDetectionStrategy,
  Component,
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
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ActivatedRoute, Router } from '@angular/router';
import { TableModule } from 'primeng/table';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { firstValueFrom } from 'rxjs';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { ApiClient } from '@core/http/api-client';
import { ToastService } from '@core/errors/toast.service';
import { TableFiltersComponent, FilterCustomDirective } from '../table-filters/table-filters.component';
import {
  defaultFilterValues,
  deriveFilters,
  filterParams,
  filterRows,
  fieldValue,
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

/** Per-user table state persisted in localStorage (plan 19.4, 21.2). */
interface TableState {
  columnOrder?: string[];
  columnWidths?: Record<string, string>;
  tableWidth?: string;
  sortField?: string;
  sortDir?: number;
  rows?: number;
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
  imports: [TranslatePipe, TableModule, NgTemplateOutlet, FormsModule, InputTextModule, ButtonModule, TableFiltersComponent, MenuModule],
  template: `
    <div class="ledger-table" [class.print-no-totals]="!printIncludeTotals()" [class.print-landscape]="printOrientation() === 'landscape'"
         [class.ledger-table--compact]="density() === 'compact'" [attr.aria-busy]="loading() || null">
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
            <app-table-filters [defs]="effectiveFilters()" [customTemplates]="customFilterTemplates()" [rows]="rows()"
                               [(values)]="filterValues" (applied)="onFiltersApplied($event)" />
          }
          <span class="ledger-table__spacer"></span>
          <!-- T6-71/T7-06: Export (Excel / PDF / CSV) - derived from the list URL,
               current filters/sort/search ride the query string -->
          @if (effectiveExportPath() !== '') {
            <p-button [label]="'common.export' | translate" size="small" severity="secondary" [outlined]="true" (onClick)="exportMenu.toggle($event)" />
            <p-menu #exportMenu appendTo="body" [model]="exportItems()" popup="true" />
          }
          @if (printable()) {
            <!-- T6-70.7: a small Print menu — orientation toggle, all rows, then window.print() -->
            <button type="button" class="topbar__icon-btn" (click)="printMenu.toggle($event)" [attr.aria-label]="'common.print' | translate" [title]="'common.print' | translate">
              <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-printer" /></svg>
            </button>
            <p-menu #printMenu appendTo="body" [model]="printItems()" popup="true" />
          }
          <button
            type="button"
            class="topbar__icon-btn"
            (click)="toggleDensity()"
            [attr.aria-label]="'common.density' | translate"
            [title]="'common.density' | translate"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use href="assets/icons/sprite.svg#tabler-menu-2" />
            </svg>
          </button>
          <button
            type="button"
            class="topbar__icon-btn"
            (click)="resetState()"
            [attr.aria-label]="'common.reset' | translate"
            [title]="'common.reset' | translate"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use href="assets/icons/sprite.svg#tabler-filter" />
            </svg>
          </button>
          <ng-content select="[toolbar]" />
        </div>
      }

      <p-table
        [value]="displayRows()"
        [lazy]="mode() === 'server'"
        (onLazyLoad)="onLazyLoad($event)"
        (onColReorder)="onColReorder($event)"
        (onColResize)="onColResize($event)"
        [rows]="perPage()"
        [totalRecords]="mode() === 'static' ? staticSorted().length : total()"
        [paginator]="true"
        [rowsPerPageOptions]="[10, 25, 50, 100]"
        [paginatorDropdownAppendTo]="'body'"
        [showCurrentPageReport]="true"
        [currentPageReportTemplate]="'common.table_report' | translate"
        [first]="first()"
        [styleClass]="density() === 'compact' ? 'p-datatable-sm' : ''"
        [resizableColumns]="true"
        [columnResizeMode]="'expand'"
        [reorderableColumns]="true"
        [sortMode]="'single'"
        [sortField]="initialState.sortField || ''"
        [sortOrder]="initialState.sortDir || 1"
        [tableStyle]="tableStyle()"
        [scrollable]="false"
      >
        <ng-template pTemplate="header">
          <tr>
            @for (col of headerColumns(); track col.field; let i = $index) {
              <th
                (dblclick)="resetColumnWidth(col.field)"
                [pSortableColumn]="isSortable(col) ? sortFieldOf(col) : undefined"
                pResizableColumn
                pReorderableColumn
                [pResizableColumnDisabled]="col.pinned != null"
                [pReorderableColumnDisabled]="col.pinned != null"
                [style.width]="columnWidth(col.field)"
                [style.minWidth.px]="col.minWidth ?? 80"
                [class.ledger-table__cell-num]="col.numeric"
                [attr.data-field]="col.field"
                scope="col"
                [attr.aria-sort]="ariaSort(col.field)"
              >
                <span class="ledger-table__header-text">{{ col.header | translate }}</span>
                @if (isSortable(col)) {
                  <p-sortIcon [field]="sortFieldOf(col)" />
                }
              </th>
            }
          </tr>
        </ng-template>

        <ng-template pTemplate="body" let-row let-index="rowIndex">
          @if (cellTemplates().size > 0) {
            <!-- T6-22: cells rendered from the ordered column list — reordering
                 always carries header + data + totals together -->
            @for (col of headerColumns(); track col.field) {
              <td [class.ledger-table__cell-num]="col.numeric">
                <ng-container
                  [ngTemplateOutlet]="cellTemplates().get(col.field) ?? null"
                  [ngTemplateOutletContext]="{ $implicit: row, index: index }"
                />
              </td>
            }
          } @else {
            <ng-container
              [ngTemplateOutlet]="body()?.template ?? null"
              [ngTemplateOutletContext]="{ $implicit: row, index: index, columns: orderedColumns() }"
            />
          }
        </ng-template>

        <ng-template pTemplate="emptymessage">
          <tr>
            <td [colSpan]="orderedColumns().length">
              @if (searchQuery() !== '' && filteredRows().length === 0) {
                <span class="u-muted">{{ 'common.no_results' | translate }}</span>
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
            <td [colSpan]="orderedColumns().length">
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
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  readonly body = contentChild(TableBodyDirective);
  readonly cells = contentChildren(TableCellDirective);
  readonly footerCells = contentChildren(TableFooterCellDirective);
  readonly filterCustoms = contentChildren(FilterCustomDirective);

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

  pageChange = output<TablePageEvent>();
  /** T6-30: the full query in server mode — page forwards it to the API as page/per_page/sort/q/filter[key] */
  queryChange = output<TableQuery>();

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
          sort: (saved.sortDir === -1 ? '-' : '') + String(savedSortField),
        });
      }
    });
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

  /** Columns reordered + widths restored from saved state */
  orderedColumns = computed(() => {
    const input = this.columns();
    if (!Array.isArray(input) || input.length === 0) return [];
    const cols = [...input];
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
    return [...rest, ...pinned];
  });

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

  /** aria-sort for the focused column header */
  ariaSort(field: string): 'ascending' | 'descending' | 'none' {
    if (this.state().sortField !== field) return 'none';
    return this.state().sortDir === -1 ? 'descending' : 'ascending';
  }

  columnWidth(field: string): string | null {
    return this.state().columnWidths?.[field] ?? null;
  }

  /** restored table width (expand resize mode grows the table past 100%) */
  tableStyle(): { width: string } | undefined {
    const w = this.state().tableWidth;
    return w ? { width: w } : undefined;
  }

  summaryEntries(): { label: string; value: string }[] {
    return Object.entries(this.summary()).map(([label, value]) => ({ label, value }));
  }

  /** T6-71: the export menu - downloads through the authenticated client as a blob */
  exportItems = computed<MenuItem[]>(() => [
    { label: this.translate.instant('common.export_excel'), command: () => void this.downloadExport('xlsx') },
    { label: this.translate.instant('common.export_pdf'), command: () => void this.downloadExport('pdf') },
    { label: this.translate.instant('common.export_csv'), command: () => void this.downloadExport('csv') },
  ]);

  /** T7-06: GET {listUrl}/export unless the page set an explicit exportPath */
  readonly effectiveExportPath = computed(() => {
    const explicit = this.exportPath();
    if (explicit !== '') {
      return explicit;
    }
    const list = this.listUrl();
    return list !== '' ? list.replace(/\/+$/, '') + '/export' : '';
  });

  /**
   * T7-06: the export replays the table's CURRENT query — format + the page's
   * own params + (server mode) the live sort/search/filter[…] values, so the
   * file always matches what is on screen.
   */
  private exportParams(format: 'xlsx' | 'pdf' | 'csv'): Record<string, string> {
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

  async downloadExport(format: 'xlsx' | 'pdf' | 'csv'): Promise<void> {
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
      link.download = 'export.' + format;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      this.toast.success(this.translate.instant('common.export_done'));
    } catch (err) {
      this.toast.apiError((err as { error?: { message?: string } })?.error?.message ?? 'errors.generic');
    }
  }

  /** T6-70: print the current view - print styles handle A4 layout + all rows */
  printTable(orientation: 'portrait' | 'landscape' = 'portrait', allRows = false): void {
    this.printOrientation.set(orientation);
    this.printingAllRows.set(allRows);
    // let the orientation class + row expansion apply before the dialog opens
    setTimeout(() => { window.print(); }, 50);
  }

  readonly printOrientation = signal<'portrait' | 'landscape'>('portrait');
  readonly printingAllRows = signal(false);

  /** the Print menu: portrait / landscape, and (static mode) all rows vs page */
  /** T6-70: include/exclude sections — org header (body-wide) and the totals */
  readonly printIncludeHeader = signal(true);
  readonly printIncludeTotals = signal(true);

  /** the Print menu: orientation, include/exclude sections, all rows, print */
  printItems = computed<MenuItem[]>(() => {
    const items: MenuItem[] = [
      { label: this.translate.instant('common.print_portrait'), command: () => { this.printTable('portrait'); } },
      { label: this.translate.instant('common.print_landscape'), command: () => { this.printTable('landscape'); } },
      {
        label: this.translate.instant(this.printIncludeHeader() ? 'common.print_omit_header' : 'common.print_include_header'),
        command: () => {
          this.printIncludeHeader.set(!this.printIncludeHeader());
          document.body.classList.toggle('print-no-org-header', !this.printIncludeHeader());
        },
      },
      {
        label: this.translate.instant(this.printIncludeTotals() ? 'common.print_omit_totals' : 'common.print_include_totals'),
        command: () => { this.printIncludeTotals.set(!this.printIncludeTotals()); },
      },
    ];
    if (this.mode() === 'static') {
      items.push({
        label: this.translate.instant(this.printingAllRows() ? 'common.print_page_only' : 'common.print_all_rows'),
        command: () => { this.printTable(this.printOrientation(), !this.printingAllRows()); },
      });
    }
    items.push({ label: this.translate.instant('common.print'), command: () => { this.printTable(this.printOrientation()); } });
    return items;
  });

  /** static mode: printing all rows shows every filtered row (capped notice past the export cap) */
  readonly printCapNotice = computed(() => {
    if (!this.printingAllRows() || this.mode() !== 'static') {
      return '';
    }
    return this.staticSorted().length > 2000
      ? this.translate.instant('common.print_cap_notice', { count: 2000 })
      : '';
  });

  onSearch(term: string): void {
    this.searchQuery.set(term);
    if (this.mode() === 'static') {
      this.staticPage.set(0);
    } else {
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

  private readonly filterDefsEffect = effect(() => {
    const defs = this.effectiveFilters();
    if (defs.length === 0 || this.filtersInitialised) {
      return;
    }
    this.filtersInitialised = true;
    // restore from ?filter[...]=… — links and refresh reproduce the view
    const restored = this.readFiltersFromUrl(defs);
    this.filterValues.set(restored ?? defaultFilterValues(defs));
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
    if (this.mode() === 'static') {
      this.staticPage.set(0);
    } else {
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
}

/** enum namespaces used across the app — for search label matching */
const ENUM_NAMESPACES = [
  'custody_status', 'transfer_status', 'return_request', 'review_state', 'social_case',
  'purchase_request', 'maintenance_request', 'task', 'salary', 'edit_request',
  'broadcast', 'transaction_type', 'expense_type', 'priority',
] as const;
