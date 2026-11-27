import { ChangeDetectionStrategy, Component, computed, input, output } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { IconComponent } from '@shared/ui/icon/icon.component';
import type { ModuleAccent } from '@shared/ui/icon/icon.component';
import { ActionButtonComponent } from '@shared/ui/actions/action-button.component';
import type { ActionKind } from '@shared/ui/actions/action-kind';

/**
 * Specific, human empty state (plan 9.3.10) with one clear action.
 *
 * R5-02: `text` is optional — a state whose one line says it all (a chart
 * with nothing to plot) has no second line, and no empty paragraph either.
 *
 * N-08: a call to action — `actionLabel` (i18n key) with `actionRoute` (a
 * link) or no route (the `action` output, for "clear the filters"). The page
 * decides whether the role may do it: an empty `actionLabel` renders no button.
 * The CTA is the secondary `quickAdd` kind by default, on purpose: the page
 * header above an empty list already carries the ONE primary "create" button,
 * and a second filled one here would break the one-primary rule.
 *
 * N-09: `compact` — icon + one line, no illustration — for the small bodies
 * it sits in (a chart card).
 */
@Component({
  selector: 'app-empty-state',
  standalone: true,
  imports: [TranslatePipe, IconComponent, ActionButtonComponent],
  template: `
    <div class="empty-state" [class.empty-state--compact]="compact()">
      @if (accent() !== '' && iconName() !== '') {
        <app-icon class="empty-state__chip" [name]="iconName()" [chip]="accent()" [size]="compact() ? 20 : 24" />
      }
      @if (!compact()) {
        <svg class="empty-state__pattern" aria-hidden="true">
          <use href="assets/patterns/tile.svg#tile" />
        </svg>
      }
      <p class="empty-state__title">{{ title() | translate }}</p>
      @if (line() !== '') {
        <p class="empty-state__text">{{ line() | translate }}</p>
      }
      @if (actionLabel() !== '') {
        <div class="empty-state__action">
          <app-action-button
            [kind]="actionKind()"
            [label]="actionLabel()"
            size="small"
            [route]="actionRoute()"
            (action)="action.emit()"
          />
        </div>
      }
      <div class="u-mt-2"><ng-content /></div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EmptyStateComponent {
  title = input.required<string>();
  /** i18n key of a second, smaller line; empty = no second line */
  text = input('');
  /** N-08: the same second line, by the name a page reads better (`text` wins when both are set) */
  hint = input('');
  /** module key (T7-09) — the empty state carries the module chip */
  accent = input<ModuleAccent | ''>('');
  /** Tabler icon name shown as the accent chip (T7-09) */
  iconName = input('');
  /** N-09: icon + one line, no illustration, little padding */
  compact = input(false);
  /** N-08: i18n key of the call to action; empty = no button */
  actionLabel = input('');
  /** N-08: the action kind of the button (look + icon come from the action-kind map) */
  actionKind = input<ActionKind>('quickAdd');
  /** N-08: where the call to action goes; null = the `action` output is emitted instead */
  actionRoute = input<readonly unknown[] | null>(null);
  /** N-08: the call to action was clicked (no route set) */
  action = output();

  /** the second line: `text` when set, else `hint` */
  line = computed(() => this.text() || this.hint());
}
