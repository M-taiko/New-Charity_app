@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-file-alt"></i> حالاتي الاجتماعية
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        جميع الحالات الاجتماعية التي قمت بإنشاؤها والمتابعة معها
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-label">إجمالي حالاتي</div>
                <div class="stat-number" style="color: var(--primary);">{{ $totalCases }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-label">حالات معلقة</div>
                <div class="stat-number" style="color: var(--warning);">{{ $pendingCases }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check"></i></div>
                <div class="stat-label">حالات موافق عليها</div>
                <div class="stat-number" style="color: var(--success);">{{ $approvedCases }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-times"></i></div>
                <div class="stat-label">حالات مرفوضة</div>
                <div class="stat-number" style="color: var(--danger);">{{ $rejectedCases }}</div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row g-4 mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="col-12">
            <form method="GET" action="{{ route('social_cases.researcher') }}" class="card">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label"><strong>البحث عن حالة:</strong></label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: white; border-right: none;">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input
                                    type="text"
                                    name="search"
                                    class="form-control"
                                    placeholder="ابحث بالاسم أو رقم الهاتف..."
                                    value="{{ $search }}"
                                    style="border-left: none;">
                            </div>
                            <small class="text-muted">البحث في الاسم والهاتف والوصف</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label"><strong>العدد بالصفحة:</strong></label>
                            <select name="per_page" class="form-select" onchange="this.form.submit()">
                                <option value="10" @selected($perPage == 10)>10</option>
                                <option value="15" @selected($perPage == 15)>15</option>
                                <option value="25" @selected($perPage == 25)>25</option>
                                <option value="50" @selected($perPage == 50)>50</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </div>
                    @if($search)
                    <div class="mt-2">
                        <span class="badge bg-info">نتائج البحث عن: "{{ $search }}"</span>
                        <a href="{{ route('social_cases.researcher') }}" class="badge bg-secondary ms-2">مسح البحث</a>
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Tabs for different case statuses -->
    <div class="row g-4" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <h5 style="margin: 0;">
                            <i class="fas fa-folder-open"></i> سجل الحالات
                        </h5>
                        <a href="{{ route('social_cases.create') }}" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus-circle"></i> إنشاء حالة جديدة
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                        <!-- All Cases Tab -->
                        <div class="tab-pane fade show active" id="all-cases" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="allCasesTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> المعرف</th>
                                            <th><i class="fas fa-user"></i> الاسم</th>
                                            <th><i class="fas fa-phone"></i> الهاتف</th>
                                            <th><i class="fas fa-hands-helping"></i> نوع المساعدة</th>
                                            <th><i class="fas fa-signal"></i> الحالة</th>
                                            <th><i class="fas fa-calendar"></i> التاريخ</th>
                                            <th><i class="fas fa-cog"></i> الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cases as $case)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('social_cases.show', $case->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                        #{{ $case->id }}
                                                    </a>
                                                </td>
                                                <td><strong>{{ $case->name }}</strong></td>
                                                <td>{{ $case->phone ?? '-' }}</td>
                                                <td>{{ $case->case_type ?? $case->assistance_type ?? '-' }}</td>
                                                <td>
                                                    @switch($case->status)
                                                        @case('pending')
                                                            <span class="badge bg-warning">قيد الانتظار</span>
                                                            @break
                                                        @case('approved')
                                                            <span class="badge bg-success">موافق عليه</span>
                                                            @break
                                                        @case('rejected')
                                                            <span class="badge bg-danger">مرفوض</span>
                                                            @break
                                                        @case('completed')
                                                            <span class="badge bg-secondary">مكتمل</span>
                                                            @break
                                                        @default
                                                            <span class="badge bg-secondary">جديد</span>
                                                    @endswitch
                                                </td>
                                                <td>{{ $case->created_at->format('Y-m-d') }}</td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ route('social_cases.show', $case->id) }}" class="btn btn-outline-primary" title="عرض التفاصيل">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="{{ route('social_cases.edit', $case->id) }}" class="btn btn-outline-warning" title="تعديل">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
                                                    <div class="empty-state">
                                                        <div class="empty-state-icon">
                                                            <i class="fas fa-inbox"></i>
                                                        </div>
                                                        <div class="empty-state-title">{{ $search ? 'لا توجد نتائج مطابقة' : 'لا توجد حالات' }}</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div>
                    <small class="text-muted">
                        عرض <strong>{{ $cases->firstItem() ?? 0 }}</strong> إلى <strong>{{ $cases->lastItem() ?? 0 }}</strong> من <strong>{{ $cases->total() }}</strong> حالة
                    </small>
                </div>
            </div>

            <!-- Pagination Links -->
            <div class="mt-3">
                {{ $cases->appends(request()->query())->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>
</div>

<!-- Family Members Modals -->
@foreach($cases as $case)
    @if($case->familyMembers && $case->familyMembers->count() > 0)
    <div class="modal fade" id="familyModal{{ $case->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 class="modal-title" style="color: white;">
                        <i class="fas fa-users"></i> أفراد عائلة: {{ $case->name }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>الاسم</th>
                                    <th>صلة القرابة</th>
                                    <th>النوع</th>
                                    <th>رقم الهاتف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($case->familyMembers as $index => $member)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><strong>{{ $member->name }}</strong></td>
                                    <td>
                                        <span class="badge bg-primary">{{ $member->relationship }}</span>
                                    </td>
                                    <td>
                                        @if($member->gender === 'male')
                                            <i class="fas fa-male text-primary"></i> ذكر
                                        @else
                                            <i class="fas fa-female text-danger"></i> أنثى
                                        @endif
                                    </td>
                                    <td>{{ $member->phone ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>
    @endif
@endforeach

@endsection
