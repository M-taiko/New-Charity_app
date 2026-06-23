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
                <div>
                    <h1 style="margin:0; font-size:2rem; font-weight:700;">
                        <i class="fas fa-chart-bar"></i>
                        @if($selectedCategory)
                            تحليل {{ $selectedCategory->name }}
                        @else
                            تحليل التوجيهات المحاسبية
                        @endif
                    </h1>
                    @if($selectedCategory)
                        <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                            <span class="badge bg-primary">{{ $selectedCategory->code }}</span>
                            إجمالي المصروف: <strong style="color: #27ae60;">{{ number_format($grandTotal, 2) }} ج.م</strong>
                        </p>
                    @endif
                </div>
                @if($selectedCategory)
                    <a href="{{ route('reports.categories-analytics') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> عودة
                    </a>
                @endif
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

        <!-- Levels Comparison Bar Chart -->
        <div class="col-12">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2 fw-bold" style="font-size: 0.95rem;">
                        <i class="fas fa-sitemap" style="color: #667eea; margin-left: 0.5rem;"></i> مقارنة المصروفات حسب المستويات
                    </h6>
                    <div style="position: relative; height: 350px;">
                        <canvas id="levelsComparisonChart"></canvas>
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
                                <th style="padding: 1rem; text-align: center;">المعتمد من قبل</th>
                                <th style="padding: 1rem; text-align: center;">النسبة</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($allData->sortByDesc('total_amount') as $data)
                            <tr style="cursor: pointer;" class="expense-row" data-item-id="{{ $data['id'] }}" data-item-type="{{ $data['level'] }}" data-item-name="{{ $data['name'] }}" data-bs-toggle="modal" data-bs-target="#expenseDetailsModal">
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
                                    @if($data['reviewed_by'])
                                        <small class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> {{ $data['reviewed_by'] }}
                                        </small>
                                    @else
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> قيد المراجعة
                                        </small>
                                    @endif
                                </td>
                                <td style="padding: 1rem; text-align: center;">
                                    <span class="badge" style="background: rgba(103, 126, 234, 0.2); color: #667eea;">
                                        {{ $data['percentage'] }}%
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" style="padding: 2rem; text-align: center; color: #999;">
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

