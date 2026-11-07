import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { map, startWith, filter } from 'rxjs';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';
import { LocalStorageService } from '@core/storage/local-storage.service';
import type { NavItem } from './nav-items';
import { NAV_GROUPS } from './nav-items';
import { TopbarComponent } from '../topbar/topbar.component';

/**
 * App shell (plan 19): right-side sidebar in RTL, 56px top bar, content on a
 * 12-column grid; phones get a bottom bar with the most-used items.
 */
@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, TranslatePipe, TopbarComponent],
  template: `
    <div class="app-shell">
      <a class="skip-link" href="#main-content">{{ 'a11y.skip_to_content' | translate }}</a>
      <aside class="sidebar" [class.sidebar--collapsed]="collapsed()" [class.sidebar--open]="menuOpen()">
        <div class="sidebar__brand">
          <svg class="sidebar__brand-logo" aria-hidden="true">
            <use href="assets/brand/monogram.svg#ns" />
          </svg>
          <span class="sidebar__brand-name">{{ orgName() }}</span>
        </div>
        <nav [attr.aria-label]="'nav.main_aria' | translate">
          @for (group of visibleGroups(); track group.label) {
            <div class="sidebar__group-label">{{ group.label | translate }}</div>
            @for (item of group.items; track item.label) {
              <a
                class="sidebar__link"
                [class.sidebar__link--active]="isActive(item)"
                [attr.data-accent]="isActive(item) ? accentFor(item) : null"
                [routerLink]="item.route"
                [attr.title]="collapsed() ? (item.label | translate) : null"
              >
                <svg class="sidebar__link-icon" aria-hidden="true">
                  <use [attr.href]="'assets/icons/sprite.svg#tabler-' + item.icon" />
                </svg>
                <span class="sidebar__link-label">{{ item.label | translate }}</span>
              </a>
            }
          }
        </nav>
        <button
          type="button"
          class="sidebar__link sidebar__toggle"
          (click)="toggle()"
          [attr.aria-label]="(collapsed() ? 'nav.expand' : 'nav.collapse') | translate"
          [attr.title]="collapsed() ? ('nav.expand' | translate) : null"
        >
          <!-- right-side RTL sidebar: expanded collapses toward the right edge, collapsed expands into content -->
          <svg class="sidebar__link-icon" aria-hidden="true">
            <use [attr.href]="'assets/icons/sprite.svg#tabler-chevron-' + (collapsed() ? 'left' : 'right')" />
          </svg>
          <span class="sidebar__link-label">{{ (collapsed() ? 'nav.expand' : 'nav.collapse') | translate }}</span>
        </button>
      </aside>

      <div class="app-shell__main">
        @if (menuOpen()) {
        <div class="sidebar-backdrop" (click)="menuOpen.set(false)" aria-hidden="true"></div>
      }
      <app-topbar (menuToggle)="menuOpen.set(!menuOpen())" />
        <main class="app-shell__content" id="main-content" tabindex="-1">
          <router-outlet />
        </main>
      </div>
    </div>

    <!-- orientation lock: phones only — desktops and tablets are unaffected -->
    <div class="orientation-lock" dir="rtl" aria-hidden="false" role="alert">
      <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="7" y="3" width="10" height="18" rx="2" />
        <path d="M2 12a10 10 0 0 1 3.5-7.6" />
        <path d="M2 12a10 10 0 0 0 3.5 7.6" />
        <path d="M2 12h4" />
      </svg>
      <p class="orientation-lock__title">{{ 'a11y.portrait_only' | translate }}</p>
      <p class="orientation-lock__text">{{ 'a11y.rotate_device' | translate }}</p>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ShellComponent {
  private translate = inject(TranslateService);
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);
  private router = inject(Router);

  /** mobile drawer (phones): the sidebar becomes an overlay */
  readonly menuOpen = signal(false);

  readonly collapsed = signal(this.storage.get('sidebar.collapsed', false));
  readonly me = this.auth.me;

  readonly visibleGroups = computed(() => {
    const abilities = this.auth.abilities();
    if (!abilities) {
      return [];
    }
    return NAV_GROUPS.map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        if (item.hiddenWhen?.(abilities)) {
          return false;
        }
        if (!item.requires) {
          return true;
        }
        return this.auth.can(item.requires);
      }),
    })).filter((group) => group.items.length > 0);
  });

  readonly orgName = computed(() => this.auth.me()?.organization?.name || this.translate.instant('common.org_name'));

  /** current in-app path (no query/hash), reactive to navigation */
  private readonly currentPath = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map(() => this.router.url),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  /** exact match only — substring matching made /custodies light up on /custodies/tracking */
  isActive(item: NavItem): boolean {
    const path = this.currentPath().split('?')[0];
    return item.route === '/dashboard'
      ? path === '/dashboard' || path === '/'
      : item.route === path;
  }

  /**
   * T7-09: the module accent of a nav entry, derived from its route — the
   * active link's indicator and icon carry the module color.
   */
  accentFor(item: NavItem): string {
    const route = item.route ?? '';
    if (route.startsWith('/custodies') || route.startsWith('/transfers')) return 'custodies';
    if (route.startsWith('/expenses')) return 'expenses';
    if (route.startsWith('/treasury')) return 'treasury';
    if (route.startsWith('/cases')) return 'cases';
    if (route.startsWith('/hr')) return 'hr';
    if (route.startsWith('/tasks') || route.startsWith('/chat')) return 'tasks';
    if (route.startsWith('/procurement')) return 'procurement';
    if (route.startsWith('/reports')) return 'reports';
    return '';
  }

  constructor() {
    // navigating from the drawer closes it on phones
    effect(() => {
      this.currentPath();
      this.menuOpen.set(false);
    });

    // best-effort OS-level lock; browsers require fullscreen — CSS overlay covers the rest
    const orientation = screen.orientation as ScreenOrientation & { lock?: (o: string) => Promise<void> };
    orientation?.lock?.('portrait')?.catch(() => undefined);
  }

  toggle(): void {
    this.collapsed.update((value) => !value);
    this.storage.set('sidebar.collapsed', this.collapsed());
  }
}
