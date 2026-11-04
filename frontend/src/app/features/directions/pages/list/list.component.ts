import { ChangeDetectionStrategy, Component, computed, inject, resource, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import type { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { CheckboxModule } from 'primeng/checkbox';
import { SelectModule } from 'primeng/select';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { AsyncPageComponent, type LoadableResource } from '@shared/ui/async-page/async-page.component';
import { LedgerTableComponent, LedgerBodyDirective, LedgerColDirective, type LedgerColumn } from '@shared/ui/ledger-table/ledger-table.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { EmptyStateComponent } from '@shared/ui/empty-state/empty-state.component';
import { RowActionsComponent, type RowActionItem } from '@shared/ui/row-actions/row-actions.component';
import { FormDialogComponent } from '@shared/ui/form-dialog/form-dialog.component';
import { FieldComponent } from '@shared/ui/field/field.component';
import { ConfirmDeleteComponent } from '@shared/ui/confirm-delete/confirm-delete.component';
import type { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface FlatNode {
  id: number; kind: 'category' | 'item'; name: string; code: string | null; level?: number;
  is_active: boolean; order: number; path: string; default_amount?: string | null;
  itemsCount: number; isActive: boolean; parent_id: number | null;
}

interface CategoryRow {
  id: number; kind: 'category'; name: string; code: string | null; level: number;
  parent_id: number | null; is_active: boolean; order: number; path: string;
  children_count: number; items_count: number;
}

interface ItemRow {
  id: number; kind: 'item'; name: string; code: string | null; parent_id: number;
  is_active: boolean; order: number; default_amount: string | null; path: string;
}

interface DirectionsData {
  categories: CategoryRow[];
  items: ItemRow[];
  stats: { roots: number; total_categories: number; total_items: number; inactive: number };
}

/**
 * Directions management (plan 20, T3-30): the four-level tree with inline
 * editing — create/edit per node, item parent (expense_category_id) + order
 * editable, toggle item status, delete category/item with the server's
 * restriction messages surfaced.
 */
@Component({
  selector: 'app-direction-list',
  standalone: true,
  imports: [
    TranslatePipe, FormsModule, ButtonModule, InputTextModule, InputNumberModule, CheckboxModule,
    SelectModule, PageHeaderComponent, AsyncPageComponent, LedgerTableComponent, LedgerBodyDirective,
    LedgerColDirective, StatTileComponent, EmptyStateComponent, RowActionsComponent,
    FormDialogComponent, FieldComponent, ConfirmDeleteComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <app-page-header [title]="'direction.list.title' | translate">
      <div actions>
        <p-button [label]="'direction.list.add_root' | translate" size="small" (click)="newNode(null, 'category')" />
      </div>
    </app-page-header>

    <app-async-page [resource]="pageResource" variant="list" [empty]="isEmpty">
      @if (safeValue(); as d) {
        <div class="kpi-grid">
          <app-stat-tile [label]="'direction.list.roots'" [value]="d.stats.roots" />
          <app-stat-tile [label]="'direction.list.total_categories'" [value]="d.stats.total_categories" />
          <app-stat-tile [label]="'direction.list.total_items'" [value]="d.stats.total_items" />
          <app-stat-tile [label]="'direction.list.inactive'" [value]="d.stats.inactive" />
        </div>

        <app-ledger-table [columns]="columns" [rows]="visibleNodes()" [title]="'direction.list.title'" [emptyLabel]="'direction.list.empty'">
          <span toolbar class="ledger-table__spacer"></span>
          <div toolbar class="u-flex u-gap-2">
            <p-button [label]="'direction.list.expand_all' | translate" size="small" [text]="true" (onClick)="setAllCollapsed(false)" />
            <p-button [label]="'direction.list.collapse_all' | translate" size="small" [text]="true" (onClick)="setAllCollapsed(true)" />
          </div>
          <ng-template appLedgerBody let-row>
            <tr class="ledger-table__row">
              <td appLedgerCol="name" [style.padding-inline-start.px]="8 + (row.level ?? 1) * 14">
                <span class="u-flex u-gap-1 u-flex-wrap">
                  @if (row.kind === 'category' && (childCounts().get(row.id) ?? 0) > 0) {
                    <!-- T5-04: intentionally a raw button — this is a tree-disclosure
                         control whose aria-expanded belongs on the button itself;
                         neither p-button nor app-row-actions forwards that attribute -->
                    <button
                      type="button"
                      class="row-actions__btn"
                      [attr.aria-expanded]="!isCollapsed(row.id)"
                      [attr.aria-label]="(isCollapsed(row.id) ? 'direction.list.expand' : 'direction.list.collapse') | translate"
                      [title]="(isCollapsed(row.id) ? 'direction.list.expand' : 'direction.list.collapse') | translate"
                      (click)="toggleNode(row.id)"
                    >
                      <svg width="14" height="14" aria-hidden="true" [style.transform]="isCollapsed(row.id) ? 'none' : 'rotate(90deg)'">
                        <use href="assets/icons/sprite.svg#tabler-chevron-right" />
                      </svg>
                    </button>
                  }
                  <span>{{ row.name }}</span>
                  @if (row.kind === 'category' && isCollapsed(row.id)) {
                    <span class="stamp stamp--neutral" aria-hidden="true">+{{ childCounts().get(row.id) }}</span>
                  }
                </span>
              </td>
              <td appLedgerCol="code" class="u-bidi">{{ row.code ?? '—' }}</td>
              <td appLedgerCol="level">{{ row.level }}</td>
              <td appLedgerCol="items_count">{{ row.itemsCount }}</td>
              <td appLedgerCol="is_active">
                <span class="stamp" [class.stamp--success]="row.isActive" [class.stamp--neutral]="!row.isActive">
                  {{ (row.isActive ? 'common.true' : 'common.false') | translate }}
                </span>
              </td>
              <td appLedgerCol="actions"><app-row-actions [actions]="actionsFor(row)" /></td>
            </tr>
          </ng-template>
        </app-ledger-table>
      }
      <div empty>
        <app-empty-state [title]="'direction.list.empty' | translate" [text]="'common.no_data' | translate" />
      </div>
    </app-async-page>

    <app-form-dialog [header]="formTitle()" [submitLabel]="'common.save'" [sending]="sending()" [(visible)]="dialogOpen" (submit)="save()">
      <div class="u-column u-gap-3">
        <app-field label="direction.form.name" controlId="node-name" [required]="true">
          <input pInputText id="node-name" class="u-full" [(ngModel)]="form.name" name="name" />
        </app-field>
        <app-field label="direction.form.code" controlId="node-code">
          <input pInputText id="node-code" class="u-full u-bidi" [(ngModel)]="form.code" name="code" />
        </app-field>
        @if (form.kind === 'item') {
          <app-field label="direction.form.default_amount" controlId="node-amount">
            <p-inputnumber inputId="node-amount" [(ngModel)]="form.default_amount" name="default_amount" mode="decimal" [minFractionDigits]="2" styleClass="u-full" />
          </app-field>
          <app-field label="direction.form.parent" controlId="node-parent" [required]="true">
            <p-select appendTo="body"
              inputId="node-parent"
              [options]="categoryOptions()"
              optionLabel="path"
              optionValue="id"
              [(ngModel)]="form.parent_id"
              name="expense_category_id"
              styleClass="u-full"
            />
          </app-field>
        }
        <app-field label="direction.form.order" controlId="node-order" [required]="true">
          <p-inputnumber inputId="node-order" [(ngModel)]="form.order" name="order" [min]="1" styleClass="u-full" />
        </app-field>
        @if (form.kind === 'item') {
          <app-field label="direction.form.active" controlId="node-active">
            <p-checkbox [(ngModel)]="form.is_active" name="is_active" [binary]="true" inputId="node-active" />
          </app-field>
        }
      </div>
    </app-form-dialog>

    <!-- T3-30: the server refuses to delete a category with children/items or an item with expenses -->
    <app-confirm-delete #deleteDlg [path]="deletePath()" [entity]="deleteEntity()" (deleted)="data.reload()" />
  `,
})
export class DirectionListPage {
  private http = inject(HttpClient);
  private api = inject(ApiClient);
  private toast = inject(ToastService);

  readonly deleteDlg = viewChild.required(ConfirmDeleteComponent);

  dialogOpen = false;
  sending = signal(false);
  form = { id: null as number | null, kind: 'category' as 'category' | 'item', name: '', code: '', parent_id: null as number | null, order: 1, default_amount: null as number | null, is_active: true };

  deletePath = signal('');
  deleteEntity = signal('');

  /** the tree table (T4-21) */
  readonly columns: LedgerColumn[] = [
    { field: 'name', header: 'common.name' },
    { field: 'code', header: 'direction.form.code' },
    { field: 'level', header: 'direction.list.col_level' },
    { field: 'items_count', header: 'direction.list.col_items' },
    { field: 'is_active', header: 'direction.list.col_active' },
    { field: 'actions', header: 'common.actions' },
  ];

  /** collapsed category keys ("category:<id>") — parents whose subtree is hidden */
  readonly collapsed = signal<ReadonlySet<string>>(new Set<string>());

  data = resource<DirectionsData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<DirectionsData>>('/expense-items')).then((r) => r.data),
  });

  /** data.value() throws on an errored resource — null keeps the template alive */
  readonly safeValue = computed(() => {
    try {
      return this.data.value() ?? null;
    } catch {
      return null;
    }
  });

  /** T4-20: a non-throwing resource view — raw ResourceRef.value() throws on a failed load */
  readonly pageResource: LoadableResource = {
    value: () => this.safeValue() ?? undefined,
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

  isEmpty = (): boolean => this.safeValue() !== null && this.visibleNodes().length === 0;

  /** item-parent choices: every category, labeled with its full path */
  categoryOptions(): { id: number; path: string }[] {
    const d = this.safeValue();
    return (d?.categories ?? []).map((c) => ({ id: c.id, path: c.path }));
  }

  /** every node (categories + items) in path order */
  readonly flatNodes = computed<FlatNode[]>(() => {
    const d = this.safeValue();
    if (!d) {
      return [];
    }
    const cats: FlatNode[] = d.categories.map((c) => ({ ...c, itemsCount: c.items_count, isActive: c.is_active }));
    const items: FlatNode[] = d.items.map((i) => ({ ...i, itemsCount: 0, isActive: i.is_active }));
    return [...cats, ...items].sort((a, b) => a.path.localeCompare(b.path, 'ar'));
  });

  /** direct-children count per category id (child categories + items) */
  readonly childCounts = computed<Map<number, number>>(() => {
    const counts = new Map<number, number>();
    for (const n of this.flatNodes()) {
      if (n.parent_id !== null) {
        counts.set(n.parent_id, (counts.get(n.parent_id) ?? 0) + 1);
      }
    }
    return counts;
  });

  /** nodes the user sees: everything whose ancestors are all expanded */
  readonly visibleNodes = computed<FlatNode[]>(() => {
    const collapsed = this.collapsed();
    if (collapsed.size === 0) {
      return this.flatNodes();
    }
    const categoriesById = new Map((this.safeValue()?.categories ?? []).map((c) => [c.id, c]));
    const isHidden = (n: FlatNode): boolean => {
      let parentId = n.parent_id ?? null;
      while (parentId !== null) {
        if (collapsed.has(`category:${parentId}`)) {
          return true;
        }
        parentId = categoriesById.get(parentId)?.parent_id ?? null;
      }
      return false;
    };
    return this.flatNodes().filter((n) => !isHidden(n));
  });

  isCollapsed(categoryId: number): boolean {
    return this.collapsed().has(`category:${categoryId}`);
  }

  /** collapse/expand one parent's subtree */
  toggleNode(categoryId: number): void {
    const key = `category:${categoryId}`;
    const next = new Set(this.collapsed());
    if (next.has(key)) {
      next.delete(key);
    } else {
      next.add(key);
    }
    this.collapsed.set(next);
  }

  /** collapse/expand every parent that has children */
  setAllCollapsed(collapsed: boolean): void {
    if (!collapsed) {
      this.collapsed.set(new Set<string>());
      return;
    }
    const keys = this.flatNodes()
      .filter((n) => n.kind === 'category' && (this.childCounts().get(n.id) ?? 0) > 0)
      .map((n) => `category:${n.id}`);
    this.collapsed.set(new Set(keys));
  }

  formTitle(): string {
    return this.form.kind === 'item'
      ? (this.form.id ? 'direction.form.edit_item' : 'direction.form.new_item')
      : (this.form.id ? 'direction.form.edit_category' : 'direction.form.new_category');
  }

  /** T4-24: add child / edit / toggle item status / delete */
  actionsFor(node: FlatNode): RowActionItem[] {
    return [
      {
        icon: 'plus',
        label: 'common.add',
        handler: () => { this.newNode(node.id, node.level === 3 ? 'item' : 'category'); },
        visible: (node.level ?? 1) < 4,
      },
      { icon: 'pencil', label: 'common.edit', handler: () => { this.editNode(node); } },
      { icon: 'check', label: 'direction.list.toggle_active', handler: () => { this.toggleStatus(node); }, visible: node.kind === 'item' },
      { icon: 'trash', label: 'common.delete', severity: 'danger', handler: () => { this.openDelete(node); } },
    ];
  }

  newNode(parentId: number | null, kind: 'category' | 'item'): void {
    this.form = { id: null, kind, name: '', code: '', parent_id: parentId, order: 1, default_amount: null, is_active: true };
    this.dialogOpen = true;
  }

  editNode(node: { id: number; kind: string; name: string; code: string | null; is_active: boolean; order: number; default_amount?: string | null; parent_id?: number | null }): void {
    this.form = {
      id: node.id,
      kind: node.kind === 'item' ? 'item' : 'category',
      name: node.name,
      code: node.code ?? '',
      parent_id: node.parent_id ?? null,
      order: node.order,
      default_amount: node.default_amount ? parseFloat(node.default_amount) : null,
      is_active: node.is_active,
    };
    this.dialogOpen = true;
  }

  /** POST /expense-items/{id}/toggle-status — items only (T3-30) */
  toggleStatus(node: { id: number }): void {
    void firstValueFrom(this.api.action(`/expense-items/${node.id}/toggle-status`))
      .then((res) => {
        this.toast.success(res.message);
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic', (err as HttpErrorResponse).error?.code);
      });
  }

  openDelete(node: { id: number; kind: string; name: string }): void {
    const base = node.kind === 'item' ? 'expense-items' : 'expense-categories';
    this.deletePath.set(`/${base}/${node.id}/delete`);
    this.deleteEntity.set(node.name);
    this.deleteDlg().open();
  }

  save(): void {
    this.sending.set(true);
    const isItem = this.form.kind === 'item';
    const base = isItem ? 'expense-items' : 'expense-categories';
    const body: Record<string, unknown> = {
      name: this.form.name,
      code: this.form.code || null,
      order: this.form.order,
    };
    if (isItem) {
      // the API requires expense_category_id on item create AND update
      body.expense_category_id = this.form.parent_id;
      body.default_amount = this.form.default_amount;
      body.is_active = this.form.is_active;
    } else if (this.form.parent_id !== null && this.form.id === null) {
      body.parent_id = this.form.parent_id;
    }
    const id = this.form.id;
    const request$ = id
      ? this.http.post<ActionResponse>(`/${base}/${id}/update`, body)
      : this.http.post<ActionResponse>(`/${base}`, body);
    void firstValueFrom(request$)
      .then((res) => {
        this.sending.set(false);
        this.toast.success(res.message);
        this.dialogOpen = false;
        this.data.reload();
      })
      .catch((err: unknown) => {
        this.sending.set(false);
        this.toast.apiError((err as HttpErrorResponse).error?.message ?? 'errors.generic');
      });
  }
}
