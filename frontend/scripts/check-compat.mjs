// check-compat (Task7 T7-01): source-level Chrome 109 gate (plan 3.4,
// docs/browser-support.md). Fails on CSS color functions and JS APIs newer
// than the baseline (Chrome 109 / Edge 109 / Firefox 115 / Safari 15.4) in
// SCSS, CSS, TS and HTML — before anything reaches a build.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src');

// CSS functions Chrome 109 cannot parse — a rejected declaration silently
// drops the whole rule, so they are banned at the source level.
const CSS_FORBIDDEN = [
  /color-mix\(/i,
  /\boklch\(/i,
  /\boklab\(/i,
  /\blab\(/i,
  /\blch\(/i,
  /light-dark\(/i,
];

// JS member APIs newer than the baseline. Matched on member access so plain
// identifiers (e.g. a `with(` label) do not false-positive.
const JS_FORBIDDEN = [
  /\.\s*toSorted\s*\(/,
  /\.\s*toReversed\s*\(/,
  /\.\s*toSpliced\s*\(/,
  /\.\s*with\s*\(/,
  /\bObject\s*\.\s*groupBy\s*\(/,
  /\bMap\s*\.\s*groupBy\s*\(/,
  /\bPromise\s*\.\s*withResolvers\b/,
  /\bAbortSignal\s*\.\s*timeout\b/,
  /\bURL\s*\.\s*canParse\b/,
  /\bArray\s*\.\s*fromAsync\b/,
  /\.\s*intersection\s*\(/,
  /\.\s*union\s*\(/,
  /\.\s*difference\s*\(/,
  /\.\s*symmetricDifference\s*\(/,
  /\.\s*isSubsetOf\s*\(/,
  /\.\s*isSupersetOf\s*\(/,
  /\.\s*isDisjointFrom\s*\(/,
];

function* srcFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* srcFiles(full);
    } else if (/\.(scss|css|ts|html)$/.test(entry)) {
      yield full;
    }
  }
}

/**
 * Native CSS nesting is compiled away in SCSS but ships as-is from .css files.
 * Flags any rule block opened inside a non-at-rule block (a real nested rule)
 * or a `&` parent selector.
 */
function cssNestingOffenders(code, report) {
  const stack = []; // true = block was opened by an at-rule (@media, @supports…)
  const lines = code.split('\n');
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    if (/^\s*&/.test(line)) {
      report(line.trim(), i + 1);
    }
    for (let c = 0; c < line.length; c++) {
      if (line[c] === '{') {
        const before = line.slice(0, c).trimStart();
        stack.push(before.startsWith('@'));
      } else if (line[c] === '}') {
        stack.pop();
      }
    }
    const opened = stack.filter((isAt) => !isAt).length;
    if (opened > 1 && /[^\s}][^{}]*\{\s*$/.test(line)) {
      report(line.trim(), i + 1);
    }
  }
}

const offenders = [];
for (const file of srcFiles(src)) {
  const normalized = file.replace(/\\/g, '/').replace(/^.*\/src\//, 'src/');
  const code = readFileSync(file, 'utf8');
  const isCss = /\.(scss|css)$/.test(file);

  // Comments may name the banned APIs (docs like this file) — code may not use them.
  // Blank the comments with same-length whitespace so match offsets stay valid.
  const stripped = code
    .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, ' '))
    .replace(/(^|[^:])\/\/[^\n]*/g, (m, p1) => p1 + ' '.repeat(m.length - p1.length));

  for (const rule of isCss ? CSS_FORBIDDEN : JS_FORBIDDEN) {
    for (const match of stripped.matchAll(new RegExp(rule.source, rule.flags.includes('g') ? rule.flags : rule.flags + 'g'))) {
      const line = stripped.slice(0, match.index).split('\n').length;
      offenders.push(`${normalized}:${line} — ${match[0]}`);
    }
  }

  if (file.endsWith('.css')) {
    cssNestingOffenders(code, (text, line) => offenders.push(`${normalized}:${line} — native CSS nesting: ${text}`));
  }
}

if (offenders.length > 0) {
  console.error('check-compat: APIs newer than the Chrome 109 baseline found:');
  for (const line of offenders) console.error(`  ${line}`);
  process.exit(1);
}
console.log('check-compat: OK');
