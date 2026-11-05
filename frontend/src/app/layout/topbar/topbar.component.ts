import { ChangeDetectionStrategy, Component, computed, inject, output, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { TranslatePipe } from '@ngx-translate/core';
import { TooltipModule } from 'primeng/tooltip';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { ThemeService } from '@core/theme/theme.service';
import { PollingService } from '@core/polling/polling.service';
import { offline } from '@core/http/progress-state';
import { IconComponent } from '@shared/ui/icon/icon.component';
import { NotificationBellComponent } from '../notification-bell/notification-bell.component';
import { UserMenuComponent } from '../user-menu/user-menu.component';
import { CommandPaletteComponent } from '../command-palette/command-palette.component';
import type { ItemResponse } from '@shared/models/api.models';

/** Payload of POST /broadcasts/active (SystemController@activeBroadcast). */
interface ActiveBroadcast {
  id: number;
  title: string;
  message: string;
  level: string;
}

/**
 * Top bar (plan 19.1): breadcrumb/title, Ctrl+K palette, theme toggle,
 * notification bell, user menu. 56px, sticky. The active-broadcast banner
 * (T3-101) renders as a slim dismissible bar right under the bar.
 */
@Component({
  selector: 'app-topbar',
  standalone: true,
  imports: [
    TranslatePipe,
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

    @if (broadcast(); as b) {
      <div class="broadcast-banner" role="status">
        <div class="topbar__broadcast">
          <app-icon [name]="levelIcon(b.level)" [size]="16" />
          <strong class="u-small">{{ b.title }}</strong>
          <span class="u-small u-truncate">{{ b.message }}</span>
          <span
            class="stamp"
            [class.stamp--info]="b.level === 'info'"
            [class.stamp--pending]="b.level === 'warning'"
            [class.stamp--danger]="b.level === 'danger'"
          >
            {{ levelKey(b.level) | translate }}
          </span>
        </div>
        <button
          type="button"
          class="topbar__icon-btn topbar__icon-btn--sm"
          (click)="dismissBroadcast()"
          [attr.aria-label]="'broadcast.dismiss' | translate"
          [title]="'broadcast.dismiss' | translate"
        >
          <app-icon name="x" [size]="14" />
        </button>
      </div>
    }

    <app-command-palette [open]="paletteOpen()" (closed)="paletteOpen.set(false)" />
  `,
  styles: `
    .topbar__icon-btn--sm {
      width: 28px;
      height: 28px;
    }

    .topbar__broadcast {
      flex: 1;
      min-width: 0;
    }

    .broadcast-banner {
      display: flex;
      align-items: center;
      gap: var(--space-2);
      padding: var(--space-2) var(--space-4);
      background: var(--color-surface-inset);
      border-block-end: 1px solid var(--color-hairline);
    }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TopbarComponent {
  readonly theme = inject(ThemeService);
  private polling = inject(PollingService);
  private router = inject(Router);
  private http = inject(HttpClient);

  readonly paletteOpen = signal(false);
  readonly menuToggle = output();
  readonly unread = this.polling.unreadCount;
  readonly offlineFlag = offline;

  /** active urgent broadcast for the current user (T3-101), null once dismissed */
  readonly broadcast = signal<ActiveBroadcast | null>(null);

  constructor() {
    this.loadBroadcast();
  }

  /** POST /broadcasts/active — a read wrapped in POST (session-dismissed ids live server-side) */
  private loadBroadcast(): void {
    void firstValueFrom(this.http.post<ItemResponse<ActiveBroadcast | null>>('/broadcasts/active', {}))
      .then((res) => {
        this.broadcast.set(res.data ?? null);
      })
      .catch(() => {
        this.broadcast.set(null);
      });
  }

  dismissBroadcast(): void {
    const current = this.broadcast();
    if (!current) {
      return;
    }
    // optimistic hide — the server records the dismissed id in the session
    this.broadcast.set(null);
    void firstValueFrom(this.http.post<{ ok: boolean }>('/broadcasts/dismiss', {})).catch(() => undefined);
  }

  levelIcon(level: string): string {
    return level === 'info' ? 'info' : 'alert-triangle';
  }

  levelKey(level: string): string {
    return level === 'info' || level === 'danger' ? `broadcast.level_${level}` : 'broadcast.level_warning';
  }

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
