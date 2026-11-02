import { ChangeDetectionStrategy, Component, computed, inject, output, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { TranslatePipe } from '@ngx-translate/core';
import { TooltipModule } from 'primeng/tooltip';
import { ThemeService } from '@core/theme/theme.service';
import { PollingService } from '@core/polling/polling.service';
import { offline } from '@core/http/progress-state';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { NotificationBellComponent } from '../notification-bell/notification-bell.component';
import { UserMenuComponent } from '../user-menu/user-menu.component';
import { CommandPaletteComponent } from '../command-palette/command-palette.component';

/**
 * Top bar (plan 19.1): breadcrumb/title, Ctrl+K palette, theme toggle,
 * notification bell, user menu. 56px, sticky.
 */
@Component({
  selector: 'app-topbar',
  standalone: true,
  imports: [
    TranslatePipe,
    RouterLink,
    TooltipModule,
    IconComponent,
    NotificationBellComponent,
    UserMenuComponent,
    CommandPaletteComponent,
  ],
  template: `
    <header class="topbar">
      <button
        type="button"
        class="topbar__icon-btn topbar__menu-btn"
        (click)="menuToggle.emit()"
        [attr.aria-label]="'nav.open_menu' | translate"
      >
        <app-icon name="menu-2" [size]="18" />
      </button>

      <span class="topbar__title">{{ pageTitle() | translate }}</span>

      @if (offlineFlag()) {
        <span class="stamp stamp--pending">{{ 'errors.offline_title' | translate }}</span>
      }

      <span class="topbar__spacer"></span>

      <button
        type="button"
        class="topbar__icon-btn theme-toggle"
        (click)="theme.toggle()"
        [attr.aria-pressed]="theme.theme() === 'dark'"
        [attr.aria-label]="(theme.theme() === 'dark' ? 'common.light_theme' : 'common.dark_theme') | translate"
        [pTooltip]="(theme.theme() === 'dark' ? 'common.light_theme' : 'common.dark_theme') | translate"
        tooltipPosition="bottom"
      >
        <app-icon [name]="theme.theme() === 'dark' ? 'sun' : 'moon'" [size]="16" />
      </button>

      <app-notification-bell />
      <app-user-menu />
    </header>
    <app-command-palette [open]="paletteOpen()" (closed)="paletteOpen.set(false)" />
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TopbarComponent {
  readonly theme = inject(ThemeService);
  private polling = inject(PollingService);
  private router = inject(Router);

  readonly paletteOpen = signal(false);
  readonly menuToggle = output<void>();
  readonly unread = this.polling.unreadCount;
  readonly offlineFlag = offline;

  /** current path — reactive on navigation (same proven pattern as the sidebar).
   * NB: the emitted value must CHANGE per navigation — toSignal applies equality
   * and a constant emission (e.g. map(() => 0)) would never propagate. */
  private readonly currentPath = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map(() => this.router.url),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  readonly pageTitle = computed(() => {
    const url = this.currentPath().split('?')[0].split('#')[0];
    const segments = url.split('/').filter(Boolean);
    if (segments.length === 0) {
      return 'nav.dashboard';
    }
    const map: Record<string, string> = {
      dashboard: 'nav.dashboard',
      treasury: 'nav.treasury',
      custodies: 'nav.custodies',
      transfers: 'nav.transfers',
      expenses: 'nav.expenses',
      directions: 'nav.directions',
      reports: 'nav.reports',
      cases: 'nav.cases',
      'social-cases': 'nav.cases',
      hr: 'nav.hr',
      procurement: 'nav.procurement',
      tasks: 'nav.tasks',
      chat: 'nav.chat',
      notifications: 'nav.notifications',
      broadcasts: 'nav.broadcasts',
      activity: 'nav.activity',
      users: 'nav.users',
      settings: 'nav.settings',
      profile: 'nav.profile',
    };
    return map[segments[0]] ?? 'nav.dashboard';
  });
}
