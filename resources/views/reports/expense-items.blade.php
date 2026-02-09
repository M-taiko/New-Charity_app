@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-list-check"></i> تقرير البنود المفصلة
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
                        <div class="col-md-4">
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

                        <div class="col-md-4">
                            <label class="form-label">من التاريخ</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">إلى التاريخ</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-search"></i> بحث
                            </button>
                            <a href="{{ route('reports.expense-items') }}" class="btn btn-secondary">
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
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">إجمالي المصروفات</p>
                            <h4 class="mb-0" style="color: #667eea;">{{ number_format($totalAmount, 2) }} ر.س</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #667eea; opacity: 0.2;">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">عدد العمليات</p>
                            <h4 class="mb-0" style="color: #764ba2;">{{ $totalExpenses }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #764ba2; opacity: 0.2;">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">عدد البنود</p>
                            <h4 class="mb-0" style="color: #10b981;">{{ $itemsData->count() }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #10b981; opacity: 0.2;">
                            <i class="fas fa-tag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">متوسط البند</p>
                            <h4 class="mb-0" style="color: #f59e0b;">
                                {{ $totalExpenses > 0 ? number_format($totalAmount / $totalExpenses, 2) : '0.00' }} ر.س
                            </h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #f59e0b; opacity: 0.2;">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> تفاصيل البنود
                    </h5>
                </div>
                <div class="card-body">
                    @if($itemsData->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="background: #f8f9ff;">
                                    <tr>
                                        <th>البند</th>
                                        <th>الفئة</th>
                                        <th>عدد العمليات</th>
                                        <th>الإجمالي</th>
                                        <th>المتوسط</th>
                                        <th>النسبة المئوية</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($itemsData as $data)
                                        @php
                                            $percentage = ($data['total_amount'] / $totalAmount) * 100;
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $data['item']->name }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $data['item']->code }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">{{ $data['item']->category->name }}</span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">{{ $data['total_count'] }}</span>
                                            </td>
                                            <td>
                                                <strong style="color: #667eea;">{{ number_format($data['total_amount'], 2) }} ر.س</strong>
                                            </td>
                                            <td>
                                                {{ number_format($data['average_amount'], 2) }} ر.س
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" style="width: {{ $percentage }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);" aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                                        </div>
                                                    </div>
                                                    <small>{{ number_format($percentage, 1) }}%</small>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> لا توجد بنود بمصروفات مطابقة للفلاتر المحددة
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Pie Chart -->
    @if($itemsData->count() > 0)
        <div class="row mt-4" data-aos="fade-up">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 style="margin: 0;">
                            <i class="fas fa-pie-chart"></i> توزيع المصروفات
                        </h5>
                    </div>
                    <div class="card-body" style="display: flex; justify-content: center;">
                        <canvas id="itemsChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });

    @if($itemsData->count() > 0)
        // Prepare data for pie chart
        const chartData = {
            labels: [
                @foreach($itemsData as $data)
                    '{{ $data['item']->name }}',
                @endforeach
            ],
            datasets: [{
                data: [
                    @foreach($itemsData as $data)
                        {{ $data['total_amount'] }},
                    @endforeach
                ],
                backgroundColor: [
                    '#667eea',
                    '#764ba2',
                    '#f5576c',
                    '#f093fb',
                    '#4facfe',
                    '#00f2fe',
                    '#43e97b',
                    '#fa709a',
                    '#fee140',
                    '#30b0fe'
                ],
                borderColor: '#fff',
                borderWidth: 2
            }]
        };

        const ctx = document.getElementById('itemsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                family: "'Cairo', 'Segoe UI', sans-serif"
                            }
                        }
                    }
                }
            }
        });
    @endif
</script>
@endpush

@endsection
