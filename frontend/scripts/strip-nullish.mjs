// One-off: remove the redundant `?? …` right-hand side at the exact
// file:line:col positions the Angular compiler reported (NG8102).
import { readFileSync, writeFileSync } from 'node:fs';

const log = readFileSync('build-output.log', 'utf8').replace(/\x1b\[[0-9;]*m/g, '');
const lines = log.split('\n');

const edits = [];
for (let i = 0; i < lines.length; i++) {
  if (!lines[i].includes('NG8102')) continue;
  for (let j = i + 1; j < Math.min(i + 8, lines.length); j++) {
    const l = lines[j].match(/(src\/app\/[\w./-]+\.ts):(\d+):(\d+)/);
    if (l) { edits.push({ file: l[1], line: Number(l[2]) }); break; }
  }
}

const byFile = new Map();
for (const e of edits) {
  if (!byFile.has(e.file)) byFile.set(e.file, []);
  byFile.get(e.file).push(e.line);
}

let fixed = 0;
for (const [file, lineNums] of byFile) {
  const src = readFileSync(file, 'utf8').split('\n');
  for (const ln of lineNums) {
    const idx = ln - 1;
    // strip `?? <rest until the next " or ] or ) or }>` — the col points at ?? start
    const before = src[idx];
    const next = before.replace(/\s*\?\?\s*(\[\]|'[^']*'|"[^"]*")/, '');
    if (next !== before) {
      src[idx] = next;
      fixed++;
    } else {
      console.log(`NO MATCH ${file}:${ln} → ${before.trim().slice(0, 90)}`);
    }
  }
  writeFileSync(file, src.join('\n'));
}
console.log(`fixed ${fixed} of ${edits.length}`);
