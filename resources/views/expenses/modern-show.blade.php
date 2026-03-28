@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-receipt"></i> تفاصيل المصروف
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-info-circle"></i> بيانات المصروف
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المستخدم:</strong></label>
                            <p>{{ $expense->user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>النوع:</strong></label>
                            <p>{{ $expense->type === 'social_case' ? 'حالة اجتماعية' : 'مصروف عام' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>المبلغ:</strong></label>
                            <p class="text-danger" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($expense->amount, 2) }} ج.م</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>التاريخ:</strong></label>
                            <p>{{ $expense->expense_date->format('Y-m-d') }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>الوصف:</strong></label>
                        <p>{{ $expense->description }}</p>
                    </div>

                    @if($expense->location)
                    <div class="mb-3">
                        <label class="form-label"><strong>الموقع:</strong></label>
                        <p>{{ $expense->location }}</p>
                    </div>
                    @endif

                    @if($expense->attachment)
                    <div class="mb-3">
                        <label class="form-label"><strong><i class="fas fa-paperclip"></i> المرفق:</strong></label>
                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#attachmentModal" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-eye"></i> عرض المرفق
                            </button>
                            <a href="{{ route('expenses.download-attachment', $expense->id) }}" class="btn btn-sm btn-success" target="_blank">
                                <i class="fas fa-download"></i> تحميل
                            </a>
                            <small class="text-muted">
                                <i class="fas fa-file"></i> {{ basename($expense->attachment) }}
                            </small>
                        </div>
                    </div>
                    @endif

                    @if($expense->socialCase)
                    <div class="mb-3">
                        <label class="form-label"><strong>الحالة الاجتماعية:</strong></label>
                        <p>
                            <a href="{{ route('social_cases.show', $expense->socialCase->id) }}" class="btn btn-sm btn-info">
                                {{ $expense->socialCase->name }}
                            </a>
                        </p>
                    </div>
                    @endif

                    <!-- حالة المصروف وأزرار الإجراءات -->
                    <div class="mb-3">
                        @if($expense->isApproved())
                            <span class="badge bg-success"><i class="fas fa-check"></i> معتمد</span>
                        @elseif($expense->hasPendingEdit())
                            <span class="badge bg-warning"><i class="fas fa-clock"></i> في انتظار الموافقة على التعديل</span>
                        @endif
                    </div>

                    <div class="d-flex gap-2" style="margin-top: 2rem;">
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> رجوع
                        </a>

                        <!-- زر طلب التعديل (للمندوب فقط) -->
                        @if(auth()->user()->hasRole('مندوب') && $expense->user_id === auth()->id() && !$expense->isApproved() && !$expense->hasPendingEdit())
                            <a href="{{ route('expense-edit-requests.create', $expense) }}" class="btn btn-warning">
                                <i class="fas fa-edit"></i> طلب تعديل
                            </a>
                        @endif

                        <!-- زر التعديل المباشر (للمحاسب/المدير) -->
                        @if((auth()->user()->hasRole('محاسب') || (auth()->user()->hasRole('مدير') && $expense->isApproved())) && !$expense->hasPendingEdit())
                            <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-info">
                                <i class="fas fa-pencil"></i> تعديل
                            </a>
                        @endif

                        <!-- زر الحذف -->
                        @if(auth()->user()->id === $expense->user_id || auth()->user()->hasRole('محاسب') || auth()->user()->hasRole('مدير'))
                            <form action="{{ route('expenses.destroy', $expense) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المصروف؟');">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); border: 1px solid rgba(245, 87, 108, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #f5576c;"></i> معلومات إضافية
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="mb-3">
                            <strong>معرف المصروف:</strong><br>
                            <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">{{ $expense->id }}</code>
                        </div>
                        <div class="mb-3">
                            <strong>تاريخ الإنشاء:</strong><br>
                            {{ $expense->created_at->format('Y-m-d H:i') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attachment Modal -->
@if($expense->attachment)
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-labelledby="attachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <h5 class="modal-title" id="attachmentModalLabel" style="color: white;">
                    <i class="fas fa-paperclip"></i> مرفق المصروف
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="padding: 2rem;">
                @php
                    $extension = strtolower(pathinfo($expense->attachment, PATHINFO_EXTENSION));
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                    $isPdf = $extension === 'pdf';
                @endphp

                @if($isImage)
                    <img src="{{ '/storage/app/public/' . $expense->attachment }}" alt="Expense Attachment" class="img-fluid" style="max-height: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                @elseif($isPdf)
                    <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px;">
                        <i class="fas fa-file-pdf" style="font-size: 5rem; color: #f5576c; margin-bottom: 1rem;"></i>
                        <h5 style="margin-bottom: 1rem;">ملف PDF</h5>
                        <p class="text-muted" style="margin-bottom: 1.5rem;">{{ basename($expense->attachment) }}</p>
                        <a href="{{ route('expenses.download-attachment', $expense->id) }}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                            <i class="fas fa-download"></i> تحميل الملف
                        </a>
                    </div>
                @else
                    <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px;">
                        <i class="fas fa-file-alt" style="font-size: 5rem; color: #667eea; margin-bottom: 1rem;"></i>
                        <h5 style="margin-bottom: 1rem;">مستند</h5>
                        <p class="text-muted" style="margin-bottom: 1.5rem;">{{ basename($expense->attachment) }}</p>
                        <a href="{{ route('expenses.download-attachment', $expense->id) }}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                            <i class="fas fa-download"></i> تحميل الملف
                        </a>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <a href="{{ route('expenses.download-attachment', $expense->id) }}" class="btn btn-success" target="_blank">
                    <i class="fas fa-download"></i> تحميل
                </a>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
