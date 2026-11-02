// Builds assets/icons/sprite.svg from the Tabler outline icons referenced by
// ICON_MAP (plan 11.6 + Appendix C). Fails the build on a missing icon.
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

const mapSource = readFileSync(resolve(root, 'src/app/shared/ui/icon/icon-map.ts'), 'utf8');
const iconNames = [...mapSource.matchAll(/'([a-z0-9-]+)'\s*[,}]/g)].map((m) => m[1]);

const symbols = [];
const missing = [];
for (const name of iconNames) {
  const file = resolve(root, `node_modules/@tabler/icons/icons/outline/${name}.svg`);
  if (!existsSync(file)) {
    missing.push(name);
    continue;
  }
  const svg = readFileSync(file, 'utf8');
  const inner = svg
    .replace(/<\?xml[^>]*\?>/, '')
    .replace(/<!DOCTYPE[^>]*>/, '')
    .replace(/<\/?svg[^>]*>/g, '')
    .replace(/stroke="[^"]*"/g, '')
    .replace(/\s+>/g, '>')
    .trim();
  symbols.push(`<symbol id="tabler-${name}" viewBox="0 0 24 24">${inner}</symbol>`);
}

if (missing.length > 0) {
  console.error('build-icons: missing icons in @tabler/icons:', missing.join(', '));
  process.exit(1);
}

const sprite = `<svg xmlns="http://www.w3.org/2000/svg" style="display:none">\n${symbols.join('\n')}\n</svg>\n`;
const outDir = resolve(root, 'src/assets/icons');
mkdirSync(outDir, { recursive: true });
writeFileSync(resolve(outDir, 'sprite.svg'), sprite);
console.log(`build-icons: ${symbols.length} icons -> src/assets/icons/sprite.svg`);
