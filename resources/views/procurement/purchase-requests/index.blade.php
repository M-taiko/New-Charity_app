@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 style="margin:0;font-size:2rem;font-weight:700;"><i class="fas fa-shopping-cart"></i> طلبات الشراء</h1>
            </div>
            <a href="{{ route('purchase-requests.create') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> طلب شراء جديد
            </a>
        </div>
    </div>

    @can('approve_custody')
    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-label">في الانتظار</div>
                <div class="stat-number" style="color:var(--warning);">{{ $stats['pending'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">موافق عليها</div>
                <div class="stat-number" style="color:var(--success);">{{ $stats['approved'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-box"></i></div>
                <div class="stat-label">تم الشراء</div>
                <div class="stat-number" style="color:var(--primary);">{{ $stats['purchased'] }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-label">إجمالي المنصرف</div>
                <div class="stat-number" style="color:var(--danger);font-size:1.2rem;">{{ number_format($stats['total_cost'], 0) }} <small>ج.م</small></div>
            </div>
        </div>
    </div>
    @endcan

    <!-- Table -->
    <div class="card" data-aos="fade-up">
        <div class="card-header"><h5 style="margin:0;"><i class="fas fa-list"></i> قائمة الطلبات</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>العنوان</th>
                            <th>الفئة</th>
                            <th>الأولوية</th>
                            <th>التكلفة التقديرية</th>
                            <th>الحالة</th>
                            <th>مقدم الطلب</th>
                            <th>التاريخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr>
                            <td class="text-muted" style="font-size:.8rem;">{{ $req->id }}</td>
                            <td><strong>{{ $req->title }}</strong></td>
                            <td><span class="badge bg-secondary">{{ $req->category_label }}</span></td>
                            <td><span class="badge bg-{{ $req->priority_color }}">{{ $req->priority_label }}</span></td>
                            <td>{{ $req->estimated_cost ? number_format($req->estimated_cost, 0) . ' ج.م' : '—' }}</td>
                            <td><span class="badge bg-{{ $req->status_color }}">{{ $req->status_label }}</span></td>
                            <td style="font-size:.85rem;">{{ $req->requester->name }}</td>
                            <td style="font-size:.8rem;">{{ $req->created_at->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('purchase-requests.show', $req) }}" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">لا توجد طلبات</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
