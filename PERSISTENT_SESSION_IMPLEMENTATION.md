# نظام الجلسات الدائمة - Persistent Sessions Implementation

## الملخص
تم تطبيق نظام جلسات دائمة (Persistent Sessions) يسمح للمستخدمين:
- عدم الخروج من النظام تلقائياً (مهما طال الوقت)
- العودة إلى نفس الصفحة التي كانوا عليها عند إغلاق الكمبيوتر وإعادة فتحه
- عدم الحاجة لإعادة إدخال بيانات الدخول

---

## التغييرات المطبقة

### 1. زيادة مدة الجلسة
**الملف:** `config/session.php`
- تم تغيير `SESSION_LIFETIME` من 120 دقيقة إلى 525600 دقيقة (سنة واحدة)

**الملف:** `.env`
- تم تحديث `SESSION_LIFETIME=525600`

**الفائدة:** يمنع انتهاء صلاحية الجلسة لمدة سنة كاملة

---

### 2. تتبع آخر صفحة زارها المستخدم
**الملف الجديد:** `app/Http/Middleware/TrackLastPageUrl.php`

```php
/**
 * يسجل آخر رابط زاره المستخدم في جلسته
 * - يتتبع طلبات GET فقط (لا API endpoints)
 * - يستثني صفحات logout
 */
```

**كيفية العمل:**
- كل طلب GET يتم معالجته
- يُخزَّن الرابط في `session['last_page_url']`
- يمكن استرجاعه لاحقاً لإعادة توجيه المستخدم

---

### 3. إعادة التوجيه إلى آخر صفحة بعد تسجيل الدخول
**الملف:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php`

```php
// في store() method:
$lastPage = session('last_page_url', '/dashboard');
return redirect()->to($lastPage);
```

**الفائدة:** المستخدم يعود إلى نفس الصفحة التي كان عليها

---

### 4. منع إعادة التوجيه للمستخدمين المسجلين
**الملف الجديد:** `app/Http/Middleware/RedirectIfAuthenticated.php`

```php
/**
 * إذا حاول مستخدم مسجل دخول الوصول لصفحة login:
 * - يُوجَّه لآخر صفحة زارها أو /dashboard
 */
```

---

### 5. تسجيل الـ Middleware في التطبيق
**الملف:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        \App\Http\Middleware\TrackLastPageUrl::class,
        \App\Http\Middleware\RedirectIfAuthenticated::class,
    ]);
})
```

---

### 6. إصلاح طارئ في Expense Model
**الملف:** `app/Models/Expense.php`

```php
public function hasPendingEdit(): bool
{
    try {
        return $this->editRequests()
            ->where('status', 'pending')
            ->exists();
    } catch (\Exception $e) {
        return false; // أمان: إذا لم يكن الجدول موجود بعد
    }
}
```

---

## الميزات الإضافية

### خيار "Remember Me"
يوجد بالفعل في صفحة تسجيل الدخول (`auth/login.blade.php`):
```html
<input type="checkbox" name="remember" class="form-check-input" id="remember">
<label class="form-check-label" for="remember">تذكرني في هذا الجهاز</label>
```

عند تفعيله، يتم استخدام `remember_token` اللارافيل لمدة أطول.

---

## سلوك النظام بعد التطبيق

### السيناريو 1: مستخدم يتصفح النظام
1. المستخدم يدخل الصفحة: `/dashboard`
2. الـ middleware يخزن الرابط: `session['last_page_url'] = '/dashboard'`
3. يزور: `/expenses/4` → يخزن الرابط الجديد
4. يزور: `/custodies` → يخزن الرابط الجديد
5. آخر رابط محفوظ: `/custodies`

