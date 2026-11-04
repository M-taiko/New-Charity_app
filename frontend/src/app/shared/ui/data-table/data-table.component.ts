import {
  ChangeDetectionStrategy,
  Component,
  Directive,
  ElementRef,
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
import { ActivatedRoute } from '@angular/router';
import { TableModule } from 'primeng/table';
import { InputTextModule } from 'primeng/inputtext';
import { ButtonModule } from 'primeng/button';
import { LocalStorageService } from '@core/storage/local-storage.service';

export interface TableColumn {
  field: string;
  header: string; // i18n key
  numeric?: boolean;
  width?: string;
  sortable?: boolean;
  /** dot-path used for client-side search when the displayed value is nested (e.g. "agent.name") */
  searchField?: string;
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
 * Marks a body <td> with its column field: <td appTableCol="agent">.
 * The cell places itself at the column's saved position on creation and
 * re-places itself when the user reorders columns — so data always moves
 * with its header, on first render AND after row re-creation.
 */
@Directive({ selector: 'td[appTableCol]' })
export class TableColDirective {
  private table = inject(DataTableComponent);
  private el = inject<ElementRef<HTMLTableCellElement>>(ElementRef);

  appTableCol = input.required<string>();

  private placer = effect(() => {
    this.table.placeCell(this.el.nativeElement, this.appTableCol());
  });
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
  imports: [TranslatePipe, TableModule, NgTemplateOutlet, FormsModule, InputTextModule, ButtonModule],
  template: `
    <div class="ledger-table" [class.ledger-table--compact]="density() === 'compact'" [attr.aria-busy]="loading() || null">
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
          <span class="ledger-table__spacer"></span>
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
        [value]="filteredRows()"
        [lazy]="true"
        (onLazyLoad)="onLazyLoad($event)"
        (onColReorder)="onColReorder($event)"
        (onColResize)="onColResize($event)"
        [rows]="perPage()"
        [totalRecords]="total()"
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
            @for (col of orderedColumns(); track col.field; let i = $index) {
              <th
                [pSortableColumn]="col.sortable === false ? undefined : col.field"
                pResizableColumn
                pReorderableColumn
                [style.width]="columnWidth(col.field)"
                [class.ledger-table__cell-num]="col.numeric"
                [attr.data-field]="col.field"
                scope="col"
                [attr.aria-sort]="ariaSort(col.field)"
              >
                <span class="ledger-table__header-text">{{ col.header | translate }}</span>
                @if (col.sortable !== false) {
                  <p-sortIcon [field]="col.field" />
                }
              </th>
            }
          </tr>
        </ng-template>

        <ng-template pTemplate="body" let-row let-index="rowIndex">
          <ng-container
            [ngTemplateOutlet]="body()?.template ?? null"
            [ngTemplateOutletContext]="{ $implicit: row, index: index, columns: orderedColumns() }"
          />
        </ng-template>

        <ng-template pTemplate="emptymessage">
          <tr>
            <td [colSpan]="orderedColumns().length">
              @if (searchQuery() !== '' && filteredRows().length === 0) {
                <span class="u-muted">{{ 'common.no_results' | translate }}</span>
              } @else {
                <ng-content select="[empty]" />
              }
            </td>
          </tr>
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
  private translate = inject(TranslateService);
  private route = inject(ActivatedRoute);
  readonly body = contentChild(TableBodyDirective);

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

  pageChange = output<TablePageEvent>();

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

  /**
   * Places a body cell at its column's position (used by TableColDirective).
   * Each cell places ITSELF when created and whenever the saved column order
   * changes — no bulk DOM permutation that could race with PrimeNG's row
   * re-rendering (pagination/sort/filter recreate rows in template order).
   */
  placeCell(td: HTMLTableCellElement, field: string): void {
    const ordered = this.orderedColumns();
    const targetIdx = ordered.findIndex((c) => c.field === field);
    if (targetIdx < 0) return;
    const tr = td.parentElement;
    if (!tr) return;

    const cells = [...tr.children].filter((c) => c.tagName === 'TD');
    const currentIdx = cells.indexOf(td);
    if (currentIdx === -1 || currentIdx === targetIdx) return;

    // moving down needs the slot AFTER the target; moving up lands on it
    const ref = currentIdx < targetIdx ? cells[targetIdx + 1] : cells[targetIdx];
    tr.insertBefore(td, ref);
  }

  /** rows filtered client-side — matches what the user SEES: raw values,
   *  comma-formatted money, dd/mm/yyyy dates, and translated enum labels */
  filteredRows = computed(() => {
    const q = this.searchQuery().trim().toLowerCase();
    const rows = this.rows();
    if (!q || !Array.isArray(rows)) return rows;
    const qPlain = q.replace(/,/g, '');
    const fields = this.orderedColumns().map((c) => c.searchField ?? c.field);
    return rows.filter((row) =>
      fields.some((field) => this.cellMatches(this.fieldValue(row, field), q, qPlain)),
    );
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

  onSearch(term: string): void {
    // purely client-side: filteredRows recomputes, no server reload
    this.searchQuery.set(term);
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
    const sortField = Array.isArray(event.sortField) ? event.sortField[0] : event.sortField;
    let sort = '';
    if (sortField) {
      sort = (event.sortOrder === -1 ? '-' : '') + sortField;
    }

    // persist sort + page size
    this.saveState({
      sortField: sortField ?? '',
      sortDir: event.sortOrder ?? 0,
      rows,
    });

    this.pageChange.emit({
      page: Math.floor(first / Math.max(rows, 1)) + 1,
      per_page: rows,
      sort,
    });
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
