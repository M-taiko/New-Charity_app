import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

// Naguib Selim Charity — PrimeNG preset generated only from the eight brand colors (plan 10.1.5).
// Scales repeat palette tones instead of generating greys. Hard 1px edges, no blur.
const INK = '#4F2F1D';
const BRAND = '#5D3722';
const MID = '#7D5F4E';
const LIGHT = '#9E877A';
const TAUPE = '#BEAFA7';
const SAND = '#DFD7D3';
const WHITE = '#FFFFFF';
const CREAM = '#F7F3EF';

export const brandPreset = definePreset(Aura, {
  semantic: {
    primary: {
      50: CREAM,
      100: SAND,
      200: SAND,
      300: TAUPE,
      400: LIGHT,
      500: MID,
      600: BRAND,
      700: BRAND,
      800: INK,
      900: INK,
      950: INK,
    },
    surface: {
      0: WHITE,
      50: CREAM,
      100: CREAM,
      200: SAND,
      300: SAND,
      400: TAUPE,
      500: LIGHT,
      600: MID,
      700: BRAND,
      800: BRAND,
      900: INK,
      950: INK,
    },
    colorScheme: {
      light: {
        primary: { color: BRAND, contrastColor: WHITE, hoverColor: INK, activeColor: INK },
        content: { background: WHITE, hoverBackground: SAND, borderColor: SAND },
        formField: { background: WHITE, borderColor: LIGHT },
        text: { color: INK, mutedColor: MID, hoverColor: INK },
        focusRing: { color: BRAND },
      },
      dark: {
        // dark theme: ink page, mid cards, cream primary — same as CSS tokens
        primary: { color: CREAM, contrastColor: INK, hoverColor: WHITE, activeColor: WHITE },
        content: { background: MID, hoverBackground: LIGHT, borderColor: LIGHT },
        formField: { background: BRAND, borderColor: LIGHT },
        text: { color: CREAM, mutedColor: SAND, hoverColor: CREAM },
        focusRing: { color: TAUPE },
      },
    },
  },
  components: {
    dialog: {
      root: { borderRadius: '10px' },
    },
    menu: { root: { borderRadius: '6px' } },
    select: {
      root: { borderRadius: '6px' },
      overlay: { background: 'var(--p-surface-600)', borderRadius: '8px' },
    },
    inputtext: { root: { borderRadius: '6px' } },
    button: { root: { borderRadius: '6px' } },
    datatable: {
      header: { background: 'var(--p-surface-100)', borderColor: 'var(--p-surface-200)' },
    },
  },
});