### السيناريو 2: المستخدم قفل الكمبيوتر
1. جلسة المستخدم محفوظة في قاعدة البيانات (جدول `sessions`)
2. الجلسة تنتهي بعد سنة واحدة (بدلاً من دقيقتين)
3. إذا أعاد فتح الكمبيوتر قبل انتهاء السنة:
   - جلسته لا تزال صحيحة
   - بيانات الدخول محفوظة (cookie)
   - يدخل مباشرة إلى `/custodies` (آخر صفحة)

### السيناريو 3: جلسة انتهت أو حُذفت
1. المستخدم يحاول الوصول إلى `/expenses`
2. الـ auth middleware يعيد التوجيه إلى `/login`
3. يسجل دخول جديد
4. يُوجَّه إلى `/expenses` (من `last_page_url`)

---

## قاعدة البيانات

### جدول sessions (موجود بالفعل)
```
id (string) - معرف الجلسة
user_id (bigint) - معرف المستخدم
ip_address - عنوان IP
user_agent - معلومات المتصفح
payload - بيانات الجلسة المشفرة
last_activity - آخر نشاط
```

### جدول expense_edit_requests (جديد)
```
id
expense_id (FK)
requested_by (FK → users)
reviewed_by (FK → users, nullable)
original_data (JSON)
requested_changes (JSON)
status (enum: pending, approved, rejected)
rejection_reason (nullable)
timestamps + softDeletes
```

---

## قائمة الـ Migrations
✅ 2026_03_28_000001_add_transferred_fields_to_custodies
✅ 2026_03_28_000002_add_custody_transfer_types_to_treasury_transactions
✅ 2026_03_28_000003_add_approval_status_to_expenses
✅ 2026_03_28_000004_create_expense_edit_requests_table

---

## الاختبار

### اختبار يدوي:
1. تسجيل دخول
2. الذهاب إلى `/custodies`
3. غلق المتصفح
4. فتح المتصفح مرة أخرى
5. الدخول إلى الموقع مرة أخرى
6. **النتيجة المتوقعة:** تُفتح صفحة `/custodies` مباشرة بدون الحاجة لإعادة تسجيل دخول

---

## ملاحظات أمان

✅ **HTTPS Cookie**: يُنصح بتفعيل HTTPS في الإنتاج لضمان أمان cookies
✅ **HTTP Only**: يمنع JavaScript من الوصول للـ session cookie
✅ **Same-Site**: ضد CSRF attacks
✅ **Session Encryption**: بيانات الجلسة مشفرة في قاعدة البيانات

---

## ملفات معدَّلة

| الملف | النوع | التفاصيل |
|-------|-------|---------|
| `config/session.php` | تعديل | زيادة SESSION_LIFETIME |
| `.env` | تعديل | تحديث SESSION_LIFETIME |
| `bootstrap/app.php` | تعديل | تسجيل Middleware |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | تعديل | إعادة التوجيه للصفحة السابقة |
| `app/Models/Expense.php` | تعديل | إضافة try/catch للأمان |

## ملفات جديدة

| الملف | النوع | الوصف |
|-------|-------|-------|
| `app/Http/Middleware/TrackLastPageUrl.php` | Middleware | تتبع آخر صفحة |
| `app/Http/Middleware/RedirectIfAuthenticated.php` | Middleware | إعادة توجيه المسجلين |

---

## الاستكمال المستقبلي (اختياري)

### 1. اختيار مدة مخصصة للجلسة
يمكن للمدير اختيار مدة الجلسة من الإعدادات:
- 8 ساعات (يوم عمل واحد)
- 30 يوم (شهر)
- 1 سنة (لا حد)

### 2. تنبيهات نشاط
إرسال تنبيه قبل انتهاء الجلسة بـ 15 دقيقة

### 3. تسجيل دخول متعدد الأجهزة
السماح بفتح جلسات متعددة على أجهزة مختلفة

---

## ملاحظات تطوير

- اختبار مع أنواع مختلفة من المتصفحات
- تحقق من سلوك الـ session مع الـ load balancing
- تأكد من حذف الجلسات القديمة تلقائياً بواسطة Laravel's session sweeper
