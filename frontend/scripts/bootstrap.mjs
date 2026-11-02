// bootstrap: checks the developer machine has what the toolchain needs.
import { execSync } from 'node:child_process';

function check(label, command, min = '') {
  try {
    const out = execSync(command, { encoding: 'utf8' }).trim();
    console.log(`  ok   ${label}: ${out.split('\n')[0]}`);
    return true;
  } catch {
    console.error(`  MISS ${label} ${min}`);
    return false;
  }
}

console.log('Naguib Selim Charity frontend bootstrap:');
let ok = true;
ok = check('Node.js (LTS, >= 20)', 'node --version') && ok;
ok = check('npm', 'npm --version') && ok;
ok = check('Angular CLI (local)', 'npx ng version --help > NUL 2>&1 || npm ls @angular/cli --depth=0') && ok;
ok = check('PHP (for the API server)', 'php --version') && ok;
ok = check('Composer', 'composer --version') && ok;
console.log(ok ? 'Everything needed is present.' : 'Install the missing tools, then re-run npm run bootstrap.');
process.exit(ok ? 0 : 1);
