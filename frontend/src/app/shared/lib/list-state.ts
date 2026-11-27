/* ── N-08: list state that several pages need ────────────────────────────── */

/** keys of a list's parameters that are not filters */
const NOT_FILTERS: readonly string[] = ['page', 'per_page', 'sort'];

/** how often a refused reload looks whether the in-flight request has settled */
const SETTLE_POLL_MS = 30;

/** set = carrying a restriction: not undefined, null, empty, false or the "all" sentinel */
function isSet(value: unknown): boolean {
  if (value === undefined || value === null || value === '' || value === false || value === 'all') {
    return false;
  }
  if (Array.isArray(value)) {
    return value.length > 0;
  }
  if (typeof value === 'object') {
    return Object.values(value as Record<string, unknown>).some((part) => part !== undefined && part !== null && part !== '');
  }
  return true;
}

/**
 * Does a list's parameter set filter anything?
 *
 * The difference between "no data at all" and "no results for this filter"
 * (N-08): a value filters when it is set and differs from the default the
 * list starts from. Paging and sorting never count.
 */
export function paramsFiltered(params: Record<string, unknown>, defaults: Record<string, unknown> = {}): boolean {
  return Object.entries(params).some(([key, value]) => {
    if (NOT_FILTERS.includes(key) || !isSet(value)) {
      return false;
    }
    return JSON.stringify(value) !== JSON.stringify(defaults[key]);
  });
}

/** what a page's list resource offers for a reload: Angular's ResourceRef, or a wrapper with the same two */
export interface Reloadable {
  /** Angular's returns false when it ignored the call; a wrapper that returns nothing counts as accepted */
  reload: () => unknown;
  isLoading: () => boolean;
}

/**
 * Reload a list for sure — also while one of its requests is still in flight.
 *
 * Angular's `reload()` does not restart an in-progress load (its own comment:
 * "We don't want to restart in-progress loads"): called while a request is
 * pending it returns false and nothing is asked again. So every filter change
 * that arrived before the previous answer was dropped — the list then showed
 * an earlier answer under a newer search box, and "clear the filters" clicked
 * during a slow request left an empty list empty for good.
 *
 * When the call is refused, it is made again as soon as the in-flight request
 * has settled (checked every 30ms until then). Nothing else changes: an
 * accepted reload behaves as before, and a destroyed resource ignores the ask.
 */
export function reloadList(resource: Reloadable): void {
  if (resource.reload() !== false) {
    return;
  }
  const again = (): void => {
    if (resource.isLoading()) {
      setTimeout(again, SETTLE_POLL_MS);
      return;
    }
    resource.reload();
  };
  setTimeout(again, SETTLE_POLL_MS);
}
