# 🎯 بطاقة المرجعية السريعة

## الروابط السريعة للميزات الجديدة

### 📊 الموارد البشرية (HR)
| الوظيفة | الرابط | المتطلبات |
|--------|--------|----------|
| لوحة التحكم | `/hr/dashboard` | مدير/محاسب |
| الموظفون | `/hr/employees` | مدير/محاسب |
| الحضور | `/hr/attendance` | مدير/محاسب |
| KPI | `/hr/kpi` | مدير/محاسب |

### 📢 الرسائل والإشعارات
| الوظيفة | الرابط | الميزة |
|--------|--------|--------|
| الرسائل العاجلة | `/broadcasts` | استهداف ذكي |
| الإشعارات | `/notifications` | polling ١٥ثانية |
| الصوت | تلقائي | Web Audio API |

### 💬 المحادثة
| الوظيفة | الرابط | الميزة |
|--------|--------|--------|
| المحادثة | `/chat` | مع الاستبيانات |
| الاستبيانات | API | JSON responses |

### 📝 الحالات والسجلات
| الوظيفة | الرابط | المميزات |
|--------|--------|----------|
| الحالات الاجتماعية | `/social_cases` | phase-based |
| سجل نشاطي | `/my-activity` | شخصي |
| سجل النشاط (للمديرين) | `/activity-logs` | الجميع |

### 💰 المصروفات
| الوظيفة | الرابط | الجديد |
|--------|--------|--------|
| المصروفات | `/expenses` | تاريخ+وقت |
| - | - | توجيه محاسبي |

---

## الأوامر السريعة

### إعادة تشغيل بعد التحديث
```bash
php artisan migrate --force
php artisan cache:clear
php artisan config:cache
```

### التحقق من الـ Routes
```bash
# الرسائل العاجلة
php artisan route:list | grep broadcasts

# الموارد البشرية
php artisan route:list | grep hr.

# استبيانات الشات
php artisan route:list | grep chat-polls

# الإشعارات
php artisan route:list | grep notifications
```

### اختبار سريع
```bash
php artisan tinker

# التحقق من الجداول
Schema::hasTable('employees')      # true
Schema::hasTable('chat_polls')     # true
Schema::hasTable('attendance_records') # true
Schema::hasTable('kpi_metrics')    # true
```

---

## خطوات الاستخدام السريعة

### ١️⃣ إضافة موظف جديد
```
١. اذهب: /hr/employees
٢. اضغط: + إضافة موظف جديد
٣. ملأ: المستخدم، القسم، المسمى، الراتب
٤. احفظ
```

### ٢️⃣ تسجيل حضور
```
١. اذهب: /hr/attendance
٢. اختر: الموظف، التاريخ، الحالة
٣. أضف وقت الحضور/الانصراف
٤. احفظ
```

### ٣️⃣ إنشاء استبيان
```
١. اذهب: /chat
٢. اكتب: السؤال والخيارات
٣. شارك الرابط
٤. المستخدمون يصوتون
```

### ٤️⃣ إرسال رسالة عاجلة موجهة
```
١. اذهب: /broadcasts
٢. اختر: "لشخص محدد"
٣. اختر المستخدم
٤. اكتب الرسالة
٥. أرسل
```

### ٥️⃣ إنشاء حالة اجتماعية
```
Phase 1:
١. اذهب: /social_cases/create
٢. ملأ: الاسم، الهاتف، التابعة، الحالة
٣. احفظ

Phase 2:
١. افتح الحالة
٢. ملأ البيانات الكاملة
٣. اختر الجنسية
٤. اكتب الهوية/جواز
٥. احفظ
```

---

## حل المشاكل الشائعة

### الإشعارات لا تصل
```bash
# مسح الكاش
php artisan cache:clear

# تحقق من الـ polling
# في console: Network tab → filter by XHR
```

### الصوت لا يعمل
```
١. تحقق من الصوت بالمتصفح
٢. ابدأ بـ user interaction أولاً
٣. استخدم Chrome أو Firefox
```

### استبيانات لا تظهر
```bash
# تحقق من الـ migration
php artisan migrate:status | grep chat_polls

# مسح الكاش
php artisan cache:clear
```

### HR routes لا تعمل
```bash
# تحقق من الـ role
php artisan tinker
auth()->loginUsingId(1)
auth()->user()->hasRole('مدير')

# يجب أن يعيد true
```

---

## الأعمدة الجديدة في المصروفات

