// check-overlays (Task6 T6-15): every PrimeNG overlay component used in a
// template must set appendTo="body" so panels escape scroll containers and
// clip contexts. Covers p-select, p-multiselect, p-datepicker, p-menu,
// p-tieredmenu, p-popover, p-autocomplete, p-cascadeselect, p-treeselect,
// p-splitbutton, p-contextmenu and p-confirmpopup.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src');

const OVERLAYS = [
  'p-select',
  'p-multiselect',
  'p-datepicker',
  'p-menu',
  'p-tieredmenu',
  'p-popover',
  'p-autocomplete',
  'p-cascadeselect',
  'p-treeselect',
  'p-splitbutton',
  'p-contextmenu',
  'p-confirmpopup',
];

function* tsFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* tsFiles(full);
    } else if (entry.endsWith('.ts')) {
      yield full;
    }
  }
}

const offenders = [];

/** counts `appendTo` occurrences on the same element as the overlay tag */
function missingAppendTo(tag, template) {
  const misses = [];
  // match the opening tag up to its closing '>' allowing newlines
  const re = new RegExp(`<${tag}\\b[^>]*?>`, 'gs');
  for (const open of template.matchAll(re)) {
    const attrs = open[0];
    if (!/appendTo\s*=/.test(attrs)) {
      misses.push(open[0].slice(0, 120).replace(/\s+/g, ' '));
    }
  }
  return misses;
}

for (const file of tsFiles(src)) {
  const code = readFileSync(file, 'utf8');
  // only inspect inline template strings
  const tplMatch = code.match(/template\s*:\s*`([\s\S]*?)`\s*(?:,|\n\s*styleUrl|}\s*$)/);
  const template = tplMatch ? tplMatch[1] : '';
  if (!template) continue;
  for (const tag of OVERLAYS) {
    const misses = missingAppendTo(tag, template);
    for (const snippet of misses) {
      offenders.push(`${rel(file)}: <${tag}> without appendTo — ${snippet}…`);
    }
  }
}

function rel(p) {
  return p.replace(/^.*[\\/]src[\\/]/, 'src/');
}

if (offenders.length > 0) {
  console.error(`check-overlays: ${offenders.length} overlay(s) missing appendTo="body":`);
  for (const line of offenders) console.error(`  ${line}`);
  process.exit(1);
}
console.log('check-overlays: OK');
