@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-receipt"></i> المصروفات
                    </h1>
                </div>
                <div class="d-flex gap-2">
                    @can('view_all_expenses')
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> تسجيل مصروف جديد
                    </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <!-- Expense Stats -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-label">إجمالي المصروفات</div>
                <div class="stat-number" style="color: var(--danger);">
                    {{ number_format(\App\Models\Expense::sum('amount') + \App\Models\PurchaseRequest::where('status', 'purchased')->sum('actual_cost') + (\App\Models\SalaryCalculation::approved()->sum('final_salary') ?? 0), 0) }}
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-label">مصروفات العهد</div>
                <div class="stat-number" style="color: var(--primary);">{{ \App\Models\Expense::count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-label">طلبات الشراء</div>
                <div class="stat-number" style="color: var(--success);">
                    {{ \App\Models\PurchaseRequest::where('status', 'purchased')->count() }}
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-money-bill"></i></div>
                <div class="stat-label">الرواتب والمرتبات</div>
                <div class="stat-number" style="color: var(--warning);">
                    {{ \App\Models\SalaryCalculation::approved()->count() ?? 0 }}
                </div>
            </div>
        </div>
    </div>

    <!-- Column Filters -->
    <div class="row mb-3" data-aos="fade-up" data-aos-delay="350">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-sm-6 col-md-3">
                            <label class="form-label small mb-1"><i class="fas fa-user"></i> المستخدم</label>
                            <input type="text" id="filter_user" class="form-control form-control-sm" placeholder="ابحث بالاسم...">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-tags"></i> النوع</label>
                            <select id="filter_type" class="form-select form-select-sm">
                                <option value="">الكل</option>
                                <option value="general">مصروف عام</option>
                                <option value="social_case">حالة اجتماعية</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-check-circle"></i> حالة المراجعة</label>
                            <select id="filter_reviewed" class="form-select form-select-sm">
                                <option value="">الكل</option>
                                <option value="reviewed">مراجع</option>
                                <option value="not_reviewed">غير مراجع</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> من تاريخ</label>
                            <input type="date" id="filter_date_from" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> إلى تاريخ</label>
                            <input type="date" id="filter_date_to" class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-md-1">
                            <button type="button" id="filter_reset" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> إعادة
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs for Different Expense Types -->
    <div class="row mb-3" data-aos="fade-up" data-aos-delay="350">
        <div class="col-12">
            <ul class="nav nav-tabs" id="expenseTypeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expensesPanel" type="button" role="tab">
                        <i class="fas fa-receipt"></i> مصروفات العهد
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchasesPanel" type="button" role="tab">
                        <i class="fas fa-shopping-cart"></i> طلبات الشراء
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="salaries-tab" data-bs-toggle="tab" data-bs-target="#salariesPanel" type="button" role="tab">
                        <i class="fas fa-money-bill"></i> الرواتب والمرتبات
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="expenseTypeTabContent">
        <!-- Expenses Tab -->
        <div class="tab-pane fade show active" id="expensesPanel" role="tabpanel">
    <!-- Expenses Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> سجل مصروفات العهد
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="expensesTable">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>النوع</th>
                                    <th>التصنيف</th>
                                    <th>الحالة الاجتماعية</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ والوقت</th>
                                    <th>التوجيه المحاسبي</th>
                                    <th>المراجعة</th>
                                    <th>المرفق</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
        </div>

        <!-- Purchase Requests Tab -->
        <div class="tab-pane fade" id="purchasesPanel" role="tabpanel">
            <div class="row" data-aos="fade-up" data-aos-delay="400">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-table"></i> سجل طلبات الشراء
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="purchasesTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>العنوان</th>
                                            <th>الفئة</th>
                                            <th>المورد</th>
                                            <th>التكلفة المقدرة</th>
                                            <th>التكلفة الفعلية</th>
                                            <th>الحالة</th>
                                            <th>التاريخ</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salaries Tab -->
        <div class="tab-pane fade" id="salariesPanel" role="tabpanel">
            <div class="row" data-aos="fade-up" data-aos-delay="400">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 style="margin: 0;">
                                <i class="fas fa-table"></i> سجل الرواتب والمرتبات
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="salariesTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>الموظف</th>
                                            <th>الراتب الأساسي</th>
                                            <th>البدلات</th>
                                            <th>الخصومات</th>
                                            <th>الراتب الصافي</th>
                                            <th>الفترة</th>
                                            <th>الحالة</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    var expensesTable;

    $(document).ready(function() {
        expensesTable = $('#expensesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("api.expenses.data") }}',
                data: function(d) {
                    d.user_filter     = $('#filter_user').val();
                    d.type_filter     = $('#filter_type').val();
                    d.reviewed_filter = $('#filter_reviewed').val();
                    d.date_from       = $('#filter_date_from').val();
                    d.date_to         = $('#filter_date_to').val();
                }
            },
            columns: [
                { data: 'user_name' },
                { data: 'type_label' },
                { data: 'category_name', defaultContent: '-' },
                { data: 'case_name' },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--danger);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</strong>';
                    }
                },
                {
                    data: 'expense_datetime',
                    render: function(data) {
                        if (!data || data === '-') return '-';
                        return data;
                    }
                },
                {
                    data: 'item_direction',
                    render: function(data) {
                        if (!data || data === '-') return '-';
                        return '<small style="color: #666;">' + data + '</small>';
                    }
                },
                {
                    data: 'reviewed_label',
                    render: function(data) {
                        if (data === 'مراجع') {
                            return '<span class="badge bg-success"><i class="fas fa-check"></i> مراجع</span>';
                        }
                        return '<span class="badge bg-secondary"><i class="fas fa-hourglass-half"></i> غير مراجع</span>';
                    }
                },
                {
                    data: 'attachment',
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (data) {
                            return `<button type="button" class="btn btn-sm btn-primary" onclick="viewAttachment(${row.id}, '${data}')" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-paperclip"></i> عرض
                            </button>`;
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return `<a href="/expenses/${data}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> عرض
                        </a>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });

        // Apply filters on change
        $('#filter_user').on('input', debounce(function() { expensesTable.ajax.reload(); }, 400));
        $('#filter_type, #filter_reviewed').on('change', function() { expensesTable.ajax.reload(); });
        $('#filter_date_from, #filter_date_to').on('change', function() { expensesTable.ajax.reload(); });

        // Reset all filters
        $('#filter_reset').on('click', function() {
            $('#filter_user').val('');
            $('#filter_type').val('');
            $('#filter_reviewed').val('');
            $('#filter_date_from').val('');
            $('#filter_date_to').val('');
            expensesTable.ajax.reload();
        });

        // Initialize Purchases Table
        $('#purchasesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("api.purchase-requests.data") }}',
            },
            columns: [
                { data: 'id' },
                { data: 'title' },
                { data: 'category_label' },
                { data: 'supplier_name', defaultContent: '-' },
                {
                    data: 'estimated_cost',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('ar') + ' ج.م' : '-';
                    }
                },
                {
                    data: 'actual_cost',
                    render: function(data) {
                        return data ? '<strong style="color: var(--danger);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</strong>' : '-';
                    }
                },
                {
                    data: 'status_label',
                    render: function(data, type, row) {
                        const colors = {
                            'في الانتظار': 'warning',
                            'موافق عليه': 'success',
                            'مرفوض': 'danger',
                            'تم الشراء': 'primary'
                        };
                        const color = colors[data] || 'secondary';
                        return '<span class="badge bg-' + color + '">' + data + '</span>';
                    }
                },
                {
                    data: 'created_at',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar');
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return '<a href="/purchase-requests/' + data + '" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> عرض</a>';
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });

        // Initialize Salaries Table
        $('#salariesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("api.salary-calculations.data") }}',
            },
            columns: [
                { data: 'id' },
                { data: 'employee_name' },
                {
                    data: 'base_salary',
                    render: function(data) {
                        return parseFloat(data).toLocaleString('ar') + ' ج.م';
                    }
                },
                {
                    data: 'allowances_total',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('ar') + ' ج.م' : '0.00 ج.م';
                    }
                },
                {
                    data: 'deductions_total',
                    render: function(data) {
                        return data ? parseFloat(data).toLocaleString('ar') + ' ج.م' : '0.00 ج.م';
                    }
                },
                {
                    data: 'total_salary',
                    render: function(data) {
                        return '<strong style="color: var(--success);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</strong>';
                    }
                },
                { data: 'period_label', defaultContent: '-' },
                {
                    data: 'status_label',
                    render: function(data) {
                        const colors = {
                            'مسودة': 'secondary',
                            'معتمدة': 'success',
                            'مرحلة': 'warning'
                        };
                        const color = colors[data] || 'secondary';
                        return '<span class="badge bg-' + color + '">' + (data || 'غير محدد') + '</span>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return '<a href="/hr/salaries/' + data + '" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> عرض</a>';
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });

    function debounce(fn, delay) {
        var timer;
        return function() {
            clearTimeout(timer);
            timer = setTimeout(fn, delay);
        };
    }

    // View attachment in modal
    function viewAttachment(expenseId, attachment) {
        const extension = attachment.split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(extension);
        const isPdf = extension === 'pdf';
        const attachmentUrl = `/storage/app/public/${attachment}`;
        const downloadUrl = `/expenses/${expenseId}/download-attachment`;

        let modalContent = '';

        if (isImage) {
            modalContent = `
                <img src="${attachmentUrl}" alt="Expense Attachment" class="img-fluid" style="max-height: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            `;
        } else if (isPdf) {
            modalContent = `
                <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px;">
                    <i class="fas fa-file-pdf" style="font-size: 5rem; color: #f5576c; margin-bottom: 1rem;"></i>
                    <h5 style="margin-bottom: 1rem;">ملف PDF</h5>
                    <p class="text-muted" style="margin-bottom: 1.5rem;">${attachment.split('/').pop()}</p>
                    <a href="${downloadUrl}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-download"></i> تحميل الملف
                    </a>
                </div>
            `;
        } else {
            modalContent = `
                <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px;">
                    <i class="fas fa-file-alt" style="font-size: 5rem; color: #667eea; margin-bottom: 1rem;"></i>
                    <h5 style="margin-bottom: 1rem;">مستند</h5>
                    <p class="text-muted" style="margin-bottom: 1.5rem;">${attachment.split('/').pop()}</p>
                    <a href="${downloadUrl}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-download"></i> تحميل الملف
                    </a>
                </div>
            `;
        }

        document.getElementById('attachmentModalContent').innerHTML = modalContent;
        document.getElementById('attachmentDownloadBtn').href = downloadUrl;

        var attachmentModal = new bootstrap.Modal(document.getElementById('attachmentModal'));
        attachmentModal.show();
    }
</script>
@endpush

<!-- Attachment Modal -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <h5 class="modal-title" id="attachmentModalLabel" style="color: white;">
                    <i class="fas fa-paperclip"></i> مرفق المصروف
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="padding: 2rem;" id="attachmentModalContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <a href="#" id="attachmentDownloadBtn" class="btn btn-success" target="_blank">
                    <i class="fas fa-download"></i> تحميل
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
