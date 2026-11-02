# Release
1) نسخة احتياطية من DB 2) `npm run build && npm run pack` (ينسخ إلى public/app + .htaccess + checksums) 3) بوابة Chrome 109 الحقيقية 4) رفع public/app مع إبقاء النسخة السابقة للتراجع 5) `php artisan route:cache` إن كنت تستخدمه 6) دخول تجريبي لكل دور. التراجع = العودة لمجلد public/app السابق أو روابط Blade.
