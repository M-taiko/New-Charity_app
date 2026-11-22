import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

// Naguib Selim Foundation — PrimeNG preset rebuilt on the Stitch palette
// (RD-1.3). Light: brown #5C4033 primary on warm surfaces. Dark: the cream
// primary strategy (#F5EFE8 buttons on warm dark neutrals). Semantic scales
// are built from success #10B981 / danger #E11D48 / warning #F59E0B / info
// #3B82F6 with darker hover/active steps and the RD-1.1 soft tints.
// Hard 1px edges, no blur.
const INK = '#3A261D'; // on-primary-container — deepest brand step
const BRAND = '#5C4033'; // primary
const BRAND_HOVER = '#4A3128';
const PRIMARY_CONTAINER = '#EFE8E1';
const SECONDARY = '#9A7B5C';
const SECONDARY_CONTAINER = '#F5EFE8';
const WHITE = '#FFFFFF';
const SURFACE = '#F4F3F1'; // page surface
const CONTAINER = '#F8FAFC'; // surface-container
const CONTAINER_HIGH = '#E2E8F0'; // surface-container-high
const OUTLINE = '#E2DCD4';
const TEXT = '#1F1B18';
const MUTED = '#6E655E';

// Dark scheme (RD-1.1 dark table)
const D_BG = '#12100E';
const D_INSET = '#1A1815';
const D_SURFACE = '#201E1B';
const D_RAISED = '#2A2825';
const D_BORDER = '#3A3835';
const D_BORDER_STRONG = '#4A4845';
const D_TEXT = '#F5EFE8';
const D_MUTED = '#BEAFA7';

// Semantic action colors (RD-1.1) — 500s carry the identity, 600/700 are the
// hover/active steps, 50/100 are the Stitch solid tints for soft surfaces.
const GREEN = '#10B981';
const GREEN_HOVER = '#0B8A61';
const GREEN_ACTIVE = '#087A54';
const GREEN_SOFT = '#ECFDF5';

const RED = '#E11D48';
const RED_HOVER = '#BE123C';
const RED_ACTIVE = '#9F1239';
const RED_SOFT = '#FFF1F2';

const AMBER = '#F59E0B';
const AMBER_HOVER = '#B45309';
const AMBER_ACTIVE = '#92400E';
const AMBER_SOFT = '#FFFBEB';

const BLUE = '#3B82F6';
const BLUE_HOVER = '#2563EB';
const BLUE_ACTIVE = '#1D4ED8';
const BLUE_SOFT = '#EFF6FF';

export const brandPreset = definePreset(Aura, {
  // Primitive color scales: consumed by button/tag/message/toast/badge
  // severities success, danger, warn and info.
  primitive: {
    green: {
      50: GREEN_SOFT,
      100: '#D1FAE5',
      200: '#A7F3D0',
      300: '#6EE7B7',
      400: '#34D399',
      500: GREEN,
      600: GREEN_HOVER,
      700: GREEN_ACTIVE,
      800: GREEN_HOVER,
      900: GREEN_ACTIVE,
      950: '#064E3B',
    },
    red: {
      50: RED_SOFT,
      100: '#FFE4E6',
      200: '#FECDD3',
      300: '#FDA4AF',
      400: '#FB7185',
      500: RED,
      600: RED_HOVER,
      700: RED_ACTIVE,
      800: RED_HOVER,
      900: RED_ACTIVE,
      950: '#4C0519',
    },
    orange: {
      50: AMBER_SOFT,
      100: '#FEF3C7',
      200: '#FDE68A',
      300: '#FCD34D',
      400: '#FBBF24',
      500: AMBER,
      600: AMBER_HOVER,
      700: AMBER_ACTIVE,
      800: AMBER_HOVER,
      900: AMBER_ACTIVE,
      950: '#451A03',
    },
    blue: {
      50: BLUE_SOFT,
      100: '#DBEAFE',
      200: '#BFDBFE',
      300: '#93C5FD',
      400: '#60A5FA',
      500: BLUE,
      600: BLUE_HOVER,
      700: BLUE_ACTIVE,
      800: BLUE_HOVER,
      900: BLUE_ACTIVE,
      950: '#172554',
    },
  },
  semantic: {
    primary: {
      50: PRIMARY_CONTAINER,
      100: PRIMARY_CONTAINER,
      200: SECONDARY_CONTAINER,
      300: SECONDARY,
      400: SECONDARY,
      500: BRAND,
      600: BRAND,
      700: BRAND_HOVER,
      800: BRAND_HOVER,
      900: INK,
      950: INK,
    },
    surface: {
      0: WHITE,
      50: SURFACE,
      100: CONTAINER,
      200: CONTAINER_HIGH,
      300: OUTLINE,
      400: MUTED,
      500: TEXT,
      600: BRAND,
      700: BRAND,
      800: BRAND_HOVER,
      900: INK,
      950: INK,
    },
    colorScheme: {
      light: {
        primary: { color: BRAND, contrastColor: WHITE, hoverColor: BRAND_HOVER, activeColor: BRAND_HOVER },
        content: { background: WHITE, hoverBackground: CONTAINER, borderColor: OUTLINE },
        formField: { background: CONTAINER, borderColor: OUTLINE },
        text: { color: TEXT, mutedColor: MUTED, hoverColor: TEXT },
        focusRing: { color: BRAND },
        // Overlay lists: white panels, container hover, primary-container selection
        overlay: {
          select: { background: '{content.background}', borderColor: '{content.border.color}', color: '{text.color}' },
          popover: { background: '{content.background}', borderColor: '{content.border.color}', color: '{text.color}' },
        },
        highlight: {
          background: '{surface.100}',
          focusBackground: '{surface.200}',
          color: '{text.color}',
          focusColor: '{text.color}',
        },
        list: {
          option: { focusBackground: '{surface.100}', focusColor: '{text.color}' },
        },
        navigation: {
          item: { focusColor: '{text.color}' },
        },
      },
      dark: {
        // dark theme: warm dark surfaces + cream primary — same as CSS tokens
        primary: { color: D_TEXT, contrastColor: INK, hoverColor: WHITE, activeColor: WHITE },
        content: { background: D_SURFACE, hoverBackground: D_RAISED, borderColor: D_BORDER },
        formField: { background: D_INSET, borderColor: D_BORDER },
        text: { color: D_TEXT, mutedColor: D_MUTED, hoverColor: D_TEXT },
        focusRing: { color: D_TEXT },
        overlay: {
          select: { background: D_SURFACE, borderColor: D_BORDER, color: D_TEXT },
          popover: { background: D_SURFACE, borderColor: D_BORDER, color: D_TEXT },
        },
        highlight: {
          background: D_BORDER,
          focusBackground: D_BORDER_STRONG,
          color: WHITE,
          focusColor: WHITE,
        },
        list: {
          option: { focusBackground: D_RAISED, focusColor: D_TEXT },
        },
      },
    },
  },
  components: {
    dialog: {
      root: { borderRadius: '16px' },
    },
    menu: { root: { borderRadius: '8px' } },
    select: {
      root: { borderRadius: '8px' },
      overlay: { borderRadius: '12px' },
    },
    multiselect: {
      root: { borderRadius: '8px' },
      overlay: { borderRadius: '12px' },
    },
    inputtext: { root: { borderRadius: '8px' } },
    button: { root: { borderRadius: '8px' } },
    datatable: {
      header: { background: 'var(--p-surface-100)', borderColor: 'var(--p-surface-200)' },
    },
  },
});
