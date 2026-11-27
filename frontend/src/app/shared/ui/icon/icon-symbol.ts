import { ICON_MAP, IconKey } from './icon-map';

/**
 * Resolve any icon name to its sprite symbol id (B-01).
 *
 * Callers may pass either a semantic key from ICON_MAP (`custody`, `success`,
 * `myExpense`) or a raw tabler name (`chevron-right`). Semantic keys map to
 * the tabler glyph; anything else passes through unchanged, so every
 * `sprite.svg#tabler-<symbol>` href in the app must be built through here.
 */
export function iconSymbol(name: string): string {
  return ICON_MAP[name as IconKey] ?? name;
}

/** Full sprite href for an icon name — the only way to build `<use href>`. */
export function iconHref(name: string): string {
  return `assets/icons/sprite.svg#tabler-${iconSymbol(name)}`;
}
