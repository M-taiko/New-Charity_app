import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { TranslatePipe } from '@ngx-translate/core';
import { IconComponent } from '@shared/ui/icon/icon.component';
import type { ModuleAccent } from '@shared/ui/icon/icon.component';

/** Title + optional stamp + actions row used on every page (plan 16.2). */
@Component({
  selector: 'app-page-header',
  standalone: true,
  imports: [TranslatePipe, IconComponent],
  template: `
    <header class="page-header">
      <div class="page-header__row">
        <div class="u-column u-gap-1">
          <h1 class="page-header__title">
            @if (accent() !== '' && iconName() !== '') {
              <app-icon class="page-header__chip" [name]="iconName()" [chip]="accent()" [size]="18" />
            }
            {{ title() | translate }}
          </h1>
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
  /** module key (T7-09) — the header icon renders as a chip in the module accent */
  accent = input<ModuleAccent | ''>('');
  /** Tabler icon name shown as the accent chip (T7-09) */
  iconName = input('');
}
