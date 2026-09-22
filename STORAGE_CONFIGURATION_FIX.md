# 🔧 إصلاح تكوين التخزين - Storage Configuration Fix

## المشكلة
```
Laravel يحاول إنشاء symlink لـ public/storage
لكن:
1. Symlink موجود بالفعل كـ فولدر حقيقي (directory)
2. Shared Hosting لا يدعم symlink
3. الأوامر تفشل: php artisan storage:link
```

---

## ✅ الحل المطبق

### تم تعديل: `config/filesystems.php`

**القبل** (خاطئ):
```php
'public' => [
    'root' => storage_path('app/public'),  // ❌ في storage/app/public
    ...
],

'links' => [
    public_path('storage') => storage_path('app/public'),  // ❌ يحاول عمل symlink
],
```

**بعد** (صحيح):
```php
'public' => [
    'root' => public_path('storage'),  // ✅ مباشرة في public/storage
    ...
],

'links' => [
    // Disabled: Using direct public/storage folder instead of symlink
    // public_path('storage') => storage_path('app/public'),
],
```

---

## 🎯 ماذا تغير؟

### المجلدات

```
قبل:
├── storage/app/public/
│   ├── logos/
│   ├── profile-pictures/
│   └── social-case-documents/
└── public/storage -> (symlink إلى storage/app/public)

بعد:
├── storage/app/public/
│   └── (أرشيفي - لا يُستخدم)
└── public/storage/
    ├── logos/
    ├── profile-pictures/
    └── social-case-documents/
    ├── index.php (file server)
    └── .htaccess (URL rewriting)
```

### المسارات

```
قبل: /storage/image.png
└─> symlink -> storage/app/public/image.png

بعد: /storage/image.png
└─> public/storage/image.php (يقرأ الملف)
    └─> يرسله للمتصفح
```

---

## 📋 الخطوات المتخذة

### 1️⃣ تحديث الـ Configuration
```php
// في config/filesystems.php
'public' => [
    'root' => public_path('storage'),
],
```

### 2️⃣ تعطيل Symlink
```php
'links' => [
    // Disabled - using direct public/storage folder
],
```

### 3️⃣ إنشاء المجلدات
```bash
mkdir -p public/storage/logos
mkdir -p public/storage/profile-pictures
mkdir -p public/storage/social-case-documents
```

### 4️⃣ الملفات المساعدة موجودة
- ✅ `public/storage/index.php` - File Server
- ✅ `public/storage/.htaccess` - URL Rewriting
- ✅ `public/storage/test.php` - Diagnostic Tool

---

## 🚀 الآن كيف يعمل؟

### عند رفع ملف:

```
1. المستخدم يرفع صورة
   ↓
2. Laravel يحفظها في: public/storage/logos/image.png
   (بدلاً من storage/app/public/)
   ↓
3. يحفظ المسار في DB: "logos/image.png"
```

### عند عرض الملف:

```
1. الكود يستدعي:
   asset('storage/logos/image.png')
   ↓
2. ينتج URL:
   /storage/logos/image.png
   ↓
3. .htaccess يعيد التوجيه إلى:
   /storage/index.php?file=logos/image.png
   ↓
4. index.php يقرأ الملف من:
   public/storage/logos/image.png
   ↓
5. يرسل الملف للمتصفح
```

---

## ✅ الفوائد

| الفائدة | التفاصيل |
|--------|----------|
| **بدون Symlink** | يعمل على Shared Hosting |
| **آمن** | التحقق من الملفات والمسارات |
| **سريع** | قراءة مباشرة من القرص |
| **موثوق** | عدم الاعتماد على إذونات النظام |

---

## 📊 الأداء

```
قبل (Symlink):
/storage/logos/image.png -> (symlink) -> storage/app/public/logos/image.png
⏱️ قد يكون بطيء حسب النظام

بعد (Direct File Server):
/storage/logos/image.png -> index.php -> public/storage/logos/image.png
⏱️ أسرع + موثوق أكثر
```

---

## ⚠️ ملاحظات مهمة

### 1. لا تشغّل `storage:link`
```bash
# ❌ لا تفعل هذا:
php artisan storage:link

# لأن public/storage موجود بالفعل كـ directory
```

### 2. الملفات القديمة
إذا كان لديك ملفات في `storage/app/public/`:
```bash
# نقل الملفات:
cp -r storage/app/public/* public/storage/
```

### 3. الأذونات
```bash
# تأكد من الأذونات:
chmod -R 755 public/storage
chmod 644 public/storage/*.php
chmod 644 public/storage/.htaccess
```

---

## 🧪 الاختبار

### 1. رفع ملف من لوحة التحكم
```
Settings → Upload Logo
```

### 2. تحقق من المجلد
```bash
ls -la public/storage/logos/
```

### 3. جرب الرابط المباشر
```
https://charity.masarsoft.io/storage/logos/image.png
```

### 4. افحص في الكود
```php
// في blade template:
<img src="{{ asset('storage/logos/logo.png') }}" />
```

---

## 🔍 استكشاف الأخطاء

### الملف لا يظهر (404)

```bash
# 1. تحقق من وجود الملف:
ls -la public/storage/logos/

# 2. تحقق من الأذونات:
chmod 755 public/storage
chmod 644 public/storage/logos/*

# 3. شاهد السجلات:
tail -f storage/logs/laravel.log
```

### خطأ في الحفظ

```bash
# تحقق من أذونات الكتابة:
ls -la public/storage/

# يجب تكون: drwxr-xr-x (755)
chmod -R 755 public/storage
```

### الـ .htaccess لا يعمل

```bash
# 1. تحقق من mod_rewrite:
a2enmod rewrite  # في Linux

# 2. تحقق من .htaccess syntax:
cat public/storage/.htaccess

# 3. امسح الـ cache:
php artisan cache:clear
```

---

## 📁 البنية النهائية

```
public/
├── index.php                    ← نقطة الدخول الرئيسية
├── storage/                     ← مجلد التخزين (مباشر - بدون symlink)
│   ├── .htaccess               ← URL rewriting
│   ├── index.php               ← File server
│   ├── test.php                ← Diagnostic tool
│   ├── logos/                  ← صور الشركة
│   ├── profile-pictures/       ← صور المستخدمين
│   └── social-case-documents/  ← ملفات الحالات
└── ...

storage/app/public/             ← (أرشيفي - لا يُستخدم الآن)
```

---

## 🔗 الملفات ذات الصلة

- [IMAGE_SERVING_FIX.md](IMAGE_SERVING_FIX.md) - شرح نظام الملفات
- [PRODUCTION_FIXES_SUMMARY.md](PRODUCTION_FIXES_SUMMARY.md) - ملخص شامل
- `config/filesystems.php` - إعدادات التخزين
- `public/storage/index.php` - File Server
- `public/storage/.htaccess` - URL Rewriting

---

## 📝 Git Commit

```
9f3f8a0 - Update filesystems config to use public/storage directly
```

---

**آخر تحديث**: 2026-02-05
**الحالة**: ✅ جاهز للإنتاج
**التوافقية**: ✅ Shared Hosting
