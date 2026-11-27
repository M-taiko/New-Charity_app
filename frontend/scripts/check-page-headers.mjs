// check-page-headers: every <app-page-header> in a component template is closed.
// A page header that is opened and never closed takes the whole page into itself
// (Angular builds it without a word): the tab bar, the tiles and the table are
// projected into the header's flex row, the table comes out 0px wide and the page
// looks empty. Round 5 shipped three such pages through 288 green matrix visits.
import { readdirSync, readFileSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const src = resolve(here, '..', 'src', 'app');

function* components(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      yield* components(full);
    } else if (entry.name.endsWith('.ts')) {
      yield full;
    }
  }
}

const bad = [];
let checked = 0;
let withHeader = 0;
for (const file of components(src)) {
  const text = readFileSync(file, 'utf8');
  const match = /(?:template|templateUrl)/.test(text) ? /template:\s*`([\s\S]*?)`/.exec(text) : null;
  if (match === null) {
    continue;
  }
  checked += 1;
  const template = match[1];
  const opened = (template.match(/<app-page-header(?![^>]*\/>)[^>]*>/g) ?? []).length;
  const selfClosed = (template.match(/<app-page-header[^>]*\/>/g) ?? []).length;
  const closed = (template.match(/<\/app-page-header>/g) ?? []).length;
  if (opened + selfClosed > 0) {
    withHeader += 1;
  }
  if (opened !== closed) {
    bad.push(`${relative(file)}: ${opened} opened, ${closed} closed${selfClosed ? `, ${selfClosed} self-closing` : ''}`);
  }
}

function relative(file) {
  return file.replace(src + '\\', '').replace(/\\/g, '/');
}

if (bad.length > 0) {
  console.error('check-page-headers: a page header is never closed:');
  for (const line of bad) {
    console.error(`  ${line}  — close it (</app-page-header>) or make it self-closing ("/>")`);
  }
  process.exit(1);
}
console.log(`check-page-headers: OK (${withHeader} page headers in ${checked} inline templates, every one closed)`);
