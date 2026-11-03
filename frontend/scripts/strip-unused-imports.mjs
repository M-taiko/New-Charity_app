// One-off: strip component imports flagged NG8113 (unused in template) from
// the imports array of each component file listed in build-output.log.
import { readFileSync, writeFileSync } from 'node:fs';

const log = readFileSync('build-output.log', 'utf8').replace(/\x1b\[[0-9;]*m/g, '');
const lines = log.split('\n');

/** file -> Set of unused symbols */
const pairs = new Map();
for (let i = 0; i < lines.length; i++) {
  const m = lines[i].match(/▲ \[WARNING\] NG8113: (\w+) is not used within the template/);
  if (!m) continue;
  let loc = '';
  for (let j = i + 1; j < Math.min(i + 8, lines.length); j++) {
    const l = lines[j].match(/(src\/app\/[\w./-]+\.ts):\d+:\d+/);
    if (l) { loc = l[1]; break; }
  }
  if (!loc) continue;
  if (!pairs.has(loc)) pairs.set(loc, new Set());
  pairs.get(loc).add(m[1]);
}

let changedFiles = 0;
let removedSymbols = 0;
const failures = [];

for (const [file, symbols] of pairs) {
  let text = readFileSync(file, 'utf8');
  for (const sym of symbols) {
    // only edit inside the `imports: [...]` array of the @Component decorator
    const importsMatch = text.match(/imports:\s*\[([^\]]*)\]/);
    if (!importsMatch) { failures.push(`${file}: no imports array (${sym})`); continue; }
    const arr = importsMatch[1];
    const next = arr
      .replace(new RegExp(`\\b${sym}\\s*,\\s*`, 'g'), '')
      .replace(new RegExp(`\\s*,\\s*${sym}\\b`, 'g'), '')
      .replace(new RegExp(`(^|\\[\\s*)${sym}\\s*(?=\\s*\\])`, 'g'), '$1');
    if (next === arr) { failures.push(`${file}: symbol not found (${sym})`); continue; }
    text = text.replace(importsMatch[0], `imports: [${next}]`);
    removedSymbols++;
  }
  writeFileSync(file, text);
  changedFiles++;
}

console.log(`files changed: ${changedFiles}, symbols removed: ${removedSymbols}`);
if (failures.length > 0) {
  console.log('failures:');
  for (const f of failures) console.log('  ' + f);
}
