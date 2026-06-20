@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h1 style="margin:0; font-size:2rem; font-weight:700;">
                    <i class="fas fa-chart-bar"></i> تحليل التوجيهات المحاسبية
                </h1>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="background: #f8f9fa;">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-bold">من التاريخ</label>
                            <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-bold">إلى التاريخ</label>
                            <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row mb-4 g-3">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">إجمالي المصروفات</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ number_format($grandTotal, 2) }} ج.م</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">عدد التوجيهات</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ $categoriesData->count() }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">إجمالي المصروفات</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ $categoriesData->sum('expense_count') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">متوسط المصروف</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ number_format($categoriesData->avg('average_amount'), 2) }} ج.م</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
                <i class="fas fa-chart-bar"></i> الرسوم البيانية
            </h2>
        </div>

        <!-- Expense Distribution Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2 fw-bold" style="font-size: 0.95rem;">
                        <i class="fas fa-pie-chart" style="color: #667eea; margin-left: 0.5rem;"></i> توزيع المصروفات
                    </h6>
                    <div style="position: relative; height: 250px;">
                        <canvas id="expenseDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Amount Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2 fw-bold" style="font-size: 0.95rem;">
                        <i class="fas fa-chart-bar" style="color: #4caf50; margin-left: 0.5rem;"></i> المبالغ المصروفة
                    </h6>
                    <div style="position: relative; height: 250px;">
                        <canvas id="expenseAmountChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Statistics Table -->
    <div class="row">
        <div class="col-12">
            <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">
                <i class="fas fa-table"></i> إحصائيات التفصيلية
            </h2>

            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <tr>
                                <th style="padding: 1rem;">التوجيه المحاسبي</th>
                                <th style="padding: 1rem; text-align: center;">إجمالي المصروفات</th>
                                <th style="padding: 1rem; text-align: center;">عدد العمليات</th>
                                <th style="padding: 1rem; text-align: center;">المتوسط</th>
                                <th style="padding: 1rem; text-align: center;">النسبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categoriesData as $data)
                            <tr style="border-bottom: 1px solid #e0e0e0; transition: background-color 0.2s;">
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: #667eea;">
                                        {{ $data['category']->name }}
                                    </div>
                                    <small style="color: #999;">{{ $data['category']->code }}</small>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span style="background: #e8eef7; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600;">
                                        {{ number_format($data['total_amount'], 2) }} ج.م
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span style="background: #f0f7ff; padding: 0.5rem 1rem; border-radius: 6px;">
                                        {{ $data['expense_count'] }}
                                    </span>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    {{ number_format($data['average_amount'], 2) }} ج.م
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <div style="width: 60px; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden;">
                                            <div style="width: {{ $data['percentage'] }}%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s;"></div>
                                        </div>
                                        <span style="font-weight: 600; color: #667eea;">{{ $data['percentage'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" style="padding: 2rem; text-align: center; color: #999;">
                                    <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity: 0.3;"></i>
                                    لا توجد بيانات مصروفات
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    // Expense Distribution Chart (Pie Chart)
    const distributionCtx = document.getElementById('expenseDistributionChart');
    if (distributionCtx) {
        new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    @foreach($categoriesData as $data)
                        '{{ $data['category']->name }}',
                    @endforeach
                ],
                datasets: [{
                    data: [
                        @foreach($categoriesData as $data)
                            {{ $data['total_amount'] }},
                        @endforeach
                    ],
                    backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#ff9800', '#f5576c', '#2196f3', '#9c27b0', '#4caf50'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 15 } }
                }
            }
        });
    }

    // Expense Amount Chart (Bar Chart)
    const amountCtx = document.getElementById('expenseAmountChart');
    if (amountCtx) {
        new Chart(amountCtx, {
            type: 'bar',
            data: {
                labels: [
                    @foreach($categoriesData as $data)
                        '{{ $data['category']->name }}',
                    @endforeach
                ],
                datasets: [{
                    label: 'المبلغ (ج.م)',
                    data: [
                        @foreach($categoriesData as $data)
                            {{ $data['total_amount'] }},
                        @endforeach
                    ],
                    backgroundColor: '#667eea',
                    borderColor: '#fff',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });
    }
</script>
@endsection
