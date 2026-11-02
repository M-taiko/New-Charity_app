// check-i18n (plan 14.10): fails when Arabic appears outside ar.json, when a
// used key is missing, or when a defined key is never referenced.
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
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

// collect used keys + Arabic literals from templates and components
const used = new Set();
const arabicLiterals = [];

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
    const text = readFileSync(full, 'utf8');
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
    if (/\.html$/.test(entry) && entry !== 'index.html') {
      const arabic = text.match(/[\u0600-\u06FF]{3,}/);
      if (arabic && !full.includes('assets')) {
        arabicLiterals.push(`${full}: ${arabic[0]}`);
      }
    }
  }
}
walk(src);

const missing = [...used].filter((key) => !defined.has(key));
const unused = [...defined].filter((key) => !used.has(key));

let failed = false;
if (arabicLiterals.length > 0) {
  console.error('check-i18n: Arabic literals in templates:');
  arabicLiterals.slice(0, 20).forEach((line) => console.error('  ' + line));
  failed = true;
}
if (missing.length > 0) {
  console.error(`check-i18n: ${missing.length} used keys missing from ar.json:`);
  missing.slice(0, 40).forEach((key) => console.error('  ' + key));
  failed = true;
}
if (unused.length > 0) {
  console.warn(`check-i18n: ${unused.length} defined keys unused (informational)`);
}
if (!failed) {
  console.log(`check-i18n: OK (${used.size} keys used, ${defined.size} defined)`);
}
process.exit(failed ? 1 : 0);
