// Helper: summarize eslint JSON output. Usage:
//   npx eslint "src/**/*.ts" --format json 2>/dev/null | node scripts/lint-report.mjs [ruleId]
// Prints per-warning file:line + message for the given rule (or all), then counts.
import { readFileSync } from 'node:fs';

const ruleFilter = process.argv[2] ?? null;
let raw = '';
process.stdin.on('data', (c) => { raw += c; });
process.stdin.on('end', () => {
  const results = JSON.parse(raw);
  const byRule = {};
  const lines = [];
  for (const f of results) {
    const short = f.filePath.replace(/^.*src[\\/]app[\\/]/, '').replace(/\\/g, '/');
    for (const m of f.messages) {
      const r = m.ruleId ?? 'null';
      byRule[r] = (byRule[r] ?? 0) + 1;
      if (!ruleFilter || r === ruleFilter || r.endsWith('/' + ruleFilter) || r === '@angular-eslint/' + ruleFilter) {
        lines.push(`${r} ${short}:${m.line}:${m.column} | ${m.message.slice(0, 100)}`);
      }
    }
  }
  for (const l of lines) console.log(l);
  if (!ruleFilter) {
    console.log('--- by rule ---');
    for (const [r, c] of Object.entries(byRule).sort((a, b) => b[1] - a[1])) console.log(c, r);
  }
});
