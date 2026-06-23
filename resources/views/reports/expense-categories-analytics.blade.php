@extends('layouts.modern')

@section('content')
@if(!auth()->user()->hasRole('محاسب') && !auth()->user()->hasRole('مدير'))
    <div class="container-fluid">
        <div class="row mt-5">
            <div class="col-12 text-center">
                <div style="padding: 3rem;">
                    <i class="fas fa-lock fa-5x mb-3 d-block" style="color: #ccc;"></i>
                    <h2 style="color: #666; margin-bottom: 1rem;">الوصول مرفوض</h2>
                    <p style="color: #999; margin-bottom: 2rem;">هذه الصفحة متاحة فقط للمحاسب والمدير</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> العودة للداشبورد
                    </a>
                </div>
            </div>
        </div>
    </div>
@else
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
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">عدد البنود والمستويات</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ $allData->count() }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">إجمالي العمليات</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ $allData->sum('expense_count') }}</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white; border-radius: 12px;">
                <div class="card-body">
                    <div style="font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem;">متوسط المصروف</div>
                    <div style="font-size: 1.8rem; font-weight: 700;">{{ number_format($allData->avg('average_amount'), 2) }} ج.م</div>
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

        <!-- Expense Distribution Doughnut Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2 fw-bold" style="font-size: 0.95rem;">
                        <i class="fas fa-pie-chart" style="color: #667eea; margin-left: 0.5rem;"></i> توزيع المصروفات
                    </h6>
                    <div style="position: relative; height: 300px;">
                        <canvas id="expenseDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expense Amount Bar Chart -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2 fw-bold" style="font-size: 0.95rem;">
                        <i class="fas fa-chart-bar" style="color: #4caf50; margin-left: 0.5rem;"></i> المبالغ المصروفة
                    </h6>
                    <div style="position: relative; height: 300px;">
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
                <i class="fas fa-table"></i> إحصائيات مفصلة
            </h2>

            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <tr>
                                <th style="padding: 1rem;">المستوى | البند</th>
                                <th style="padding: 1rem; text-align: center;">الكود</th>
                                <th style="padding: 1rem; text-align: center;">إجمالي المصروفات</th>
                                <th style="padding: 1rem; text-align: center;">عدد العمليات</th>
                                <th style="padding: 1rem; text-align: center;">المتوسط</th>
                                <th style="padding: 1rem; text-align: center;">النسبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allData->sortByDesc('total_amount') as $data)
                            <tr>
                                <td style="padding: 1rem;">
                                    @if($data['level'] == 1)
                                        <span class="badge bg-primary me-2">م1</span>
                                    @elseif($data['level'] == 2)
                                        <span class="badge bg-info me-2">م2</span>
                                    @elseif($data['level'] == 3)
                                        <span class="badge bg-warning text-dark me-2">م3</span>
                                    @else
                                        <span class="badge bg-secondary me-2">بند</span>
                                    @endif
                                    <strong>{{ $data['name'] }}</strong>
                                    @if(isset($data['parent_name']))
                                        <br><small style="color: #999;">تحت: {{ $data['parent_name'] }}</small>
                                    @endif
                                </td>
                                <td style="padding: 1rem; text-align: center; color: #666;">
                                    <code style="font-size: 0.8rem;">{{ $data['code'] }}</code>
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <strong style="color: #27ae60;">{{ number_format($data['total_amount'], 2) }} ج.م</strong>
                                </td>
                                <td style="padding: 1rem; text-align: center;">{{ $data['expense_count'] }}</td>
                                <td style="padding: 1rem; text-align: center;">{{ number_format($data['average_amount'], 2) }} ج.م</td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background: rgba(103, 126, 234, 0.2); color: #667eea;">
                                        {{ $data['percentage'] }}%
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" style="padding: 2rem; text-align: center; color: #999;">
                                    <i class="fas fa-chart-line fa-3x mb-2 d-block" style="opacity: 0.2;"></i>
                                    لا توجد بيانات للعرض
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

@push('scripts')
<script>
    $(document).ready(function() {
        const chartData = @json($allData->sortByDesc('total_amount')->take(10));

        if (!chartData || chartData.length === 0) {
            console.warn('No chart data available');
            return;
        }

        const labels = chartData.map(d => d.name);
        const amounts = chartData.map(d => parseFloat(d.total_amount));
        const colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140'
        ];

        console.log('Chart Data:', chartData);
        console.log('Labels:', labels);
        console.log('Amounts:', amounts);

        // Distribution Chart (Doughnut)
        const ctx1 = document.getElementById('expenseDistributionChart');
        if (!ctx1) {
            console.error('expenseDistributionChart element not found');
            return;
        }

        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: amounts,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: 'white',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: "'Cairo', sans-serif", size: 11 } }
                    }
                }
            }
        });

        // Amount Chart (Bar)
        const ctx2 = document.getElementById('expenseAmountChart');
        if (!ctx2) {
            console.error('expenseAmountChart element not found');
            return;
        }

        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'المبلغ المصروف (ج.م)',
                    data: amounts,
                    backgroundColor: colors.slice(0, labels.length),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
@endpush
@endif
@endsection
