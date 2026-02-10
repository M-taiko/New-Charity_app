@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-edit"></i> تعديل الحالة الاجتماعية
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
                        <i class="fas fa-pen-square"></i> بيانات الحالة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('social_cases.update', $socialCase->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>اسم الحالة</strong></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $socialCase->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>رقم الهوية</strong></label>
                                <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror" value="{{ old('national_id', $socialCase->national_id) }}">
                                @error('national_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>رقم الهاتف</strong></label>
                                <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $socialCase->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>نوع المساعدة</strong></label>
                                <select name="assistance_type" class="form-select @error('assistance_type') is-invalid @enderror" required>
                                    <option value="">-- اختر نوع المساعدة --</option>
                                    <option value="cash" {{ old('assistance_type', $socialCase->assistance_type) == 'cash' ? 'selected' : '' }}>مساعدة مالية</option>
                                    <option value="monthly_salary" {{ old('assistance_type', $socialCase->assistance_type) == 'monthly_salary' ? 'selected' : '' }}>راتب شهري</option>
                                    <option value="medicine" {{ old('assistance_type', $socialCase->assistance_type) == 'medicine' ? 'selected' : '' }}>أدوية وعلاج</option>
                                    <option value="treatment" {{ old('assistance_type', $socialCase->assistance_type) == 'treatment' ? 'selected' : '' }}>علاج طبي متخصص</option>
                                    <option value="other" {{ old('assistance_type', $socialCase->assistance_type) == 'other' ? 'selected' : '' }}>أخرى</option>
                                </select>
                                @error('assistance_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الباحث الاجتماعي</strong></label>
                            <select name="researcher_id" class="form-select @error('researcher_id') is-invalid @enderror" required>
                                <option value="">-- اختر باحثاً --</option>
                                @foreach(\App\Models\User::where('is_active', true)->get() as $user)
                                    <option value="{{ $user->id }}" {{ old('researcher_id', $socialCase->researcher_id) == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('researcher_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description', $socialCase->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                            <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #4facfe;"></i> معلومات الحالة
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 1.8;">
                        <div class="mb-2">
                            <strong>الحالة:</strong>
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
                        </div>
                        <div class="mb-2">
                            <strong>المبلغ المصروف:</strong> {{ number_format($socialCase->getTotalSpent(), 2) }} ج.م
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
