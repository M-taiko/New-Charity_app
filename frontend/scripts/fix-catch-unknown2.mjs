// v2: for every `.catch((x: unknown) => …)` statement (fresh lint.json),
// cast EVERY bare reference to x inside the callback body — including whole-
// value uses like `this.fail(err)` — to `(x as HttpErrorResponse)`.
import { readFileSync, writeFileSync } from 'node:fs';

const report = JSON.parse(readFileSync('lint.json', 'utf8'));

for (const f of report) {
  const hits = f.messages.filter((m) => m.ruleId === '@typescript-eslint/use-unknown-in-catch-callback-variable');
  if (hits.length === 0) continue;
  const lines = readFileSync(f.filePath, 'utf8').split('\n');
  let fileChanged = false;

  const ordered = [...hits].sort((a, b) => b.line - a.line);
  for (const m of ordered) {
    const start = m.line - 1;
    const head = lines[start];
    const headMatch = head.match(/\.catch\(\((\w+)(?::\s*\w+)?\)/);
    if (!headMatch) continue;
    const name = headMatch[1];

    // 1) normalize the head to `(name: unknown)`
    let newHead = head.replace(/\.catch\(\((\w+)(?::\s*\w+)?\)/, `.catch((${name}: unknown)`);
    // if the arrow starts on the head line, also fix references after `=>`
    const arrowIdx = newHead.indexOf('=>');
    if (arrowIdx >= 0) {
      const before = newHead.slice(0, arrowIdx);
      let after = newHead.slice(arrowIdx);
      after = after.split(`(${name} as HttpErrorResponse)`).join(name);
      after = after.replace(new RegExp(`\\b${name}\\b(?!\\s*:)`, 'g'), `(${name} as HttpErrorResponse)`);
      newHead = before + after;
    }
    if (newHead !== head) { lines[start] = newHead; fileChanged = true; }

    // 2) extent of the statement (brace matching from the arrow onward)
    let depth = 0, seenArrow = arrowIdx >= 0, end = start;
    for (let i = start; i < Math.min(start + 40, lines.length); i++) {
      const text = i === start ? lines[i].slice(arrowIdx >= 0 ? arrowIdx : 0) : lines[i];
      if (!seenArrow && text.includes('=>')) seenArrow = true;
      if (seenArrow) {
        for (const ch of text) {
          if (ch === '{' || ch === '(') depth++;
          else if (ch === '}' || ch === ')') depth--;
        }
        if (depth <= 0 && text.trimEnd().endsWith(';')) { end = i; break; }
      }
      end = i;
    }

    // 3) body lines: strip existing casts, then cast every bare reference
    for (let i = start + 1; i <= end; i++) {
      const original = lines[i];
      let text = original.split(`(${name} as HttpErrorResponse)`).join(name);
      text = text.replace(new RegExp(`\\b${name}\\b(?!\\s*:)`, 'g'), `(${name} as HttpErrorResponse)`);
      if (text !== original) { lines[i] = text; fileChanged = true; }
    }
  }
  if (fileChanged) writeFileSync(f.filePath, lines.join('\n'));
}
console.log('done');
