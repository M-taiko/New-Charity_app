# Tooling
Node 20 LTS + npm. الأوامر في package.json: start/build/build:analyze/lint/lint:styles/check:i18n/check:readmes/check:palette/check:size/icons/fonts/pack/bootstrap.
- ESLint (flat) بقواعد Angular + no-console + no-explicit-any.
- Stylelint: BEM pattern، بلا IDs/!important/ng-deep، وفحص الميزات غير المدعومة في المتصفحات.
- Prettier + EditorConfig موحّدان.
- توليد الصفحات: scripts/gen-pages*.py (يفكّ المواصفات إلى مكونات حقيقية).
