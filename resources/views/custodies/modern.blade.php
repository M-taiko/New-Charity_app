@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700; color: var(--dark);">
                        <i class="fas fa-hand-holding-heart"></i> العهد
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        إدارة العهد المالية والموافقات
                    </p>
                </div>
                <div class="d-flex gap-2" style="flex-wrap: wrap;">
                    @can('create_custody')
                        @if(auth()->user()->hasAnyRole(['محاسب', 'مدير']))
                            <!-- Button for accountants/managers to request custody for themselves -->
                            <a href="{{ route('custodies.create', ['for' => 'self']) }}" class="btn btn-success">
                                <i class="fas fa-user-plus"></i> طلب عهدة شخصية
                            </a>
                            <!-- Button for accountants/managers to create custody for an agent -->
                            <a href="{{ route('custodies.create', ['for' => 'agent']) }}" class="btn btn-primary">
                                <i class="fas fa-users"></i> إنشاء عهدة لمندوب
                            </a>
                        @else
                            <a href="{{ route('custodies.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> إنشاء عهدة جديدة
                            </a>
                        @endif
                    @endcan
                    @role('مندوب')
                    <a href="{{ route('custody-transfers.create') }}" class="btn btn-info">
                        <i class="fas fa-exchange-alt"></i> تحويل عهدتي
                    </a>
                    @endrole
                </div>
            </div>
        </div>
    </div>

    <!-- Custody Stats -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">العهد المقبولة</div>
                <div class="stat-number">{{ \App\Models\Custody::where('status', 'accepted')->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">العهد المعلقة</div>
                <div class="stat-number">{{ \App\Models\Custody::where('status', 'pending')->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-label">العهد المرفوضة</div>
                <div class="stat-number">{{ \App\Models\Custody::where('status', 'rejected')->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stat-label">إجمالي العهد</div>
                <div class="stat-number">{{ \App\Models\Custody::count() }}</div>
            </div>
        </div>
    </div>

    <!-- Custodies Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> قائمة العهد
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="custodiesTable">
                            <thead>
                                <tr>
                                    <th>المندوب</th>
                                    <th>المبلغ</th>
                                    <th>المصروف</th>
                                    <th>المتبقي</th>
                                    <th>نسبة الإنفاق</th>
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

@push('scripts')
<script>
    $(document).ready(function() {
        $('#custodiesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.custodies.data") }}',
            columns: [
                { data: 'agent_name' },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong>' + parseFloat(data).toLocaleString('ar') + ' ج.م</strong>';
                    }
                },
                {
                    data: 'spent',
                    render: function(data) {
                        return '<span style="color: var(--danger);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</span>';
                    }
                },
                {
                    data: 'remaining',
                    render: function(data) {
                        return '<span style="color: var(--success);">' + parseFloat(data).toLocaleString('ar') + ' ج.م</span>';
                    }
                },
                {
                    data: 'spent_percent',
                    render: function(data) {
                        const percent = parseInt(data);
                        return `<div class="progress" style="height: 24px; border-radius: 4px;">
                                    <div class="progress-bar" style="width: ${percent}%; background: linear-gradient(90deg, var(--primary), var(--secondary));">
                                        ${percent}%
                                    </div>
                                </div>`;
                    }
                },
                { data: 'status_label' },
                {
                    data: 'actions',
                    orderable: false,
                    searchable: false
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
