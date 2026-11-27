import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { HttpClient } from '@angular/common/http';
import { NavigationEnd, Router, RouterLink, RouterOutlet } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { map, startWith, filter } from 'rxjs';
import { TranslatePipe, TranslateService } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';
import { LocalStorageService } from '@core/storage/local-storage.service';
import { iconSymbol } from '@shared/ui/icon/icon-symbol';
import type { NavItem } from './nav-items';
import { NAV_GROUPS } from './nav-items';
import { TopbarComponent } from '../topbar/topbar.component';
import { ToastHostComponent } from '@shared/ui/toast-host/toast-host.component';
import type { ItemResponse } from '@shared/models/api.models';

/** /lookups attention_counts (D-01 badges) — null for viewers */
interface AttentionCounts {
  pending_custodies: number;
  pending_edit_requests: number;
}

/**
 * App shell (plan 19 + D-01): right-side RTL sidebar (256px / 64 collapsed),
 * 64px top bar; phones get a drawer + bottom bar. The sidebar is the Stitch
 * _2 aside: 4 groups, ~40px rows with 28px icon tiles, accordion children,
 * and a BRAND-BROWN active state (primary-container row + filled tile) —
 * never per-module accent colours.
 */
@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, TranslatePipe, TopbarComponent, ToastHostComponent],
  template: `
    <div class="app-shell">
      <a class="skip-link" href="#main-content">{{ 'a11y.skip_to_content' | translate }}</a>
      <aside class="sidebar" [class.sidebar--collapsed]="collapsed()" [class.sidebar--open]="menuOpen()">
        <div class="sidebar__brand">
          <span class="sidebar__brand-mark" aria-hidden="true">NS</span>
          <div class="sidebar__brand-text">
            <span class="sidebar__brand-name">{{ orgName() }}</span>
            <span class="sidebar__brand-subtitle">{{ 'nav.brand_tagline' | translate }}</span>
          </div>
        </div>
        <nav [attr.aria-label]="'nav.main_aria' | translate">
          @for (group of visibleGroups(); track group.label) {
            <div class="sidebar__group-label">{{ group.label | translate }}</div>
            @for (item of group.items; track item.label) {
              @if (item.children && item.children.length > 0) {
                <div class="sidebar__branch" [class.sidebar__branch--open]="isOpen(item)">
                  <div class="sidebar__branch-row">
                    @if (item.route; as route) {
                      <a
                        class="sidebar__link"
                        [class.sidebar__link--active]="isActive(item)"
                        [attr.aria-current]="isExact(item) ? 'page' : null"
                        [routerLink]="route"
                        [attr.title]="collapsed() ? (item.label | translate) : null"
                      >
                        <span class="sidebar__link-tile">
                          <svg class="sidebar__link-icon" aria-hidden="true">
                            <use [attr.href]="iconHref(item.icon)" />
                          </svg>
                        </span>
                        <span class="sidebar__link-label">{{ item.label | translate }}</span>
                      </a>
                    } @else {
                      <button type="button" class="sidebar__link" (click)="toggleOpen(item)">
                        <span class="sidebar__link-tile">
                          <svg class="sidebar__link-icon" aria-hidden="true">
                            <use [attr.href]="iconHref(item.icon)" />
                          </svg>
                        </span>
                        <span class="sidebar__link-label">{{ item.label | translate }}</span>
                      </button>
                    }
                    @if (badgeFor(item); as badge) {
                      <span class="sidebar__badge u-bidi">{{ badge }}</span>
                    }
                    <button
                      type="button"
                      class="sidebar__chevron"
                      (click)="toggleOpen(item)"
                      [attr.aria-expanded]="isOpen(item)"
                      [attr.aria-label]="item.label | translate"
                    >
                      <svg class="sidebar__chevron-icon" aria-hidden="true">
                        <use [attr.href]="iconHref('chevronDown')" />
                      </svg>
                    </button>
                  </div>
                  @if (isOpen(item)) {
                    <div class="sidebar__children">
                      @for (child of item.children; track child.label) {
                        <a
                          class="sidebar__link sidebar__link--child"
                          [class.sidebar__link--active]="isActive(child)"
                          [attr.aria-current]="isExact(child) ? 'page' : null"
                          [routerLink]="child.route!"
                        >
                          <span class="sidebar__link-tile">
                            <svg class="sidebar__link-icon" aria-hidden="true">
                              <use [attr.href]="iconHref(child.icon)" />
                            </svg>
                          </span>
                          <span class="sidebar__link-label">{{ child.label | translate }}</span>
                          @if (badgeFor(child); as badge) {
                            <span class="sidebar__badge u-bidi">{{ badge }}</span>
                          }
                        </a>
                      }
                    </div>
                  }
                </div>
              } @else {
                <a
                  class="sidebar__link"
                  [class.sidebar__link--active]="isActive(item)"
                  [attr.aria-current]="isExact(item) ? 'page' : null"
                  [routerLink]="item.route"
                  [attr.title]="collapsed() ? (item.label | translate) : null"
                >
                  <span class="sidebar__link-tile">
                    <svg class="sidebar__link-icon" aria-hidden="true">
                      <use [attr.href]="iconHref(item.icon)" />
                    </svg>
                  </span>
                  <span class="sidebar__link-label">{{ item.label | translate }}</span>
                  @if (badgeFor(item); as badge) {
                    <span class="sidebar__badge u-bidi">{{ badge }}</span>
                  }
                </a>
              }
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
          <span class="sidebar__link-tile">
            <svg class="sidebar__link-icon" aria-hidden="true">
              <use [attr.href]="iconHref(collapsed() ? 'chevronLeft' : 'chevronRight')" />
            </svg>
          </span>
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

        <!-- X-06: field agents record expenses on phones — a labelled
             floating button (icon + "تسجيل مصروف"), PHONES ONLY. The first
             version had an invisible icon and showed at every width under
             1024px, where it floated over the page as a mystery circle. -->
        @if (canQuickExpense()) {
          <a class="agent-fab" routerLink="/expenses/new">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <use [attr.href]="iconHref('add')" />
            </svg>
            <span class="agent-fab__label">{{ 'dashboard.agent.quick_expense' | translate }}</span>
          </a>
        }
      </div>
    </div>

    <!-- the one toast host for the whole app (was never mounted — toasts vanished) -->
    <app-toast-host />
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ShellComponent {
  private translate = inject(TranslateService);
  private auth = inject(AuthService);
  private storage = inject(LocalStorageService);
  private router = inject(Router);
  private http = inject(HttpClient);

  /** mobile drawer (phones): the sidebar becomes an overlay */
  readonly menuOpen = signal(false);

  /** X-06: the quick-expense FAB shows for spenders (agents) on phones */
  readonly canQuickExpense = computed(() => this.auth.can('spend_money'));

  readonly collapsed = signal(this.storage.get('sidebar.collapsed', false));
  readonly me = this.auth.me;

  /** D-01: accordion parents remembered open across sessions (by label key) */
  private readonly openParents = signal<ReadonlySet<string>>(
    new Set(this.storage.get<string[]>('sidebar.openParents', ['/custodies'])),
  );

  /** D-01 badge counts — one cacheable /lookups call, refreshed with the shell */
  readonly attentionCounts = signal<AttentionCounts | null>(null);

  /** every visible item flattened WITH its children (active + badges) */
  private visibleItemsFlat = computed<NavItem[]>(() =>
    this.visibleGroups().flatMap((group) =>
      group.items.flatMap((item) => [item, ...(item.children ?? [])]),
    ),
  );

  readonly visibleGroups = computed(() => {
    const abilities = this.auth.abilities();
    if (!abilities) {
      return [];
    }
    const canSee = (item: NavItem): boolean => {
      if (item.hiddenWhen?.(abilities)) {
        return false;
      }
      if (!item.requires) {
        return true;
      }
      return this.auth.can(item.requires);
    };
    return NAV_GROUPS.map((group) => ({
      ...group,
      items: group.items
        .map((item) => ({ ...item, children: (item.children ?? []).filter(canSee) }))
        // a parent shows when IT is visible or any child is (مصروفاتي for agents)
        .filter((item) => canSee(item) || (item.children?.length ?? 0) > 0),
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

  /**
   * B-10: longest-prefix match over every visible item (children included) —
   * exactly ONE highlighted link per route. `/custodies/tracking` beats
   * `/custodies`; `/custodies/101` highlights "العهد".
   */
  readonly activeItem = computed<NavItem | null>(() => {
    const path = this.currentPath().split('?')[0];
    let best: NavItem | null = null;
    for (const item of this.visibleItemsFlat()) {
      const route = item.route ?? '';
      if (route === '') {
        continue;
      }
      const matches =
        route === '/dashboard'
          ? path === '/dashboard' || path === '/'
          : path === route || path.startsWith(route + '/');
      if (matches && (best === null || route.length > (best.route?.length ?? 0))) {
        best = item;
      }
    }
    return best;
  });

  isActive(item: NavItem): boolean {
    return this.activeItem() === item;
  }

  /** aria-current="page" only on the exact match, not the section parent */
  isExact(item: NavItem): boolean {
    const path = this.currentPath().split('?')[0];
    return this.isActive(item) && (path === item.route || (item.route === '/dashboard' && path === '/'));
  }

  /** accordion open state — parents are keyed by their label */
  isOpen(item: NavItem): boolean {
    return this.openParents().has(item.label);
  }

  toggleOpen(item: NavItem): void {
    const next = new Set(this.openParents());
    if (next.has(item.label)) {
      next.delete(item.label);
    } else {
      next.add(item.label);
    }
    this.openParents.set(next);
    this.storage.set('sidebar.openParents', [...next]);
  }

  /** D-01: the numeric badge of an item, when its queue is non-empty */
  badgeFor(item: NavItem): number | null {
    const counts = this.attentionCounts();
    if (!counts || !item.badgeKey) {
      return null;
    }
    const value = counts[item.badgeKey];
    return value > 0 ? value : null;
  }

  constructor() {
    // navigating from the drawer closes it on phones
    effect(() => {
      this.currentPath();
      this.menuOpen.set(false);
    });

    // the active child auto-opens its parent accordion (once, on load)
    effect(() => {
      const active = this.activeItem();
      if (!active) {
        return;
      }
      for (const item of this.visibleGroups().flatMap((g) => g.items)) {
        if ((item.children ?? []).some((child) => child.route === active.route) && !this.openParents().has(item.label)) {
          this.toggleOpen(item);
        }
      }
    });

    // D-01 badges — /lookups is cacheable; keep a 60s sessionStorage copy so
    // full page loads (and the e2e matrix) do not hammer the endpoint
    const cacheKey = 'sidebar.attention_counts';
    const cached = this.storage.get<{ at: number; counts: AttentionCounts } | null>(cacheKey, null);
    if (cached && Date.now() - cached.at < 60_000) {
      this.attentionCounts.set(cached.counts);
    } else {
      void firstValueFrom(this.http.get<ItemResponse<{ attention_counts: AttentionCounts | null }>>('/lookups'))
        .then((res) => {
          const counts = res.data?.attention_counts ?? null;
          this.attentionCounts.set(counts);
          if (counts) {
            this.storage.set(cacheKey, { at: Date.now(), counts });
          }
        })
        .catch(() => { return undefined; });
    }
  }

  toggle(): void {
    this.collapsed.update((value) => !value);
    this.storage.set('sidebar.collapsed', this.collapsed());
  }

  /** sprite href for an icon name (kept simple — inline concatenation
   *  in the template trips an esbuild incremental-cache quirk, NG8002) */
  iconHref(icon: string): string {
    return `assets/icons/sprite.svg#tabler-${iconSymbol(icon)}`;
  }
}
