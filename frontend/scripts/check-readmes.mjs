// check-readmes: per the owner's decision, exactly three READMEs exist at the
// end — repository root, backend API layer, and the frontend workspace.
import { existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const required = [
  resolve(here, '..', '..', 'README.md'),
  resolve(here, '..', 'README.md'),
  resolve(here, '..', '..', 'app/Http/Controllers/Api/README.md'),
];

let failed = false;
for (const file of required) {
  if (!existsSync(file)) {
    console.error(`check-readmes: missing ${file}`);
    failed = true;
  }
}
if (!failed) {
  console.log('check-readmes: OK (root, frontend, backend)');
}
process.exit(failed ? 1 : 0);
