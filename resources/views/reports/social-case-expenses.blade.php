@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-chart-pie"></i> تقرير مصروفات الحالات الاجتماعية
            </h1>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-filter"></i> الفلاتر
                    </h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">الحالة الاجتماعية</label>
                            <select name="social_case_id" class="form-select">
                                <option value="">-- جميع الحالات --</option>
                                @foreach($socialCases as $case)
                                    <option value="{{ $case->id }}" {{ $socialCaseId == $case->id ? 'selected' : '' }}>
                                        {{ $case->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">الفئة</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- جميع الفئات --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">من التاريخ</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">إلى التاريخ</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <a href="{{ route('reports.social-case-expenses') }}" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> إعادة تعيين
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4" data-aos="fade-up">
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">إجمالي المصروفات</p>
                            <h4 class="mb-0" style="color: #667eea;">{{ number_format($totalAmount, 2) }} ج.م</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #667eea; opacity: 0.2;">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">عدد المصروفات</p>
                            <h4 class="mb-0" style="color: #764ba2;">{{ $expenseCount }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #764ba2; opacity: 0.2;">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">متوسط المصروف</p>
                            <h4 class="mb-0" style="color: #10b981;">
                                {{ $expenseCount > 0 ? number_format($totalAmount / $expenseCount, 2) : '0.00' }} ج.م
                            </h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #10b981; opacity: 0.2;">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Categories Breakdown -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-list"></i> توزيع المصروفات حسب الفئة
                    </h5>
                </div>
                <div class="card-body">
                    @if($expensesByCategory->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background: #f8f9ff;">
                                    <tr>
                                        <th>الفئة</th>
                                        <th>العدد</th>
                                        <th>الإجمالي</th>
                                        <th>النسبة</th>
                                        <th>الرسم البياني</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expensesByCategory as $categoryName => $data)
                                        <tr>
                                            <td><strong>{{ $categoryName }}</strong></td>
                                            <td>{{ $data['count'] }}</td>
                                            <td>{{ number_format($data['amount'], 2) }} ج.م</td>
                                            <td>{{ $data['percentage'] }}%</td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" style="width: {{ $data['percentage'] }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);" aria-valuenow="{{ $data['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                                        {{ $data['percentage'] }}%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> لا توجد مصروفات مطابقة للفلاتر المحددة
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Expenses Table -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> تفاصيل المصروفات
                    </h5>
                </div>
                <div class="card-body">
                    @if($expenses->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background: #f8f9ff;">
                                    <tr>
                                        <th>التاريخ</th>
                                        <th>الحالة</th>
                                        <th>الفئة</th>
                                        <th>البند</th>
                                        <th>المبلغ</th>
                                        <th>المندوب</th>
                                        <th>الوصف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($expenses as $expense)
                                        <tr>
                                            <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                                            <td>
                                                @if($expense->socialCase)
                                                    <span class="badge bg-info">{{ $expense->socialCase->name }}</span>
                                                @else
                                                    <span class="badge bg-secondary">عام</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($expense->category)
                                                    <strong>{{ $expense->category->name }}</strong>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($expense->item)
                                                    {{ $expense->item->name }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <strong style="color: #667eea;">{{ number_format($expense->amount, 2) }} ج.م</strong>
                                            </td>
                                            <td>{{ $expense->user->name }}</td>
                                            <td>
                                                <small>{{ Str::limit($expense->description, 30) }}</small>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> لا توجد مصروفات مطابقة للفلاتر المحددة
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>
@endpush

@endsection
