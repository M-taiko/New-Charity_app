import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { RouterLink } from '@angular/router';
import type { ActionKind } from './action-kind';
import { actionStyle } from './action-kind';

/** Tabler icon inside a p-button content slot (one icon system — T6-41.4). */
@Component({
  selector: 'app-action-icon',
  standalone: true,
  template: `<svg width="16" height="16" aria-hidden="true" focusable="false">
    <use [attr.href]="'assets/icons/sprite.svg#tabler-' + name()" />
  </svg>`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActionIconComponent {
  name = input.required<string>();
}

/**
 * The semantic action button (T6-13): pass a `kind` and get the one correct
 * look — severity, variant and icon come from the action-kind map, never from
 * the page. Use for every decision/navigation action in headers, dialogs and
 * sticky bars; rows keep `app-row-actions`.
 */
@Component({
  selector: 'app-action-button',
  standalone: true,
  imports: [TranslatePipe, ButtonModule, RouterLink, ActionIconComponent],
  template: `
    @if (route(); as link) {
      <a
        pButton
        [label]="label() === '' ? undefined : (label() | translate)"
        [severity]="style().severity"
        [outlined]="style().variant === 'outlined'"
        [text]="style().variant === 'text'"
        [size]="size()"
        [ariaLabel]="ariaLabel()"
        [routerLink]="link"
        [queryParams]="queryParams()"
      >
        @if (showIcon() && style().icon) {
          <app-action-icon [name]="style().icon!" />
        }
      </a>
    } @else {
      <p-button
        [label]="label() === '' ? undefined : (label() | translate)"
        [severity]="style().severity"
        [outlined]="style().variant === 'outlined'"
        [text]="style().variant === 'text'"
        [size]="size()"
        [loading]="loading()"
        [disabled]="disabled()"
        [ariaLabel]="ariaLabel()"
        (onClick)="action.emit($event)"
      >
        @if (showIcon() && style().icon) {
          <app-action-icon [name]="style().icon!" />
        }
      </p-button>
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActionButtonComponent {
  /** the action's kind — decides severity, variant and icon (T6-13 table) */
  kind = input.required<ActionKind>();
  /** i18n key; empty string renders an icon-only button */
  label = input('');
  size = input<'small' | 'large' | undefined>(undefined);
  loading = input(false);
  disabled = input(false);
  /** render an <a routerLink> instead of a button */
  route = input<readonly unknown[] | null>(null);
  /** query params for the routerLink variant (T7-05: report/dashboard links) */
  queryParams = input<Record<string, unknown> | null>(null);
  /** accessible name — required for icon-only buttons (already-translated text) */
  ariaLabel = input('');

  action = output<MouseEvent>();

  style = computed(() => actionStyle(this.kind()));
  showIcon = computed(() => this.label() === '' || this.withIcon());
  withIcon = input(true);
}

