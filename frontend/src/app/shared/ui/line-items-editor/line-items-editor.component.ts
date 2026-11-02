import { ChangeDetectionStrategy, Component, computed, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DecimalPipe } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { InputNumberModule } from 'primeng/inputnumber';
import { InputTextModule } from 'primeng/inputtext';
import { IconComponent } from '@shared/ui/icon/icon.component';

export interface LineItem {
  description: string;
  quantity: number;
  unit_price: number;
}

/**
 * Line items editor (plan 11.10, T4/T5/D3): rows with a running total and a
 * NON-BLOCKING warning when the total differs from the expense amount.
 */
@Component({
  selector: 'app-line-items-editor',
  standalone: true,
  imports: [DecimalPipe, TranslatePipe, FormsModule, ButtonModule, InputTextModule, InputNumberModule, IconComponent],
  template: `
    <div class="u-column u-gap-2 u-full">
      @for (item of items(); track $index; let i = $index) {
        <div class="line-items__row">
          <input
            pInputText
            type="text"
            [(ngModel)]="item.description" [name]="'li-desc-' + i"
            (ngModelChange)="change()"
            [placeholder]="'expense.form.line_items.description' | translate"
            [attr.aria-label]="'expense.form.line_items.description' | translate"
          />
          <p-inputnumber
            [(ngModel)]="item.quantity" [name]="'li-qty-' + i"
            (ngModelChange)="change()"
            [min]="1"
            [showButtons]="true"
            [attr.aria-label]="'expense.form.line_items.quantity' | translate"
            size="small"
          />
          <div class="line-items__cell-price">
            <p-inputnumber
              [(ngModel)]="item.unit_price" [name]="'li-price-' + i"
              (ngModelChange)="change()"
              [minFractionDigits]="2"
              [maxFractionDigits]="2"
              [min]="0"
              mode="decimal"
              [attr.aria-label]="'expense.form.line_items.unit_price' | translate"
              size="small"
            />
          </div>
          <p-button severity="danger" [text]="true" (onClick)="removeRow(i)" [ariaLabel]="'common.remove' | translate">
            <ng-template pTemplate="icon"><app-icon name="trash" [size]="14" /></ng-template>
          </p-button>
        </div>
      }
      <div>
        <p-button [label]="'expense.form.line_items.add' | translate" size="small" [outlined]="true" (onClick)="addRow()" />
      </div>
      <div class="line-items__total">
        <span>{{ 'expense.form.line_items.total' | translate }}:</span>
        <strong class="u-bidi">{{ total() | number: '1.2-2' }}</strong>
        {{ 'common.egp' | translate }}
      </div>
      @if (mismatch()) {
        <div class="line-items__warning" role="status">
          <svg width="14" height="14" aria-hidden="true">
            <use href="assets/icons/sprite.svg#tabler-alert-triangle" />
          </svg>
          {{ 'expense.form.line_items.mismatch' | translate }}
        </div>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LineItemsEditorComponent {
  amount = input<number>(0);

  items = signal<LineItem[]>([{ description: '', quantity: 1, unit_price: 0 }]);
  itemsChange = output<LineItem[]>();

  total = computed(() =>
    this.items().reduce((sum, item) => sum + (item.quantity || 0) * (item.unit_price || 0), 0),
  );

  mismatch = computed(() => {
    const amount = this.amount();
    return amount > 0 && Math.abs(this.total() - amount) > 0.009;
  });

  addRow(): void {
    this.items.update((rows) => [...rows, { description: '', quantity: 1, unit_price: 0 }]);
    this.change();
  }

  removeRow(index: number): void {
    this.items.update((rows) => rows.filter((_, i) => i !== index));
    this.change();
  }

  preset(rows: LineItem[] | null): void {
    if (rows && rows.length) {
      this.items.set(rows.map((r) => ({ ...r })));
    }
  }

  change(): void {
    this.itemsChange.emit(this.items());
  }
}
