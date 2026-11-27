/**
 * The keyboard side of the undo toast (N-06): which key undoes, where it must
 * not, and how the shortcut is written on this platform. Pure — no Angular.
 */

/** Ctrl+Z (Cmd+Z on a Mac) undoes the newest undoable toast. Shift and Alt are left to the browser and the screen reader. */
export function isUndoKey(event: { readonly key: string; readonly ctrlKey: boolean; readonly metaKey: boolean; readonly shiftKey: boolean; readonly altKey: boolean }): boolean {
  return (event.ctrlKey || event.metaKey) && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 'z';
}

/** the input types that hold no text to undo */
const BUTTON_LIKE_TYPES = ['button', 'submit', 'reset', 'checkbox', 'radio', 'range', 'color', 'file', 'hidden'];

/**
 * Ctrl+Z inside a field is the field's own undo — never taken away from it.
 * Structural, so it can be asked of anything (also where `Element` has no
 * DOM): a content-editable, or an input that is not button-like.
 */
export function isEditableTarget(target: unknown): boolean {
  if (target === null || typeof target !== 'object') {
    return false;
  }
  const element = target as { isContentEditable?: boolean; closest?: (selector: string) => unknown; tagName?: string; type?: string };
  if (element.isContentEditable === true) {
    return true;
  }
  if (typeof element.closest !== 'function') {
    return false;
  }
  const field = element.closest('input, textarea, select') as { tagName?: string; type?: string } | null;
  if (field === null) {
    return false;
  }
  return field.tagName !== 'INPUT' || !BUTTON_LIKE_TYPES.includes((field.type ?? 'text').toLowerCase());
}

/** the undo shortcut as this platform writes it — shown on the toast's undo button */
export function undoShortcutLabel(navigatorLike: { userAgentData?: { platform?: string }; userAgent: string } | undefined = typeof navigator === 'undefined' ? undefined : navigator): string {
  if (navigatorLike === undefined) {
    return 'Ctrl+Z';
  }
  // userAgentData where the browser has it (`platform` is deprecated: "macOS", "iOS", …), the user-agent string otherwise
  const platform = navigatorLike.userAgentData?.platform ?? navigatorLike.userAgent;
  return /mac|iphone|ipad|ios/i.test(platform) ? '⌘Z' : 'Ctrl+Z';
}
