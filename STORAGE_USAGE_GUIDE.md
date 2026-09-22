# 📁 دليل استخدام نظام التخزين - Storage Usage Guide

## 🎯 الملخص

النظام يستخدم الآن **`storage/app/public/`** لتخزين جميع الملفات المرفوعة (الصور، الملفات، إلخ).

---

## 📍 مسارات التخزين

### الملفات المرفوعة توُحفظ في:
```
storage/app/public/
├── logos/                  ← شعارات الشركة
├── profile-pictures/       ← صور المستخدمين
└── social-cases/           ← ملفات الحالات الاجتماعية
```

### روابط الملفات تُعرض كـ:
```
https://charity.masarsoft.io/storage/app/public/logos/image.png
```

---

## 💾 كيفية الحفظ

### في Controllers:
```php
// رفع صورة
$path = $request->file('logo')->store('logos', 'public');
// يحفظ في: storage/app/public/logos/{filename}

$path = $request->file('image')->store('profile-pictures', 'public');
// يحفظ في: storage/app/public/profile-pictures/{filename}
```

### في النموذج:
```php
// حفظ المسار
$settings['logo'] = $logoPath;  // مثال: "logos/image.png"
$settings->save();
```

---

## 🖼️ كيفية العرض

### في Blade Templates:

#### الطريقة 1: استخدام `asset()` (الحالية)
```blade
<!-- عرض شعار -->
<img src="{{ asset('storage/' . $logo) }}" alt="شعار">

<!-- عرض صورة ملف شخصي -->
<img src="{{ asset('storage/' . auth()->user()->profile_picture) }}" alt="الصورة">

<!-- تحميل ملف -->
<a href="{{ asset('storage/' . $file->path) }}" download>تحميل</a>
```

#### الطريقة 2: استخدام الـ Helper (جديد)
```blade
<!-- استخدام helper function -->
<img src="{{ storage_url($logo) }}" alt="شعار">

<!-- أو Blade directive -->
<img src="@storageUrl('logos/image.png')" alt="شعار">
```

### في PHP Code:
```php
// في Controller أو Helper
$url = storage_url('logos/image.png');
// يرجع: https://charity.masarsoft.io/storage/app/public/logos/image.png

// التحقق من وجود الملف
if (storage_exists('logos/image.png')) {
    // الملف موجود
}

// حذف الملف
storage_delete('logos/image.png');
```

---

## 📋 أمثلة عملية

### مثال 1: رفع وحفظ شعار

**في Controller:**
```php
public function updateLogo(Request $request)
{
    $request->validate([
        'logo' => 'required|image|max:2048',
    ]);

    // حذف الشعار القديم
    $oldLogo = Setting::get('logo');
    if ($oldLogo && storage_exists($oldLogo)) {
        storage_delete($oldLogo);
    }

    // رفع الشعار الجديد
    $logoPath = $request->file('logo')->store('logos', 'public');

    // حفظ المسار
    Setting::set('logo', $logoPath);

    return back()->with('success', 'تم تحديث الشعار');
}
```

**في Blade:**
```blade
<!-- عرض الشعار الحالي -->
@if($settings['logo'])
    <img src="{{ asset('storage/' . $settings['logo']) }}"
         alt="الشعار الحالي">
@endif

<!-- نموذج الرفع -->
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="logo" accept="image/*">
    <button type="submit">رفع شعار</button>
</form>
```

---

### مثال 2: صور المستخدمين

**في Controller:**
```php
public function updateProfilePicture(Request $request, User $user)
{
    $request->validate([
        'profile_picture' => 'required|image|max:1024',
    ]);

    // حذف الصورة القديمة
    if ($user->profile_picture && storage_exists($user->profile_picture)) {
        storage_delete($user->profile_picture);
    }

    // رفع الصورة الجديدة
    $picturePath = $request->file('profile_picture')
        ->store('profile-pictures', 'public');

    // تحديث المستخدم
    $user->update(['profile_picture' => $picturePath]);

    return back()->with('success', 'تم تحديث صورتك');
}
```

**في Blade:**
```blade
<!-- عرض صورة المستخدم -->
@if(auth()->user()->profile_picture)
    <img src="{{ asset('storage/' . auth()->user()->profile_picture) }}"
         alt="صورتي"
         style="width: 100px; height: 100px; border-radius: 50%;">
@else
    <div class="placeholder">لا توجد صورة</div>
@endif
```

