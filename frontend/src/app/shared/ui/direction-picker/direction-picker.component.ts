import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { TranslatePipe } from '@ngx-translate/core';
import { SelectModule } from 'primeng/select';
import { DirectionItem, DirectionNode } from '@shared/models/api.models';

export interface DirectionSelection {
  category_id: number | null;
  item_id: number | null;
  /** true only when a final item is chosen (mandatory to the last level, T30) */
  complete: boolean;
}

/**
 * Cascading four-level direction picker (plan 11.6, T30/T31). Selection is
 * mandatory down to the last level (the item). Emits on every level change.
 */
@Component({
  selector: 'app-direction-picker',
  standalone: true,
  imports: [TranslatePipe, SelectModule, FormsModule],
  template: `
    <div class="u-column u-gap-2 u-full" role="group" [attr.aria-label]="'direction.picker.group' | translate">
      <div>
        <label class="field__label" [for]="idBase + '-l1'">
          {{ 'direction.picker.level_1' | translate }} <span class="field__required" aria-hidden="true">*</span>
        </label>
        <p-select appendTo="body"
          [inputId]="idBase + '-l1'"
          [options]="level1() ?? []"
          optionLabel="name"
          optionValue="id"
          [(ngModel)]="selected1" name="selected1"
          (ngModelChange)="onLevel1()"
          [placeholder]="'common.choose' | translate"
          styleClass="u-full"
          [autofocus]="false"
        />
      </div>

      @if (level2().length) {
        <div>
          <label class="field__label" [for]="idBase + '-l2'">
            {{ 'direction.picker.level_2' | translate }}
          </label>
          <p-select appendTo="body"
            [inputId]="idBase + '-l2'"
            [options]="level2() ?? []"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="selected2" name="selected2"
            (ngModelChange)="onLevel2()"
            [placeholder]="'common.choose' | translate"
            styleClass="u-full"
          />
        </div>
      }

      @if (level3().length) {
        <div>
          <label class="field__label" [for]="idBase + '-l3'">
            {{ 'direction.picker.level_3' | translate }}
          </label>
          <p-select appendTo="body"
            [inputId]="idBase + '-l3'"
            [options]="level3() ?? []"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="selected3" name="selected3"
            (ngModelChange)="onLevel3()"
            [placeholder]="'common.choose' | translate"
            styleClass="u-full"
          />
        </div>
      }

      @if (items().length) {
        <div>
          <label class="field__label" [for]="idBase + '-item'">
            {{ 'direction.picker.item' | translate }} <span class="field__required" aria-hidden="true">*</span>
          </label>
          <p-select appendTo="body"
            [inputId]="idBase + '-item'"
            [options]="items() ?? []"
            optionLabel="name"
            optionValue="id"
            [(ngModel)]="selectedItem" name="selectedItem"
            (ngModelChange)="emit()"
            [placeholder]="'common.choose' | translate"
            styleClass="u-full"
          />
          @if (selectedItem() && defaultAmount() !== null) {
            <p class="field__hint u-bidi">{{ 'direction.picker.default_amount' | translate }}: {{ defaultAmount() }}</p>
          }
        </div>
      }

      @if (touched() && !complete()) {
        <p class="field__error">
          {{ 'direction.picker.incomplete' | translate }}
        </p>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class DirectionPickerComponent {
  tree = input.required<DirectionNode[]>();
  idBase = input('dir');
  required = input(true);

  selectionChange = output<DirectionSelection>();

  selected1 = signal<number | null>(null);
  selected2 = signal<number | null>(null);
  selected3 = signal<number | null>(null);
  selectedItem = signal<number | null>(null);
  touched = signal(false);

  private counter = 0;
  readonly uid = `dir-${++DirectionPickerComponent.prototype.counter}-${Math.floor(Math.random() * 10000)}`;

  level1 = computed<DirectionNode[]>(() => this.tree());
  level2 = computed<DirectionNode[]>(() => this.find(this.selected1())?.children ?? []);
  level3 = computed<DirectionNode[]>(() => this.find(this.selected2())?.children ?? []);
  items = computed<DirectionItem[]>(() => {
    const node = this.find(this.selected3()) ?? this.find(this.selected2());
    return node?.items ?? [];
  });

  defaultAmount = computed<string | null>(() => {
    const item = this.items().find((i) => i.id === this.selectedItem());
    return item?.default_amount ?? null;
  });

  complete = computed(() => this.selectedItem() !== null);

  private find(id: number | null): DirectionNode | undefined {
    if (id === null) {
      return undefined;
    }
    const walk = (nodes: DirectionNode[]): DirectionNode | undefined => {
      for (const node of nodes) {
        if (node.id === id) {
          return node;
        }
        const child = walk(node.children);
        if (child) {
          return child;
        }
      }
      return undefined;
    };
    return walk(this.tree());
  }

  /**
   * Restore a saved selection (edit mode): resolves the ancestor chain of the
   * category from the tree and selects the item — no selectionChange emission,
   * the form already knows the saved direction.
   */
  seed(categoryId: number | null, itemId: number | null): void {
    if (categoryId === null) {
      return;
    }
    const path = this.findPath(categoryId);
    if (!path || path.length === 0) {
      return;
    }
    this.selected1.set(path[0] ?? null);
    this.selected2.set(path[1] ?? null);
    this.selected3.set(path[2] ?? null);
    this.selectedItem.set(itemId);
  }

  private findPath(id: number, nodes: DirectionNode[] = this.tree(), acc: number[] = []): number[] | null {
    for (const node of nodes) {
      const next = [...acc, node.id];
      if (node.id === id) {
        return next;
      }
      const found = this.findPath(id, node.children, next);
      if (found) {
        return found;
      }
    }
    return null;
  }

  onLevel1(): void {
    this.selected2.set(null);
    this.selected3.set(null);
    this.selectedItem.set(null);
    this.touched.set(true);
    this.emit();
  }

  onLevel2(): void {
    this.selected3.set(null);
    this.selectedItem.set(null);
    this.touched.set(true);
    this.emit();
  }

  onLevel3(): void {
    this.selectedItem.set(null);
    this.touched.set(true);
    this.emit();
  }

  /** Preset the picker from saved ids (edit forms). */
  preset(categoryId: number | null, itemId: number | null): void {
    const node = categoryId !== null ? this.find(categoryId) : undefined;
    if (!node) {
      return;
    }
    // level 1..3 chain to the node holding the item
    this.selected1.set(node.level === 1 ? node.id : this.ancestorAt(node, 1)?.id ?? node.id);
    this.selected2.set(node.level === 2 ? node.id : this.ancestorAt(node, 2)?.id ?? null);
    this.selected3.set(node.level === 3 ? node.id : null);
    this.selectedItem.set(itemId);
  }

  private ancestorAt(node: DirectionNode, level: number): DirectionNode | null {
    const walk = (nodes: DirectionNode[], depth: number): DirectionNode | null => {
      for (const candidate of nodes) {
        if (candidate.level === level && this.isAncestor(candidate, node)) {
          return candidate;
        }
        const found = walk(candidate.children, depth);
        if (found) {
          return found;
        }
      }
      return null;
    };
    return walk(this.tree(), level);
  }

  private isAncestor(candidate: DirectionNode, node: DirectionNode): boolean {
    const walk = (nodes: DirectionNode[]): boolean =>
      nodes.some((n) => n.id === node.id || walk(n.children));
    return walk(candidate.children);
  }

  emit(): void {
    this.selectionChange.emit({
      category_id: this.selected2() ?? this.selected3() ?? this.selected1(),
      item_id: this.selectedItem(),
      complete: this.complete(),
    });
  }
}
