// check-drag (Task6 T6-17): nothing in the app may be made draggable or
// resizable except inside the shared data-table (column reorder/resize).
// Fails on cdkDrag, pDraggable, draggable="true", [draggable]="true" and
// [resizable]="true" outside shared/ui/data-table.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src');

const FORBIDDEN = [/cdkDrag\b/, /cdk-drag\b/, /\bpDraggable\b/, /draggable="true"/, /\[draggable\]="\s*true\s*"/, /\[resizable\]="\s*true\s*"/];

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
for (const file of tsFiles(src)) {
  const normalized = file.replace(/\\/g, '/');
  if (normalized.includes('/shared/ui/data-table/')) {
    continue; // the one allowed place
  }
  const code = readFileSync(file, 'utf8');
  for (const rule of FORBIDDEN) {
    for (const match of code.matchAll(new RegExp(rule.source, 'g'))) {
      const line = code.slice(0, match.index).split('\n').length;
      offenders.push(`${normalized.replace(/^.*\/src\//, 'src/')}:${line} — ${match[0]}`);
    }
  }
}

if (offenders.length > 0) {
  console.error(`check-drag: draggable/resizable code outside the data-table found:`);
  for (const line of offenders) console.error(`  ${line}`);
  process.exit(1);
}
console.log('check-drag: OK');
