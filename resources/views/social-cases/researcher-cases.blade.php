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
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-tabs" role="tablist" style="border-bottom: 2px solid #e5e7eb;">
                        <li class="nav-item">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-cases" type="button" role="tab">
                                <i class="fas fa-list"></i> جميع الحالات
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-cases" type="button" role="tab">
                                <i class="fas fa-hourglass"></i> قيد الانتظار
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-cases" type="button" role="tab">
                                <i class="fas fa-check-circle"></i> موافق عليها
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected-cases" type="button" role="tab">
                                <i class="fas fa-times-circle"></i> مرفوضة
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
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
                                                <td>{{ $case->name }}</td>
                                                <td>{{ $case->phone ?? '-' }}</td>
                                                <td>{{ $case->assistance_type }}</td>
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
                                                        <div class="empty-state-title">لا توجد حالات</div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pending Cases Tab -->
                        <div class="tab-pane fade" id="pending-cases" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> المعرف</th>
                                            <th><i class="fas fa-user"></i> الاسم</th>
                                            <th><i class="fas fa-phone"></i> الهاتف</th>
                                            <th><i class="fas fa-hands-helping"></i> نوع المساعدة</th>
                                            <th><i class="fas fa-calendar"></i> التاريخ</th>
                                            <th><i class="fas fa-cog"></i> الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cases->where('status', 'pending') as $case)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('social_cases.show', $case->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                        #{{ $case->id }}
                                                    </a>
                                                </td>
                                                <td>{{ $case->name }}</td>
                                                <td>{{ $case->phone ?? '-' }}</td>
                                                <td>{{ $case->assistance_type }}</td>
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
                                                <td colspan="6" class="text-center py-5">
                                                    <div style="color: #6b7280;">لا توجد حالات قيد الانتظار</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Approved Cases Tab -->
                        <div class="tab-pane fade" id="approved-cases" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> المعرف</th>
                                            <th><i class="fas fa-user"></i> الاسم</th>
                                            <th><i class="fas fa-phone"></i> الهاتف</th>
                                            <th><i class="fas fa-hands-helping"></i> نوع المساعدة</th>
                                            <th><i class="fas fa-money-bill"></i> المبلغ المصروف</th>
                                            <th><i class="fas fa-cog"></i> الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cases->where('status', 'approved') as $case)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('social_cases.show', $case->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                        #{{ $case->id }}
                                                    </a>
                                                </td>
                                                <td>{{ $case->name }}</td>
                                                <td>{{ $case->phone ?? '-' }}</td>
                                                <td>{{ $case->assistance_type }}</td>
                                                <td>
                                                    <strong style="color: #4caf50;">{{ number_format($case->getTotalSpent(), 2) }} ر.س</strong>
                                                </td>
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
                                                <td colspan="6" class="text-center py-5">
                                                    <div style="color: #6b7280;">لا توجد حالات موافق عليها</div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Rejected Cases Tab -->
                        <div class="tab-pane fade" id="rejected-cases" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-hashtag"></i> المعرف</th>
                                            <th><i class="fas fa-user"></i> الاسم</th>
                                            <th><i class="fas fa-phone"></i> الهاتف</th>
                                            <th><i class="fas fa-hands-helping"></i> نوع المساعدة</th>
                                            <th><i class="fas fa-comment"></i> ملاحظات الرفض</th>
                                            <th><i class="fas fa-cog"></i> الإجراءات</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($cases->where('status', 'rejected') as $case)
                                            <tr>
                                                <td>
                                                    <a href="{{ route('social_cases.show', $case->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                        #{{ $case->id }}
                                                    </a>
                                                </td>
                                                <td>{{ $case->name }}</td>
                                                <td>{{ $case->phone ?? '-' }}</td>
                                                <td>{{ $case->assistance_type }}</td>
                                                <td>{{ $case->internal_notes ?? '-' }}</td>
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
                                                <td colspan="6" class="text-center py-5">
                                                    <div style="color: #6b7280;">لا توجد حالات مرفوضة</div>
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
    </div>
</div>
@endsection
