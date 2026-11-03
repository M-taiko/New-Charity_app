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
import { DialogModule } from 'primeng/dialog';
import { ToastService } from '@core/errors/toast.service';
import { ApiClient } from '@core/http/api-client';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
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
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, InputNumberModule, CheckboxModule, SelectModule, DialogModule, PageHeaderComponent, StatTileComponent, ConfirmDeleteComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (data.value(); as d) {
      <app-page-header [title]="'direction.list.title' | translate">
        <div actions>
          <p-button [label]="'direction.list.add_root' | translate" size="small" (click)="newNode(null, 'category')" />
        </div>
      </app-page-header>

      <div class="kpi-grid">
        <app-stat-tile [label]="'direction.list.roots'" [value]="d.stats.roots" />
        <app-stat-tile [label]="'direction.list.total_categories'" [value]="d.stats.total_categories" />
        <app-stat-tile [label]="'direction.list.total_items'" [value]="d.stats.total_items" />
        <app-stat-tile [label]="'direction.list.inactive'" [value]="d.stats.inactive" />
      </div>

      <div class="ledger-table">
        <div class="ledger-table__header">
          <span class="ledger-table__title">{{ 'direction.list.title' | translate }}</span>
          <span class="ledger-table__spacer"></span>
          <p-button [label]="'direction.list.expand_all' | translate" size="small" [text]="true" (onClick)="setAllCollapsed(false)" />
          <p-button [label]="'direction.list.collapse_all' | translate" size="small" [text]="true" (onClick)="setAllCollapsed(true)" />
        </div>
        <table>
          <thead><tr>
            <th>{{ 'common.name' | translate }}</th><th>{{ 'direction.form.code' | translate }}</th>
            <th>{{ 'direction.list.col_level' | translate }}</th><th>{{ 'direction.list.col_items' | translate }}</th>
            <th>{{ 'direction.list.col_active' | translate }}</th><th>{{ 'common.actions' | translate }}</th>
          </tr></thead>
          <tbody>
            @for (node of visibleNodes(); track node.id) {
              <tr class="ledger-table__row">
                <td [style.padding-inline-start.px]="8 + (node.level ?? 1) * 14">
                  @if (node.kind === 'category' && (childCounts().get(node.id) ?? 0) > 0) {
                    <button
                      type="button"
                      class="row-actions__btn"
                      style="width: 22px; height: 22px; margin-inline-end: var(--space-1); vertical-align: middle"
                      [attr.aria-expanded]="!isCollapsed(node.id)"
                      [attr.aria-label]="(isCollapsed(node.id) ? 'direction.list.expand' : 'direction.list.collapse') | translate"
                      [title]="(isCollapsed(node.id) ? 'direction.list.expand' : 'direction.list.collapse') | translate"
                      (click)="toggleNode(node.id)"
                    >
                      <svg width="14" height="14" aria-hidden="true" [style.transform]="isCollapsed(node.id) ? 'none' : 'rotate(90deg)'">
                        <use href="assets/icons/sprite.svg#tabler-chevron-right" />
                      </svg>
                    </button>
                  }
                  {{ node.name }}
                </td>
                <td class="u-bidi">{{ node.code ?? '—' }}</td>
                <td>{{ node.level }}</td>
                <td>{{ node.itemsCount }}</td>
                <td>
                  <span class="stamp" [class.stamp--success]="node.isActive" [class.stamp--neutral]="!node.isActive">
                    {{ (node.isActive ? 'common.true' : 'common.false') | translate }}
                  </span>
                </td>
                <td>
                  <div class="row-actions">
                    @if ((node.level ?? 1) < 4) {
                      <button type="button" class="row-actions__btn" [title]="'common.add' | translate" [attr.aria-label]="'common.add' | translate" (click)="newNode(node.id, node.level === 3 ? 'item' : 'category')">
                        <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-plus" /></svg>
                      </button>
                    }
                    <button type="button" class="row-actions__btn" [title]="'common.edit' | translate" [attr.aria-label]="'common.edit' | translate" (click)="editNode(node)">
                      <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-pencil" /></svg>
                    </button>
                    @if (node.kind === 'item') {
                      <button type="button" class="row-actions__btn" [title]="'direction.list.toggle_active' | translate" [attr.aria-label]="'direction.list.toggle_active' | translate" (click)="toggleStatus(node)">
                        <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-check" /></svg>
                      </button>
                    }
                    <button type="button" class="row-actions__btn row-actions__btn--danger" [title]="'common.delete' | translate" [attr.aria-label]="'common.delete' | translate" (click)="openDelete(node)">
                      <svg width="16" height="16" aria-hidden="true"><use href="assets/icons/sprite.svg#tabler-trash" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="u-muted">{{ 'direction.list.empty' | translate }}</td></tr>
            }
          </tbody>
        </table>
      </div>
    }

    <p-dialog appendTo="body" [(visible)]="dialogOpen" [header]="formTitle() | translate" [modal]="true" [style]="{ width: 'min(460px, 94vw)' }">
      <div class="u-column u-gap-3">
        <div class="field">
          <label class="field__label" for="node-name">{{ 'direction.form.name' | translate }} *</label>
          <input pInputText id="node-name" [(ngModel)]="form.name" name="name" style="width: 100%" />
        </div>
        <div class="field">
          <label class="field__label" for="node-code">{{ 'direction.form.code' | translate }}</label>
          <input pInputText id="node-code" [(ngModel)]="form.code" name="code" class="u-bidi" style="width: 100%" />
        </div>
        @if (form.kind === 'item') {
          <div class="field">
            <label class="field__label" for="node-amount">{{ 'direction.form.default_amount' | translate }}</label>
            <p-inputnumber inputId="node-amount" [(ngModel)]="form.default_amount" name="default_amount" mode="decimal" [minFractionDigits]="2" styleClass="u-full" />
          </div>
          <div class="field">
            <label class="field__label" for="node-parent">{{ 'direction.form.parent' | translate }} *</label>
            <p-select appendTo="body"
              inputId="node-parent"
              [options]="categoryOptions()"
              optionLabel="path"
              optionValue="id"
              [(ngModel)]="form.parent_id"
              name="expense_category_id"
              styleClass="u-full"
            />
          </div>
        }
        <div class="field">
          <label class="field__label" for="node-order">{{ 'direction.form.order' | translate }} *</label>
          <p-inputnumber inputId="node-order" [(ngModel)]="form.order" name="order" [min]="1" styleClass="u-full" />
        </div>
        @if (form.kind === 'item') {
          <label class="u-flex u-gap-1">
            <p-checkbox [(ngModel)]="form.is_active" name="is_active" [binary]="true" inputId="node-active" />
            {{ 'direction.form.active' | translate }}
          </label>
        }
      </div>
      <p-footer>
        <div class="u-flex" style="justify-content: flex-end">
          <p-button [label]="'common.cancel' | translate" [text]="true" (onClick)="dialogOpen = false" />
          <p-button [label]="'common.save' | translate" (onClick)="save()" [loading]="sending()" />
        </div>
      </p-footer>
    </p-dialog>

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

  /** collapsed category keys ("category:<id>") — parents whose subtree is hidden */
  readonly collapsed = signal<ReadonlySet<string>>(new Set<string>());

  data = resource<DirectionsData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<DirectionsData>>('/expense-items')).then((r) => r.data),
  });

  /** item-parent choices: every category, labeled with its full path */
  categoryOptions(): { id: number; path: string }[] {
    const d = this.data.value();
    return (d?.categories ?? []).map((c) => ({ id: c.id, path: c.path }));
  }

  /** every node (categories + items) in path order */
  readonly flatNodes = computed<FlatNode[]>(() => {
    const d = this.data.value();
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
    const categoriesById = new Map((this.data.value()?.categories ?? []).map((c) => [c.id, c]));
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
