import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { MenuModule } from 'primeng/menu';
import { SharedModule } from 'primeng/api';
import type { MenuItem } from 'primeng/api';
import { AuthService } from '@core/auth/auth.service';
import { MotionService } from '@core/motion/motion.service';
import { ThemeService } from '@core/theme/theme.service';
import { IconComponent } from '@shared/ui/icon/icon.component';

/** Menu entry carrying the sprite icon name (the app has no PrimeIcons font). */
type UserMenuItem = MenuItem & { iconName?: string };

/** Profile, my activity, animation setting, logout (plan 19.1). */
@Component({
  selector: 'app-user-menu',
  standalone: true,
  imports: [TranslatePipe, RouterLink, MenuModule, SharedModule, IconComponent],
  template: `
    <p-menu #menu [model]="items()" [popup]="true" appendTo="body">
      <!-- custom item template: sprite icons via <app-icon> instead of pi pi-* font glyphs -->
      <ng-template pTemplate="item" let-item>
        @if (item.routerLink) {
          <a class="p-menu-item-link" tabindex="-1" [routerLink]="item.routerLink">
            <app-icon [name]="item.iconName ?? ''" [size]="16" />
            <span class="p-menu-item-label">{{ item.label }}</span>
          </a>
        } @else if (item.command) {
          <a class="p-menu-item-link" tabindex="-1">
            <app-icon [name]="item.iconName ?? ''" [size]="16" />
            <span class="p-menu-item-label">{{ item.label }}</span>
          </a>
        } @else {
          <span class="p-menu-item-label">{{ item.label }}</span>
        }
      </ng-template>
    </p-menu>
    <button
      type="button"
      class="topbar__icon-btn user-chip"
      (click)="menu.toggle($event)"
      [attr.aria-label]="'user.menu' | translate"
    >
      @if (pictureUrl()) {
        <img
          class="user-chip__avatar"
          [src]="pictureUrl()"
          [alt]="name()"
          style="object-fit: cover; display: inline-flex"
        />
      } @else {
        <span class="user-chip__avatar">{{ initials() }}</span>
      }
      <span class="user-chip__name">{{ name() }}</span>
    </button>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class UserMenuComponent {
  private auth = inject(AuthService);
  private motion = inject(MotionService);
  private theme = inject(ThemeService);
  private translate = inject(TranslateService);

  readonly name = computed(() => this.auth.me()?.name ?? '');

  /** profile picture served from the public storage disk (T3-103); null → initials */
  readonly pictureUrl = computed(() => {
    const path = this.auth.me()?.profile_picture;
    return path ? `/storage/app/public/${path}` : null;
  });

  readonly initials = computed(() =>
    (this.auth.me()?.name ?? '?')
      .split(/\s+/)
      .slice(0, 2)
      .map((part) => part.charAt(0))
      .join(''),
  );

  private t(key: string): string {
    const result = this.translate.instant(key);
    return result !== key ? result : key;
  }

  items = computed<UserMenuItem[]>(() => {
    const name = this.auth.me()?.name ?? '';
    const roles = (this.auth.me()?.roles ?? []).join('، ');
    const level = this.motion.level();
    const motionLabels: Record<string, string> = {
      full: this.t('profile.motion.full'),
      lite: this.t('profile.motion.lite'),
      off: this.t('profile.motion.off'),
    };
    return [
      { label: name, styleClass: 'u-small' },
      { label: roles, styleClass: 'u-small', disabled: true },
      { separator: true },
      { label: this.t('nav.profile'), iconName: 'user', routerLink: '/profile' },
      { label: this.t('nav.my_activity'), iconName: 'history', routerLink: '/activity/mine' },
      {
        label: motionLabels[level] ?? level,
        iconName: 'bolt',
        command: () => this.motion.set(level === 'full' ? 'lite' : level === 'lite' ? 'off' : 'full'),
      },
      {
        label: this.t('common.switch_theme'),
        iconName: 'palette',
        command: () => this.theme.toggle(),
      },
      { separator: true },
      { label: this.t('auth.logout'), iconName: 'logout', command: () => this.auth.logout() },
    ];
  });
}
