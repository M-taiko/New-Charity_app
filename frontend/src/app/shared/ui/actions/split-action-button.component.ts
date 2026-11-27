import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { SplitButtonModule } from 'primeng/splitbutton';
import type { MenuItem } from 'primeng/api';
import type { ActionKind } from './action-kind';
import { actionStyle } from './action-kind';
import { ActionIconComponent } from './action-button.component';

/**
 * D-03: the semantic SPLIT action button — creation variants ("for me /
 * for agent / transfer…") live here, inside the header's ONE primary slot.
 * Severity, variant and icon come from the action-kind map exactly like
 * `app-action-button`; pages never touch a raw `p-splitbutton`.
 */
@Component({
  selector: 'app-split-action-button',
  standalone: true,
  imports: [TranslatePipe, SplitButtonModule, ActionIconComponent],
  template: `
    <p-splitbutton
      appendTo="body"
      [label]="label() === '' ? undefined : (label() | translate)"
      [severity]="style().severity"
      [outlined]="style().variant === 'outlined'"
      [styleClass]="style().variant === 'soft' ? 'p-button-soft' : ''"
      [size]="size()"
      [model]="menu()"
      (onClick)="action.emit($event)"
    >
      @if (style().icon) {
        <app-action-icon [name]="style().icon!" />
      }
    </p-splitbutton>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SplitActionButtonComponent {
  /** the primary action's kind — decides severity, variant and icon (T6-13) */
  kind = input.required<ActionKind>();
  /** i18n key for the default (left) action */
  label = input('');
  /** the dropdown items — the creation variants */
  menu = input.required<MenuItem[]>();
  size = input<'small' | 'large' | undefined>(undefined);

  action = output<MouseEvent>();

  style = computed(() => actionStyle(this.kind()));
}
