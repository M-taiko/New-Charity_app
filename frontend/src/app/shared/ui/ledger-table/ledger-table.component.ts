import { ChangeDetectionStrategy, Component, Directive, TemplateRef, computed, contentChild, inject, input, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';

export interface LedgerColumn {
  /** column id — cells reference it via appLedgerCol */
  field: string;
  /** i18n key */
  header: string;
  numeric?: boolean;
  /** enable client-side sorting on this column (default: not sortable) */
  sortable?: boolean;
}

/** Marks the projected per-row template: <ng-template appLedgerBody let-row> */
@Directive({ selector: '[appLedgerBody]' })
export class LedgerBodyDirective {
  template = inject<TemplateRef<unknown>>(TemplateRef);
}

/**
 * Marks a body <td> with its column field: <td appLedgerCol="amount">.
 * Applies the numeric alignment class from the column definition.
 */
@Directive({
  selector: 'td[appLedgerCol]',
  host: { '[class.ledger-table__cell-num]': 'numeric()' },
})
export class LedgerColDirective {
  private table = inject(LedgerTableComponent);

  appLedgerCol = input.required<string>();

  readonly numeric = computed(() =>
    this.table.columns().find((c) => c.field === this.appLedgerCol())?.numeric === true);
}

/**
 * The one static table (T4-21) for detail pages, reports and secondary lists:
 * content-projected rows, built-in empty state, horizontal scroll, optional
 * client-side sort and a projected footer for totals. Interactive lists keep
 * using app-data-table (pagination, column drag, saved state).
 */
@Component({
  selector: 'app-ledger-table',
  standalone: true,
  imports: [TranslatePipe, NgTemplateOutlet],
  template: `
    <div class="ledger-table ledger-table--static">
      @if (title() !== '') {
        <div class="ledger-table__header">
          <span class="ledger-table__title">{{ title() | translate }}</span>
          <ng-content select="[toolbar]" />
        </div>
      }
      <div class="ledger-table__scroll">
        <table>
          <thead>
            <tr>
              @for (col of columns(); track col.field) {
                <th scope="col" [class.ledger-table__cell-num]="col.numeric === true">
                  @if (col.sortable === true) {
                    <button
                      type="button"
                      class="ledger-table__sort"
                      (click)="toggleSort(col.field)"
                      [attr.aria-sort]="ariaSort(col.field)"
                    >
                      <span>{{ col.header | translate }}</span>
                      <svg width="12" height="12" aria-hidden="true">
                        <use [attr.href]="sortIcon(col.field)" />
                      </svg>
                    </button>
                  } @else {
                    <span>{{ col.header | translate }}</span>
                  }
                </th>
              }
            </tr>
          </thead>
          <tbody>
            @for (row of sortedRows(); track $index; let i = $index) {
              <ng-container
                [ngTemplateOutlet]="body()?.template ?? null"
                [ngTemplateOutletContext]="{ $implicit: row, index: i }"
              />
            } @empty {
              <tr>
                <td [colSpan]="columns().length">
                  <ng-content select="[empty]" />
                  @if (!hasEmptySlot()) {
                    <span class="u-muted">{{ emptyLabel() | translate }}</span>
                  }
                </td>
              </tr>
            }
          </tbody>
          <ng-content select="[footer]" />
        </table>
      </div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LedgerTableComponent {
  columns = input.required<LedgerColumn[]>();
  rows = input.required<readonly unknown[]>();
  title = input('');
  emptyLabel = input('common.no_data');

  readonly body = contentChild(LedgerBodyDirective);
  private readonly emptySlot = contentChild(LedgerEmptyDirective);

  hasEmptySlot = computed(() => this.emptySlot() !== undefined);

  sortField = signal('');
  sortDir = signal<1 | -1>(1);

  sortedRows = computed(() => {
    const field = this.sortField();
    if (field === '') {
      return this.rows();
    }
    const dir = this.sortDir();
    const key = (row: unknown) => fieldValue(row, field);
    return [...this.rows()].sort((a, b) => compare(key(a), key(b)) * dir);
  });

  toggleSort(field: string): void {
    if (this.sortField() === field) {
      this.sortDir.set(this.sortDir() === 1 ? -1 : 1);
    } else {
      this.sortField.set(field);
      this.sortDir.set(1);
    }
  }

  ariaSort(field: string): 'ascending' | 'descending' | 'none' {
    if (this.sortField() !== field) {
      return 'none';
    }
    return this.sortDir() === 1 ? 'ascending' : 'descending';
  }

  sortIcon(field: string): string {
    if (this.sortField() !== field) {
      return 'assets/icons/sprite.svg#tabler-arrows-sort';
    }
    return this.sortDir() === 1
      ? 'assets/icons/sprite.svg#tabler-sort-ascending'
      : 'assets/icons/sprite.svg#tabler-sort-descending';
  }
}

/** Marks the projected empty state: <div empty>…</div> */
@Directive({ selector: '[empty]' })
export class LedgerEmptyDirective {}

/** dot-path lookup used for client-side sorting */
function fieldValue(row: unknown, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, part) => {
    if (acc !== null && acc !== undefined && typeof acc === 'object') {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, row);
}

/** numeric-aware ascending comparison */
function compare(a: unknown, b: unknown): number {
  const na = typeof a === 'number' ? a : Number(String(a ?? '').replace(/,/g, ''));
  const nb = typeof b === 'number' ? b : Number(String(b ?? '').replace(/,/g, ''));
  if (!Number.isNaN(na) && !Number.isNaN(nb) && String(a ?? '') !== '' && String(b ?? '') !== '') {
    return na === nb ? 0 : na < nb ? -1 : 1;
  }
  return String(a ?? '').localeCompare(String(b ?? ''), 'ar');
}
