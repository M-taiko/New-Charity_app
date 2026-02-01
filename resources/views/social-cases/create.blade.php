@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>إنشاء حالة اجتماعية جديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('social_cases.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">اسم الحالة <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                            @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم الهوية</label>
                            <input type="text" name="national_id" class="form-control" value="{{ old('national_id') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">نوع المساعدة <span class="text-danger">*</span></label>
                            <select name="assistance_type" class="form-select" required>
                                <option value="">اختر نوع المساعدة</option>
                                <option value="cash">نقدي</option>
                                <option value="monthly_salary">راتب شهري</option>
                                <option value="medicine">أدوية</option>
                                <option value="treatment">علاج</option>
                                <option value="other">أخرى</option>
                            </select>
                            @error('assistance_type') <div class="text-danger">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3" id="otherAssistanceDiv" style="display:none;">
                            <label class="form-label">توضيح نوع المساعدة الأخرى</label>
                            <input type="text" name="assistance_other" class="form-control" value="{{ old('assistance_other') }}">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">حفظ</button>
                            <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelector('[name="assistance_type"]').addEventListener('change', function() {
        document.getElementById('otherAssistanceDiv').style.display = this.value === 'other' ? 'block' : 'none';
    });
</script>
@endpush
@endsection
