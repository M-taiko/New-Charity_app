// Self-hosted WOFF2 subsets (plan 10.3): IBM Plex Sans Arabic 400/500/600 for
// UI + Amiri 700 for display numerals. Subset again with pyftsubset (fonttools
// + brotli, the plan's own toolchain) to stay inside the 220 KB budget.
import { readdirSync, statSync, writeFileSync, mkdirSync, existsSync, copyFileSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');
const outDir = resolve(root, 'src/assets/fonts');
mkdirSync(outDir, { recursive: true });

const FAMILIES = [
  // 500 dropped to honor the 220KB budget (plan 10.3): 400 + 600 cover the UI;
  // recorded in docs/dependencies.md.
  { pkg: '@fontsource/ibm-plex-sans-arabic', prefix: 'ibm-plex-sans-arabic', family: 'IBM Plex Sans Arabic', weights: [400, 600], name: (w) => (w === 400 ? 'regular' : 'semibold') },
  { pkg: '@fontsource/amiri', prefix: 'amiri', family: 'Amiri', weights: [700], name: () => 'bold' },
];

let total = 0;
const css = [];

for (const family of FAMILIES) {
  for (const weight of family.weights) {
    const dir = resolve(root, `node_modules/${family.pkg}/files`);
    if (!existsSync(dir)) {
      console.error(`build-fonts: missing package ${family.pkg}`);
      process.exit(1);
    }
    // Arabic + Latin subsets (arabic subset covers the letters; latin covers digits).
    // fontsource naming: {prefix}-{subset}-{weight}-{style}.woff2
    const files = readdirSync(dir).filter((f) => f.endsWith('.woff2'));
    const picked = [];
    for (const subset of ['arabic', 'latin']) {
      const match = files.find((f) => f === `${family.prefix}-${subset}-${weight}-normal.woff2`);
      if (match) {
        picked.push({ file: match, subset });
      }
    }
    if (picked.length === 0) {
      console.error(`build-fonts: no woff2 for ${family.family} ${weight}`);
      process.exit(1);
    }
    const urls = [];
    for (const { file, subset } of picked) {
      const dest = `${family.prefix}-${weight}-${subset}.woff2`;
      const out = join(outDir, dest);
      if (subset === 'arabic') {
        // strip Quranic/extended blocks the interface never prints
        const tmp = join(tmpdir(), `naguib-selim-${family.prefix}-${weight}.woff2`);
        execFileSync('python', [
          '-m', 'fontTools.subset', join(dir, file),
          `--output-file=${tmp}`,
          '--flavor=woff2',
          '--unicodes=U+0020-007E,U+00A0,U+0600-06FF,U+0750-077F,U+200C-2011,U+2013-2026,U+2039-203A',
          '--layout-features=ccmp,mark,mkmk,init,medi,fina,rlig,liga',
        ], { stdio: 'ignore' });
        writeFileSync(out, readFileSync(tmp));
      } else {
        copyFileSync(join(dir, file), out);
      }
      total += statSync(out).size;
      urls.push(`url('${dest}') format('woff2')`);
    }
    css.push(`@font-face {
  font-family: '${family.family}';
  font-style: normal;
  font-display: swap;
  font-weight: ${weight};
  src: ${urls.join(', ')};
}`);
  }
}

writeFileSync(join(outDir, 'fonts.css'), css.join('\n') + '\n');

const budget = 220 * 1024;
console.log(`build-fonts: ${total} bytes total (${(total / 1024).toFixed(1)} KB)`);
if (total > budget) {
  console.error(`build-fonts: exceeds the 220KB budget`);
  process.exit(1);
}
