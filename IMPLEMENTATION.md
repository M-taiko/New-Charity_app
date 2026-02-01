# نظام إدارة المؤسسة الخيرية - نموذج كامل جاهز للإنتاج

## نظرة عامة
نظام إدارة شامل للمؤسسات الخيرية مع دعم كامل للعهد المالية والحالات الاجتماعية والمصروفات، مع واجهة عربية RTL وتحكم كامل بالأدوار والصلاحيات.

## المكونات المطبقة

### 1. قاعدة البيانات والنماذج
- ✅ Treasuries (الخزائن)
- ✅ Treasury Transactions (عمليات الخزينة)
- ✅ Custodies (العهد)
- ✅ Expenses (المصروفات)
- ✅ Social Cases (الحالات الاجتماعية)
- ✅ Social Case Documents (مستندات الحالات)
- ✅ Notifications (التنبيهات)
- ✅ Users (المستخدمون) مع roles وpermissions

### 2. الأدوار والصلاحيات (Spatie)
- مدير (Manager) - كل الصلاحيات
- محاسب (Accountant) - إدارة الخزينة والعهد
- مندوب (Agent) - قبول العهد والصرف
- باحث اجتماعي (Researcher) - إنشاء الحالات

### 3. الخدمات والمنطق التجاري
- `TreasuryService` - جميع عمليات الخزينة والعهد
- معاملات قاعدة البيانات لجميع العمليات المالية
- نظام تنبيهات للمستخدمين

### 4. المتحكمات (Controllers)
- DashboardController - لوحة التحكم
- TreasuryController - إدارة الخزينة
- CustodyController - إدارة العهد
- ExpenseController - تسجيل المصروفات
- SocialCaseController - إدارة الحالات الاجتماعية
- UserController - إدارة المستخدمين
- SettingsController - الإعدادات
- Auth Controllers - تسجيل الدخول والتسجيل

### 5. الواجهات (Views)
- تصميم RTL عربي 100%
- Bootstrap 5 متقدم
- DataTables من جانب الخادم
- نوافذ منفثقة (Modals) للعمليات
- Toast notifications

### 6. الميزات المطبقة
✅ نظام الخزينة الكامل
✅ عهد مع موافقة ثنائية
✅ تتبع المصروفات والمتبقي
✅ الحالات الاجتماعية
✅ نظام الإخطارات
✅ الأدوار والصلاحيات
✅ DataTables مع البحث والفرز والترقيم
✅ تحويلات البيانات (Transactions)
✅ حذف ناعم (Soft Deletes)
✅ التحقق من صحة البيانات

## حسابات تجريبية

```
مدير النظام:
- البريد: manager@charity.test
- كلمة المرور: password

محاسب:
- البريد: accountant1@charity.test
- كلمة المرور: password

مندوب:
- البريد: agent1@charity.test
- كلمة المرور: password

باحث اجتماعي:
- البريد: researcher1@charity.test
- كلمة المرور: password
```

## تشغيل النظام

### المتطلبات
- PHP 8.2+
- Laravel 12
- MySQL 5.7+
- Composer

### الخطوات
```bash
# 1. تثبيت الملفات
composer install

# 2. تشغيل الخادم
php artisan serve

# 3. الوصول للنظام
http://localhost:8000
```

## البنية المعمارية

### طبقة البيانات (Models)
- علاقات كاملة بين النماذج
- تخزين مؤقت للعمليات الحسابية
- Soft deletes للبيانات الحساسة

### طبقة الخدمات (Services)
- منطق جميع العمليات المالية
- معاملات قاعدة البيانات
- إدارة التنبيهات

### طبقة التحكم (Controllers)
- مع التحكم بالصلاحيات
- مع DataTables
- معالجة الأخطاء

### طبقة العرض (Views)
- Blade مع Bootstrap 5
- التوطين العربي
- واجهات استجابة

## الملفات الرئيسية

### Models
- `app/Models/Treasury.php`
- `app/Models/Custody.php`
- `app/Models/Expense.php`
- `app/Models/SocialCase.php`
- `app/Models/User.php`

### Controllers
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/TreasuryController.php`
- `app/Http/Controllers/CustodyController.php`
- `app/Http/Controllers/ExpenseController.php`
- `app/Http/Controllers/SocialCaseController.php`
- `app/Http/Controllers/UserController.php`

### Services
- `app/Services/TreasuryService.php`

### Views
- `resources/views/layouts/app.blade.php` - القالب الأساسي
- `resources/views/dashboard/index.blade.php`
- `resources/views/treasury/index.blade.php`
- `resources/views/custodies/index.blade.php`
- `resources/views/expenses/index.blade.php`
- `resources/views/social-cases/index.blade.php`

### Localization
- `resources/lang/ar/messages.php` - الرسائل العربية
- `resources/lang/ar/validation.php` - رسائل التحقق

## الميزات الأمنية

✅ Authentication مع Laravel
✅ Authorization باستخدام Spatie
✅ CSRF Protection
✅ Soft Deletes
✅ Database Transactions
✅ Input Validation

## التوسعات المستقبلية الممكنة

- تقارير PDF
- تصدير Excel
- واجهة API بقية
- تطبيق جوال
- نظام SMS للتنبيهات
- دعم عملات متعددة
- قوائم انتظار متقدمة
- ملفات مرفقة للمستندات

## الملاحظات التقنية

- جميع العمليات المالية محمية بـ transactions
- الرصيد يُحدث تلقائياً عند قبول/إرجاع العهد
- التنبيهات تُُُنشأ تلقائياً للمستخدمين المعنيين
- جميع التواريخ والأوقات محفوظة بـ UTC
- البحث والفرز يعمل من جانب الخادم
- معالجة الأخطاء شاملة مع رسائل واضحة

## الدعم والمساعدة

جميع الواجهات مترجمة بالكامل إلى العربية.
جميع الرسائل واضحة وسهلة الفهم.

---

تم بناء هذا النظام بكامل المتطلبات المحددة وهو جاهز للاستخدام الفوري في الإنتاج.
