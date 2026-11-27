import { ChangeDetectionStrategy, Component, computed, inject, input, output } from '@angular/core';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { ButtonModule } from 'primeng/button';
import { RouterLink } from '@angular/router';
import { iconHref } from '@shared/ui/icon/icon-symbol';
import type { ActionKind } from './action-kind';
import { actionStyle } from './action-kind';

/** Tabler icon inside a p-button content slot (one icon system — T6-41.4). */
@Component({
  selector: 'app-action-icon',
  standalone: true,
  template: `<svg width="16" height="16" aria-hidden="true" focusable="false">
    <use [attr.href]="iconHref(name())" />
  </svg>`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActionIconComponent {
  readonly iconHref = iconHref;

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
        [class.p-button-soft]="style().variant === 'soft'"
        [size]="size()"
        [ariaLabel]="accessibleName()"
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
        [styleClass]="style().variant === 'soft' ? 'p-button-soft' : ''"
        [size]="size()"
        [loading]="loading()"
        [disabled]="disabled()"
        [ariaLabel]="accessibleName()"
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

  private translate = inject(TranslateService);

  /**
   * N-06: the button's accessible name. An explicit `ariaLabel` wins. Otherwise
   * the label's own text: a row button collapses to its icon below 1280px
   * (R2-05), and a label hidden by CSS must not take the name with it.
   */
  readonly accessibleName = computed(() => {
    const explicit = this.ariaLabel();
    const text = this.label();
    if (explicit !== '') {
      return explicit;
    }
    return text === '' ? '' : this.translate.instant(text);
  });

  action = output<MouseEvent>();

  style = computed(() => actionStyle(this.kind()));
  showIcon = computed(() => this.label() === '' || this.withIcon());
  withIcon = input(true);
}

