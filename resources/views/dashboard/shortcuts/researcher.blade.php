<div class="shortcut-grid">
    <a href="{{ route('social_cases.researcher') }}" class="shortcut-card">
        <div class="shortcut-icon">📁</div>
        <div class="shortcut-title">حالاتي</div>
        <div class="shortcut-desc">عرض جميع حالاتي الاجتماعية</div>
    </a>

    <a href="{{ route('social_cases.create') }}" class="shortcut-card">
        <div class="shortcut-icon">➕</div>
        <div class="shortcut-title">حالة جديدة</div>
        <div class="shortcut-desc">إنشاء حالة اجتماعية جديدة</div>
    </a>

    <a href="{{ route('social_cases.index') }}" class="shortcut-card">
        <div class="shortcut-icon">📋</div>
        <div class="shortcut-title">جميع الحالات</div>
        <div class="shortcut-desc">عرض جميع الحالات في النظام</div>
    </a>

    <a href="{{ route('social_cases.researcher') }}?filter=pending" class="shortcut-card">
        <div class="shortcut-icon">⏳</div>
        <div class="shortcut-title">الحالات المعلقة</div>
        <div class="shortcut-desc">حالاتي التي تحتاج متابعة</div>
    </a>

    <a href="{{ route('social_cases.researcher') }}?filter=approved" class="shortcut-card">
        <div class="shortcut-icon">✅</div>
        <div class="shortcut-title">الحالات الموافق عليها</div>
        <div class="shortcut-desc">الحالات التي تم الموافقة عليها</div>
    </a>

    <a href="{{ route('social_cases.researcher') }}" class="shortcut-card">
        <div class="shortcut-icon">📊</div>
        <div class="shortcut-title">تقاريري</div>
        <div class="shortcut-desc">عرض إحصائيات أدائي</div>
    </a>
</div>
