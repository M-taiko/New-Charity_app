import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';

/** Row action severity follows the action-kind map (T6-13): danger red, success green, warn amber, info blue-teal. */
export type RowActionSeverity = 'default' | 'danger' | 'success' | 'warn' | 'info';

/** One row action: route link or handler, icon button with visible label on small screens. */
export interface RowActionItem {
  /** tabler icon name, e.g. 'eye' */
  readonly icon: string;
  /** i18n key */
  readonly label: string;
  readonly severity?: RowActionSeverity;
  readonly visible?: boolean;
  readonly disabled?: boolean;
  /** routerLink commands — renders an <a> */
  readonly route?: readonly unknown[];
  /** click handler — renders a <button> */
  readonly handler?: () => void;
}

/**
 * The one row-actions pattern (T4-24): icon buttons with tooltip +
 * aria-label; destructive actions last and visually distinct; collapses into
 * a labelled "⋯" dropdown under 640 px so touch rows stay tappable.
 */
@Component({
  selector: 'app-row-actions',
  standalone: true,
  imports: [TranslatePipe, RouterLink],
  template: `
    <div class="row-actions">
      @for (action of visibleActions(); track $index) {
        @if (action.route; as route) {
          <a
            class="row-actions__btn"
            [class]="rowActionClass(action)"
            [class.row-actions__btn--disabled]="action.disabled === true"
            [title]="action.label | translate"
            [attr.aria-label]="action.label | translate"
            [routerLink]="route"
            [attr.tabindex]="action.disabled === true ? -1 : null"
            [attr.aria-disabled]="action.disabled === true ? true : null"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use [attr.href]="'assets/icons/sprite.svg#tabler-' + action.icon" />
            </svg>
          </a>
        } @else {
          <button
            type="button"
            class="row-actions__btn"
            [class]="rowActionClass(action)"
            [title]="action.label | translate"
            [attr.aria-label]="action.label | translate"
            [disabled]="action.disabled === true"
            (click)="action.handler?.()"
          >
            <svg width="16" height="16" aria-hidden="true">
              <use [attr.href]="'assets/icons/sprite.svg#tabler-' + action.icon" />
            </svg>
          </button>
        }
      }
    </div>

    <div class="row-actions-menu">
      <button
        type="button"
        class="row-actions__btn"
        [attr.aria-label]="'common.actions' | translate"
        [attr.aria-expanded]="menuOpen"
        (click)="toggle()"
        (keydown.escape)="close()"
      >
        <svg width="16" height="16" aria-hidden="true">
          <use href="assets/icons/sprite.svg#tabler-dots" />
        </svg>
      </button>
      @if (menuOpen) {
        <div class="row-actions-menu__panel" role="menu" (keydown.escape)="close()">
          @for (action of visibleActions(); track $index) {
            @if (action.route; as route) {
              <a
                class="row-actions-menu__item"
                [class]="rowMenuClass(action)"
                role="menuitem"
                [routerLink]="route"
                (click)="close()"
              >
                <svg width="16" height="16" aria-hidden="true">
                  <use [attr.href]="'assets/icons/sprite.svg#tabler-' + action.icon" />
                </svg>
                <span>{{ action.label | translate }}</span>
              </a>
            } @else {
              <button
                type="button"
                class="row-actions-menu__item"
                [class]="rowMenuClass(action)"
                role="menuitem"
                [disabled]="action.disabled === true"
                (click)="action.handler?.(); close()"
              >
                <svg width="16" height="16" aria-hidden="true">
                  <use [attr.href]="'assets/icons/sprite.svg#tabler-' + action.icon" />
                </svg>
                <span>{{ action.label | translate }}</span>
              </button>
            }
          }
        </div>
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { '(document:click)': 'onDocumentClick($event)', class: 'row-actions-host' },
})
export class RowActionsComponent {
  actions = input.required<RowActionItem[]>();

  menuOpen = false;

  visibleActions = computed(() => this.actions().filter((a) => a.visible !== false));

  /** icon-button class per action severity (T6-13) */
  rowActionClass(action: RowActionItem): string {
    return action.severity && action.severity !== 'default'
      ? `row-actions__btn row-actions__btn--${action.severity}`
      : 'row-actions__btn';
  }

  /** dropdown-item class per action severity (T6-13) */
  rowMenuClass(action: RowActionItem): string {
    return action.severity && action.severity !== 'default'
      ? `row-actions-menu__item row-actions-menu__item--${action.severity}`
      : 'row-actions-menu__item';
  }

  toggle(): void {
    this.menuOpen = !this.menuOpen;
  }

  close(): void {
    this.menuOpen = false;
  }

  onDocumentClick(event: MouseEvent): void {
    if (!this.menuOpen) {
      return;
    }
    const target = event.target as HTMLElement | null;
    if (target && !target.closest('.row-actions-host')) {
      this.close();
    }
  }
}
