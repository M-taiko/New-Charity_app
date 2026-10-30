@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-chart-bar"></i> التقارير والإحصائيات
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        ملخصات شاملة لعمليات المؤسسة
                    </p>
                </div>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-print"></i> طباعة
                </button>
            </div>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row g-4 mb-4">
        <!-- Total Treasury -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                <div class="stat-label">رصيد الخزينة</div>
                <div class="stat-number" style="color: var(--success);">
                    {{ number_format(\App\Models\Treasury::first()?->balance ?? 0, 2) }}
                </div>
                <small style="color: #6b7280;">ج.م</small>
            </div>
        </div>

        <!-- Total Expenses (يتأثر بالتوجيه) -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card danger" id="reactingGroup">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-label">إجمالي المصروفات <span class="badge bg-info" style="font-size: 0.65rem;">يتأثر بالتوجيه</span></div>
                <div class="stat-number" style="color: var(--danger);" id="rptTotalExpenses">
                    {{ number_format($totalExpenses, 2) }}
                </div>
                <small style="color: #6b7280;">ج.م</small>
            </div>
        </div>

        <!-- Active Custodies -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-label">العهد النشطة</div>
                <div class="stat-number" style="color: var(--info);">
                    {{ \App\Models\Custody::where('status', 'accepted')->count() }}
                </div>
            </div>
        </div>

        <!-- Social Cases -->
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-people-group"></i></div>
                <div class="stat-label">الحالات الاجتماعية</div>
                <div class="stat-number" style="color: var(--warning);">
                    {{ \App\Models\SocialCase::count() }}
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border" style="background: #f8f9fa;">
        <i class="fas fa-info-circle text-muted"></i>
        <small class="text-muted">الأرقام الثابتة (أرصدة الخزائن والعهد وعدد الحالات) لا تتأثر بالتوجيه — البطاقة والرسم المعلَّمان بـ "يتأثر بالتوجيه" يتغيران مع اختيارك أعلاه وأدناه.</small>
    </div>

    <!-- Charts Section -->
    <div class="row g-4 mb-4">
        <!-- Expenses by Date Chart (يتأثر بالتوجيه والفترة) -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="400">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-chart-line"></i> المصروفات آخر 7 أيام
                        <span class="badge bg-light text-dark" style="font-size: 0.65rem;">يتأثر بالتوجيه</span>
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="expensesByDateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Direction Explorer (رسم المصروفات حسب التوجيه مع الحفر للأسفل) -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="500">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-sitemap"></i> المصروفات حسب التوجيه
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <nav id="dirTrail" aria-label="مسار التوجيه" class="small" style="line-height: 1.8;"></nav>
                        <button type="button" id="dirClear" class="btn btn-sm btn-outline-secondary" onclick="dirClearFilter()">
                            <i class="fas fa-times"></i> إلغاء التصفية
                        </button>
                        <a href="{{ route('reports.categories-analytics') }}" id="dirAnalyticsLink" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i> فتح تحليل التوجيهات
                        </a>
                        <span id="dirLoading" class="text-muted small" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> جارٍ التحديث...
                        </span>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> من تاريخ</label>
                            <input type="date" id="dirDateFrom" class="form-control form-control-sm" value="{{ $directionDateFrom ?? '' }}">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> إلى تاريخ</label>
                            <input type="date" id="dirDateTo" class="form-control form-control-sm" value="{{ $directionDateTo ?? '' }}">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-5">
                            <div style="position: relative; height: 250px;">
                                <canvas id="directionExplorerChart"></canvas>
                            </div>
                        </div>
                        <div class="col-12 col-md-7">
                            <div id="directionExplorerList" style="max-height: 250px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Fixed Charts: الأرقام الثابتة لا تتأثر بالتوجيه -->
    <div class="d-flex align-items-center gap-2 mb-2 mt-2">
        <span class="badge bg-secondary"><i class="fas fa-lock"></i> الأرقام الثابتة لا تتأثر بالتوجيه</span>
        <hr class="flex-grow-1 m-0">
    </div>

    <!-- Custodies and Cases Charts -->
    <div class="row g-4 mb-4">
        <!-- Custodies by Agents Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="600">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-hand-holding-heart"></i> العهدات حسب المندوبين
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="custodiesByAgentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cases by Researchers Chart -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="700">
            <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-people-group"></i> الحالات حسب الباحثين
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 300px;">
                        <canvas id="casesByResearchersChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Reports Section -->
    <div class="row g-4">
        <!-- Custody Report -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="600">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-hand-holding-heart"></i> تقرير العهد
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي العهد الصادرة</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #667eea;">
                                    {{ number_format($custodyAmount, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المصروف</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #f57c00;">
                                    {{ number_format($custodySpent, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المردود</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #4caf50;">
                                    {{ number_format($custodyReturned, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">المتبقي</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #2196f3;">
                                    {{ number_format($custodyAmount - $custodySpent, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div style="text-align: center; padding: 10px; background: #e3f2fd; border-radius: 4px; margin-bottom: 10px;">
                                <small style="color: #1565c0;">إجمالي العهد</small>
                                <h5 style="margin: 0.3rem 0; color: #0d47a1;">{{ $totalCustodies }}</h5>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div style="text-align: center; padding: 10px; background: #f3e5f5; border-radius: 4px; margin-bottom: 10px;">
                                <small style="color: #7b1fa2;">المغلقة</small>
                                <h5 style="margin: 0.3rem 0; color: #4a148c;">{{ $closedCustodies }}</h5>
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="{{ route('custodies.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> عرض جميع العهد
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Cases Report -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="700">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-people-group"></i> تقرير الحالات الاجتماعية
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">حالات موافق عليها</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #4caf50;">
                                    {{ $approvedCases }}
                                </h4>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">قيد الانتظار</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #ff9800;">
                                    {{ $pendingCases }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">إجمالي المصروف</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #2196f3;">
                                    {{ number_format($socialCaseSpent, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                                <small style="color: #666;">المرفوضة</small>
                                <h4 style="margin: 0.5rem 0 0 0; color: #f44336;">
                                    {{ $rejectedCases }}
                                </h4>
                            </div>
                        </div>
                    </div>
                    <div style="padding: 15px; background: #f5f5f5; border-radius: 4px; text-align: center;">
                        <small style="color: #666;">إجمالي الحالات</small>
                        <h4 style="margin: 0.5rem 0 0 0; color: #4facfe;">
                            {{ $totalSocialCases }}
                        </h4>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="{{ route('social_cases.index') }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> عرض جميع الحالات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses Summary -->
    <div class="row g-4 mt-2">
        <div class="col-12" data-aos="fade-up" data-aos-delay="800">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-receipt"></i> ملخص المصروفات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row" id="reactingGroup">
                        <div class="col-6 col-md">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات اليوم</small>
                                <h4 style="margin: 0; color: #f5576c;" id="rptToday">
                                    {{ number_format($expensesToday, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذا الشهر</small>
                                <h4 style="margin: 0; color: #f5576c;" id="rptMonth">
                                    {{ number_format($expensesThisMonth, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">مصروفات هذه السنة</small>
                                <h4 style="margin: 0; color: #f5576c;" id="rptYear">
                                    {{ number_format($expensesThisYear, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">إجمالي فترة التصفية</small>
                                <h4 style="margin: 0; color: #f57c00;" id="rptPeriod">
                                    {{ number_format($periodExpenses, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م — يتأثر بنطاق التاريخ</small>
                            </div>
                        </div>
                        <div class="col-6 col-md">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 4px; margin-bottom: 15px;">
                                <small style="color: #666; display: block; margin-bottom: 0.5rem;">إجمالي جميع المصروفات</small>
                                <h4 style="margin: 0; color: #f5576c;" id="rptGrand">
                                    {{ number_format($totalExpenses, 2) }}
                                </h4>
                                <small style="color: #999;">ج.م</small>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted d-block"><i class="fas fa-info-circle"></i> ملخص المصروفات يتأثر بالتوجيه المختار؛ نوافذ اليوم/الشهر/السنة لا تتأثر بنطاق التاريخ.</small>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Expenses by Date Chart (T33: قابل للتحديث بالتوجيه)
        const dateChartData = @json($expensesByDate);
        let dateLabels = dateChartData.map(d => d.date);
        let dateAmounts = dateChartData.map(d => d.amount);
        window.dateFullDates = dateChartData.map(d => d.fullDate);

        window.expensesDateChart = new Chart(document.getElementById('expensesByDateChart'), {
            type: 'line',
            data: {
                labels: dateLabels,
                datasets: [{
                    label: 'المصروفات (ج.م)',
                    data: dateAmounts,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const date = window.dateFullDates[index];
                        if (date) {
                            window.location.href = `{{ route('expenses.index') }}?date=${date}`;
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { family: "'Cairo', sans-serif" } }
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Change cursor for date chart
        document.getElementById('expensesByDateChart').style.cursor = 'pointer';

        // ──── T33: مستكشف التوجيه المحاسبي ────
        const dirState = {
            direction: {{ $directionId ?: 'null' }},
            item: {{ $directionItemId ?: 'null' }},
        };
        const EXPLORER_COLORS = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe', '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#ff9800', '#9c27b0'];
        let explorerChart = null;

        function dirRows(explorer) {
            return explorer.children.length ? explorer.children : explorer.items;
        }

        function renderExplorer(explorer) {
            const rows = dirRows(explorer);
            const labels = rows.map(r => r.name);
            const amounts = rows.map(r => r.amount);

            if (!explorerChart) {
                explorerChart = new Chart(document.getElementById('directionExplorerChart'), {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: amounts,
                            backgroundColor: EXPLORER_COLORS.slice(0, labels.length),
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onClick: function(e, els) {
                            if (els.length) {
                                const row = rows[els[0].index];
                                dirSelect(row.is_item ? null : row.id, row.is_item ? row.id : null);
                            }
                        },
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 10 } } }
                        }
                    }
                });
            } else {
                explorerChart.data.labels = labels;
                explorerChart.data.datasets[0].data = amounts;
                explorerChart.data.datasets[0].backgroundColor = EXPLORER_COLORS.slice(0, labels.length);
                explorerChart.update();
            }
            document.getElementById('directionExplorerChart').style.cursor = rows.length ? 'pointer' : 'default';

            // القائمة الجانبية: المبلغ والحصة
            const total = amounts.reduce((a, b) => a + b, 0);
            let html = '';
            rows.forEach(r => {
                const share = total > 0 ? (r.amount / total * 100).toFixed(1) : '0.0';
                const selected = r.is_item && r.id == dirState.item;
                html += `<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 ${selected ? 'active' : ''}" onclick="dirSelect(${r.is_item ? 'null, ' + r.id : r.id + ', null'})">
                    <span class="small">${r.name}</span>
                    <span dir="ltr" class="small">${formatMoney(r.amount)} <small>(${share}%)</small></span>
                </button>`;
            });
            if (explorer.direct_amount > 0 && rows.length) {
                html += `<div class="list-group-item d-flex justify-content-between text-muted py-1 small">
                    <span>مصروفات على الفئة نفسها مباشرة</span>
                    <span dir="ltr">${formatMoney(explorer.direct_amount)}</span>
                </div>`;
            }
            if (!rows.length) {
                html = '<div class="text-muted small p-2">لا توجد مستويات أدنى تحت هذه العقدة — استخدم مسار التنقل للرجوع.</div>';
            }
            document.getElementById('directionExplorerList').innerHTML = html;

            // مسار التنقل: الكل ‹ المستوى الأول ‹ ...
            let trail = '<button type="button" class="btn btn-sm btn-link p-0" onclick="dirSelect(null, null)">الكل</button>';
            explorer.trail.forEach(t => {
                trail += ' ‹ <button type="button" class="btn btn-sm btn-link p-0" onclick="dirSelect(' + t.id + ', null)">' + t.name + '</button>';
            });
            document.getElementById('dirTrail').innerHTML = trail;

            // فتح تحليل التوجيهات بنفس التوجيه المختار
            document.getElementById('dirAnalyticsLink').href =
                '{{ route('reports.categories-analytics') }}' + (dirState.direction ? '?category_id=' + dirState.direction : '');
        }

        function applyFigures(figures) {
            const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = formatMoney(v); };
            set('rptTotalExpenses', figures.grand_total);
            set('rptToday', figures.today);
            set('rptMonth', figures.this_month);
            set('rptYear', figures.this_year);
            set('rptPeriod', figures.period_total);

            if (window.expensesDateChart) {
                window.expensesDateChart.data.labels = figures.by_day.map(d => d.label);
                window.expensesDateChart.data.datasets[0].data = figures.by_day.map(d => d.amount);
                window.expensesDateChart.update();
                window.dateFullDates = figures.by_day.map(d => d.date);
            }
        }

        async function dirSelect(categoryId, itemId) {
            dirState.direction = categoryId;
            dirState.item = itemId;
            document.getElementById('dirLoading').style.display = '';
            try {
                const params = new URLSearchParams();
                if (categoryId) params.set('direction', categoryId);
                if (itemId) params.set('item', itemId);
                const dateFrom = document.getElementById('dirDateFrom').value;
                const dateTo = document.getElementById('dirDateTo').value;
                if (dateFrom) params.set('date_from', dateFrom);
                if (dateTo) params.set('date_to', dateTo);

                const res = await fetch('{{ route('api.directional-stats') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                applyFigures(data.figures);
                renderExplorer(data.explorer);

                // حفظ التصفية في رابط الصفحة: التحديث أو المشاركة يعيدان نفس الشاشة
                const url = new URL(window.location.href);
                ['direction', 'item', 'date_from', 'date_to'].forEach(k => url.searchParams.delete(k));
                if (categoryId) url.searchParams.set('direction', categoryId);
                if (itemId) url.searchParams.set('item', itemId);
                if (dateFrom) url.searchParams.set('date_from', dateFrom);
                if (dateTo) url.searchParams.set('date_to', dateTo);
                history.replaceState(null, '', url);
            } catch (e) {
                console.error('فشل تحديث أرقام التوجيه', e);
            } finally {
                document.getElementById('dirLoading').style.display = 'none';
            }
        }
        window.dirSelect = dirSelect;

        function dirClearFilter() { dirSelect(null, null); }
        window.dirClearFilter = dirClearFilter;

        document.getElementById('dirDateFrom').addEventListener('change', () => dirSelect(dirState.direction, dirState.item));
        document.getElementById('dirDateTo').addEventListener('change', () => dirSelect(dirState.direction, dirState.item));

        // العرض الأول من الخادم — نفس أرقام نقطة JSON
        renderExplorer(@json($directionExplorer));

        // Custodies by Agents Chart - showing remaining balance after spending
        const agentsData = @json($custodiesByAgents);
        const agentLabels = agentsData.map(d => d.agent_name);
        const agentRemaining = agentsData.map(d => d.remaining);  // Show remaining after spending
        const agentIds = agentsData.map(d => d.agent_id);

        new Chart(document.getElementById('custodiesByAgentsChart'), {
            type: 'bar',
            data: {
                labels: agentLabels,
                datasets: [{
                    label: 'المبلغ المتبقي بعد الصرف (ج.م)',
                    data: agentRemaining,
                    backgroundColor: '#f093fb',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const agentId = agentIds[index];
                        if (agentId) {
                            window.location.href = `{{ route('accountant.all-custodies') }}?agent_filter=${agentId}`;
                        }
                    }
                },
                plugins: {
                    legend: { display: true }
                },
                scales: {
                    x: { beginAtZero: true }
                }
            }
        });

        document.getElementById('custodiesByAgentsChart').style.cursor = 'pointer';

        // Cases by Researchers Chart
        const researchersData = @json($casesByResearchers);
        const researcherLabels = researchersData.map(d => d.researcher_name);
        const researcherCounts = researchersData.map(d => d.cases_count);
        const researcherSpent = researchersData.map(d => d.spent);
        const researcherIds = researchersData.map(d => d.researcher_id);

        new Chart(document.getElementById('casesByResearchersChart'), {
            type: 'bar',
            data: {
                labels: researcherLabels,
                datasets: [
                    {
                        label: 'عدد الحالات',
                        data: researcherCounts,
                        backgroundColor: '#4facfe',
                        borderRadius: 6,
                        yAxisID: 'y'
                    },
                    {
                        label: 'المبلغ المصروف (ج.م)',
                        data: researcherSpent,
                        backgroundColor: '#00f2fe',
                        borderRadius: 6,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                onClick: function(event, elements) {
                    if (elements.length > 0) {
                        const index = elements[0].index;
                        const researcherId = researcherIds[index];
                        if (researcherId) {
                            window.location.href = `{{ route('social_cases.index') }}?researcher_id=${researcherId}`;
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: { font: { family: "'Cairo', sans-serif" } }
                    }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: { display: true, text: 'العدد' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { display: true, text: 'المبلغ (ج.م)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });

        document.getElementById('casesByResearchersChart').style.cursor = 'pointer';
    });
</script>
@endpush
@endsection
