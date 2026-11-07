import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { Router } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MenuModule } from 'primeng/menu';
import type { MenuItem } from 'primeng/api';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import type { ActionKind } from '@shared/ui/actions/action-kind';

/** One page-level action for the header action bar (T7-10). */
export interface PageAction {
  /** look + icon come from the action-kind map, never the page */
  kind: ActionKind;
  /** i18n key */
  label: string;
  /** navigate instead of running a handler */
  route?: readonly unknown[];
  queryParams?: Record<string, unknown>;
  run?: () => void;
  loading?: boolean;
  disabled?: boolean;
  /** false hides the action entirely (role checks) */
  visible?: boolean;
}

/** kinds that may occupy the single primary slot */
const PRIMARY_KINDS: readonly string[] = ['save', 'create', 'submit', 'apply', 'send'];

/**
 * The page action bar (T7-10): enforces the header rule instead of trusting
 * it — at most ONE primary action and TWO secondary actions render as
 * buttons, everything else folds into a "More" menu. Pages declare WHAT the
 * actions are; this component decides where they land.
 */
@Component({
  selector: 'app-page-actions',
  standalone: true,
  imports: [TranslatePipe, MenuModule, ActionButtonComponent],
  template: `
    <div class="page-actions">
      @for (action of visibleActions(); track action.label) {        <app-action-button
          [kind]="action.kind"
          [label]="action.label"
          size="small"
          [route]="action.route ?? null"
          [queryParams]="action.queryParams ?? null"
          [loading]="action.loading ?? false"
          [disabled]="action.disabled ?? false"
          (action)="runAction(action)"
        />
      }
      @if (overflowActions().length > 0) {
        <app-action-button kind="more" [label]="'common.more'" size="small" [withIcon]="false" (action)="moreMenu.toggle($event)" />
        <p-menu #moreMenu appendTo="body" [model]="overflowItems()" popup="true" />
      }
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'u-flex u-gap-2 u-flex-wrap' },
})
export class PageActionsComponent {
  private router = inject(Router);
  private translate = inject(TranslateService);

  pageActions = input.required<PageAction[]>();

  private open = computed(() => this.pageActions().filter((a) => a.visible !== false));

  /** the first primary action renders as THE primary button; extras overflow */
  private primary = computed(() => {
    const primaries = this.open().filter((a) => PRIMARY_KINDS.includes(a.kind));
    if (primaries.length > 1 && typeof ngDevMode !== 'undefined' && ngDevMode) {
      // eslint-disable-next-line no-console -- dev-time rule check (T7-10)
      console.warn(`[page-actions] ${primaries.length} primary actions declared — only the first renders as primary, the rest moved to More`);
    }
    return primaries[0] ?? null;
  });

  visibleActions = computed<PageAction[]>(() => {
    const primary = this.primary();
    const secondary = this.open().filter((a) => a !== primary).slice(0, primary ? 2 : 3);
    return primary ? [primary, ...secondary] : secondary;
  });

  overflowActions = computed<PageAction[]>(() => {
    const visible = this.visibleActions();
    return this.open().filter((a) => !visible.includes(a));
  });

  overflowItems = computed<MenuItem[]>(() =>
    this.overflowActions().map((action) => ({
      label: this.translate.instant(action.label),
      disabled: action.disabled || action.loading,
      command: () => { this.runAction(action); },
    })),
  );

  runAction(action: PageAction): void {
    if (action.route) {
      void this.router.navigate(action.route as never[], action.queryParams ? { queryParams: action.queryParams } : undefined);
      return;
    }
    action.run?.();
  }
}
