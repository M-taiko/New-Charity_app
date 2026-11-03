// One-off fixers for the two mechanical lint warning classes. Reads eslint JSON
// from lint.json in this directory.
//   node scripts/fix-lint-bulk.mjs imports   → remove never-used named imports
//   node scripts/fix-lint-bulk.mjs catch     → type catch callbacks as HttpErrorResponse
import { readFileSync, writeFileSync, existsSync } from 'node:fs';

const mode = process.argv[2];
const report = JSON.parse(readFileSync('lint.json', 'utf8'));

/** file -> array of {line, column, message, rule} */
const sites = new Map();
for (const f of report) {
  for (const m of f.messages) {
    const list = sites.get(f.filePath) ?? [];
    list.push(m);
    sites.set(f.filePath, list);
  }
}

function importRangeForLine(lines, line) {
  // find the import statement (possibly multi-line) that spans `line`
  let start = line - 1;
  while (start >= 0 && !/^import\s/.test(lines[start])) start--;
  if (start < 0) return null;
  let end = start;
  while (end < lines.length && !/from\s+['"][^'"]+['"];?\s*$/.test(lines[end])) {
    end++;
    if (end - start > 12) return null; // not an import after all
  }
  return { start, end };
}

if (mode === 'imports') {
  let removed = 0, droppedLines = 0, skipped = [];
  for (const [file, messages] of sites) {
    const unused = messages
      .filter((m) => m.ruleId === '@typescript-eslint/no-unused-vars')
      .map((m) => ({ name: m.message.match(/^'([^']+)'/)?.[1], line: m.line }))
      .filter((u) => u.name)
      .sort((a, b) => b.line - a.line); // bottom-up: drops never invalidate earlier lines
    if (unused.length === 0) continue;
    const lines = readFileSync(file, 'utf8').split('\n');
    let changed = false;
    for (const { name, line } of unused) {
      const range = importRangeForLine(lines, line);
      if (!range) { skipped.push(`${file}:${line} ${name} (no import stmt)`); continue; }
      const stmt = lines.slice(range.start, range.end + 1).join('\n');
      const escaped = name.replace(/\$/g, '\\$');
      // named import list contains it
      const inList = new RegExp(`\\{[^}]*\\b${escaped}\\b[^}]*\\}`);
      if (!inList.test(stmt)) { skipped.push(`${file}:${line} ${name} (not a named import)`); continue; }
      let next = stmt
        .replace(new RegExp(`(\\b${escaped}\\s*,\\s*)`), '')
        .replace(new RegExp(`(,\\s*${escaped}\\b)`), '');
      if (next === stmt) {
        // sole named import (no commas) → drop the whole statement
        const sole = new RegExp(`^import\\s*\\{\\s*${escaped}\\s*\\}\\s*from\\s*['"][^'"]+['"];?\\s*$`, 'm');
        if (sole.test(stmt)) {
          lines.splice(range.start, range.end - range.start + 1);
          droppedLines++;
          removed++;
          changed = true;
        } else {
          skipped.push(`${file}:${line} ${name} (no comma match, not sole)`);
        }
        continue;
      }
      // sole named import left empty braces → drop the whole statement
      if (/\{\s*\}/.test(next) || /\{\s*,\s*\}/.test(next)) {
        lines.splice(range.start, range.end - range.start + 1);
        droppedLines++;
      } else {
        const parts = next.split('\n');
        lines.splice(range.start, range.end - range.start + 1, ...parts);
      }
      removed++;
      changed = true;
    }
    if (changed) writeFileSync(file, lines.join('\n'));
  }
  console.log(`imports removed: ${removed}, statements dropped: ${droppedLines}`);
  for (const s of skipped) console.log('SKIPPED ' + s);
}

if (mode === 'catch') {
  let fixed = 0, importsAdded = 0, skipped = [];
  for (const [file, messages] of sites) {
    const hits = messages.filter((m) => m.ruleId === '@typescript-eslint/use-unknown-in-catch-callback-variable');
    if (hits.length === 0) continue;
    const lines = readFileSync(file, 'utf8').split('\n');
    let changed = false;
    let needImport = false;
    for (const m of hits) {
      const idx = m.line - 1;
      const before = lines[idx];
      // .catch((err) =>  /  .catch((e) =>
      const next = before.replace(/\.catch\(\((\w+)\)\s*=>/, (full, name) => `.catch((${name}: HttpErrorResponse) =>`);
      if (next === before) {
        // multi-line arrow: `.catch((err)` or param on its own line
        const alt = before.replace(/\.catch\(\((\w+)\)?/, (full, name) => full.replace(`(${name}`, `(${name}: HttpErrorResponse`));
        if (alt === before) { skipped.push(`${file}:${m.line} (pattern miss) ${before.trim().slice(0, 70)}`); continue; }
        lines[idx] = alt;
      } else {
        lines[idx] = next;
      }
      needImport = true;
      fixed++;
      changed = true;
    }
    if (changed && needImport) {
      const text = lines.join('\n');
      const alreadyImported = /import\s*(?:type\s*)?\{[^}]*HttpErrorResponse[^}]*\}\s*from\s*'@angular\/common\/http'/.test(text);
      if (!alreadyImported) {
        // add to the existing @angular/common/http import, else insert a new one
        if (/import\s*\{[^}]*\}\s*from\s*'@angular\/common\/http'/.test(text)) {
          const withImport = text.replace(/import\s*\{([^}]*)\}\s*from\s*'@angular\/common\/http'/,
            (full, names) => `import {${names.trim()}, HttpErrorResponse } from '@angular/common/http'`);
          writeFileSync(file, withImport);
          importsAdded++;
          continue;
        }
        const m = text.match(/^import[^\n]*from '@angular\/core';/m);
        const insertAt = m ? text.indexOf(m[0]) + m[0].length : text.indexOf('\n') + 1;
        const updated = text.slice(0, insertAt) + `\nimport { HttpErrorResponse } from '@angular/common/http';` + text.slice(insertAt);
        writeFileSync(file, updated);
        importsAdded++;
        continue;
      }
    }
    if (changed) writeFileSync(file, lines.join('\n'));
  }
  console.log(`catch params typed: ${fixed}, imports added: ${importsAdded}`);
  for (const s of skipped.slice(0, 20)) console.log('SKIPPED ' + s);
}

if (!mode) console.log('usage: node scripts/fix-lint-bulk.mjs imports|catch');
if (mode && !existsSync('lint.json')) console.log('lint.json missing');
