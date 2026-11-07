// check-i18n (plan 14.10, hardened in Task4 T4-30): fails when Arabic appears
// outside ar.json (templates AND component code — comments allowed), when a
// used key is missing, when a defined key is never referenced, or when a
// page template carries an inline `style="` or a raw `<table` (T4-21/22).
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve, dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const src = resolve(root, 'src');

const translations = JSON.parse(readFileSync(resolve(src, 'assets/i18n/ar.json'), 'utf8'));

function flatten(obj, prefix = '') {
  const keys = [];
  for (const [key, value] of Object.entries(obj)) {
    const full = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      keys.push(...flatten(value, full));
    } else {
      keys.push(full);
    }
  }
  return keys;
}

const defined = new Set(flatten(translations));

// collect used keys + violations from templates and components
const used = new Set();
const arabicLiterals = [];
const inlineStyles = [];
const rawTables = [];

/** every source text, for the literal-mention fallback below */
const corpus = [];

/** namespaces resolved only at runtime — their children are constructed as
 * `prefix + value` (T5-01: the unused-keys cleanup deleted these once and the
 * UI fell back to English codes), so they are never counted unused and must
 * never be deleted by hand either */
const PROTECTED = [
  /^enums\./, // enumLabel pipe / status-stamp: `enums.${ns}.${value}`
  /^case\.enums\./, // social-case pages: label('case.enums.' + group + '.' + value)
  /^transfer\.list\.tabs\./, // transfers list: 'transfer.list.tabs.' + tab.value
  /^errors\.codes\./, // toasts keyed by backend error code
  /^filter\./, // meta.filters labels built server-side (ApiList::filterDefs, T7-07)
  /\.(one|other|zero|two|few|many)$/, // plural.ts variants
];

const ARABIC = /[\u0600-\u06FF]/;
const ARABIC_WORD = /[\u0600-\u06FF]{3,}/;

function stripComments(text) {
  // line comments + block comments + inline-template HTML comments (rough but
  // sufficient — string contents may occasionally eat a "//" but the goal is
  // skipping comments, and a false negative only means we check a comment too)
  return text
    .replace(/\/\*[\s\S]*?\*\//g, ' ')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1 ')
    .replace(/<!--[\s\S]*?-->/g, ' ');
}

function walk(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      walk(full);
      continue;
    }
    if (!/\.(ts|html)$/.test(entry)) {
      continue;
    }
    const rel = relative(root, full).replaceAll('\\', '/');
    const isSharedUi = rel.includes('src/app/shared/ui/');
    const text = readFileSync(full, 'utf8');
    corpus.push(text);
    for (const match of text.matchAll(/'([a-z0-9_]+(?:\.[a-z0-9_]+)+)'\s*\|\s*(?:translate|enumLabel)/g)) {
      used.add(match[1]);
    }
    for (const match of text.matchAll(/(?:translate|enumLabel)\s*:\s*'([a-z0-9_.]+)'/g)) {
      // pipe-argument namespaces (enumLabel's 2nd arg) are not keys
      if (match[1].includes('.')) {
        used.add(match[1]);
      }
    }
    for (const match of text.matchAll(/\.instant\('([a-z0-9_.]+)'/g)) {
      used.add(match[1]);
    }

    if (rel.includes('assets')) {
      continue;
    }

    // Arabic literals: templates whole-file; .ts files with comments stripped.
    // The org name / currency / separator literals live in ar.json, not in
    // code (T4-30) — since T5-05 even a single Arabic character fails in .ts
    // (the old 3+ letter threshold let 'ج.م' and '، ' through).
    if (/\.html$/.test(entry) && entry !== 'index.html') {
      if (ARABIC_WORD.test(text)) {
        arabicLiterals.push(`${rel}: ${ARABIC_WORD.exec(text)[0]}`);
      }
    } else if (/\.ts$/.test(entry)) {
      const code = stripComments(text);
      if (ARABIC.test(code)) {
        const line = code.split('\n').findIndex((l) => ARABIC.test(l));
        const word = ARABIC.exec(code)[0];
        arabicLiterals.push(`${rel}:${line + 1}: ${word}`);
      }
    }

    // inline static styles and raw tables are banned outside shared/ui (T4-21/22);
    // since T5-03 every static style=" fails — sizing goes through the
    // field--sm/md/lg/xl and filter-bar__* classes
    function hasBannedStyle(source) {
      return /style="[^"]*"/.test(source);
    }

    if (!isSharedUi && /\.ts$/.test(entry)) {
      const code = stripComments(text);
      if (hasBannedStyle(code)) {
        inlineStyles.push(rel);
      }
      if (/<table[\s>]/.test(code)) {
        rawTables.push(rel);
      }
    }
    if (/\.html$/.test(entry) && entry !== 'index.html' && !isSharedUi) {
      if (hasBannedStyle(text)) {
        inlineStyles.push(rel);
      }
      if (/<table[\s>]/.test(text)) {
        rawTables.push(rel);
      }
    }
  }
}
walk(src);

const missing = [...used].filter((key) => !defined.has(key));
const allText = corpus.join('\n');
/** a key is "used" when its exact string appears anywhere in src — covers
 *  helpers like label('a.b.c') and dynamic-call literals the regexes miss */
const unused = [...defined].filter((key) => {
  if (used.has(key) || PROTECTED.some((re) => re.test(key))) {
    return false;
  }
  return !allText.includes(`'${key}'`) && !allText.includes(`"${key}"`) && !allText.includes(key);
});

let failed = false;
if (arabicLiterals.length > 0) {
  console.error('check-i18n: Arabic literals outside ar.json:');
  arabicLiterals.slice(0, 30).forEach((line) => console.error('  ' + line));
  failed = true;
}
if (inlineStyles.length > 0) {
  console.error('check-i18n: inline style=" — use utilities/shared components instead:');
  [...new Set(inlineStyles)].slice(0, 30).forEach((f) => console.error('  ' + f));
  failed = true;
}
if (rawTables.length > 0) {
  console.error('check-i18n: raw <table> outside shared/ui — use app-ledger-table / app-data-table:');
  [...new Set(rawTables)].slice(0, 30).forEach((f) => console.error('  ' + f));
  failed = true;
}
if (missing.length > 0) {
  console.error(`check-i18n: ${missing.length} used keys missing from ar.json:`);
  missing.slice(0, 40).forEach((key) => console.error('  ' + key));
  failed = true;
}
if (unused.length > 0) {
  console.error(`check-i18n: ${unused.length} defined keys unused — remove them from ar.json:`);
  unused.slice(0, 40).forEach((key) => console.error('  ' + key));
  failed = true;
}
if (!failed) {
  console.log(`check-i18n: OK (${used.size} keys used, ${defined.size} defined)`);
}
process.exit(failed ? 1 : 0);
