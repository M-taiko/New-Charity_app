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
                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                    @can('spend_money')
                    <a href="{{ route('expenses.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> تسجيل مصروف جديد
                    </a>
                    @endcan
                    @role('مندوب')
                    <a href="{{ route('custodies.create') }}" class="btn btn-success">
                        <i class="fas fa-hand-holding-usd"></i> طلب عهدة جديدة
                    </a>
                    <a href="{{ route('custody-transfers.create') }}" class="btn btn-info">
                        <i class="fas fa-exchange-alt"></i> تحويل عهدتي
                    </a>
                    @endrole
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
                    {{ number_format(\App\Models\Expense::sum('amount'), 0) }}
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-label">عدد المصروفات</div>
                <div class="stat-number" style="color: var(--primary);">{{ \App\Models\Expense::count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-people-group"></i></div>
                <div class="stat-label">حالات اجتماعية</div>
                <div class="stat-number" style="color: var(--success);">
                    {{ \App\Models\Expense::where('type', 'social_case')->count() }}
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-tasks"></i></div>
                <div class="stat-label">مصروفات عامة</div>
                <div class="stat-number" style="color: var(--warning);">
                    {{ \App\Models\Expense::where('type', 'general')->count() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> سجل المصروفات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="expensesTable">
                            <thead>
                                <tr>
                                    <th>المستخدم</th>
                                    <th>النوع</th>
                                    <th>الحالة</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
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
            ajax: '{{ route("api.expenses.data") }}',
            columns: [
                { data: 'user_name' },
                { data: 'type_label' },
                { data: 'case_name' },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--danger);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</strong>';
                    }
                },
                {
                    data: 'created_at',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar');
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
