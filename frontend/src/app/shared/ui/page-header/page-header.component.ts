import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { IconComponent } from '@shared/ui/icon/icon.component';

/** Title + optional stamp + actions row used on every page (plan 16.2). */
@Component({
  selector: 'app-page-header',
  standalone: true,
  imports: [TranslatePipe, IconComponent],
  template: `
    <header class="page-header">
      <div class="page-header__row">
        <div class="u-column u-gap-1">
          <h1 class="page-header__title">{{ title() | translate }}</h1>
          @if (subtitle()) {
            <p class="page-header__subtitle">{{ subtitle() | translate }}</p>
          }
        </div>
        <div class="page-header__actions">
          <ng-content select="[actions]" />
        </div>
      </div>
      <div class="page-header__meta">
        <ng-content />
      </div>
    </header>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PageHeaderComponent {
  title = input.required<string>();
  subtitle = input<string>('');
}
