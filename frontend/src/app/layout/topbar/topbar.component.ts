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
import { offline, pendingRequests } from '@core/http/progress-state';
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

      <!-- D-02: breadcrumb, not a repeated page title — the page H1 says that -->
      <nav class="topbar__crumbs" [attr.aria-label]="'a11y.breadcrumb' | translate">
        @for (crumb of crumbs(); track $index; let last = $last) {
          @if (!last) {
            <span class="topbar__crumb">{{ crumb.key ? (crumb.key | translate) : crumb.raw }}</span>
            <span class="topbar__crumb-sep" aria-hidden="true">‹</span>
          } @else {
            <span class="topbar__crumb topbar__crumb--current">{{ crumb.key ? (crumb.key | translate) : crumb.raw }}</span>
          }
        }
      </nav>

      @if (offlineFlag()) {
        <span class="stamp stamp--pending">{{ 'errors.offline_title' | translate }}</span>
      }

      <span class="topbar__spacer"></span>

      <!-- D-02/X-01: the visible search trigger — opens the Ctrl+K palette -->
      <button type="button" class="topbar__search" (click)="paletteOpen.set(true)">
        <app-icon name="search" [size]="14" />
        <span class="topbar__search-hint">{{ 'command.search_hint' | translate }}</span>
        <kbd class="topbar__search-kbd">Ctrl K</kbd>
      </button>

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

    <!-- UX-6.1: slim global progress line while any API call is in flight -->
    <div
      class="topbar__progress"
      role="progressbar"
      [attr.aria-label]="'common.loading' | translate"
      [class.topbar__progress--active]="pending() > 0"
    ></div>

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
  host: {
    // N-01: Ctrl+K (Cmd+K on a Mac) opens the palette — the shortcut the search box advertises
    '(document:keydown)': 'onShortcut($event)',
  },
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
  readonly pending = pendingRequests;

  /** an urgent broadcast's own shortcut (if it ever has one) is left alone: Shift and Alt are not ours */
  onShortcut(event: KeyboardEvent): void {
    if ((event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      this.paletteOpen.set(!this.paletteOpen());
    }
  }

  /** active urgent broadcast for the current user (T3-101), null once dismissed */
  readonly broadcast = signal<ActiveBroadcast | null>(null);

  constructor() {
    this.loadBroadcast();
  }

  /** POST /broadcasts/active — a read wrapped in POST (session-dismissed ids live server-side).
   *  D-02: an empty payload (no title AND no message) renders nothing. */
  private loadBroadcast(): void {
    void firstValueFrom(this.http.post<ItemResponse<ActiveBroadcast | null>>('/broadcasts/active', {}))
      .then((res) => {
        const b = res.data ?? null;
        this.broadcast.set(b && (b.title?.trim() !== '' || b.message?.trim() !== '') ? b : null);
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

  /** first URL segment → module label */
  private static readonly MODULE_LABELS: Record<string, string> = {
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

  /** second URL segment → page label (compound keys first, then generic) */
  private static readonly SUB_LABELS: Record<string, string> = {
    'custodies/mine': 'nav.my_custodies',
    'expenses/mine': 'nav.my_expenses',
    'social-cases/mine': 'nav.my_cases',
    'hr/employees': 'hr.employees.title',
    'hr/attendance': 'hr.dashboard.view_attendance',
    'hr/salaries': 'hr.dashboard.total_salaries',
    'hr/kpi': 'hr.dashboard.kpi_achievement',
    tracking: 'nav.custody_tracking',
    movements: 'nav.custody_movements',
    'return-requests': 'nav.return_requests',
    'edit-requests': 'nav.edit_requests',
    new: 'common.add',
    edit: 'common.edit',
    transfer: 'nav.transfers',
    purchase: 'nav.purchase_requests',
    maintenance: 'nav.maintenance_requests',
    suppliers: 'nav.suppliers',
  };

  /**
   * D-02 breadcrumb: module › page (#id for detail routes). The topbar never
   * repeats the page H1 — deep leaf actions (edit/new) map to their label.
   */
  readonly crumbs = computed<{ key: string; raw?: string }[]>(() => {
    const url = this.currentPath().split('?')[0].split('#')[0];
    const segments = url.split('/').filter(Boolean);
    if (segments.length === 0) {
      return [{ key: 'nav.dashboard' }];
    }
    const crumbs: { key: string; raw?: string }[] = [
      { key: TopbarComponent.MODULE_LABELS[segments[0]] ?? 'nav.dashboard' },
    ];
    if (segments[1] !== undefined) {
      if (/^\d+$/.test(segments[1])) {
        crumbs.push({ key: '', raw: `#${segments[1]}` });
        if (segments[2] !== undefined) {
          crumbs.push({ key: TopbarComponent.SUB_LABELS[segments[2]] ?? 'common.open' });
        }
      } else {
        const compound = `${segments[0]}/${segments[1]}`;
        crumbs.push({ key: TopbarComponent.SUB_LABELS[compound] ?? TopbarComponent.SUB_LABELS[segments[1]] ?? 'common.open' });
      }
    }
    return crumbs;
  });
}
