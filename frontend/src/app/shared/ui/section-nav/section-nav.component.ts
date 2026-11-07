import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { TranslatePipe } from '@ngx-translate/core';
import { AuthService } from '@core/auth/auth.service';

/** One entry of a module's section nav (T6-10). */
export interface SectionNavItem {
  /** i18n key */
  readonly label: string;
  readonly route: string;
  /** ability required to open (same rule as the sidebar) */
  readonly requires?: string;
  readonly icon?: string;
}

/**
 * The module sub-navigation (T6-10): a route-driven tab bar under the page
 * header, one per module — cross-page links become navigation, not buttons.
 * Entries the user may not open are hidden (same visibility rules as the
 * sidebar).
 */
const SECTION_NAVS: Record<string, SectionNavItem[]> = {
  custodies: [
    { label: 'nav.custodies', route: '/custodies', requires: 'may_manage_treasury', icon: 'wallet' },
    { label: 'nav.my_custodies', route: '/custodies/mine', icon: 'wallet' },
    { label: 'nav.custody_tracking', route: '/custodies/tracking', requires: 'may_view_all_records', icon: 'eye' },
    { label: 'nav.custody_movements', route: '/custodies/movements', requires: 'may_view_all_records', icon: 'arrows-exchange' },
    { label: 'custody.return_requests.title', route: '/custodies/return-requests', requires: 'may_view_all_records', icon: 'arrow-back-up' },
    { label: 'nav.transfers', route: '/transfers', icon: 'arrows-exchange' },
  ],
  expenses: [
    { label: 'nav.expenses', route: '/expenses', requires: 'may_view_all_expenses', icon: 'receipt-2' },
    { label: 'nav.my_expenses', route: '/expenses/mine', icon: 'receipt' },
    { label: 'expense.edit_requests.title', route: '/expenses/edit-requests', requires: 'may_view_all_expenses', icon: 'pencil' },
  ],
  treasury: [
    { label: 'nav.treasury', route: '/treasury', requires: 'may_manage_treasury', icon: 'building-bank' },
    { label: 'treasury.transfer.title', route: '/treasury/transfer', requires: 'may_manage_treasury', icon: 'arrows-exchange' },
  ],
  hr: [
    { label: 'nav.hr', route: '/hr', requires: 'may_manage_treasury', icon: 'briefcase' },
    { label: 'hr.employees.title', route: '/hr/employees', requires: 'may_manage_treasury' },
    { label: 'hr.attendance.title', route: '/hr/attendance', requires: 'may_manage_treasury' },
    { label: 'hr.kpi.title', route: '/hr/kpi', requires: 'may_manage_treasury' },
    { label: 'hr.salaries.title', route: '/hr/salaries', requires: 'may_manage_treasury' },
    { label: 'hr.salaries.history_title', route: '/hr/salary-history', requires: 'may_manage_treasury' },
  ],
  procurement: [
    { label: 'nav.purchase_requests', route: '/procurement/purchase', icon: 'shopping-cart' },
    { label: 'nav.maintenance_requests', route: '/procurement/maintenance', icon: 'tool' },
    { label: 'nav.suppliers', route: '/procurement/suppliers', requires: 'may_manage_treasury', icon: 'truck-delivery' },
  ],
  cases: [
    { label: 'nav.cases', route: '/cases', icon: 'heart-handshake' },
    { label: 'nav.my_cases', route: '/cases/mine', icon: 'user' },
  ],
};

@Component({
  selector: 'app-section-nav',
  standalone: true,
  imports: [RouterLink, RouterLinkActive, TranslatePipe],
  template: `
    <nav class="section-nav" [class]="'section-nav--' + module()" [attr.aria-label]="module() | translate">
      @for (item of items(); track item.route) {
        <a
          class="section-nav__link"
          [routerLink]="item.route"
          routerLinkActive="section-nav__link--active"
          [routerLinkActiveOptions]="{ exact: true }"
        >
          {{ item.label | translate }}
        </a>
      }
    </nav>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SectionNavComponent {
  /** module key: custodies | expenses | treasury | hr | procurement | cases */
  module = input.required<string>();

  private auth = inject(AuthService);

  items = computed(() => {
    const nav = SECTION_NAVS[this.module()] ?? [];
    return nav.filter((item) => !item.requires || this.auth.can(item.requires));
  });
}
