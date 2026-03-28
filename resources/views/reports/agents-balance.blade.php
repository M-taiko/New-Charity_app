@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-chart-line"></i> تقرير رصيد المندوبين
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        عرض تفصيلي لأرصدة جميع المندوبين
                    </p>
                </div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> طباعة التقرير
                </button>
            </div>
        </div>
    </div>

    <!-- Grand Total Statistics -->
    <div class="row g-3 mb-4" data-aos="fade-up">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #667eea !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-users text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">عدد المندوبين</div>
                            <h5 class="mb-0">{{ $grandTotals['total_agents'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #3b82f6 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <i class="fas fa-hand-holding-usd text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">إجمالي المستلم</div>
                            <h6 class="mb-0" style="font-size: 0.95rem;">{{ number_format($grandTotals['total_received'], 0) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #ef4444 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <i class="fas fa-shopping-cart text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">إجمالي المصروف</div>
                            <h6 class="mb-0" style="font-size: 0.95rem;">{{ number_format($grandTotals['total_spent'], 0) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #10b981 !important;">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                                <i class="fas fa-wallet text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">الرصيد الحالي</div>
                            <h6 class="mb-0" style="font-size: 0.95rem;">{{ number_format($grandTotals['total_balance'], 0) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents Table -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> تفاصيل أرصدة المندوبين
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم المندوب</th>
                                    <th class="text-center">عدد العهدات</th>
                                    <th class="text-center">النشطة</th>
                                    <th class="text-center">المغلقة</th>
                                    <th class="text-end">المستلم</th>
                                    <th class="text-end">المصروف</th>
                                    <th class="text-end">المرتجع</th>
                                    <th class="text-end">الرصيد الحالي</th>
                                    <th class="text-center">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($agentsData as $index => $data)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                {{ substr($data['agent']->name, 0, 2) }}
                                            </div>
                                            <div class="ms-2">
                                                <div class="fw-semibold">{{ $data['agent']->name }}</div>
                                                <small class="text-muted">{{ $data['agent']->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary">{{ $data['total_custodies'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">{{ $data['active_custodies'] }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark">{{ $data['closed_custodies'] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <span style="color: #3b82f6; font-weight: 600;">
                                            {{ number_format($data['total_received'], 2) }} ج.م
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span style="color: #ef4444; font-weight: 600;">
                                            {{ number_format($data['total_spent'], 2) }} ج.م
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span style="color: #10b981; font-weight: 600;">
                                            {{ number_format($data['total_returned'], 2) }} ج.م
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-size: 0.9rem; padding: 0.4rem 0.8rem;">
                                            {{ number_format($data['current_balance'], 2) }} ج.م
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#agentDetailsModal{{ $data['agent']->id }}"
                                                title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <p>لا يوجد مندوبين حصلوا على عهدات بعد</p>
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
</div>

<!-- Agent Details Modals -->
@foreach($agentsData as $data)
<div class="modal fade" id="agentDetailsModal{{ $data['agent']->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-user"></i> تفاصيل المندوب - {{ $data['agent']->name }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Agent Info -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">المعلومات الشخصية</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="40%">الاسم:</th>
                                <td>{{ $data['agent']->name }}</td>
                            </tr>
                            <tr>
                                <th>البريد الإلكتروني:</th>
                                <td>{{ $data['agent']->email }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">إحصائيات العهدات</h6>
                        <table class="table table-sm">
                            <tr>
                                <th width="50%">إجمالي العهدات:</th>
                                <td><span class="badge bg-secondary">{{ $data['total_custodies'] }}</span></td>
                            </tr>
                            <tr>
                                <th>العهدات النشطة:</th>
                                <td><span class="badge bg-success">{{ $data['active_custodies'] }}</span></td>
                            </tr>
                            <tr>
                                <th>العهدات المغلقة:</th>
                                <td><span class="badge bg-dark">{{ $data['closed_custodies'] }}</span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Financial Summary -->
                <h6 class="text-muted mb-3">الملخص المالي</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المستلم</div>
                            <h6 class="mb-0" style="color: #3b82f6;">{{ number_format($data['total_received'], 2) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المصروف</div>
                            <h6 class="mb-0" style="color: #ef4444;">{{ number_format($data['total_spent'], 2) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المرتجع</div>
                            <h6 class="mb-0" style="color: #10b981;">{{ number_format($data['total_returned'], 2) }} ج.م</h6>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));">
                            <div class="text-muted small">الرصيد الحالي</div>
                            <h6 class="mb-0" style="color: #667eea;">{{ number_format($data['current_balance'], 2) }} ج.م</h6>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endforeach

<style>
@media print {
    .btn, .modal, .card-header button {
        display: none !important;
    }
    .card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
