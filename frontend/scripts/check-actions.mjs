// check-actions (Task6 T6-13, hardened in Task7 T7-05): button severities in
// templates may only come from the action-kind map's semantic set — danger,
// success, warn, info, secondary. Any other severity (PrimeNG defaults like
// "contrast", custom names, or a hand-picked severity without an allowed
// value) fails. Warn is always outlined/text per the kind table.
// T7-05: inside src/app/features even an ALLOWED severity fails — feature
// templates must use <app-action-button kind="…">; the map, not the page,
// decides severity/variant. Hand-picked severities live only in shared/ui
// (the map's own components) where they are the implementation.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src');

const ALLOWED = new Set(['secondary', 'danger', 'success', 'warn', 'info']);

function* tsFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* tsFiles(full);
    } else if (entry.endsWith('.ts')) {
      yield full;
    }
  }
}

const offenders = [];
for (const file of tsFiles(src)) {
  const code = readFileSync(file, 'utf8');
  const tpl = code.match(/template\s*:\s*`([\s\S]*?)`/);
  if (!tpl) continue;
  const template = tpl[1];
  const isFeature = short(file).startsWith('src/app/features/');
  // opening tags with a severity attribute
  for (const open of template.matchAll(/<(?:p-button|a|button)\b[^>]*?>/gs)) {
    const tag = open[0];
    for (const sev of tag.matchAll(/severity="([a-z-]+)"/g)) {
      if (isFeature) {
        offenders.push(`${short(file)}: severity="${sev[1]}" in a feature template — use <app-action-button kind="…"> (T7-05) — ${tag.slice(0, 90).replace(/\s+/g, ' ')}…`);
      } else if (!ALLOWED.has(sev[1])) {
        offenders.push(`${short(file)}: severity="${sev[1]}" is outside the action-kind set — ${tag.slice(0, 90).replace(/\s+/g, ' ')}…`);
      }
    }
    if (/severity="warn"/.test(tag) && !/\[(outlined|text)\]/.test(tag)) {
      offenders.push(`${short(file)}: warn severity must be outlined or text (T6-13) — ${tag.slice(0, 90).replace(/\s+/g, ' ')}…`);
    }
  }
}

function short(p) {
  return p.replace(/^.*[\\/]src[\\/]/, 'src/');
}

if (offenders.length > 0) {
  console.error(`check-actions: severities outside the action-kind map found:`);
  for (const line of offenders) console.error(`  ${line}`);
  process.exit(1);
}
console.log('check-actions: OK');
