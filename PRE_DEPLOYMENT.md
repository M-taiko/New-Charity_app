# قائمة التحقق قبل الرفع على الـ Hosting

## 1. تحضير ملف .env

```bash
# اسخ ملف .env.example إلى .env على الـ hosting
cp .env.example .env

# أو إذا لم يكن موجود، اسخ من نسخة محلية وحدث:
```

تأكد من تحديث المتغيرات التالية:

```env
APP_NAME="اسم المؤسسة الخيرية"
APP_ENV=production
APP_DEBUG=false  # ضروري! لا تجعله true على الـ production
APP_URL=https://yourdomain.com  # ضع دومينك الفعلي

# تحديث بيانات الـ Database
DB_CONNECTION=mysql
DB_HOST=localhost  # أو الـ host من provider
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# تحديث الـ Mail (اختياري)
MAIL_MAILER=smtp
MAIL_HOST=your_mail_host
MAIL_PORT=your_mail_port
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_password

# Session & Cache
SESSION_DRIVER=database
CACHE_STORE=database
```

## 2. تثبيت التبعيات

```bash
# على الـ hosting عبر SSH
composer install --optimize-autoloader --no-dev

# للـ development (اختياري)
composer install
```

## 3. إنشاء كمية التشفير (Encryption Key)

إذا لم تحتوي `.env` على `APP_KEY`:

```bash
php artisan key:generate
```

## 4. تشغيل قاعدة البيانات

```bash
# تشغيل الـ migrations
php artisan migrate

# تشغيل الـ seeders (ينشئ البيانات الافتراضية)
php artisan db:seed

# أو seeders محددة
php artisan db:seed --class=RoleAndPermissionSeeder
php artisan db:seed --class=DemoDataSeeder
```

## 5. تحسين الأداء

```bash
# تخزين التكوين في الـ cache
php artisan config:cache

# تخزين الـ routes في الـ cache
php artisan route:cache

# تخزين الـ views في الـ cache (اختياري)
php artisan view:cache
```

## 6. إعدادات الأذونات

على الـ FTP أو SSH:

```bash
# الأذونات الصحيحة للمجلدات
chmod -R 755 public/
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# ملف .env
chmod 600 .env

# إذا لم تعمل الأذونات، جرّب 777 (أقل أماناً):
chmod -R 777 storage/
chmod -R 777 bootstrap/cache/
```

## 7. إنشاء المجلدات الضرورية

```bash
mkdir -p storage/app/public
mkdir -p storage/logs
mkdir -p bootstrap/cache
```

## 8. الملفات والمجلدات الواجب تجاهلها (يجب أن تكون في .gitignore)

```
.env          # لا ترفع ملف .env الفعلي
node_modules/
vendor/       # يتم تثبيته عبر composer
/storage/logs
/bootstrap/cache
.DS_Store
*.log
```

## 9. التحقق النهائي

قبل فتح الموقع للمستخدمين:

- [ ] تحقق من صفحة الـ login: `https://yourdomain.com/login`
- [ ] حاول تسجيل الدخول برقم المستخدم الافتراضي:
  - Email: `donia.a5ra2019@gmail.com`
  - Password: `123456789`
- [ ] جرّب رفع صورة في الإعدادات
- [ ] تحقق من ظهور الصور في السايد بار و navbar
- [ ] اختبر جميع الأدوار (مدير، محاسب، مندوب، باحث)

## 10. مشاكل شائعة وحلولها

### مشكلة: "Call to undefined function Illuminate\Filesystem\exec()"

**السبب:** الـ hosting لا يسمح بـ `exec()` function

**الحل:** تم تضمين حل في المشروع:
- استخدم ملف `public/storage/serve.php`
- لا تشغّل `php artisan storage:link`

### مشكلة: 500 Error عند الدخول

```bash
# تحقق من الأخطاء
cat storage/logs/laravel.log

# أعد تشغيل الـ cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### مشكلة: الصور لا تظهر

1. تحقق من أن المجلد `public/storage/` موجود
2. تحقق من أذونات المجلد
3. جرّب الدخول المباشر: `https://yourdomain.com/storage/filename`

### مشكلة: Database Connection Error

- تحقق من بيانات الـ database في `.env`
- جرّب الاتصال عبر command line
- تأكد من أن الـ database موجود

## 11. للمتقدمين: استخدام HTTPS

```bash
# تحديث APP_URL
APP_URL=https://yourdomain.com

# إجبار HTTPS في الكود
# أضف في AppServiceProvider.php:
if (env('APP_ENV') == 'production') {
    \Illuminate\Support\Facades\URL::forceScheme('https');
}
```

## 12. نصائح الأمان

- [ ] تأكد من `APP_DEBUG=false` على الـ production
- [ ] غيّر كلمة مرور الـ admin الافتراضي
- [ ] استخدم HTTPS على الموقع
- [ ] حدّث Laravel و جميع التبعيات بانتظام
- [ ] نسخ احتياطية من قاعدة البيانات بانتظام

## 13. الخطوات النهائية

```bash
# بعد التأكد من كل شيء
php artisan optimize

# لتصفية الـ cache (في حالة وجود مشاكل)
php artisan cache:clear
php artisan config:clear
```

---

**ملاحظة:** احتفظ بنسخة من ملف `.env` الفعلي في مكان آمن ولا تضعه أبداً في نظام التحكم الإصدار!
