/**
 * Sums money exactly. The API sends amounts as two-decimal strings; adding
 * them as JavaScript numbers would drift (0.1 + 0.2). Here every amount is
 * read as text and counted in whole piasters.
 */

/** the amount as whole piasters; 0 for anything that is not an amount */
export function toPiasters(value: string | number | null | undefined): number {
  const text = value === null || value === undefined ? '' : String(value).trim();
  const match = /^(-?)(\d+)(?:\.(\d*))?$/.exec(text);
  if (match === null) {
    return 0;
  }
  // the sign, the whole pounds, and the fraction when there is one
  const [, sign, whole, fraction = ''] = match;
  const piasters = Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2));

  return sign === '-' ? -piasters : piasters;
}

/** whole piasters back to a two-decimal amount */
export function fromPiasters(piasters: number): string {
  const sign = piasters < 0 ? '-' : '';
  const absolute = Math.abs(piasters);

  return `${sign}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`;
}

/** the exact total of amounts given as two-decimal strings, as a two-decimal string */
export function sumDecimals(values: readonly (string | number | null | undefined)[]): string {
  let piasters = 0;
  for (const value of values) {
    piasters += toPiasters(value);
  }

  return fromPiasters(piasters);
}
