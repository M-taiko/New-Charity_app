/** Shared API shapes — the list contract of plan 7.3. Money is always a 2-decimal string. */

export interface ListMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
  summary?: Record<string, unknown>;
}

export interface ListResponse<T> {
  data: T[];
  meta: ListMeta;
  links: { first: string; last: string; next: string | null; prev: string | null };
}

export interface ItemResponse<T> {
  data: T;
}

export interface ActionResponse {
  data: unknown;
  message: string;
  level?: 'success' | 'error' | 'warning' | 'info';
}

export interface ApiErrorBody {
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

export interface Me {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  profile_picture: string | null;
  roles: string[];
  permissions: string[];
  abilities: Abilities;
  organization: { name: string; logo: string | null; email: string | null };
  unread_notifications_count: number;
}

export interface Abilities {
  may_spend: boolean;
  may_manage_treasury: boolean;
  may_approve_custody: boolean;
  may_create_custody: boolean;
  may_transfer_custody: boolean;
  may_direct_spend: boolean;
  may_manage_expense_items: boolean;
  may_view_all_records: boolean;
  may_view_all_expenses: boolean;
  may_manage_users: boolean;
  may_manage_settings: boolean;
  may_manage_social_cases: boolean;
  may_review_social_case: boolean;
  may_add_comments: boolean;
  owns_custody: boolean;
  is_supervisor: boolean;
  is_agent: boolean;
  is_manager: boolean;
  is_accountant: boolean;
  is_researcher: boolean;
}

export interface Lookups {
  roles: string[];
  governorates: string[];
  statuses: Record<string, string[]>;
  treasuries: { id: number; name: string; balance: string }[];
  direction_tree: DirectionNode[];
  /** distinct values present in expenses.type (T31 expense filter) */
  expense_types: string[];
  users: { id: number; name: string; roles: string[] }[];
  agents: { id: number; name: string }[];
  researchers: { id: number; name: string }[];
}

export interface DirectionNode {
  id: number;
  name: string;
  level: number;
  order: number;
  children: DirectionNode[];
  items: DirectionItem[];
}

export interface DirectionItem {
  id: number;
  name: string;
  code: string | null;
  default_amount: string | null;
  is_active: boolean;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  sort?: string;
  q?: string;
  [key: string]: unknown;
}
