// check-palette (plan 10.1.6 / RD-1.2): the built CSS may only contain the
// Stitch "Naguib Selim Foundation" palette (light + dark), black-with-alpha
// (shadows/scrim), transparent and currentColor. Catches accidental greys and
// blends. Any new color must land in _tokens.scss FIRST, then here.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { resolve, dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..');

const PALETTE = new Set([
  // ── Light palette (Stitch 1.md → _tokens.scss + Appendix B) ──
  '#5c4033', '#efe8e1', '#3a261d', '#9a7b5c', '#f5efe8', '#4a3525',
  '#f4f3f1', '#1f1b18', '#6e655e', '#f8fafc', '#e2e8f0', '#e2dcd4', '#f1f5f9', '#ffffff', '#fff',
  '#e11d48', '#10b981', '#f59e0b', '#d97706', '#3b82f6',
  '#ecfdf5', '#eff6ff', '#fffbeb', '#fff1f2',
  '#f43f5e', '#ef4444',
  // status pill / soft-button TEXT foregrounds (B-03 — AA on the tints)
  '#047857', '#b45309', '#be123c', '#1d4ed8', '#57534e',
  // ── Chart palette (light) ──
  '#2563eb', '#15803d', '#dc2626', '#cbd5e1', '#eae4dc',
  // ── Tinted summary-card borders (light) ──
  '#bfdbfe', '#fecdd3', '#a7f3d0', '#fde68a',
  // ── Dark palette (RD-1.1 dark table) ──
  '#12100e', '#1a1815', '#201e1b', '#2a2825', '#3a3835', '#4a4845',
  '#34d399', '#fb7185', '#fbbf24', '#60a5fa',
  '#a1886f', '#4ade80', '#f87171', '#93c5fd',
  '#b99b7c', '#beafa7',
  '#4a3128', // light primary hover step
  // ── PrimeNG preset semantic scale steps (brand-preset.ts) ──
  '#d1fae5', '#6ee7b7', '#0b8a61', '#087a54', '#064e3b',
  '#ffe4e6', '#fda4af', '#be123c', '#9f1239', '#4c0519',
  '#fef3c7', '#fcd34d', '#b45309', '#92400e', '#451a03',
  '#dbeafe', '#1d4ed8', '#172554',
  // ── Module accent set (light + dark variants) ──
  '#0e7168', '#6d4fc1', '#9c4a1f', '#4848a0',
  '#5ec6ba', '#e0a43c', '#a68fec', '#d98e5f', '#9090e8',
]);

/** black with an optional alpha channel — shadows and modal scrims only */
function isShadowBlack(color) {
  return /^#0{3}(?:[0-9a-f]{1})?$|^#000000(?:[0-9a-f]{2})?$/.test(color);
}

function* cssFiles(dir) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry);
    if (statSync(full).isDirectory()) {
      yield* cssFiles(full);
    } else if (entry.endsWith('.css')) {
      yield full;
    }
  }
}

const offenders = new Map();
const dist = resolve(root, 'dist');
try {
  for (const file of cssFiles(dist)) {
    const css = readFileSync(file, 'utf8').toLowerCase();
    for (const match of css.matchAll(/#[0-9a-f]{3,8}\b/g)) {
      const color = match[0];
      if (!PALETTE.has(color) && !isShadowBlack(color)) {
        offenders.set(color, (offenders.get(color) ?? 0) + 1);
      }
    }
  }
} catch {
  console.warn('check-palette: dist/ not built yet — skipping');
  process.exit(0);
}

if (offenders.size > 0) {
  console.error('check-palette: colors outside the eight brand colors found:');
  for (const [color, count] of offenders) {
    console.error(`  ${color} x${count}`);
  }
  process.exit(1);
}
console.log('check-palette: OK');
