// One-off: rewrite `.catch((err) => …)` / `.catch((err: HttpErrorResponse) => …)`
// to `(err: unknown)` with member accesses cast inside the callback body.
// Driven by fresh eslint JSON (lint.json) so positions are exact.
import { readFileSync, writeFileSync } from 'node:fs';

const report = JSON.parse(readFileSync('lint.json', 'utf8'));

for (const f of report) {
  const hits = f.messages.filter((m) => m.ruleId === '@typescript-eslint/use-unknown-in-catch-callback-variable');
  if (hits.length === 0) continue;
  const lines = readFileSync(f.filePath, 'utf8').split('\n');
  let fileChanged = false;

  // process bottom-up so earlier line numbers stay valid
  const ordered = [...hits].sort((a, b) => b.line - a.line);
  for (const m of ordered) {
    const start = m.line - 1;
    const head = lines[start];
    const headMatch = head.match(/\.catch\(\((\w+)(?::\s*\w+)?\)/);
    if (!headMatch) {
      console.log(`SKIP ${f.filePath}:${m.line} no catch head → ${head.trim().slice(0, 70)}`);
      continue;
    }
    const name = headMatch[1];
    lines[start] = head.replace(/\.catch\(\((\w+)(?::\s*\w+)?\)/, `.catch((${name}: unknown)`);

    // find the callback body extent: from start until the line whose trimmed
    // content closes the statement — tracking braces opened after the arrow
    let depth = 0;
    let seenArrow = false;
    let end = start;
    for (let i = start; i < Math.min(start + 40, lines.length); i++) {
      const text = lines[i];
      for (const ch of text) {
        if (ch === '=' && text[text.indexOf(ch) + 1] === '>') seenArrow = true;
        if (!seenArrow) continue;
        if (ch === '{' || ch === '(') depth++;
        if (ch === '}' || ch === ')') depth--;
      }
      if (seenArrow && depth <= 0 && i > start) { end = i; break; }
      if (i === start && seenArrow) {
        // single-line body: `.catch((err) => …);` closes on the same line
        if (/=>[^;]*;\s*$/.test(text) && depth <= 0) { end = i; break; }
      }
      end = i;
    }

    // cast member accesses on the catch parameter inside [start..end]
    const castRe = new RegExp(`\\b${name}\\.(error|message|status|code)\\b`, 'g');
    for (let i = start; i <= end; i++) {
      if (!castRe.test(lines[i])) continue;
      lines[i] = lines[i].replace(new RegExp(`\\b${name}\\.(error|message|status|code)\\b`, 'g'), `(${name} as HttpErrorResponse).$1`);
    }
    fileChanged = true;
  }
  if (fileChanged) writeFileSync(f.filePath, lines.join('\n'));
}
console.log('done');
