# إعداد الموقع على الـ Hosting

## خطوات التثبيت على الـ Hosting

### 1. رفع الملفات
- رفع جميع ملفات المشروع إلى مجلد `public_html` أو أي مجلد تم تحديده

### 2. إعدادات الـ Database
```bash
ssh user@hosting.com
cd public_html

# تحديث .env بمعلومات الـ database
nano .env

# تشغيل الـ migrations
php artisan migrate

# تشغيل الـ seeders (اختياري - لإنشاء البيانات الافتراضية)
php artisan db:seed
```

### 3. حل مشكلة Storage Link
على الـ Hosting المشترك، قد لا تعمل الأوامر التالية:
```bash
php artisan storage:link  # قد لا يعمل على shared hosting
```

**الحل:**
- المجلد `public/storage/` موجود بالفعل
- ملف `public/storage/serve.php` يتولى توزيع الملفات
- ملف `public/storage/.htaccess` يعيد التوجيه إلى `serve.php`

### 4. الأذونات على المجلدات

```bash
# على الـ FTP أو SSH
chmod -R 755 public/
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chmod 644 .env
```

### 5. إنشاء المجلدات المطلوبة (إذا لم تكن موجودة)

```bash
mkdir -p storage/app/public
mkdir -p storage/logs
mkdir -p bootstrap/cache
```

### 6. رابط الدخول

بعد التثبيت، يمكنك الدخول للموقع مباشرة عبر:
```
https://yourdomain.com/
```

أو إذا كان الموقع في مجلد فرعي:
```
https://yourdomain.com/New-Charity_app/
```

## استكشاف الأخطاء

### مشكلة: 500 Internal Server Error

1. تحقق من ملف `storage/logs/laravel.log` للأخطاء
2. تأكد من أذونات المجلدات (755 أو 777)
3. تحقق من تثبيت التبعيات:
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

### مشكلة: الملفات المرفوعة لا تظهر

- تأكد من أن مجلد `public/storage/` موجود وله أذونات 755
- تحقق من أن الملفات تُحفظ في `storage/app/public/`
- استخدم المسار الكامل عند الإشارة للملفات: `asset('storage/...')`

### مشكلة: database connection error

- تحقق من بيانات الـ database في ملف `.env`
- تأكد من أن الـ database موجود
- قد تحتاج لإنشاء database جديد من لوحة التحكم

## نصائح مهمة

- **لا تضع** الملف `.env` في الـ public directory
- استخدم ملف `.env.production` مع `php artisan config:cache`
- فعّل `https` على موقعك
- قم بتحديث `APP_URL` في `.env` ليطابق دومينك الفعلي
- استخدم `php artisan config:cache` و `php artisan route:cache` لتحسين الأداء

## استكشاف مشاكل التخزين

إذا كانت الصور لا تظهر بعد الرفع:

1. تحقق من المسار المحفوظ في قاعدة البيانات:
   ```
   SELECT profile_picture FROM users WHERE id = 1;
   ```

2. تأكد من وجود الملف في:
   ```
   storage/app/public/profile-pictures/...
   storage/app/public/logos/...
   storage/app/public/social-case-documents/...
   ```

3. استخدم URL مباشر للتحقق:
   ```
   https://yourdomain.com/storage/profile-pictures/filename.jpg
   ```

## الدعم والمساعدة

للحصول على مساعدة إضافية، راجع:
- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Deployment](https://laravel.com/docs/deployment)
