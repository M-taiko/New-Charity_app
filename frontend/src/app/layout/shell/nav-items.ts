import type { Abilities } from '@shared/models/api.models';

export interface NavItem {
  label: string; // i18n key under nav.*
  icon: string; // icon-map key (unique across the whole tree, D-01)
  route?: string; // omit on pure accordion parents (e.g. الإدارة)
  children?: NavItem[];
  /** null = always visible; otherwise one of the /me abilities */
  requires?: string | null;
  /** hide when true (e.g. supervisor read-only exclusions) */
  hiddenWhen?: (abilities: Abilities) => boolean;
  /** bottom-nav priority (the 5 most-used items per role); lower = earlier */
  mobilePriority?: number;
  /** D-01: numeric badge source key from /lookups attention_counts */
  badgeKey?: 'pending_custodies' | 'pending_edit_requests';
}

export interface NavGroup {
  label: string; // i18n key under nav.groups.*
  items: NavItem[];
}

/**
 * Navigation (D-01, Stitch _2): FOUR groups — الرئيسية · الحسابات ·
 * الحالات والتقارير · الموارد والإدارة. "Mine" pages and admin screens
 * live under their module as accordion children instead of crowding the top
 * level, so every primary destination is visible without scrolling at 1080px.
 * Role visibility is preserved per item; a parent shows when it OR any of
 * its children is visible (مصروفاتي must survive for agents even when the
 * management expenses list is hidden). Every item has a UNIQUE icon.
 */
export const NAV_GROUPS: NavGroup[] = [
  {
    label: 'nav.groups.main',
    items: [{ label: 'nav.dashboard', icon: 'dashboard', route: '/dashboard', mobilePriority: 0 }],
  },
  {
    label: 'nav.groups.accounts',
    items: [
      {
        label: 'nav.treasury',
        icon: 'treasury',
        route: '/treasury',
        requires: 'may_manage_treasury',
      },
      {
        label: 'nav.custodies',
        icon: 'custody',
        route: '/custodies',
        requires: 'may_manage_treasury',
        badgeKey: 'pending_custodies',
        children: [
          { label: 'nav.custody_tracking', icon: 'view', route: '/custodies/tracking', requires: 'may_view_all_records' },
          { label: 'nav.custody_movements', icon: 'transfer', route: '/custodies/movements', requires: 'may_view_all_records' },
          { label: 'nav.my_custodies', icon: 'money', route: '/custodies/mine', mobilePriority: 1 },
          { label: 'nav.return_requests', icon: 'return', route: '/custodies/return-requests', requires: 'may_view_all_records' },
        ],
      },
      {
        label: 'nav.expenses',
        icon: 'expense',
        route: '/expenses',
        requires: 'may_view_all_expenses',
        mobilePriority: 2,
        children: [
          { label: 'nav.my_expenses', icon: 'myExpense', route: '/expenses/mine' },
          { label: 'nav.edit_requests', icon: 'edit', route: '/expenses/edit-requests', badgeKey: 'pending_edit_requests' },
        ],
      },
      { label: 'nav.directions', icon: 'direction', route: '/directions', requires: 'may_manage_expense_items' },
    ],
  },
  {
    label: 'nav.groups.cases_reports',
    items: [
      {
        label: 'nav.cases',
        icon: 'case',
        route: '/social-cases',
        children: [{ label: 'nav.my_cases', icon: 'user', route: '/social-cases/mine' }],
      },
      { label: 'nav.reports', icon: 'reports', route: '/reports', requires: 'may_manage_treasury' },
    ],
  },
  {
    label: 'nav.groups.resources',
    items: [
      {
        label: 'nav.procurement',
        icon: 'purchase',
        children: [
          { label: 'nav.purchase_requests', icon: 'file', route: '/procurement/purchase', mobilePriority: 3 },
          { label: 'nav.maintenance_requests', icon: 'maintenance', route: '/procurement/maintenance' },
          { label: 'nav.suppliers', icon: 'suppliers', route: '/procurement/suppliers', requires: 'may_manage_treasury' },
        ],
      },
      { label: 'nav.hr', icon: 'hr', route: '/hr', requires: 'may_manage_treasury' },
      {
        label: 'nav.work',
        icon: 'tasks',
        children: [
          { label: 'nav.tasks', icon: 'success', route: '/tasks' },
          { label: 'nav.chat', icon: 'chat', route: '/chat', mobilePriority: 4 },
        ],
      },
      {
        label: 'nav.admin',
        icon: 'settings',
        children: [
          { label: 'nav.activity', icon: 'activity', route: '/activity', requires: 'may_view_all_records' },
          { label: 'nav.users', icon: 'users', route: '/users', requires: 'may_manage_users' },
          { label: 'nav.broadcasts', icon: 'broadcast', route: '/broadcasts', requires: 'is_manager' },
          { label: 'nav.settings', icon: 'sliders', route: '/settings', requires: 'may_manage_settings' },
        ],
      },
    ],
  },
];
