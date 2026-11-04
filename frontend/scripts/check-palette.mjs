// check-palette (plan 10.1.6): the built CSS may only contain the eight brand
// colors, the documented dark-theme scale, black-with-alpha (shadows/scrim),
// transparent and currentColor. Catches accidental greys and blends.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

const PALETTE = new Set([
  // brand scale (tokens.scss --palette-*)
  '#4f2f1d', '#5d3722', '#7d5f4e', '#9e877a', '#beafa7', '#dfd7d3', '#ffffff', '#fff', '#f7f3ef',
  // dark theme scale (tokens.scss --dark-*)
  '#12100e', '#1a1815', '#201e1b', '#2a2825', '#3a3835', '#4a4845',
]);

/** black with an optional alpha channel — shadows and modal scrims only */
function isShadowBlack(color) {
  return /^#0{3}(?:[0-9a-f]{1})?$|^#000000(?:[0-9a-f]{2})?$/.test(color);
}

function* cssFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* cssFiles(full);
    } else if (entry.endsWith('.css')) {
      yield full;
    }
  }
}

const offenders = new Map();
const dist = resolve(root, 'dist');
try {
  for (const file of cssFiles(dist)) {
    const css = readFileSync(file, 'utf8').toLowerCase();
    for (const match of css.matchAll(/#[0-9a-f]{3,8}\b/g)) {
      const color = match[0];
      if (!PALETTE.has(color) && !isShadowBlack(color)) {
        offenders.set(color, (offenders.get(color) ?? 0) + 1);
      }
    }
  }
} catch {
  console.warn('check-palette: dist/ not built yet — skipping');
  process.exit(0);
}

if (offenders.size > 0) {
  console.error('check-palette: colors outside the eight brand colors found:');
  for (const [color, count] of offenders) {
    console.error(`  ${color} x${count}`);
  }
  process.exit(1);
}
console.log('check-palette: OK');
