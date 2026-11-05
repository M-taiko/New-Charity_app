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
  '#d2c7c1', // sand-deep — selected+focused overlay options (T6-14)
  // dark theme scale (tokens.scss --dark-*)
  '#12100e', '#1a1815', '#201e1b', '#2a2825', '#3a3835', '#4a4845',
  // semantic action scales (T6-13 — brand-preset.ts + _tokens.scss)
  '#1e7a46', '#175f37', '#124a2c', '#0b3520', '#38955d', '#5cb383', '#a8d6be', '#d2ebdd', '#e7f5ec', '#4fbf82',
  '#b3261e', '#991d15', '#7c170f', '#370604', '#c93b30', '#db7f79', '#e8a9a5', '#f5d2d0', '#fbeae9', '#ef8377',
  '#96650a', '#7a5006', '#5f3e04', '#2e2000', '#c8920d', '#e5b653', '#f0d08a', '#f7e4b4', '#fbf0d5', '#e0b45c',
  '#0e6e77', '#0a565e', '#084449', '#032a2e', '#2e93a0', '#57b3bd', '#8fcfd6', '#c3e5e9', '#e1f1f4', '#63bfc9',
  // module accent set (T6-40 — _tokens.scss, light + dark variants)
  '#0e7168', '#8f5700', '#66701a', '#b03b4b', '#2f5fa3', '#6d4fc1', '#9c4a1f', '#4848a0',
  '#5ec6ba', '#e0a43c', '#b6c051', '#e2798a', '#7aa5e8', '#a68fec', '#d98e5f', '#9090e8',
  // color-blind-safe chart palette (T6-40 — chart-theme)
  '#0f766e', '#b8860b', '#7a4f9e', '#2f6cb3', '#b3555e', '#4f8f3a', '#8a6d3b', '#5e5e5e',
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
