@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تفاصيل المصروف</h5>
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
                            <p class="text-danger">{{ number_format($expense->amount, 2) }} ج.م</p>
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
                        <p>{{ $expense->socialCase->name }}</p>
                    </div>
                    @endif

                    <div class="d-flex gap-2">
                        <a href="{{ route('expenses.index') }}" class="btn btn-secondary">رجوع</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
