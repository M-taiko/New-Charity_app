# Browser support (بوابة Chrome 109)
Baseline: Chrome 109, Edge 109, Firefox ESR 115, Safari 15.4 (`.browserslistrc`).
- **Angular 20.3 LTS** chosen: ES2022 output matches the baseline; zoneless (dev-preview in v20) not used — zone.js + OnPush/signals instead. Newest major (22) not adopted: cannot be verified against a real Chrome 109 here; step back recorded per plan 3.2.
- Forbidden in code: color-mix/oklch/nesting/toSorted/groupBy/withResolvers/URL.canParse… (ESLint + stylelint-no-unsupported-browser-features enforce).
- **T7-01 `check:compat`** (part of `npm run verify`): a SOURCE-level scan of `src/**/*.{scss,css,ts,html}` — fails on `color-mix()/oklch()/oklab()/lab()/lch()/light-dark()`, native CSS nesting in plain `.css`, and the JS APIs newer than the baseline (toSorted/toReversed/toSpliced/.with(/Object.groupBy/Map.groupBy/Promise.withResolvers/AbortSignal.timeout/URL.canParse/Array.fromAsync/Set union-intersection methods). The soft tint tokens in `00-tokens/_tokens.scss` are channel-alpha `rgb(var(--x-rgb) / N%)` (Chrome 65+), never `color-mix()`.
- **Runtime PrimeNG theme CSS**: any rejected declaration found during a real Chrome 109 pass must be overridden in `src/styles/02-primeng/` (folder reserved) and recorded here. Overrides recorded so far: none (pending real-device gate).
- Manual gate checklist: boot, login, each list+form, pagination, dialogs, theme toggle both ways, charts, animations degrade, print — on a real Win7/Chrome109 box before every release.

## Automated scan result (2026-10-31)
- **JS API scan**: PASS — no `toSorted/toReversed/toSpliced/with/groupBy/withResolvers/AbortSignal.timeout/URL.canParse/Set methods` in built bundles.
- **CSS function scan**: PASS — no `color-mix()/oklch()/oklab()/lab()/lch()` in built CSS.
- Full gate still requires a real Chrome 109 on Windows 7.

## Source-level gate (2026-11-05, T7-01)
- `npm run check:compat`: PASS across `src/` — the color-mix() soft tokens were replaced with channel-alpha rgb() (Chrome 65+), so stamps/chips keep their tint on Chrome 109.
