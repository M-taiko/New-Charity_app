@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-user-tie"></i> تفاصيل الحالة الاجتماعية
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-info-circle"></i> بيانات الحالة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>اسم الحالة:</strong></label>
                            <p>{{ $socialCase->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهوية:</strong></label>
                            <p>{{ $socialCase->national_id ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهاتف:</strong></label>
                            <p>{{ $socialCase->phone ?? '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الباحث:</strong></label>
                            <p>{{ $socialCase->researcher->name }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>نوع المساعدة:</strong></label>
                            <p>{{ $socialCase->assistance_type }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الحالة:</strong></label>
                            <p>
                                @switch($socialCase->status)
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
                            </p>
                        </div>
                    </div>

                    @if($socialCase->description)
                    <div class="mt-3 mb-3">
                        <label class="form-label"><strong>الوصف:</strong></label>
                        <p>{{ $socialCase->description }}</p>
                    </div>
                    @endif

                    @if($socialCase->monthly_expenses && $socialCase->monthly_income && $socialCase->monthly_expenses > $socialCase->monthly_income)
                    <div class="alert alert-danger mb-3" style="border-right: 4px solid #dc3545;">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3" style="color: #dc3545;"></i>
                            <div>
                                <h6 class="mb-1"><strong>تحذير مهم للمدير</strong></h6>
                                <p class="mb-0">
                                    المصروفات الشهرية ({{ number_format($socialCase->monthly_expenses, 2) }} ج.م)
                                    أعلى من الدخل الشهري ({{ number_format($socialCase->monthly_income, 2) }} ج.م)
                                    بمبلغ <strong>{{ number_format($socialCase->monthly_expenses - $socialCase->monthly_income, 2) }} ج.م</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if($socialCase->getTotalSpent() > 0)
                    <div class="mt-3 mb-3 alert alert-info" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                        <strong>المبلغ المصروف:</strong> {{ number_format($socialCase->getTotalSpent(), 2) }} ج.م
                    </div>
                    @endif

                    @if($socialCase->expenses->count() > 0)
                    <hr>

                    <div class="mt-4">
                        <h6 style="margin-bottom: 1rem;">
                            <i class="fas fa-list"></i> المصروفات المتعلقة بالحالة
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>الرقم</th>
                                        <th>النوع</th>
                                        <th>المبلغ</th>
                                        <th>التاريخ</th>
                                        <th>الوصف</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($socialCase->expenses as $expense)
                                    <tr>
                                        <td>
                                            <a href="{{ route('expenses.show', $expense->id) }}" style="text-decoration: none; color: var(--primary); font-weight: 600;">
                                                #{{ $expense->id }}
                                            </a>
                                        </td>
                                        <td>
                                            @if($expense->type === 'social_case')
                                                <span class="badge bg-info">حالة اجتماعية</span>
                                            @else
                                                <span class="badge bg-secondary">عام</span>
                                            @endif
                                        </td>
                                        <td>
                                            <strong style="color: #4caf50;">{{ number_format($expense->amount, 2) }} ج.م</strong>
                                        </td>
                                        <td>{{ $expense->created_at->format('Y-m-d') }}</td>
                                        <td>
                                            <small>{{ Str::limit($expense->description, 30) }}</small>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    @if($socialCase->familyMembers && $socialCase->familyMembers->count() > 0)
                    <hr>

                    <div class="mt-4">
                        <h6 style="margin-bottom: 1rem;">
                            <i class="fas fa-users"></i> أفراد العائلة ({{ $socialCase->familyMembers->count() }})
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
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
                                    @foreach($socialCase->familyMembers as $index => $member)
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
                    @endif

                    @if($socialCase->documents->count() > 0)
                    <hr>

                    <div class="mt-4">
                        <h6 style="margin-bottom: 1rem;">
                            <i class="fas fa-file-upload"></i> الملفات المرفقة
                        </h6>
                        <div class="list-group">
                            @foreach($socialCase->documents as $document)
                            <div class="list-group-item" style="display: flex; align-items: center; justify-content: space-between;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i class="fas fa-file" style="color: #4facfe; font-size: 1.2rem;"></i>
                                    <div>
                                        <strong style="display: block;">{{ $document->name }}</strong>
                                        <small style="color: #6b7280;">{{ $document->file_type }}</small>
                                    </div>
                                </div>
                                <a href="{{ '/storage/app/public/' . $document->file_path }}" class="btn btn-sm btn-outline-primary" download>
                                    <i class="fas fa-download"></i> تحميل
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <hr>
                    @endif

                    <div class="d-flex gap-2">
                        @if(auth()->user()->hasRole('باحث اجتماعي') || request()->route()->getName() == 'social_cases.show')
                            <a href="{{ route('social_cases.researcher') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> رجوع
                            </a>
                        @else
                            <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> رجوع
                            </a>
                        @endif
                        @can('review_social_case')
                            @if($socialCase->status == 'pending')
                                <form action="{{ route('social_cases.approve', $socialCase->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> الموافقة
                                    </button>
                                </form>
                                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times"></i> الرفض
                                </button>
                            @endif
                        @endcan
                        @can('manage_social_cases')
                            <a href="{{ route('social_cases.edit', $socialCase->id) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Case Summary -->
            <div class="card mb-3" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-chart-pie" style="color: #4facfe;"></i> ملخص الحالة
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="mb-3">
                            <strong>معرف الحالة:</strong><br>
                            <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">{{ $socialCase->id }}</code>
                        </div>
                        <div class="mb-3">
                            <strong>تاريخ الإنشاء:</strong><br>
                            {{ $socialCase->created_at->format('Y-m-d H:i') }}
                        </div>
                        <div class="mb-3">
                            <strong>الباحث:</strong><br>
                            {{ $socialCase->researcher->name }}
                        </div>
                        @if($socialCase->reviewer)
                        <div class="mb-3">
                            <strong>المراجع:</strong><br>
                            {{ $socialCase->reviewer->name }}
                        </div>
                        @endif
                        @if($socialCase->reviewed_at)
                        <div class="mb-3">
                            <strong>تاريخ المراجعة:</strong><br>
                            {{ $socialCase->reviewed_at->format('Y-m-d H:i') }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            @if($socialCase->status == 'rejected' && $socialCase->internal_notes)
            <!-- Rejection Notes -->
            <div class="card" style="background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(229, 57, 53, 0.1)); border: 1px solid rgba(229, 57, 53, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3" style="color: #e53935;">
                        <i class="fas fa-exclamation-circle"></i> ملاحظات الرفض
                    </h6>
                    <p style="font-size: 0.9rem; color: #666;">{{ $socialCase->internal_notes }}</p>
                </div>
            </div>
            @endif

            @if($socialCase->getTotalSpent() > 0)
            <!-- Spending Summary -->
            <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3" style="color: #4caf50;">
                        <i class="fas fa-money-bill-wave"></i> ملخص الصرف
                    </h6>
                    <div style="font-size: 0.9rem;">
                        <div class="mb-2">
                            <strong>إجمالي المصروف:</strong><br>
                            <span style="color: #4caf50; font-size: 1.2rem; font-weight: bold;">
                                {{ number_format($socialCase->getTotalSpent(), 2) }} ج.م
                            </span>
                        </div>
                        <div>
                            <strong>عدد المصروفات:</strong><br>
                            {{ $socialCase->expenses->count() }}
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                <h5 class="modal-title" style="color: white;">رفض الحالة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('social_cases.reject', $socialCase->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>ملاحظات الرفض</strong></label>
                        <textarea name="notes" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">رفض</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
