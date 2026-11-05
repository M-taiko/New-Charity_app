import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';

// Naguib Selim Charity — PrimeNG preset generated only from the eight brand
// colors (plan 10.1.5), the four semantic action colors (T6-13) and the module
// accent set (T6-40). Scales repeat palette tones instead of generating greys.
// Hard 1px edges, no blur.
const INK = '#4F2F1D';
const BRAND = '#5D3722';
const MID = '#7D5F4E';
const LIGHT = '#9E877A';
const TAUPE = '#BEAFA7';
const SAND = '#DFD7D3';
const SAND_DEEP = '#D2C7C1'; // one step below sand — selected+focused options (T6-14)
const WHITE = '#FFFFFF';
const CREAM = '#F7F3EF';

// Semantic action colors (T6-13) — one green/red/amber/blue-teal pair per scale,
// repeated across tones like the brand scales. Filled 500s all pass 4.5:1 with
// white text; the 700/800 tones are for outlined/text variants on light surfaces.
// Mapping them here means button, tag, message, toast and badge severities can
// never fall back to PrimeNG's default greens/blues/oranges.
const GREEN = '#1E7A46';
const GREEN_HOVER = '#175F37';
const GREEN_ACTIVE = '#124A2C';
const GREEN_SOFT = '#E7F5EC';
const GREEN_MID = '#A8D6BE';

const RED = '#B3261E';
const RED_HOVER = '#991D15';
const RED_ACTIVE = '#7C170F';
const RED_SOFT = '#FBEAE9';
const RED_MID = '#E8A9A5';

const AMBER = '#96650A';
const AMBER_HOVER = '#7A5006';
const AMBER_ACTIVE = '#5F3E04';
const AMBER_SOFT = '#FBF0D5';
const AMBER_MID = '#F0D08A';

const TEAL = '#0E6E77';
const TEAL_HOVER = '#0A565E';
const TEAL_ACTIVE = '#084449';
const TEAL_SOFT = '#E1F1F4';
const TEAL_MID = '#8FCFD6';

export const brandPreset = definePreset(Aura, {
  // Primitive color scales (T6-13): consumed by button/tag/message/toast/badge
  // severities success, danger, warn and info.
  primitive: {
    green: {
      50: GREEN_SOFT,
      100: '#D2EBDD',
      200: GREEN_MID,
      300: '#5CB383',
      400: '#38955D',
      500: GREEN,
      600: GREEN_HOVER,
      700: GREEN_ACTIVE,
      800: GREEN_HOVER,
      900: GREEN_ACTIVE,
      950: '#0B3520',
    },
    red: {
      50: RED_SOFT,
      100: '#F5D2D0',
      200: RED_MID,
      300: '#DB7F79',
      400: '#C93B30',
      500: RED,
      600: RED_HOVER,
      700: RED_ACTIVE,
      800: RED_HOVER,
      900: RED_ACTIVE,
      950: '#370604',
    },
    orange: {
      50: AMBER_SOFT,
      100: '#F7E4B4',
      200: AMBER_MID,
      300: '#E5B653',
      400: '#C8920D',
      500: AMBER,
      600: AMBER_HOVER,
      700: AMBER_ACTIVE,
      800: AMBER_HOVER,
      900: AMBER_ACTIVE,
      950: '#2E2000',
    },
    blue: {
      50: TEAL_SOFT,
      100: '#C3E5E9',
      200: TEAL_MID,
      300: '#57B3BD',
      400: '#2E93A0',
      500: TEAL,
      600: TEAL_HOVER,
      700: TEAL_ACTIVE,
      800: TEAL_HOVER,
      900: TEAL_ACTIVE,
      950: '#032A2E',
    },
  },
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
      300: SAND_DEEP,
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
        // Overlay lists (T6-14): white panel, cream hover, sand selection —
        // no brown fill anywhere in the list. Covers select, multiselect,
        // autocomplete, listbox, menu, datepicker and popover overlays.
        overlay: {
          select: { background: '{content.background}', borderColor: '{content.border.color}', color: '{text.color}' },
          popover: { background: '{content.background}', borderColor: '{content.border.color}', color: '{text.color}' },
        },
        highlight: {
          background: '{surface.200}',
          focusBackground: '{surface.300}',
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
        // dark theme: ink page, mid cards, cream primary — same as CSS tokens
        primary: { color: CREAM, contrastColor: INK, hoverColor: WHITE, activeColor: WHITE },
        content: { background: MID, hoverBackground: LIGHT, borderColor: LIGHT },
        formField: { background: BRAND, borderColor: LIGHT },
        text: { color: CREAM, mutedColor: SAND, hoverColor: CREAM },
        focusRing: { color: TAUPE },
        // Dark overlays keep the explicit _dark-overrides.scss look (T6-14:
        // only the light theme changes). These values make any overlay not
        // covered by that stylesheet follow the same scheme.
        overlay: {
          select: { background: '#201E1B', borderColor: '#3A3835', color: CREAM },
          popover: { background: '#201E1B', borderColor: '#3A3835', color: CREAM },
        },
        highlight: {
          background: '#3A3835',
          focusBackground: '#4A4845',
          color: WHITE,
          focusColor: WHITE,
        },
        list: {
          option: { focusBackground: '#2A2825', focusColor: CREAM },
        },
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
      overlay: { borderRadius: '8px' },
    },
    multiselect: {
      root: { borderRadius: '6px' },
      overlay: { borderRadius: '8px' },
    },
    inputtext: { root: { borderRadius: '6px' } },
    button: { root: { borderRadius: '6px' } },
    datatable: {
      header: { background: 'var(--p-surface-100)', borderColor: 'var(--p-surface-200)' },
    },
  },
});
