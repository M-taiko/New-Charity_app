@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-chart-line"></i> مؤشرات الأداء الرئيسية (KPI)
            </h1>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <div class="row g-4">
        @forelse($employees as $employee)
        <div class="col-lg-12" data-aos="fade-up">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;"><i class="fas fa-user"></i> {{ $employee->user->name }} ({{ $employee->job_title }})</h5>
                </div>
                <div class="card-body">
                    @if($employee->kpiMetrics->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>المؤشر</th>
                                    <th>الفترة</th>
                                    <th>الهدف</th>
                                    <th>التحقيق الفعلي</th>
                                    <th>النسبة %</th>
                                    <th>المستوى</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($employee->kpiMetrics as $metric)
                                <tr>
                                    <td><strong>{{ $metric->metric_name }}</strong></td>
                                    <td>{{ $metric->period_start->format('Y-m-d') }} - {{ $metric->period_end->format('Y-m-d') }}</td>
                                    <td>{{ number_format($metric->target_value, 2) }}</td>
                                    <td>{{ number_format($metric->actual_value, 2) }}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" style="width: {{ min($metric->score ?? 0, 100) }}%;">
                                                {{ number_format($metric->score ?? 0, 1) }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if(($metric->score ?? 0) >= 90)
                                            <span class="badge bg-success">ممتاز</span>
                                        @elseif(($metric->score ?? 0) >= 75)
                                            <span class="badge bg-info">جيد</span>
                                        @elseif(($metric->score ?? 0) >= 60)
                                            <span class="badge bg-warning">مقبول</span>
                                        @else
                                            <span class="badge bg-danger">ضعيف</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted text-center py-4">لا توجد مؤشرات أداء لهذا الموظف</p>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle"></i> لا توجد موظفين نشطين
            </div>
        </div>
        @endforelse
    </div>

    <div class="card mt-4" data-aos="fade-up">
        <div class="card-header">
            <h5 style="margin: 0;"><i class="fas fa-plus-circle"></i> إضافة مؤشر أداء جديد</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('hr.kpi.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>الموظف <span class="text-danger">*</span></strong></label>
                        <select name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" required>
                            <option value="">-- اختر موظف --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->user->name }}</option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>اسم المؤشر <span class="text-danger">*</span></strong></label>
                        <input type="text" name="metric_name" class="form-control @error('metric_name') is-invalid @enderror" placeholder="مثال: إتمام المشاريع" required>
                        @error('metric_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>الهدف <span class="text-danger">*</span></strong></label>
                        <input type="number" step="0.01" name="target_value" class="form-control @error('target_value') is-invalid @enderror" required>
                        @error('target_value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>التحقيق الفعلي <span class="text-danger">*</span></strong></label>
                        <input type="number" step="0.01" name="actual_value" class="form-control @error('actual_value') is-invalid @enderror" required>
                        @error('actual_value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>بداية الفترة <span class="text-danger">*</span></strong></label>
                        <input type="date" name="period_start" class="form-control @error('period_start') is-invalid @enderror" required>
                        @error('period_start')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><strong>نهاية الفترة <span class="text-danger">*</span></strong></label>
                        <input type="date" name="period_end" class="form-control @error('period_end') is-invalid @enderror" required>
                        @error('period_end')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> إضافة المؤشر
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
