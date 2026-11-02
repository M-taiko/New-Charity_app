import { computed, ChangeDetectionStrategy, Component, inject, resource, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { HttpErrorResponse } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { InputNumberModule } from 'primeng/inputnumber';
import { CheckboxModule } from 'primeng/checkbox';
import { SelectModule } from 'primeng/select';
import { DialogModule } from 'primeng/dialog';
import { ToastService } from '@core/errors/toast.service';
import { PageHeaderComponent } from '@shared/ui/page-header/page-header.component';
import { StatTileComponent } from '@shared/ui/stat-tile/stat-tile.component';
import { ActionResponse, ItemResponse } from '@shared/models/api.models';

interface FlatNode {
  id: number; kind: 'category' | 'item'; name: string; code: string | null; level?: number;
  is_active: boolean; order: number; path: string; default_amount?: string | null;
  itemsCount: number; isActive: boolean;
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
 * Directions management (plan 20): the four-level tree with inline editing in
 * a drawer — create/edit/toggle per node, same rules as the Blade page.
 */
@Component({
  selector: 'app-direction-list',
  standalone: true,
  imports: [TranslatePipe, FormsModule, ButtonModule, InputTextModule, InputNumberModule, CheckboxModule, SelectModule, DialogModule, PageHeaderComponent, StatTileComponent],
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
        <div class="ledger-table__header"><span class="ledger-table__title">{{ 'direction.list.title' | translate }}</span></div>
        <table>
          <thead><tr>
            <th>{{ 'common.name' | translate }}</th><th>{{ 'direction.form.code' | translate }}</th>
            <th>{{ 'direction.list.col_level' | translate }}</th><th>{{ 'direction.list.col_items' | translate }}</th>
            <th>{{ 'direction.list.col_active' | translate }}</th><th>{{ 'common.actions' | translate }}</th>
          </tr></thead>
          <tbody>
            @for (node of flatNodes(); track node.id) {
              <tr class="ledger-table__row">
                <td [style.padding-inline-start.px]="8 + (node.level ?? 1) * 14">{{ node.name }}</td>
                <td class="u-bidi">{{ node.code ?? '—' }}</td>
                <td>{{ node.level }}</td>
                <td>{{ node.itemsCount }}</td>
                <td>
                  <span class="stamp" [class.stamp--success]="node.isActive" [class.stamp--neutral]="!node.isActive">
                    {{ (node.isActive ? 'common.true' : 'common.false') | translate }}
                  </span>
                </td>
                <td>
                  <div class="ledger-table__actions">
                    @if ((node.level ?? 1) < 4) {
                      <p-button [label]="'common.add' | translate" size="small" [text]="true" (click)="newNode(node.id, node.level === 3 ? 'item' : 'category')" />
                    }
                    <p-button [label]="'common.edit' | translate" size="small" [text]="true" (click)="editNode(node)" />
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
        }
        <div class="field">
          <label class="field__label" for="node-order">{{ 'direction.form.order' | translate }}</label>
          <p-inputnumber inputId="node-order" [(ngModel)]="form.order" name="order" styleClass="u-full" />
        </div>
        <label class="u-flex u-gap-1">
          <p-checkbox [(ngModel)]="form.is_active" name="is_active" [binary]="true" inputId="node-active" />
          {{ 'direction.form.active' | translate }}
        </label>
      </div>
      <p-footer>
        <div class="u-flex" style="justify-content: flex-end">
          <p-button [label]="'common.cancel' | translate" [text]="true" (onClick)="dialogOpen = false" />
          <p-button [label]="'common.save' | translate" (onClick)="save()" [loading]="sending()" />
        </div>
      </p-footer>
    </p-dialog>
  `,
})
export class DirectionListPage {
  private http = inject(HttpClient);
  private toast = inject(ToastService);

  dialogOpen = false;
  sending = signal(false);
  form = { id: null as number | null, kind: 'category' as 'category' | 'item', name: '', code: '', parent_id: null as number | null, order: 0, default_amount: null as number | null, is_active: true };

  data = resource<DirectionsData, unknown>({
    loader: () => firstValueFrom(this.http.get<ItemResponse<DirectionsData>>('/expense-items')).then((r) => r.data),
  });

  flatNodes(): FlatNode[] {
    const d = this.data.value();
    if (!d) {
      return [];
    }
    const cats: FlatNode[] = d.categories.map((c) => ({ ...c, itemsCount: c.items_count, isActive: c.is_active }));
    const items: FlatNode[] = d.items.map((i) => ({ ...i, itemsCount: 0, isActive: i.is_active }));
    return [...cats, ...items].sort((a, b) => a.path.localeCompare(b.path, 'ar'));
  }

  formTitle(): string {
    return this.form.kind === 'item' ? 'direction.form.new_item' : this.form.id ? 'direction.form.edit_category' : 'direction.form.new_category';
  }

  newNode(parentId: number | null, kind: 'category' | 'item'): void {
    this.form = { id: null, kind, name: '', code: '', parent_id: parentId, order: 0, default_amount: null, is_active: true };
    this.dialogOpen = true;
  }

  editNode(node: { id: number; kind: string; name: string; code: string | null; is_active: boolean; order: number; default_amount?: string | null }): void {
    this.form = {
      id: node.id,
      kind: node.kind === 'item' ? 'item' : 'category',
      name: node.name,
      code: node.code ?? '',
      parent_id: null,
      order: node.order,
      default_amount: node.default_amount ? parseFloat(node.default_amount) : null,
      is_active: node.is_active,
    };
    this.dialogOpen = true;
  }

  save(): void {
    this.sending.set(true);
    const isItem = this.form.kind === 'item';
    const base = isItem ? 'expense-items' : 'expense-categories';
    const body: Record<string, unknown> = {
      name: this.form.name,
      code: this.form.code || null,
      order: this.form.order,
      is_active: this.form.is_active,
    };
    if (isItem) {
      body.default_amount = this.form.default_amount;
    } else if (this.form.parent_id !== null) {
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
      .catch((err: HttpErrorResponse) => {
        this.sending.set(false);
        this.toast.apiError(err.error?.message ?? 'errors.generic');
      });
  }
}
