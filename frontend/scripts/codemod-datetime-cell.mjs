// One-shot codemod (B-04): replace one-line `{{ row.x | datetime }}` table
// cells with the two-line <app-datetime-cell>, and wire the import.
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const appDir = resolve(root, 'src/app/features');

function* tsFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) yield* tsFiles(full);
    else if (entry.endsWith('.ts')) yield full;
  }
}

const cellRe = /<span class="u-bidi(?: u-muted)?">\{\{\s*(row\.[A-Za-z0-9_]+)\s*\|\s*datetime\s*\}\}<\/span>/g;
const importPath = "import { DatetimeCellComponent } from '@shared/ui/datetime-cell/datetime-cell.component';";

let changed = 0;
for (const file of tsFiles(appDir)) {
  let source = readFileSync(file, 'utf8');
  if (!cellRe.test(source)) continue;
  cellRe.lastIndex = 0;

  source = source.replace(cellRe, (_m, field) => `<app-datetime-cell [value]="${field}" />`);
  if (!source.includes('DatetimeetimeCell') && !source.includes(importPath)) {
    // insert the import after the last existing import line
    const imports = [...source.matchAll(/^import .*$/gm)];
    const last = imports[imports.length - 1];
    const at = last.index + last[0].length;
    source = source.slice(0, at) + '\n' + importPath + source.slice(at);
    // add to the component's imports array (first standalone component)
    source = source.replace(/(imports:\s*\[)/, `$1DatetimeCellComponent, `);
  }
  writeFileSync(file, source);
  changed++;
  console.log('updated', file.slice(root.length + 1));
}
console.log(`codemod: ${changed} file(s) updated`);
