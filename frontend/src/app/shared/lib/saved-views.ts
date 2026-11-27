import { isFilterActive, type TableFilterValue } from './table-filters';

/**
 * Saved views (N-02): the table's filters, search, sort and visible columns
 * kept under a name. Pure — the table owns the storage and the user; this
 * file owns the rules: what a view holds, when two states are the same view,
 * and the limit.
 */

/** the part of a table's state a view holds */
export interface ViewSnapshot {
  readonly filters: Readonly<Record<string, TableFilterValue | undefined>>;
  /** the search box */
  readonly q: string;
  /** the server sort, as the table sends it: "-amount" or "created_at" */
  readonly sort: string;
  readonly hiddenColumns: readonly string[];
}

export interface SavedView extends ViewSnapshot {
  readonly id: string;
  readonly name: string;
  readonly savedAt: number;
}

export interface SavedViews {
  readonly views: readonly SavedView[];
  /** the view a fresh visit of the table starts with; null = the plain table */
  readonly defaultId: string | null;
  /** the view applied last — the chip shows it, and says when the table moved away from it */
  readonly activeId: string | null;
}

export const MAX_SAVED_VIEWS = 10;
/** a view's name is this long at most */
export const MAX_VIEW_NAME_LENGTH = 40;

export type NameProblem = 'empty' | 'taken' | 'limit';

export type SaveViewResult =
  | { readonly ok: true; readonly state: SavedViews; readonly view: SavedView }
  | { readonly ok: false; readonly problem: NameProblem };

export function emptyViews(): SavedViews {
  return { views: [], defaultId: null, activeId: null };
}

/**
 * The storage key. `tableStateKey` is the table's own state key (its page
 * route + table key): every list calls its table "list.component", so the
 * bare table key would make one page's views appear on another.
 */
export function viewsStorageKey(tableStateKey: string, userId: number | string | null | undefined): string {
  const user = userId === null || userId === undefined || userId === '' ? 'anonymous' : String(userId);
  return `table.${tableStateKey}.views.${user}`;
}

function newViewId(): string {
  return 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
}

/** one filter value in its one canonical form: ranges lose their empty parts and keep a stable key order */
function canonicalValue(value: Exclude<TableFilterValue, null>): unknown {
  if (Array.isArray(value)) {
    return value;
  }
  if (typeof value === 'object') {
    const entries = Object.entries(value as Record<string, unknown>)
      .filter(([, part]) => part !== undefined && part !== null && part !== '')
      .sort(([a], [b]) => a.localeCompare(b));
    return Object.fromEntries(entries);
  }
  return value;
}

/** is this filter set? narrows the value to what `canonicalValue` takes */
function isSetFilter(value: TableFilterValue | undefined): value is Exclude<TableFilterValue, null | undefined> {
  return isFilterActive(value);
}

/** the snapshot as one comparable string — only what filters, and always in the same order */
export function snapshotKey(snapshot: ViewSnapshot): string {
  const filters: Record<string, unknown> = {};
  const entries = Object.entries(snapshot.filters)
    .filter(([, value]) => isFilterActive(value))
    .sort(([a], [b]) => a.localeCompare(b));
  for (const [key, value] of entries) {
    // narrowed here, not in the filter above: a guard on a destructured parameter does not refine the entry
    if (isSetFilter(value)) {
      filters[key] = canonicalValue(value);
    }
  }
  const columns = [...new Set(snapshot.hiddenColumns)].sort();
  return JSON.stringify([filters, snapshot.q.trim(), snapshot.sort, columns]);
}

/** is the table exactly in this view's state? */
export function sameView(a: ViewSnapshot, b: ViewSnapshot): boolean {
  return snapshotKey(a) === snapshotKey(b);
}

export function findView(state: SavedViews, id: string | null): SavedView | null {
  if (id === null) {
    return null;
  }
  return state.views.find((view) => view.id === id) ?? null;
}

/** the view on the chip, and whether the table moved away from it */
export function activeView(state: SavedViews, current: ViewSnapshot): { view: SavedView; edited: boolean } | null {
  const view = findView(state, state.activeId);
  return view === null ? null : { view, edited: !sameView(view, current) };
}