<!-- Expense Details Modal -->
<div class="modal fade" id="expenseDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <h5 class="modal-title">تفاصيل المصروفات</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="expenseDetailsContent" style="padding: 1.5rem;">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Prepare chart data safely by passing through data attribute
    const chartDataElement = document.createElement('div');
    chartDataElement.textContent = JSON.stringify(@json($allData));
    const allDataRaw = JSON.parse(chartDataElement.textContent);

    $(document).ready(function() {
        try {
            const chartData = allDataRaw && allDataRaw.length > 0 ?
                allDataRaw.sort((a, b) => parseFloat(b.total_amount) - parseFloat(a.total_amount)).slice(0, 10)
                : [];

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

        // Only show charts if we have data
        if (labels.length === 0) {
            console.warn('No chart data - hiding charts');
            const chart1 = document.getElementById('expenseDistributionChart');
            if (chart1 && chart1.parentElement && chart1.parentElement.parentElement) {
                chart1.parentElement.parentElement.style.display = 'none';
            }
            const chart2 = document.getElementById('expenseAmountChart');
            if (chart2 && chart2.parentElement && chart2.parentElement.parentElement) {
                chart2.parentElement.parentElement.style.display = 'none';
            }
            return;
        }

        // Distribution Chart (Doughnut)
        const ctx1 = document.getElementById('expenseDistributionChart');
        if (!ctx1) {
            console.error('expenseDistributionChart element not found');
            return;
        }

        const isSingleItem = chartData.length === 1;

        const chart1Config = {
            type: isSingleItem ? 'bar' : 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    label: 'المبلغ المصروف (ج.م)',
                    data: amounts,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: 'white',
                    borderWidth: 2,
                    borderRadius: isSingleItem ? 6 : 0
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
        };

        // Add scales only for bar chart
        if (isSingleItem) {
            chart1Config.options.scales = {
                y: { beginAtZero: true }
            };
        }

        new Chart(ctx1, chart1Config);

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

        // Levels Comparison Chart
        const allChartData = allDataRaw || [];
        const levelGroups = {
            'م1': allChartData.filter(d => d.level == 1),
            'م2': allChartData.filter(d => d.level == 2),
            'م3': allChartData.filter(d => d.level == 3),
            'بند': allChartData.filter(d => d.level === 'item')
        };

        const levelLabels = Object.keys(levelGroups);
        const levelCounts = levelLabels.map(level => levelGroups[level].length);
        const levelTotals = levelLabels.map(level =>
            levelGroups[level].reduce((sum, item) => sum + parseFloat(item.total_amount), 0)
        );
        const levelAverages = levelLabels.map(level =>
            levelGroups[level].length > 0 ? levelTotals[levelLabels.indexOf(level)] / levelGroups[level].length : 0
        );

        const ctx3 = document.getElementById('levelsComparisonChart');
        if (ctx3) {
            new Chart(ctx3, {
                type: 'bar',
                data: {
                    labels: levelLabels,
                    datasets: [
                        {
                            label: 'الإجمالي (ج.م)',
                            data: levelTotals,
                            backgroundColor: '#667eea',
                            borderRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'المتوسط (ج.م)',
                            data: levelAverages,
                            backgroundColor: '#f5576c',
                            borderRadius: 6,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'العدد',
                            data: levelCounts,
                            backgroundColor: '#43e97b',
                            borderRadius: 6,
                            yAxisID: 'y2'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { font: { family: "'Cairo', sans-serif", size: 11 } }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'الإجمالي (ج.م)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'المتوسط (ج.م)' },
                            grid: { drawOnChartArea: false }
                        },
                        y2: {
                            type: 'linear',
                            display: false,
                            position: 'right'
                        }
                    }
                }
            });
        }
        } catch (error) {
            console.error('Chart initialization error:', error);
            console.error('Error details:', error.message);
            console.error('Stack:', error.stack);
        }

        // Handle expense row click
        $(document).on('click', '.expense-row', function() {
            const itemId = $(this).data('item-id');
            const itemType = $(this).data('item-type');
            const itemName = $(this).data('item-name');

            // Load expenses for this item
            $.ajax({
                url: '{{ route("api.category-item-expenses") }}',
                type: 'GET',
                data: {
                    item_id: itemId,
                    item_type: itemType
                },
                success: function(response) {
                    displayExpenseDetails(response, itemName);
                },
                error: function(error) {
                    $('#expenseDetailsContent').html(`
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle"></i> حدث خطأ في تحميل البيانات
                        </div>
                    `);
                    console.error('Error:', error);
                }
            });
        });

        function displayExpenseDetails(expenses, itemName) {
            if (!expenses || expenses.length === 0) {
                $('#expenseDetailsContent').html(`
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i> لا توجد مصروفات لهذا البند
                    </div>
                `);
                return;
            }

            let totalAmount = 0;
            let html = `
                <h6 class="mb-3 fw-bold">
                    <i class="fas fa-receipt"></i> المصروفات - ${itemName}
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead style="background: #f8f9fa;">
                            <tr>
                                <th>التاريخ</th>
                                <th>الوصف</th>
                                <th>من قبل</th>
                                <th>الحالة</th>
                                <th class="text-end">المبلغ</th>
                                <th class="text-center">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            expenses.forEach(function(expense) {
                totalAmount += parseFloat(expense.amount);

                // Status badge
                let statusBadge = '';
                if (expense.status === 'approved' || expense.approved_by) {
                    statusBadge = '<span class="badge bg-success">معتمد</span>';
                } else if (expense.status === 'pending') {
                    statusBadge = '<span class="badge bg-warning">قيد المراجعة</span>';
                } else {
                    statusBadge = '<span class="badge bg-info">مسجل</span>';
                }

                // Approval info
                let approvalInfo = expense.approved_by ?
                    `<small class="d-block text-muted">${expense.approved_by}</small>` :
                    '<small class="d-block text-muted">لم يتم الاعتماد</small>';

                html += `
                    <tr>
                        <td>
                            <small>${new Date(expense.created_at).toLocaleDateString('ar-EG')}</small>
                        </td>
                        <td>
                            <small>${expense.description || 'بدون وصف'}</small>
                        </td>
                        <td>
                            <small><strong>${expense.created_by || 'غير معروف'}</strong></small>
                        </td>
                        <td>
                            ${statusBadge}
                            ${approvalInfo}
                        </td>
                        <td class="text-end fw-bold" style="color: #27ae60;">
                            <small>${parseFloat(expense.amount).toLocaleString('ar')} ج.م</small>
                        </td>
                        <td class="text-center">
                            <a href="/expenses/${expense.id}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-light border border-top-0 mt-3" style="border-top: 3px solid #667eea !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="fas fa-sum"></i> الإجمالي:</span>
                        <span class="fs-5 fw-bold" style="color: #27ae60;">${totalAmount.toLocaleString('ar')} ج.م</span>
                    </div>
                    <small class="text-muted d-block mt-2">عدد المصروفات: ${expenses.length}</small>
                </div>
            `;

            $('#expenseDetailsContent').html(html);
        }
    });
</script>
@endpush
@endif
@endsection
