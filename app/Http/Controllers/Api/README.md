# Backend /api/v1 — طبقة JSON أمامية للخدمات القائمة

توفر هذه الطبقة كل ما تستهلكه الواجهة الجديدة (SPA) من `/api/v1` بدون نسخ أي منطق أعمال: **169 مسارًا** يغطي جرد الميزات كاملًا (القسم 21 من الخطة).

## المبدأ المعماري

1. **القراءات** (`Api\V1\*Controller`) — استعلامات مباشرة على الموديلات الحالية بعقد قوائم موحّد، والأرقام المالية دائمًا نصّ بخانتين عشريتين، والتواريخ ISO-8601 UTC.
2. **الأفعال** (إنشاء/موافقة/رفض/تحويل…) — **تفويض مباشر إلى المتحكمات والخدمات القائمة نفسها** (`TreasuryService`, `CustodyTransferService`, …). قواعد T1–T36 تبقى بمصدر واحد؛ وسيط `ApiV1Response` يحوّل إجاباتها (redirect + flash) إلى JSON بالشكل الموحّد.
3. **الصلاحيات** كما هي تمامًا: نفس permissions/gates عبر `$this->authorize()`؛ الـ API ليس أكثر تساهلًا من Blade مطلقًا.

## الملفات

```
app/Http/Controllers/Api/V1/
├─ AuthController          login/logout/me (الأدوار+القدرات+إعدادات المؤسسة)
├─ LookupController        /lookups — المحافظات، شجرة التوجيهات، الخزائن، المستخدمون
├─ DashboardController     لوحات الأدوار الأربع + مستكشف التوجيه (T33) — يوسّع متحكم Blade
├─ TreasuryController      الخزائن وحركاتها
├─ CustodyController       العهد: القائمة، التفاصيل، عهداتي، المتابعة، حركات العهد، طلبات الرد
├─ CustodyTransferController  التحويلات (مرسل/مستلم/الكل — T11/T26/T32)
├─ ExpenseController       المصروفات + فلاتر T31 + طلبات التعديل
├─ ExpenseItemController   شجرة التوجيهات المحاسبية (أربعة مستويات + بنود)
├─ ReportController        التقارير الثمانية بما فيها تقرير المحافظات (T34)
├─ SocialCaseController    الحالات الاجتماعية بفلتر المحافظة المسوّى
├─ HrController            الموظفون/الحضور/KPI/المرتبات
├─ ProcurementController   الشراء/الصيانة/الموردون
└─ SystemController        المهام، المحادثة، الإشعارات، الرسائل العاجلة، السجل، المستخدمون، الإعدادات، الملف الشخصي
```

مسارات: `routes/api_v1.php` (داخل مجموعة web — جلسة + CSRF كما هي، والعميل يعيد ترويسة X-XSRF-TOKEN تلقائيًا). الـ 401 لانتهاء الجلسة JSON من `bootstrap/app.php`.

## عقد الاستجابة

- القوائم: `{ data: [...], meta: { page, per_page, total, last_page, summary? }, links }`
- عنصر واحد: `{ data: {...} }` — فعل: `{ data, message, level }`
- أخطاء التحقق 422 `{ message, errors: {field: [...] } }` — خطأ أعمال 409/422 مع `code` ثابت.

## التشغيل والاختبار

```bash
php artisan route:list | grep api/v1   # استعراض المسارات
php artisan serve                      # ثم فتح /app للواجهة الجديدة
```
