import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';

/** Specific, human empty state (plan 9.3.10) with one clear action. */
@Component({
  selector: 'app-empty-state',
  standalone: true,
  imports: [TranslatePipe],
  template: `
    <div class="empty-state">
      <svg class="empty-state__pattern" aria-hidden="true">
        <use href="assets/patterns/tile.svg#tile" />
      </svg>
      <p class="empty-state__title">{{ title() | translate }}</p>
      <p class="empty-state__text">{{ text() | translate }}</p>
      <div class="u-mt-2"><ng-content /></div>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class EmptyStateComponent {
  title = input.required<string>();
  text = input.required<string>();
}
