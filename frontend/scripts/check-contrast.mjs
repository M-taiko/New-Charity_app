// check-contrast (B-03): every status pill / soft-button foreground must
// pass WCAG AA (>= 4.5:1) on its tint background, in BOTH themes.
//
// Sources of truth, parsed straight from _tokens.scss:
//   light: --status-<t>-bg / --status-<t>-text (solid hexes)
//   dark:  --status-<t>-bg is rgb(<channels> / N%) — blended over the card
//          surface (--color-surface) the way the browser composites it.
// The bright --status-<t>-fg hues are intentionally NOT checked here: they
// are reserved for icons/dots/borders where 3:1 graphical contrast applies.
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const tokens = readFileSync(resolve(here, '../src/styles/00-tokens/_tokens.scss'), 'utf8');

function themeBlock(theme) {
  const start = tokens.indexOf(`:root[data-theme='${theme}']`);
  const end = tokens.indexOf('}', start);
  return tokens.slice(start, end);
}

const THEMES = { light: themeBlock('light'), dark: themeBlock('dark') };
const TONES = ['active', 'info', 'warning', 'danger', 'neutral'];

function tokenValue(theme, name) {
  const match = THEMES[theme].match(new RegExp(`--${name}:\\s*([^;]+);`));
  return match ? match[1].trim() : null;
}

function parseColor(raw) {
  const hex = raw.match(/^#([0-9a-f]{6})$/i);
  if (hex) {
    const n = parseInt(hex[1], 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255, 1];
  }
  const rgb = raw.match(/^rgb\(\s*(\d+)\s+(\d+)\s+(\d+)\s*\/\s*([\d.]+)%?\s*\)$/);
  if (rgb) {
    return [Number(rgb[1]), Number(rgb[2]), Number(rgb[3]), Number(rgb[4]) / (raw.includes('%') ? 100 : 1)];
  }
  return null;
}

function blend(fg, bg) {
  return fg.map((channel, i) => i < 3 ? channel * fg[3] + bg[i] * (1 - fg[3]) : 1);
}

function luminance([r, g, b]) {
  const [lr, lg, lb] = [r, g, b].map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * lr + 0.7152 * lg + 0.0722 * lb;
}

function contrast(a, b) {
  const l1 = Math.max(luminance(a), luminance(b));
  const l2 = Math.min(luminance(a), luminance(b));
  return (l1 + 0.05) / (l2 + 0.05);
}

const failures = [];
const rows = [];

for (const theme of ['light', 'dark']) {
  // the surface an alpha tint composites over (cards carry the pills)
  const surfaceRaw = tokenValue(theme, 'color-surface');
  const surface = parseColor(theme === 'light' ? '#ffffff' : '#201e1b');
  if (!surface) {
    failures.push(`${theme}: cannot parse card surface for blending (${surfaceRaw})`);
    continue;
  }
  for (const tone of TONES) {
    const bgRaw = tokenValue(theme, `status-${tone}-bg`);
    const fgRaw = tokenValue(theme, `status-${tone}-text`);
    const bg = parseColor(bgRaw ?? '');
    const fg = parseColor(fgRaw ?? '');
    if (!bg || !fg) {
      failures.push(`${theme} --status-${tone}: unparsable bg "${bgRaw}" or text "${fgRaw}"`);
      continue;
    }
    const effectiveBg = bg[3] < 1 ? blend(bg, surface) : bg;
    const ratio = contrast(fg, effectiveBg);
    rows.push(`${theme.padEnd(5)} ${tone.padEnd(7)} ${ratio.toFixed(2)}:1`);
    if (ratio < 4.5) {
      failures.push(`${theme} --status-${tone}-text (${fgRaw}) on ${bgRaw} = ${ratio.toFixed(2)}:1 — must be >= 4.5:1`);
    }
  }
}

console.log(rows.join('\n'));

if (failures.length > 0) {
  console.error(`\ncheck-contrast: ${failures.length} failure(s):`);
  for (const failure of failures) {
    console.error(`  ✗ ${failure}`);
  }
  process.exit(1);
}
console.log('check-contrast: OK (AA 4.5:1 in both themes)');
