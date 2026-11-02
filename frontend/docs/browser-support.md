# Browser support (بوابة Chrome 109)
Baseline: Chrome 109, Edge 109, Firefox ESR 115, Safari 15.4 (`.browserslistrc`).
- **Angular 20.3 LTS** chosen: ES2022 output matches the baseline; zoneless (dev-preview in v20) not used — zone.js + OnPush/signals instead. Newest major (22) not adopted: cannot be verified against a real Chrome 109 here; step back recorded per plan 3.2.
- Forbidden in code: color-mix/oklch/nesting/toSorted/groupBy/withResolvers/URL.canParse… (ESLint + stylelint-no-unsupported-browser-features enforce).
- **Runtime PrimeNG theme CSS**: any rejected declaration found during a real Chrome 109 pass must be overridden in `src/styles/02-primeng/` (folder reserved) and recorded here. Overrides recorded so far: none (pending real-device gate).
- Manual gate checklist: boot, login, each list+form, pagination, dialogs, theme toggle both ways, charts, animations degrade, print — on a real Win7/Chrome109 box before every release.

## Automated scan result (2026-10-31)
- **JS API scan**: PASS — no `toSorted/toReversed/toSpliced/with/groupBy/withResolvers/AbortSignal.timeout/URL.canParse/Set methods` in built bundles.
- **CSS function scan**: PASS — no `color-mix()/oklch()/oklab()/lab()/lch()` in built CSS.
- Full gate still requires a real Chrome 109 on Windows 7.
