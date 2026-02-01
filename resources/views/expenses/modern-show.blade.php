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
                            <p class="text-danger" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($expense->amount, 2) }} ر.س</p>
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

                    <div class="d-flex gap-2" style="margin-top: 2rem;">
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> رجوع
                        </a>
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
@endsection
