@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-exchange-alt"></i> حركاتي من الخزينة
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        جميع العمليات المالية والعهد المتعلقة بحسابك
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Stats -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-label">عدد العهد</div>
                <div class="stat-number" style="color: var(--primary);">{{ $custodiesCount }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-arrow-down"></i></div>
                <div class="stat-label">إجمالي العهد المستلمة</div>
                <div class="stat-number" style="color: var(--success);">{{ number_format($totalReceived, 2) }}</div>
                <small style="color: #6b7280;">ر.س</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-receipt"></i></div>
                <div class="stat-label">إجمالي المصروفات</div>
                <div class="stat-number" style="color: var(--danger);">{{ number_format($totalSpent, 2) }}</div>
                <small style="color: #6b7280;">ر.س</small>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-arrow-up"></i></div>
                <div class="stat-label">إجمالي المبالغ المردودة</div>
                <div class="stat-number" style="color: var(--warning);">{{ number_format($totalReturned, 2) }}</div>
                <small style="color: #6b7280;">ر.س</small>
            </div>
        </div>
    </div>

    <!-- Tabs for different transaction types -->
    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-history"></i> سجل الحركات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <ul class="nav nav-tabs" role="tablist" style="border-bottom: 2px solid #e5e7eb;">
                        <li class="nav-item">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-transactions" type="button" role="tab">
                                <i class="fas fa-list"></i> جميع الحركات
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received-transactions" type="button" role="tab">
                                <i class="fas fa-arrow-down"></i> عهد مستلمة
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expense-transactions" type="button" role="tab">
                                <i class="fas fa-receipt"></i> مصروفات
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="returned-tab" data-bs-toggle="tab" data-bs-target="#returned-transactions" type="button" role="tab">
                                <i class="fas fa-arrow-up"></i> مبالغ مردودة
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- All Transactions Tab -->
                        <div class="tab-pane fade show active" id="all-transactions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="allTransactionsTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>نوع العملية</th>
                                            <th>المبلغ</th>
                                            <th>الوصف</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Received Custodies Tab -->
                        <div class="tab-pane fade" id="received-transactions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="receivedTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>المبلغ</th>
                                            <th>الحالة</th>
                                            <th>الملاحظات</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($custodies as $custody)
                                        <tr>
                                            <td>{{ $custody->created_at->toDateString() }}</td>
                                            <td>
                                                <strong style="color: var(--success);">{{ number_format($custody->amount, 2) }} ر.س</strong>
                                            </td>
                                            <td>
                                                @switch($custody->status)
                                                    @case('pending')
                                                        <span class="badge bg-warning">قيد الانتظار</span>
                                                        @break
                                                    @case('accepted')
                                                        <span class="badge bg-success">موافق عليه</span>
                                                        @break
                                                    @case('rejected')
                                                        <span class="badge bg-danger">مرفوض</span>
                                                        @break
                                                    @case('closed')
                                                        <span class="badge bg-secondary">مغلق</span>
                                                        @break
                                                    @case('partially_returned')
                                                        <span class="badge bg-info">مردود جزئي</span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td>{{ $custody->notes ? Str::limit($custody->notes, 30) : '-' }}</td>
                                            <td>
                                                <a href="{{ route('custodies.show', $custody->id) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Expenses Tab -->
                        <div class="tab-pane fade" id="expense-transactions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="expensesTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>النوع</th>
                                            <th>المبلغ</th>
                                            <th>الوصف</th>
                                            <th>الإجراءات</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>

                        <!-- Returned Amounts Tab -->
                        <div class="tab-pane fade" id="returned-transactions" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="returnedTable">
                                    <thead>
                                        <tr>
                                            <th>التاريخ</th>
                                            <th>المبلغ المردود</th>
                                            <th>العهدة</th>
                                            <th>الوصف</th>
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
    $(document).ready(function() {
        // All Transactions Table
        $('#allTransactionsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.agent.transactions") }}',
            columns: [
                {
                    data: 'transaction_date',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar-SA');
                    }
                },
                {
                    data: 'type',
                    render: function(data) {
                        let typeLabel = {
                            'custody_out': 'عهدة مستلمة',
                            'expense': 'مصروف',
                            'custody_return': 'مبلغ مردود'
                        };
                        return typeLabel[data] || data;
                    }
                },
                {
                    data: 'amount',
                    render: function(data, type, row) {
                        let color = row.type === 'custody_out' ? 'var(--success)' : row.type === 'expense' ? 'var(--danger)' : 'var(--warning)';
                        return `<strong style="color: ${color};">${parseFloat(data).toLocaleString('ar-SA', { minimumFractionDigits: 2 })} ر.س</strong>`;
                    }
                },
                { data: 'description' },
                {
                    data: 'type',
                    render: function(data) {
                        let status = {
                            'custody_out': '<span class="badge bg-success">دخول</span>',
                            'expense': '<span class="badge bg-danger">خروج</span>',
                            'custody_return': '<span class="badge bg-warning">رد</span>'
                        };
                        return status[data] || data;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });

        // Expenses Table
        $('#expensesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.agent-expenses.data") }}',
            columns: [
                {
                    data: 'expense_date',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar-SA');
                    }
                },
                {
                    data: 'type_label',
                    render: function(data) {
                        return data;
                    }
                },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--danger);">' + parseFloat(data).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) + '</strong> ر.س';
                    }
                },
                { data: 'description' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data) {
                        return `<a href="/expenses/${data.id}" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });

        // Returned Amounts Table
        $('#returnedTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.agent.returned") }}',
            columns: [
                {
                    data: 'transaction_date',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar-SA');
                    }
                },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--warning);">' + parseFloat(data).toLocaleString('ar-SA', { minimumFractionDigits: 2 }) + '</strong> ر.س';
                    }
                },
                {
                    data: 'custody_id',
                    render: function(data, type, row) {
                        return row.custody ? `<a href="/custodies/${data}" style="color: var(--primary); text-decoration: none;">عهدة #${data}</a>` : '-';
                    }
                },
                { data: 'description' }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            },
            order: [[0, 'desc']]
        });
    });
</script>
@endpush
@endsection