function trimmed(name: string): string {
  return name.trim().replace(/\s+/g, ' ').slice(0, MAX_VIEW_NAME_LENGTH);
}

/** can a view be saved under this name? The first problem, or null */
export function nameProblem(state: SavedViews, name: string): NameProblem | null {
  const clean = trimmed(name);
  if (clean === '') {
    return 'empty';
  }
  if (state.views.some((view) => view.name.toLocaleLowerCase() === clean.toLocaleLowerCase())) {
    return 'taken';
  }
  return state.views.length >= MAX_SAVED_VIEWS ? 'limit' : null;
}

export function saveView(
  state: SavedViews,
  snapshot: ViewSnapshot,
  name: string,
  now: number = Date.now(),
  makeId: () => string = newViewId,
): SaveViewResult {
  const problem = nameProblem(state, name);
  if (problem !== null) {
    return { ok: false, problem };
  }
  const view: SavedView = {
    id: makeId(),
    name: trimmed(name),
    savedAt: now,
    filters: { ...snapshot.filters },
    q: snapshot.q.trim(),
    sort: snapshot.sort,
    hiddenColumns: [...new Set(snapshot.hiddenColumns)],
  };
  return { ok: true, state: { ...state, views: [...state.views, view], activeId: view.id }, view };
}

export function renameView(state: SavedViews, id: string, name: string): SavedViews {
  const problem = nameProblem({ ...state, views: state.views.filter((view) => view.id !== id) }, name);
  if (problem !== null) {
    return state;
  }
  return { ...state, views: state.views.map((view) => (view.id === id ? { ...view, name: trimmed(name) } : view)) };
}

export function deleteView(state: SavedViews, id: string): SavedViews {
  if (!state.views.some((view) => view.id === id)) {
    return state;
  }
  const views = state.views.filter((view) => view.id !== id);
  return {
    views,
    defaultId: state.defaultId === id ? null : state.defaultId,
    activeId: state.activeId === id ? null : state.activeId,
  };
}

/** which view a fresh visit starts with; null goes back to the plain table */
export function setDefaultView(state: SavedViews, id: string | null): SavedViews {
  return { ...state, defaultId: id };
}

export function setActiveView(state: SavedViews, id: string | null): SavedViews {
  return { ...state, activeId: id };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * What was stored, as far as it is well-formed. Anything else is dropped —
 * a view without an id or a name, a list that is not a list — so a damaged
 * or hand-edited entry can never break the table. Never throws.
 */
export function parseViews(raw: unknown): SavedViews {
  if (!isRecord(raw) || !Array.isArray(raw.views)) {
    return emptyViews();
  }
  const views: SavedView[] = [];
  const ids = new Set<string>();
  const names = new Set<string>();
  for (const entry of raw.views) {
    if (!isRecord(entry) || typeof entry.id !== 'string' || entry.id === '' || typeof entry.name !== 'string' || trimmed(entry.name) === '') {
      continue;
    }
    const lower = trimmed(entry.name).toLocaleLowerCase();
    if (ids.has(entry.id) || names.has(lower) || views.length >= MAX_SAVED_VIEWS) {
      continue;
    }
    const filters: Record<string, TableFilterValue | undefined> = {};
    if (isRecord(entry.filters)) {
      for (const [key, value] of Object.entries(entry.filters)) {
        if (value === null || value === undefined || typeof value === 'string' || typeof value === 'boolean' || Array.isArray(value) || isRecord(value)) {
          filters[key] = value as TableFilterValue;
        }
      }
    }
    ids.add(entry.id);
    names.add(lower);
    views.push({
      id: entry.id,
      name: trimmed(entry.name),
      savedAt: typeof entry.savedAt === 'number' ? entry.savedAt : 0,
      filters,
      q: typeof entry.q === 'string' ? entry.q : '',
      sort: typeof entry.sort === 'string' ? entry.sort : '',
      hiddenColumns: Array.isArray(entry.hiddenColumns) ? entry.hiddenColumns.filter((field): field is string => typeof field === 'string') : [],
    });
  }
  const pick = (value: unknown): string | null => (typeof value === 'string' && ids.has(value) ? value : null);
  return {
    views,
    defaultId: pick(raw.defaultId),
    activeId: pick(raw.activeId),
  };
}
