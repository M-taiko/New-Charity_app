@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 style="margin:0;font-size:2rem;font-weight:700;"><i class="fas fa-tools"></i> طلبات الصيانة</h1>
            <a href="{{ route('maintenance-requests.create') }}" class="btn btn-warning">
                <i class="fas fa-plus-circle"></i> إبلاغ عن مشكلة
            </a>
        </div>
    </div>

    @can('approve_custody')
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-label">في الانتظار</div>
                <div class="stat-number" style="color:var(--warning);">{{ $stats['pending'] }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-wrench"></i></div>
                <div class="stat-label">جاري المعالجة</div>
                <div class="stat-number" style="color:var(--primary);">{{ $stats['in_progress'] }}</div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">تم الحل</div>
                <div class="stat-number" style="color:var(--success);">{{ $stats['resolved'] }}</div>
            </div>
        </div>
    </div>
    @endcan

    <div class="card" data-aos="fade-up">
        <div class="card-header"><h5 style="margin:0;"><i class="fas fa-list"></i> سجل الطلبات</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المشكلة</th>
                            <th>الموقع</th>
                            <th>الأولوية</th>
                            <th>الحالة</th>
                            <th>المُبلِّغ</th>
                            <th>المكلَّف</th>
                            <th>التاريخ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr>
                            <td class="text-muted" style="font-size:.8rem;">{{ $req->id }}</td>
                            <td><strong>{{ $req->title }}</strong></td>
                            <td style="font-size:.85rem;">{{ $req->location ?? '—' }}</td>
                            <td><span class="badge bg-{{ $req->priority_color }}">{{ $req->priority_label }}</span></td>
                            <td><span class="badge bg-{{ $req->status_color }}">{{ $req->status_label }}</span></td>
                            <td style="font-size:.85rem;">{{ $req->reporter->name }}</td>
                            <td style="font-size:.85rem;">{{ $req->assignee?->name ?? '—' }}</td>
                            <td style="font-size:.8rem;">{{ $req->created_at->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('maintenance-requests.show', $req) }}" class="btn btn-sm btn-info">
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
