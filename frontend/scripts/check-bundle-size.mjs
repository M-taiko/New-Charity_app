// check-bundle-size (plan 22.2): flexible budgets —
// TARGET exceeded → warning (exit 0), MAX exceeded → fails the build (exit 1).
import { readdirSync, statSync, existsSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';
import { readFileSync } from 'node:fs';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const dist = resolve(root, 'dist/app/browser');

if (!existsSync(dist)) {
  console.warn('check-bundle-size: dist not built yet — skipping');
  process.exit(0);
}

// gzipped bytes; targets keep us honest, maxes guard the build
const BUDGETS = {
  initial: { target: 300 * 1024, max: 350 * 1024 }, // initial JS
  lazyChunk: { target: 100 * 1024, max: 120 * 1024 }, // per lazy chunk
  globalCss: { target: 50 * 1024, max: 60 * 1024 },
};

const files = readdirSync(dist).filter((f) => f.endsWith('.js') || f.endsWith('.css'));
let failed = false;
let warned = false;

function gzippedSize(file) {
  return gzipSync(readFileSync(file)).length;
}

/** warn above target, fail above max */
function enforce(label, size, budget) {
  if (size > budget.max) {
    console.error(`check-bundle-size: FAIL ${label} ${size} > max ${budget.max} (gzipped)`);
    failed = true;
  } else if (size > budget.target) {
    console.warn(`check-bundle-size: WARN ${label} ${size} > target ${budget.target} (max ${budget.max})`);
    warned = true;
  }
}

// chunk-*.js with 4+ digit hash are lazy; initial files are the rest
for (const file of files.map((f) => join(dist, f))) {
  const base = file.split(/[\\/]/).pop();
  const size = gzippedSize(file);
  if (base.endsWith('.css')) {
    enforce(`CSS ${base}`, size, BUDGETS.globalCss);
  } else if (base.includes('chunk-')) {
    enforce(`lazy ${base}`, size, BUDGETS.lazyChunk);
  }
}

// initial = main + polyfills + runtime + scripts referenced by index.html
const index = readFileSync(join(dist, 'index.html'), 'utf8');
let initial = 0;
for (const match of index.matchAll(/src="([^"]+\.js)"/g)) {
  const file = join(dist, match[1].replace(/^\/?/, ''));
  if (existsSync(file)) {
    initial += gzippedSize(file);
  }
}
enforce('initial JS', initial, BUDGETS.initial);

if (!failed) {
  console.log(`check-bundle-size: ${warned ? 'OK with warnings' : 'OK'} (initial ${initial} gzipped)`);
}
process.exit(failed ? 1 : 0);
