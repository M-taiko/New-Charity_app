# Tooling

Node 20 LTS + npm. `package.json` scripts:

| Command | What it does |
|---|---|
| `start` / `build` / `watch` | dev server / production build (`ng build`) |
| `lint` | ESLint (flat): Angular TS rules, `no-console`, strict-typed |
| `lint:styles` | Stylelint for `src/styles/**` (BEM, no IDs/`!important`/`ng-deep`) |
| `check:i18n` | ar.json vs usage: missing keys, Arabic literals, unused keys, inline `style="` and raw `<table>` in `src/app` |
| `check:palette` | built CSS contains only the eight brand colors |
| `check:size` | gzipped bundle budgets (initial/lazy/CSS) — fails over max |
| `check:readmes` | the three READMEs exist (root, frontend, backend API) |
| `verify` | **the gate**: lint + lint:styles + check:i18n + build + size + palette + readmes |
| `icons` / `fonts` | rebuild the icon sprite / self-host fonts |
| `test` / `e2e` | Karma unit tests / Playwright e2e (both hard-fail; no `|| echo` fallbacks) |
| `pack` / `bootstrap` | bundle dist into `public/app` / first-run setup |

- `npm run verify` is the pre-push gate; CI runs it plus the backend suite.
- Page scaffolding generators live in `scripts/gen-pages*.py` (one-shot,
  historical — hand-edit the components now).
- Every script in `frontend/scripts/` is referenced from `package.json` or
  documented here; nothing else may be committed there.
