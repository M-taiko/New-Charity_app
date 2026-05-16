@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-receipt"></i> مصروفاتي
                    </h1>
                </div>
                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#quickExpenseModal">
                        <i class="fas fa-bolt"></i> مصروف سريع
                    </button>
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> مصروف جديد
                    </a>
                    <a href="{{ route('custodies.create') }}" class="btn btn-success">
                        <i class="fas fa-hand-holding-usd"></i> طلب عهدة جديدة
                    </a>
                    <a href="{{ route('custody-transfers.create') }}" class="btn btn-info">
                        <i class="fas fa-exchange-alt"></i> تحويل عهدتي
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-calculator"></i></div>
                <div class="stat-label">إجمالي المصروفات</div>
                <div class="stat-number" style="color: var(--primary);">{{ number_format($totalExpenses, 2) }}</div>
                <small style="color: #6b7280;">ج.م</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-label">عدد المصروفات</div>
                <div class="stat-number" style="color: var(--info);">{{ $expenseCount }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">مصروفات عامة</div>
                <div class="stat-number" style="color: var(--success);">{{ $generalExpenseCount }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-user-tie"></i></div>
                <div class="stat-label">حالات اجتماعية</div>
                <div class="stat-number" style="color: var(--warning);">{{ $socialCaseExpenseCount }}</div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> قائمة مصروفاتي
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="expensesTable">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ والوقت</th>
                                    <th>التوجيه المحاسبي</th>
                                    <th>الوصف</th>
                                    <th>الحالة الاجتماعية</th>
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

<!-- Quick Expense Modal -->
<div class="modal fade" id="quickExpenseModal" tabindex="-1" aria-labelledby="quickExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                <h5 class="modal-title" id="quickExpenseModalLabel" style="color: white;">
                    <i class="fas fa-bolt"></i> إضافة مصروف سريع
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickExpenseForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quick_custody_id" class="form-label">
                            <i class="fas fa-box"></i> العهدة
                        </label>
                        <select id="quick_custody_id" name="custody_id" class="form-select" required>
                            <option value="">اختر العهدة...</option>
                        </select>
                        <small class="text-muted d-block mt-1">الرصيد المتاح: <span id="custody_balance" class="fw-bold text-success">0</span></small>
                    </div>

                    <div class="mb-3">
                        <label for="quick_expense_date" class="form-label">
                            <i class="fas fa-calendar"></i> تاريخ المصروف
                        </label>
                        <input type="date" id="quick_expense_date" name="expense_date" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="quick_amount" class="form-label">
                            <i class="fas fa-money-bill"></i> المبلغ
                        </label>
                        <input type="number" id="quick_amount" name="amount" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label for="quick_description" class="form-label">
                            <i class="fas fa-file-alt"></i> وصف المصروف
                        </label>
                        <textarea id="quick_description" name="description" class="form-control" rows="3" placeholder="وصف المصروف..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="quick_line_items" class="form-label">
                            <i class="fas fa-list"></i> تفاصيل البنود <span class="badge bg-secondary">اختياري</span>
                        </label>
                        <textarea id="quick_line_items" name="line_items" class="form-control" rows="2" placeholder="مثال: 2x قميص بـ 50 ج.م، 1x حذاء بـ 100 ج.م..."></textarea>
                    </div>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> هذا المصروف سيُسجّل كمصروف سريع بدون تفاصيل كاملة. يمكنك تعديله لاحقاً لإضافة المزيد من التفاصيل.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> حفظ المصروف السريع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Set default expense date to today
        const today = new Date().toISOString().split('T')[0];
        $('#quick_expense_date').val(today);

        // Load custodies for quick expense form
        loadQuickExpenseCustodies();

        // Quick expense form submission
        $('#quickExpenseForm').on('submit', function(e) {
            e.preventDefault();
            submitQuickExpense();
        });

        // Update custody balance when custody is selected
        $('#quick_custody_id').on('change', function() {
            updateQuickCustodyBalance();
        });

        $('#expensesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.agent-expenses.data") }}',
            columns: [
                {
                    data: 'type_label',
                    render: function(data) {
                        return data;
                    }
                },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: #e53935;">' + parseFloat(data).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) + '</strong> ج.م';
                    }
                },
                {
                    data: 'expense_datetime',
                    render: function(data) {
                        return data && data !== '-' ? data : '-';
                    }
                },
                {
                    data: 'item_direction',
                    render: function(data) {
                        return data && data !== '-' ? '<small style="color: #666;">' + data + '</small>' : '-';
                    }
                },
                { data: 'description' },
                {
                    data: 'case_name',
                    render: function(data) {
                        return data ?? '<span style="color: #999;">-</span>';
                    }
                },
                {
                    data: null,
                    render: function(data) {
                        return `<a href="/expenses/${data.id}" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });

    // Load custodies for quick expense modal
    function loadQuickExpenseCustodies() {
        $.ajax({
            url: '{{ route("api.user-custodies") }}',
            type: 'GET',
            success: function(data) {
                const select = $('#quick_custody_id');
                select.find('option:not(:first)').remove();

                console.log('Custodies data:', data);

                if (!data || data.length === 0) {
                    select.append('<option disabled>لا توجد عهد متاحة</option>');
                    return;
                }

                data.forEach(function(custody) {
                    const balance = parseFloat(custody.balance) - parseFloat(custody.spent);
                    const reason = custody.reason || 'عهدة بدون وصف';
                    console.log('Custody:', reason, 'Balance:', custody.balance, 'Spent:', custody.spent, 'Remaining:', balance);
                    if (balance > 0) {
                        select.append(`
                            <option value="${custody.id}" data-balance="${balance}">
                                ${reason} (الرصيد: ${balance.toLocaleString('ar')} ج.م)
                            </option>
                        `);
                    }
                });

                if (select.find('option').length === 1) {
                    select.append('<option disabled>لا توجد عهد بها رصيد متاح</option>');
                }
            },
            error: function(xhr) {
                console.error('Error loading custodies:', xhr);
                alert('حدث خطأ في تحميل العهد: ' + xhr.statusText);
            }
        });
    }

    // Update custody balance display
    function updateQuickCustodyBalance() {
        const selectedOption = $('#quick_custody_id option:selected');
        const balance = selectedOption.data('balance');

        if (balance !== undefined) {
            $('#custody_balance').text(parseFloat(balance).toLocaleString('ar') + ' ج.م');
            $('#quick_amount').attr('max', balance);
        }
    }

    // Submit quick expense
    function submitQuickExpense() {
        const formData = {
            custody_id: $('#quick_custody_id').val(),
            expense_date: $('#quick_expense_date').val(),
            amount: parseFloat($('#quick_amount').val()),
            description: $('#quick_description').val(),
            line_items: $('#quick_line_items').val() || null,
            is_quick_expense: true
        };

        $.ajax({
            url: '{{ route("expenses.quick-store") }}',
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('quickExpenseModal'));
                    if (modal) modal.hide();

                    // Reset form
                    $('#quickExpenseForm')[0].reset();
                    const today = new Date().toISOString().split('T')[0];
                    $('#quick_expense_date').val(today);

                    // Show success message
                    showAlert('success', 'تم تسجيل المصروف السريع بنجاح. يمكنك تعديله لاحقاً');

                    // Reload table
                    $('#expensesTable').DataTable().ajax.reload();
                } else {
                    showAlert('danger', response.message || 'حدث خطأ');
                }
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.message || 'حدث خطأ أثناء تسجيل المصروف';
                showAlert('danger', error);
            }
        });
    }

    // Show alert message
    function showAlert(type, message) {
        const alertId = 'tempAlert_' + Date.now();
        const alertHtml = `
            <div id="${alertId}" class="alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 400px;">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        $('body').append(alertHtml);

        setTimeout(function() {
            $(`#${alertId}`).fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
</script>
@endpush
@endsection
