/**
 * Table-driven filters (Task6 T6-30): pages stop building filter bars. A page
 * passes column definitions — or receives them from the API's `meta.filters` —
 * and the table draws the filter UI and runs it. All JSON, no page-specific
 * shapes.
 */

export type TableFilterType =
  | 'text'
  | 'select'
  | 'multiselect'
  | 'boolean'
  | 'number-range'
  | 'date-range'
  | 'month'
  | 'lookup'
  | 'custom';

export interface TableFilterOption {
  value: unknown;
  label: string;
}

export interface TableFilterDef {
  /** query key, e.g. "status" — also the URL key filter[status]=… */
  key: string;
  /** i18n key */
  label: string;
  type: TableFilterType;
  /** column field it relates to (default = key) */
  field?: string;
  /** static options */
  options?: TableFilterOption[];
  /** derive options: from the loaded rows, a named lookup, or API meta */
  optionsFrom?: 'rows' | 'lookup:<name>' | 'meta';
  /** initial value (applied until the user changes it) */
  default?: unknown;
  /** keep in URL/state but do not render a control */
  hidden?: boolean;
  order?: number;
}

/** a filter's current value: text, values array, {min,max}, {from,to} or boolean */
export type TableFilterValue = string | unknown[] | { min?: number; max?: number } | { from?: string; to?: string } | boolean | null;

/** what the table emits and what the server receives (T6-30 contract) */
export interface TableQuery {
  page: number;
  perPage: number;
  sort: string;
  q: string;
  filters: Record<string, unknown>;
}

export function isFilterActive(value: TableFilterValue | undefined): boolean {
  if (value === undefined || value === null || value === '') {
    return false;
  }
  if (Array.isArray(value)) {
    return value.length > 0;
  }
  if (typeof value === 'object') {
    return Object.values(value as Record<string, unknown>).some((v) => v !== undefined && v !== null && v !== '');
  }
  if (typeof value === 'boolean') {
    return value !== null;
  }
  return true;
}

/** number of active filters (for the badge) */
export function activeFilterCount(filters: Record<string, TableFilterValue | undefined>, defs: TableFilterDef[]): number {
  return defs.filter((d) => !d.hidden && isFilterActive(filters[d.key])).length;
}

/** the default filter state from definitions */
export function defaultFilterValues(defs: TableFilterDef[]): Record<string, TableFilterValue | undefined> {
  const out: Record<string, TableFilterValue | undefined> = {};
  for (const def of defs) {
    out[def.key] = def.default as TableFilterValue | undefined;
  }
  return out;
}

/**
 * The generic static-mode filter engine: null-safe, dot-path aware,
 * locale-aware text compare. One implementation of contains / equals / in /
 * range / date-range / boolean for every static table.
 */
export function filterRows<T>(rows: readonly T[], filters: Record<string, TableFilterValue | undefined>, defs: readonly TableFilterDef[]): T[] {
  const active = defs.filter((d) => isFilterActive(filters[d.key]));
  if (active.length === 0) {
    return [...rows];
  }
  return rows.filter((row) => active.every((def) => rowMatches(row, filters[def.key], def)));
}

function rowMatches(row: unknown, value: TableFilterValue | undefined, def: TableFilterDef): boolean {
  const cell = fieldValue(row, def.field ?? def.key);
  switch (def.type) {
    case 'text':
      return textOf(cell).includes(String(value).trim().toLowerCase());
    case 'select':
    case 'lookup':
      return equalsLoose(cell, value);
    case 'multiselect':
      return Array.isArray(value) && value.some((v) => equalsLoose(cell, v));
    case 'boolean':
      return Boolean(cell) === Boolean(value);
    case 'number-range': {
      const range = value as { min?: number; max?: number } | undefined;
      const n = Number.parseFloat(String(cell ?? ''));
      if (!Number.isFinite(n)) {
        return false;
      }
      if (range?.min !== undefined && range.min !== null && n < range.min) {
        return false;
      }
      if (range?.max !== undefined && range.max !== null && n > range.max) {
        return false;
      }
      return true;
    }
    case 'date-range':
    case 'month': {
      const range = value as { from?: string; to?: string } | undefined;
      const raw = String(cell ?? '');
      const date = raw.slice(0, 10);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
        return false;
      }
      if (range?.from && date < range.from) {
        return false;
      }
      if (range?.to && date > range.to) {
        return false;
      }
      return true;
    }
    default:
      return true;
  }
}

function textOf(value: unknown): string {
  if (value === null || value === undefined || typeof value === 'object') {
    return '';
  }
  return String(value).toLowerCase();
}

function equalsLoose(a: unknown, b: unknown): boolean {
  return String(a) === String(b);
}

/** dot-path lookup: "agent.name" → row.agent?.name */
export function fieldValue(row: unknown, field: string): unknown {
  return field.split('.').reduce<unknown>((acc, part) => {
    if (acc !== null && acc !== undefined && typeof acc === 'object') {
      return (acc as Record<string, unknown>)[part];
    }
    return undefined;
  }, row);
}

/** derive filter definitions from columns + row values (autoFilters, T6-30) */
export function deriveFilters(columns: readonly { field: string; header: string; sortable?: boolean; numeric?: boolean }[], rows: readonly unknown[]): TableFilterDef[] {
  const defs: TableFilterDef[] = [];
  for (const col of columns) {
    if (col.field === 'actions') {
      continue;
    }
    const values = rows.map((r) => fieldValue(r, col.field));
    const nonNull = values.filter((v) => v !== null && v !== undefined);
    if (nonNull.length === 0) {
      continue;
    }
    const allNumbers = nonNull.every((v) => typeof v === 'number' || /^-?\d+(\.\d+)?$/.test(String(v)));
    const allDates = nonNull.every((v) => /^\d{4}-\d{2}-\d{2}/.test(String(v)));
    if (allNumbers && !allDates) {
      defs.push({ key: col.field, label: col.header, type: 'number-range', order: defs.length });
    } else if (allDates) {
      defs.push({ key: col.field, label: col.header, type: 'date-range', order: defs.length });
    } else {
      const distinct = [...new Set(nonNull.map((v) => String(v)))];
      if (distinct.length > 0 && distinct.length <= 12) {
        defs.push({
          key: col.field,
          label: col.header,
          type: 'select',
          options: distinct.sort((a, b) => a.localeCompare(b, 'ar')).map((v) => ({ value: v, label: v })),
          order: defs.length,
        });
      } else {
        defs.push({ key: col.field, label: col.header, type: 'text', order: defs.length });
      }
    }
  }
  return defs;
}
