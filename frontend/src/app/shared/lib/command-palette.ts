/**
 * N-01: the command palette's record search — the pure decisions, apart from
 * Angular and the network: when to ask the server, how a match is
 * highlighted, and how pages, records and "see all" links become one list
 * the arrow keys walk down.
 */

export const SEARCH_MIN_CHARS = 2;
export const SEARCH_DEBOUNCE_MS = 250;

export type SearchGroupKey = 'custodies' | 'expenses' | 'social_cases' | 'users';

export interface SearchHit {
  readonly id: number;
  readonly title: string;
  readonly subtitle: string | null;
}

/** the answer of GET /api/v1/search */
export interface SearchAnswer {
  readonly data: Record<SearchGroupKey, readonly SearchHit[]>;
  readonly meta: {
    readonly q: string;
    readonly per_group: number;
    /** the groups the server searched for this user */
    readonly groups: readonly SearchGroupKey[];
  };
}

/** a page of the app the palette can jump to */
export interface PageEntry {
  /** i18n key of its name */
  readonly label: string;
  /** its name as the user reads it (the translation); the key when the page did not give it */
  readonly text?: string;
  readonly route: string;
  readonly icon: string;
  readonly recent?: boolean;
}

/** what the palette knows about one group of records */
export interface GroupInfo {
  readonly key: SearchGroupKey;
  /** i18n key of its heading */
  readonly label: string;
  readonly icon: string;
  /** where "see all results" goes, for this user */
  readonly list: string;
  /** the record's page, without the id */
  readonly detail: string;
}

export type PaletteItem =
  | { readonly kind: 'page'; readonly entry: PageEntry }
  | { readonly kind: 'hit'; readonly group: GroupInfo; readonly hit: SearchHit }
  | { readonly kind: 'all'; readonly group: GroupInfo };

/** is the search text long enough to ask the server? (the server refuses less) */
export function needsRecordSearch(term: string): boolean {
  return term.trim().length >= SEARCH_MIN_CHARS;
}

/** the text a page entry or a record is matched against */
export function normalize(text: string): string {
  return text.trim().toLowerCase();
}

/**
 * Does the page match what was typed? By the name the user reads, or by its
 * route. (It used to match the translation key — `nav.treasury` — so a
 * page's Arabic name never found it.)
 */
export function pageMatches(entry: PageEntry, term: string): boolean {
  const needle = normalize(term);
  if (needle === '') {
    return true;
  }
  return normalize(entry.text ?? entry.label).includes(needle) || normalize(entry.route).includes(needle);
}

/** the first occurrence of `term` in `text` (whatever its case), in three parts; null when there is none */
export function splitMatch(text: string, term: string): { before: string; match: string; after: string } | null {
  const needle = term.trim();
  if (needle === '') {
    return null;
  }
  const at = normalize(text).indexOf(normalize(needle));
  if (at === -1) {
    return null;
  }
  return { before: text.slice(0, at), match: text.slice(at, at + needle.length), after: text.slice(at + needle.length) };
}

export interface PaletteState {
  readonly term: string;
  readonly pages: readonly PageEntry[];
  readonly groups: readonly GroupInfo[];
  /** the hits by group — a group the server left out reads as empty */
  readonly hits: Readonly<Partial<Record<SearchGroupKey, readonly SearchHit[]>>> | null;
}

/**
 * Everything the palette lists, top to bottom: the pages that match (at most
 * `maxPages`), then each group's records, each followed by its "see all
 * results" when it has at least one.
 */
export function paletteItems(state: PaletteState, maxPages = 5): PaletteItem[] {
  const items: PaletteItem[] = [];
  for (const entry of state.pages.filter((page) => pageMatches(page, state.term)).slice(0, maxPages)) {
    items.push({ kind: 'page', entry });
  }
  if (state.hits === null) {
    return items;
  }
  for (const group of state.groups) {
    const hits = state.hits[group.key] ?? [];
    for (const hit of hits) {
      items.push({ kind: 'hit', group, hit });
    }
    if (hits.length > 0) {
      items.push({ kind: 'all', group });
    }
  }
  return items;
}

/** the next item under the arrow key: it stops at the ends, and never leaves an empty list */
export function moveActive(current: number, delta: -1 | 1, length: number): number {
  if (length === 0) {
    return 0;
  }
  return Math.min(length - 1, Math.max(0, current + delta));
}

/** whether an answer still belongs to the text that is on screen */
export function answerIsFor(answer: SearchAnswer, term: string): boolean {
  return normalize(answer.meta.q) === normalize(term);
}

/**
 * The heading above the item at `index`, when the item starts a section
 * (the pages, or a group of records); '' when it continues one.
 */
export function sectionStart(items: readonly PaletteItem[], index: number): 'pages' | SearchGroupKey | '' {
  const item = items.at(index);
  // no item there: no heading (the template only asks for items it draws; a function still answers for the rest)
  if (item === undefined) {
    return '';
  }
  const previous: PaletteItem | undefined = index > 0 ? items.at(index - 1) : undefined;
  if (item.kind === 'page') {
    return previous === undefined || previous.kind !== 'page' ? 'pages' : '';
  }
  const key = item.group.key;
  const previousKey = previous === undefined || previous.kind === 'page' ? '' : previous.group.key;
  return previousKey === key ? '' : key;
}