---

### مثال 3: ملفات الحالات الاجتماعية

**في Controller:**
```php
public function storeSocialCase(Request $request)
{
    $request->validate([
        'documents.*' => 'required|file|max:5120',
    ]);

    $case = SocialCase::create($request->validated());

    // رفع الملفات
    if ($request->hasFile('documents')) {
        foreach ($request->file('documents') as $file) {
            $path = $file->store("social-cases/{$case->id}", 'public');

            $case->documents()->create([
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
            ]);
        }
    }

    return back()->with('success', 'تم إضافة الحالة');
}
```

**في Blade:**
```blade
<!-- عرض الملفات -->
@foreach($case->documents as $document)
    <a href="{{ asset('storage/' . $document->file_path) }}"
       download>
        {{ $document->file_name }}
    </a>
@endforeach
```

---

## 🔧 Configuration

### في `config/filesystems.php`:

```php
'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL') . '/storage/app/public',
    'visibility' => 'public',
],
```

**ماذا يعني:**
- `root`: مجلد التخزين الفعلي
- `url`: الـ URL الأساسي للملفات

---

## 🆘 استكشاف المشاكل

### المشكلة: الصور لا تظهر (404)

**الحل:**
```bash
# 1. تحقق من وجود الملفات
ls -la storage/app/public/logos/

# 2. تحقق من الأذونات
chmod -R 755 storage/app/public

# 3. تحقق من المسار في DB
mysql> SELECT logo FROM settings;

# 4. شاهد السجلات
tail -f storage/logs/laravel.log
```

### المشكلة: خطأ في الرفع

**الحل:**
```bash
# 1. تحقق من أذونات الكتابة
ls -la storage/app/

# يجب تكون: drwxrwx---

# 2. إصلاح الأذونات
chmod -R 775 storage/app/public
chmod -R 775 storage/app/private

# 3. تأكد من web server يمكنه الكتابة
chown -R www-data:www-data storage/

# 4. امسح الـ cache
php artisan cache:clear
```

---

## 📊 البنية الكاملة

```
charity.masarsoft.io/
├── public/
│   ├── index.php
│   └── ...
├── storage/
│   ├── app/
│   │   ├── private/      ← ملفات خاصة (غير متاحة للعامة)
│   │   └── public/       ← ملفات عامة (تُعرض على الموقع)
│   │       ├── logos/
│   │       ├── profile-pictures/
│   │       └── social-cases/
│   ├── logs/
│   └── ...
└── ...
```

---

## ✅ الروابط الصحيحة

| الاستخدام | المسار | الرابط |
|-----------|--------|--------|
| **شعار الشركة** | `logos/image.png` | `https://charity.masarsoft.io/storage/app/public/logos/image.png` |
| **صورة المستخدم** | `profile-pictures/user.jpg` | `https://charity.masarsoft.io/storage/app/public/profile-pictures/user.jpg` |
| **ملف الحالة** | `social-cases/1/file.pdf` | `https://charity.masarsoft.io/storage/app/public/social-cases/1/file.pdf` |

---

## 🔐 الأمان

✅ **الملفات في `storage/app/public/`:**
- مرئية للعامة (يقصدها مجلد التخزين العام)
- لا يمكن تنفيذ أكواد PHP (محمية بـ `.htaccess`)
- يمكن حذفها من قبل التطبيق

✅ **الملفات في `storage/app/private/`:**
- غير مرئية مباشرة
- آمنة من الوصول المباشر

---

## 📝 الخلاصة

**الطريقة الصحيحة:**
```php
// حفظ
$path = $file->store('logos', 'public');  // ينتج: logos/filename.ext

// عرض
<img src="{{ asset('storage/' . $path) }}" />  // ينتج URL صحيح
```

**لا تفعل:**
```php
// ❌ لا تحفظ المسار الكامل
$path = 'https://charity.masarsoft.io/storage/...'  // خطأ!

// ❌ لا تستخدم مسار مختلف
$path = 'public/' . $filename;  // خطأ!
```

---

**آخر تحديث**: 2026-02-06
**الحالة**: ✅ جاهز للاستخدام
