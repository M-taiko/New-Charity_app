/**
 * Every number Chart.js paints (axis ticks, tooltips) goes through here.
 *
 * Charts live on an RTL page, and a canvas that inherits that direction
 * reorders a leading sign to the END of a number: the tick `-400K` was painted
 * as `400K-` (R4-02). A real minus on its own does not help — `−400K` paints
 * as `400K−` just the same. What fixes it is pinning the number between
 * LEFT-TO-RIGHT MARKs: the digits after an LRM resolve L-to-R (UAX#9 W7) and
 * the sign between them follows (N1), so the text paints in logical order
 * whatever direction the canvas inherits.
 *
 * Digits are always Western (`en-US`): the default locale formatter drew
 * Arabic-Indic `٧٠٬٠٠٠` on the axes while the rest of the UI uses `70,000`.
 */

/** U+200E LEFT-TO-RIGHT MARK — zero width, no glyph */
const LRM = '\u200E';

/** U+2212 MINUS SIGN — a real minus, the width of a digit (not a hyphen) */
export const MINUS = '\u2212';

/** pin a number so it paints in logical order on an RTL canvas */
export function bidiNumber(text: string): string {
  return `${LRM}${text}${LRM}`;
}

/** sign in front, by the rules of the locale formatter, never `−0` */
function signed(value: number, format: (magnitude: number) => string): string {
  const magnitude = format(Math.abs(value));
  return value < 0 && magnitude !== format(0) ? MINUS + magnitude : magnitude;
}

const compact = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

/** axis tick — compact, Western digits, minus first: −50K · 400K · 1.2M */
export function formatChartTick(value: number): string {
  return bidiNumber(signed(value, (magnitude) => compact.format(magnitude)));
}

/**
 * tooltip amount — full precision with `fractionDigits` decimals, Western
 * digits, minus first: −1,234.50
 */
export function formatChartAmount(value: number, fractionDigits = 2): string {
  const full = new Intl.NumberFormat('en-US', { minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits });
  return bidiNumber(signed(value, (magnitude) => full.format(magnitude)));
}
