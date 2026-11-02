import { Abilities } from '@shared/models/api.models';

export interface NavItem {
  label: string; // i18n key under nav.*
  icon: string; // tabler icon name
  route?: string;
  children?: NavItem[];
  /** null = always visible; otherwise one of the /me abilities */
  requires?: string | null;
  /** hide when true (e.g. supervisor read-only exclusions) */
  hiddenWhen?: (abilities: Abilities) => boolean;
  /** bottom-nav priority (the 5 most-used items per role); lower = earlier */
  mobilePriority?: number;
}

export interface NavGroup {
  label: string; // i18n key under nav.groups.*
  items: NavItem[];
}

/**
 * Navigation by role (plan 19.3) — derived from the current sidebar guards.
 * The supervisor never sees a data-changing action.
 */
export const NAV_GROUPS: NavGroup[] = [
  {
    label: 'nav.groups.main',
    items: [{ label: 'nav.dashboard', icon: 'layout-dashboard', route: '/dashboard', mobilePriority: 0 }],
  },
  {
    label: 'nav.groups.accounts',
    items: [
      {
        label: 'nav.treasury',
        icon: 'building-bank',
        route: '/treasury',
        requires: 'may_manage_treasury',
      },
      { label: 'nav.custodies', icon: 'wallet', route: '/custodies', requires: 'may_manage_treasury' },
      { label: 'nav.custody_tracking', icon: 'eye', route: '/custodies/tracking', requires: 'may_view_all_records' },
      { label: 'nav.custody_movements', icon: 'arrows-exchange', route: '/custodies/movements', requires: 'may_view_all_records' },
      { label: 'nav.expenses', icon: 'receipt-2', route: '/expenses', requires: 'may_view_all_expenses', mobilePriority: 2 },
      { label: 'nav.directions', icon: 'sitemap', route: '/directions', requires: 'may_manage_expense_items' },
      { label: 'nav.my_custodies', icon: 'wallet', route: '/custodies/mine', mobilePriority: 1 },
      { label: 'nav.my_expenses', icon: 'receipt', route: '/expenses/mine' },
      { label: 'nav.my_movements', icon: 'history', route: '/custodies/mine/movements' },
    ],
  },
  {
    label: 'nav.groups.reports',
    items: [
      { label: 'nav.reports', icon: 'report-analytics', route: '/reports', requires: 'may_manage_treasury' },
    ],
  },
  {
    label: 'nav.groups.cases',
    items: [
      { label: 'nav.cases', icon: 'heart-handshake', route: '/cases' },
      { label: 'nav.my_cases', icon: 'user', route: '/cases/mine' },
    ],
  },
  {
    label: 'nav.groups.procurement',
    items: [
      { label: 'nav.purchase_requests', icon: 'shopping-cart', route: '/procurement/purchase', mobilePriority: 3 },
      { label: 'nav.maintenance_requests', icon: 'tool', route: '/procurement/maintenance' },
      { label: 'nav.suppliers', icon: 'truck-delivery', route: '/procurement/suppliers', requires: 'may_manage_treasury' },
    ],
  },
  {
    label: 'nav.groups.hr',
    items: [{ label: 'nav.hr', icon: 'briefcase', route: '/hr', requires: 'may_manage_treasury' }],
  },
  {
    label: 'nav.groups.work',
    items: [
      { label: 'nav.tasks', icon: 'list-check', route: '/tasks' },
      { label: 'nav.chat', icon: 'message-circle', route: '/chat', mobilePriority: 4 },
    ],
  },
  {
    label: 'nav.groups.admin',
    items: [
      { label: 'nav.activity', icon: 'history', route: '/activity', requires: 'may_view_all_records' },
      { label: 'nav.users', icon: 'users-group', route: '/users', requires: 'may_manage_users' },
      { label: 'nav.broadcasts', icon: 'speakerphone', route: '/broadcasts', requires: 'is_manager' },
      { label: 'nav.settings', icon: 'settings', route: '/settings', requires: 'may_manage_settings' },
    ],
  },
];
