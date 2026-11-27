/**
 * Undoing "mark as read" (N-06) — the pure part: what the server answered and
 * what an undo sends back.
 */

/** the instant mark-all-read answered with, when it answered with one */
export function markedAt(body: unknown): string | null {
  const data = (body as { data?: { marked_at?: unknown } } | null)?.data;
  return typeof data?.marked_at === 'string' && data.marked_at !== '' ? data.marked_at : null;
}

/** what an undo puts back: these few notifications, or everything read since an instant */
export type UnreadSelection = { readonly ids: readonly number[] } | { readonly since: string };

/** the request body of an undo */
export function unreadBody(selection: UnreadSelection): Record<string, unknown> {
  return 'ids' in selection ? { ids: selection.ids } : { since: selection.since };
}
