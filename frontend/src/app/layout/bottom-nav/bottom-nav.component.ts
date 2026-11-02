import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { NavItem } from '../shell/nav-items';

/** Bottom navigation on phones (plan 19.2): the role's most-used items. */
@Component({
  selector: 'app-bottom-nav',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, TranslatePipe],
  template: `
    <nav class="bottom-nav" [attr.aria-label]="'nav.main_aria' | translate">
      @for (item of items(); track item.label) {
        <a
          class="bottom-nav__item"
          routerLinkActive="bottom-nav__item--active"
          [routerLink]="item.route"
        >
          <svg width="20" height="20" aria-hidden="true">
            <use [attr.href]="'assets/icons/sprite.svg#tabler-' + item.icon" />
          </svg>
          <span>{{ item.label | translate }}</span>
        </a>
      }
    </nav>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BottomNavComponent {
  items = input<NavItem[]>([]);
}
