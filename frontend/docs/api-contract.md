# API contract
المرجع: `routes/api_v1.php` و README الطبقة في app/Http/Controllers/Api.
- القوائم: { data, meta{page,per_page,total,last_page,summary}, links } — الأموال نص بخانتين، التواريخ ISO-8601Z.
- الأفعال تُنفَّذ عبر المتحكمات القائمة (مصدر واحد لقواعد T1–T36) وسيط ApiV1Response يوحّد الاستجابة.
