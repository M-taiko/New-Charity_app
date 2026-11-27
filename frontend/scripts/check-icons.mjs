// check-icons (B-01): every icon name used in src/app must resolve to a
// symbol that exists in assets/icons/sprite.svg. A blank icon slot is a
// silent visual bug — this makes it a build failure instead.
//
// Covered usages:
//   - ICON_MAP itself (key → tabler name; build-icons checks @tabler, we
//     check the generated sprite so `npm run icons` can never be forgotten)
//   - `name="x"` on <app-icon …> in inline templates
//   - `icon: 'x'` object members (action kinds, nav items, stamp shapes,
//     row actions, dashboards)
//   - `iconName: 'x'` / `iconName="x"`
//   - static `sprite.svg#tabler-x` hrefs in templates
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

// ── 1. ICON_MAP + sprite ────────────────────────────────────────────────
const mapSource = readFileSync(resolve(root, 'src/app/shared/ui/icon/icon-map.ts'), 'utf8');
const iconMap = new Map();
for (const [, key, value] of mapSource.matchAll(/^\s{2}([A-Za-z0-9_]+):\s*'([a-z0-9-]+)',/gm)) {
  iconMap.set(key, value);
}

const spriteSource = readFileSync(resolve(root, 'src/assets/icons/sprite.svg'), 'utf8');
const symbols = new Set([...spriteSource.matchAll(/id="tabler-([a-z0-9-]+)"/g)].map((m) => m[1]));

const problems = [];

for (const [key, tabler] of iconMap) {
  if (!symbols.has(tabler)) {
    problems.push(`ICON_MAP.${key} -> 'tabler-${tabler}' is missing from the sprite (run: npm run icons)`);
  }
}

/** semantic key or raw tabler name -> sprite symbol */
function resolveIcon(name) {
  return iconMap.get(name) ?? name;
}

// ── 2. scan src/app/**/*.ts for usages ─────────────────────────────────
function* tsFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* tsFiles(full);
    } else if (entry.endsWith('.ts') && !entry.endsWith('.spec.ts')) {
      yield full;
    }
  }
}

const used = new Map(); // name -> [locations]
function record(name, where) {
  if (!/^[a-z0-9][a-z0-9-]*$/.test(name)) {
    return; // dynamic expressions, i18n keys, css… only plain icon ids
  }
  if (!used.has(name)) {
    used.set(name, []);
  }
  used.get(name).push(where);
}

for (const file of tsFiles(resolve(root, 'src/app'))) {
  const source = readFileSync(file, 'utf8');
  const rel = file.slice(root.length + 1);

  // <app-icon name="x" …> (multiline inline templates)
  for (const tag of source.matchAll(/<app-icon\b[^>]*>/gs)) {
    const name = tag[0].match(/\bname="([a-z0-9-]+)"/);
    if (name) {
      record(name[1], `${rel} <app-icon>`);
    }
  }
  // iconName="x" on any element (host bindings of icon inputs)
  for (const m of source.matchAll(/\biconName="([a-z0-9-]+)"/g)) {
    record(m[1], `${rel} iconName=`);
  }
  // icon: 'x' object members (action-kind map, nav items, stamps, rows)
  for (const m of source.matchAll(/\bicon:\s*'([a-z0-9-]+)'/g)) {
    record(m[1], `${rel} icon:`);
  }
  // static sprite hrefs written straight into templates
  for (const m of source.matchAll(/#tabler-([a-z0-9-]+)/g)) {
    record(m[1], `${rel} #tabler-`);
  }
}

for (const [name, locations] of used) {
  const symbol = resolveIcon(name);
  if (!symbols.has(symbol)) {
    const note = iconMap.has(name)
      ? `ICON_MAP key '${name}' -> 'tabler-${symbol}' not in sprite (run: npm run icons)`
      : `'${name}' resolves to 'tabler-${symbol}' which is not in the sprite — add it to ICON_MAP or use iconHref()`;
    problems.push(`${note}\n    used at: ${[...new Set(locations)].slice(0, 5).join(', ')}`);
  }
}

if (problems.length > 0) {
  console.error(`check-icons: ${problems.length} unresolved icon reference(s):`);
  for (const problem of problems) {
    console.error(`  ✗ ${problem}`);
  }
  process.exit(1);
}
console.log(`check-icons: OK (${used.size} used names, ${symbols.size} sprite symbols)`);
