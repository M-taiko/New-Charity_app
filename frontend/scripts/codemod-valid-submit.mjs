// One-shot codemod (D-09/P-10): wire appValidSubmit into every feature form
// that disabled its submit button on f.invalid — the button stays enabled,
// the template guard validates + focuses the first invalid field instead.
import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const features = resolve(root, 'src/app/features');

function* tsFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) yield* tsFiles(full);
    else if (entry.endsWith('.ts')) yield full;
  }
}

const importLine = "import { ValidSubmitDirective } from '@shared/ui/form/valid-submit.directive';";

let changed = 0;
for (const file of tsFiles(features)) {
  let source = readFileSync(file, 'utf8');
  const before = source;

  // 1. the disabled button line: drop the "<ref>.invalid || " condition
  source = source.replace(/\[disabled\]="([a-zA-Z]+)\.invalid \|\| /g, '[disabled]="');

  // 2. the form tag: guard the handler + attach the directive
  source = source.replace(
    /<form \(ngSubmit\)="([a-zA-Z]+)\(\)" (#[a-zA-Z]+="ngForm")>/g,
    '<form (ngSubmit)="vs.validate() && $1()" $2 appValidSubmit #vs="appValidSubmit">',
  );

  if (source !== before) {
    // 3. imports (directive + component imports array)
    if (!source.includes('ValidSubmitDirective')) {
      const imports = [...source.matchAll(/^import .*$/gm)];
      const last = imports[imports.length - 1];
      const at = last.index + last[0].length;
      source = source.slice(0, at) + '\n' + importLine + source.slice(at);
      source = source.replace(/(imports: \[)/, `$1ValidSubmitDirective, `);
    }
    writeFileSync(file, source);
    changed++;
    console.log('updated', file.slice(root.length + 1));
  }
}
console.log(`codemod: ${changed} file(s)`);
