@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تسجيل مصروف جديد</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('expenses.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">العهدة <span class="text-danger">*</span></label>
                            <select name="custody_id" class="form-select" required>
                                <option value="">اختر العهدة</option>
                                @foreach($custodies as $custody)
                                    <option value="{{ $custody->id }}">
                                        {{ $custody->agent->name }} - {{ number_format($custody->getRemainingBalance(), 2) }} ر.س
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">نوع المصروف <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">اختر النوع</option>
                                <option value="social_case">حالة اجتماعية</option>
                                <option value="general">مصروف عام</option>
                            </select>
                        </div>

                        <div class="mb-3" id="caseDiv" style="display:none;">
                            <label class="form-label">الحالة الاجتماعية</label>
                            <select name="social_case_id" class="form-select">
                                <option value="">اختر الحالة</option>
                                @foreach($cases as $case)
                                    <option value="{{ $case->id }}">{{ $case->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">المبلغ (ر.س) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="amount" class="form-control" required value="{{ old('amount') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الوصف <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" required rows="3">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الموقع</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location') }}">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">حفظ</button>
                            <a href="{{ route('expenses.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelector('[name="type"]').addEventListener('change', function() {
        document.getElementById('caseDiv').style.display = this.value === 'social_case' ? 'block' : 'none';
    });
</script>
@endpush
@endsection
