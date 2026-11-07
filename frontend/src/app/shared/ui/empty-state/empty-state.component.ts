import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { IconComponent } from '@shared/ui/icon/icon.component';
import type { ModuleAccent } from '@shared/ui/icon/icon.component';

/** Specific, human empty state (plan 9.3.10) with one clear action. */
@Component({
  selector: 'app-empty-state',
  standalone: true,
  imports: [TranslatePipe, IconComponent],
  template: `
    <div class="empty-state">
      @if (accent() !== '' && iconName() !== '') {
        <app-icon class="empty-state__chip" [name]="iconName()" [chip]="accent()" [size]="24" />
      }
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
  /** module key (T7-09) — the empty state carries the module chip */
  accent = input<ModuleAccent | ''>('');
  /** Tabler icon name shown as the accent chip (T7-09) */
  iconName = input('');
}