### قبل
```
الاسم | الفئة | المبلغ
```

### بعد ✨
```
الاسم | الفئة | المبلغ | التاريخ والوقت | التوجيه المحاسبي
```

---

## معادلات حساب HR

### حساب الحضور الشهري
```
نسبة الحضور = (أيام حاضر / إجمالي الأيام) × ١٠٠
مثال: 20 يوم حاضر / 22 يوم = 90.9%
```

### حساب KPI
```
درجة الأداء = (التحقيق الفعلي / الهدف) × ١٠٠
مثال: 85 / 100 = 85%

التقييم:
- ممتاز: ≥ 90%
- جيد: ≥ 75%
- مقبول: ≥ 60%
- ضعيف: < 60%
```

---

## الرموز والألوان

### حالة الموظف
```
🟢 نشط        (active)
⚫ غير نشط    (inactive)
🟡 في إجازة  (on_leave)
```

### حالة الحضور
```
🟢 حاضر       (present)
🔴 غائب       (absent)
🟠 متأخر      (late)
🔵 إجازة      (leave)
```

### درجات KPI
```
🟢 ممتاز     ≥ 90%
🔵 جيد      ≥ 75%
🟡 مقبول    ≥ 60%
🔴 ضعيف     < 60%
```

### مستويات الرسائل
```
🔵 معلومة
🟠 تحذير
🔴 خطر
```

---

## الملفات الأساسية للتعديل

### إذا أردت تغيير مدة الـ polling
```
File: resources/views/layouts/modern.blade.php
Search: setInterval(..., 15000)
Change: 15000 → 10000 (10 ثواني)
```

### إذا أردت تغيير صيغة التاريخ
```
File: app/Http/Controllers/ExpenseController.php
Search: ->format('Y-m-d H:i')
Change: إلى أي صيغة تريد
```

### إذا أردت إضافة roles جديدة للـ HR
```
File: app/Http/Controllers/HrController.php
Line: $this->middleware('role:مدير|محاسب');
Change: أضف الـ role الجديد مفصول بـ |
```

---

## الجداول الجديدة

### chat_polls
```
id | chat_message_id | question | options (JSON) | created_by | is_closed | created_at
```

### chat_poll_votes
```
id | poll_id | user_id | option_index | created_at
```

### employees
```
id | user_id | department | job_title | hire_date | salary | status | created_at
```

### attendance_records
```
id | employee_id | date | check_in | check_out | status | created_at
```

### kpi_metrics
```
id | employee_id | metric_name | target_value | actual_value | period_start | period_end | score | created_at
```

---

## الـ API Endpoints الجديدة

### Notifications
```
GET /api/notifications/poll
Response: {unread_count, latest, broadcast}
```

### Chat Polls
```
POST   /api/chat-polls              (create)
POST   /api/chat-polls/{id}/vote    (vote)
GET    /api/chat-polls/{id}         (show)
POST   /api/chat-polls/{id}/close   (close)
```

---

## ملخص الوقت المتوقع

| الوظيفة | الوقت |
|--------|--------|
| إضافة موظف | ٢ دقيقة |
| تسجيل حضور | ١ دقيقة |
| إضافة KPI | ٢ دقيقة |
| إنشاء استبيان | ٢ دقيقة |
| إرسال رسالة عاجلة | ١ دقيقة |
| إنشاء حالة (Phase 1) | ٣ دقائق |
| إكمال حالة (Phase 2) | ٥ دقائق |

---

## نصائح الإنتاجية

1. **استخدم الـ shortcuts:**
   - `Ctrl+Shift+M` → البحث السريع في Chrome
   - `F5` → تحديث الصفحة

2. **عجّل العمل:**
   - استخدم copy-paste للبيانات المتشابهة
   - استخدم التقويم للتواريخ
   - استخدم dropdown بدلاً من الكتابة

3. **تجنب الأخطاء:**
   - تحقق من الحقول المطلوبة قبل الحفظ
   - استخدم الـ validation الحمراء كنبيه
   - اقرأ رسائل الخطأ بعناية

---

## الدعم الفني

**للمشاكل:**
1. اقرأ `SETUP_GUIDE.md`
2. تحقق من `VERIFICATION_CHECKLIST.md`
3. راجع `FEATURES_IMPLEMENTED.md`
4. تفقد `storage/logs/laravel.log`

---

**تم إعداده:** ٢٠٢٦-٠٤-١١  
**الإصدار:** 1.0  
**الحالة:** ✅ جاهز للاستخدام
