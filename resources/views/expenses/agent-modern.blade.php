@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-receipt"></i> مصروفاتي
                    </h1>
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
                                    <th>التاريخ</th>
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

@push('scripts')
<script>
    $(document).ready(function() {
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
                    data: 'expense_date',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar-SA');
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
</script>
@endpush
@endsection
