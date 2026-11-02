# تدقيق توافق الواجهة — Angular/PrimeNG مقابل Chrome 109 والمتصفحات الحديثة

التاريخ: 2026-11-01 · **توثيق فقط — لم تُغيَّر أي إصدارات** (تكملة العملية في compatibility-prototype.md).

## الإصدارات الفعلية المستخدمة
| المكتبة | الإصدار | الحد الأدنى الرسمي للمتصفحات | ملاحظة 109 |
|---------|---------|------------------------------|------------|
| Angular (core/router/forms…) | 20.3.34 | Chrome 110 رسميًا (v20 رفع الحد) | يعمل عمليًا مع polyfills — التفاصيل أدناه |
| zone.js | 0.15.1 | ES2015+ | ✅ |
| TypeScript (target ES2022) | 5.8.3 | — | ES2022 مدعوم كاملًا في 109 ✅ |
| PrimeNG + @primeuix/themes | 20.4.0 / 3.1.0 | متصفحات حديثة فقط | يعمل — بحدود موثقة أدناه |
| chart.js | 4.6.1 | ES2020+ | ✅ (109 = ES2022) |
| @ngx-translate | 18.1.0 | يعتمد على Angular فقط | ✅ |
| gsap | 3.15.1 | IE11+ تاريخيًا | ✅ |
| @tabler/icons (SVG sprite) | 3.50.0 | SVG فقط | ✅ |
| @fontsource (amiri, plex-arabic) | 5.3.0 | woff2 | ✅ (109 يدعم woff2) |

## إعداد البناء (الحاسم)
`browserslist`: `chrome >= 109, edge >= 109, firefox >= 115, safari >= 15.4` —
إصلاح ES/CSS **downlevel تلقائي** في البناء، ونتيجة فحص الـ JS/CSS API سابقة: **PASS** على Chrome 109.

## نتائج الفحص
### ✅ متوافق
- **ES2022 كهدف TS** — Chrome 109 (يناير 2023) يدعم ES2022 كاملًا؛ لا حاجة لخفض الهدف.
- **Intl.DateTimeFormat / RelativeTimeFormat** (`en-GB`, `ar-EG`) — مدعومان منذ Chrome 24/71.
- **CSS منطقي** (inline-start/end, border-block) — منذ Chrome 87.
- **dvh** — منذ Chrome 108 ✅ (حدودنا 109).
- **inset/overscroll-behavior** — قديم كافٍ ✅.

### ⚠️ نقاط حدّية موثقة (لا تكسر الواجهة)
1. **`:has()` — 7 استخدامات** (قفل التمرير عند الحوار، شبكة KPI، شريط جانبي): مدعوم منذ **Chrome 105** ✅ — لكنه **معطّل افتراضيًا في 105–107** ومفعّل من 108؛ عندنا 109 ✅. يبقى حذر: الاستخدامات **تحسينية فقط** (fallback موجود بصريًا).
2. **Angular 20 حدّه الرسمي Chrome 110+** — أساسًا بسبب الاعتماد على ميزات ES2022 (مدعومة في 109) وبعض APIs التوقيت. الفحص الفعلي (النموذج الأولي) هو الحَكَم؛ سابقًا: SPA يعمل على 109 في اختبارات الـ API scan.
3. **PrimeNG 20**: يستخدم خصائص CSS حديثة في الثيم (custom properties فقط — آمن) وoverlays عبر Popper-free positioning يدوي — لا اعتماد معلن على > 109.
4. **`Intl.RelativeTimeFormat('ar-EG', {numeric:'auto'})`** — صيغ auto كاملة منذ 71 ✅.
5. **フォント woff2**: ✅. لا woff3.

### ❌ غير مستخدمة (فُحصت لتأكيد الغياب)
`structuredClone`, `toSorted/toSpliced`, `Array.grouping`, `@layer`, `color-mix`, `oklch`, subgrid, container queries — **لا توجد في الكود**.

## التوصيات (بدون تغيير إصدارات)
1. إبقاء `browserslist` كما هو — هو خط الدفاع (downlevel تلقائي لأي ميزة أحدث).
2. أي كود جديد: تجنّب APIs ما بعد 109 القائمة أعلاه (يكتشفها الفحص: `stylelint-no-unsupported-browser-features` للـ CSS + فحص الـ JS API السكربت).
3. عند ترقية Angular مستقبلًا: إعادة تشغيل النموذج الأولي (المهمة 8) قبل الإصدار.
4. رفع الهدف لـ Chrome 111+ يحتاج قرارًا مستقلًا (مستخدمو Windows 7 هم سبب حد 109).
