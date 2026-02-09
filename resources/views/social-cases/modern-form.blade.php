@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-user-tie"></i> {{ isset($socialCase) ? 'تعديل الحالة الاجتماعية' : 'إضافة حالة اجتماعية جديدة' }}
            </h1>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-10 offset-lg-1">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-form"></i> بيانات الحالة الاجتماعية
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($socialCase) ? route('social_cases.update', $socialCase) : route('social_cases.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if(isset($socialCase))
                            @method('PUT')
                        @endif

                        <!-- Section 1: Basic Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-user-circle"></i> البيانات الأساسية
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>اسم الحالة</strong></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $socialCase->name ?? '') }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>رقم الهوية</strong></label>
                                    <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror"
                                           value="{{ old('national_id', $socialCase->national_id ?? '') }}">
                                    @error('national_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>رقم الهاتف</strong></label>
                                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone', $socialCase->phone ?? '') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>الجنس</strong></label>
                                    <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        <option value="male" {{ old('gender', $socialCase->gender ?? '') == 'male' ? 'selected' : '' }}>ذكر</option>
                                        <option value="female" {{ old('gender', $socialCase->gender ?? '') == 'female' ? 'selected' : '' }}>أنثى</option>
                                    </select>
                                    @error('gender')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>تاريخ الميلاد</strong></label>
                                    <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror"
                                           value="{{ old('birth_date', $socialCase->birth_date ?? '') }}">
                                    @error('birth_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>الحالة الاجتماعية</strong></label>
                                    <select name="marital_status" class="form-select @error('marital_status') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        <option value="single" {{ old('marital_status', $socialCase->marital_status ?? '') == 'single' ? 'selected' : '' }}>أعزب</option>
                                        <option value="married" {{ old('marital_status', $socialCase->marital_status ?? '') == 'married' ? 'selected' : '' }}>متزوج</option>
                                        <option value="widowed" {{ old('marital_status', $socialCase->marital_status ?? '') == 'widowed' ? 'selected' : '' }}>أرمل</option>
                                        <option value="divorced" {{ old('marital_status', $socialCase->marital_status ?? '') == 'divorced' ? 'selected' : '' }}>مطلق</option>
                                    </select>
                                    @error('marital_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>عدد أفراد الأسرة</strong></label>
                                    <input type="number" name="family_members_count" class="form-control @error('family_members_count') is-invalid @enderror"
                                           value="{{ old('family_members_count', $socialCase->family_members_count ?? '') }}" min="1">
                                    @error('family_members_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Location Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-map-marker-alt"></i> معلومات السكن
                            </h6>

                            <div class="mb-3">
                                <label class="form-label"><strong>العنوان</strong></label>
                                <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
                                       value="{{ old('address', $socialCase->address ?? '') }}" placeholder="الشارع والحي والتفاصيل">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>المدينة</strong></label>
                                    <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
                                           value="{{ old('city', $socialCase->city ?? '') }}">
                                    @error('city')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>الحي/المنطقة</strong></label>
                                    <input type="text" name="district" class="form-control @error('district') is-invalid @enderror"
                                           value="{{ old('district', $socialCase->district ?? '') }}">
                                    @error('district')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Financial Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-wallet"></i> المعلومات المالية
                            </h6>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>الدخل الشهري (ر.س)</strong></label>
                                    <input type="number" name="monthly_income" class="form-control @error('monthly_income') is-invalid @enderror"
                                           value="{{ old('monthly_income', $socialCase->monthly_income ?? '') }}" step="0.01" min="0">
                                    @error('monthly_income')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>المصروفات الشهرية (ر.س)</strong></label>
                                    <input type="number" name="monthly_expenses" class="form-control @error('monthly_expenses') is-invalid @enderror"
                                           value="{{ old('monthly_expenses', $socialCase->monthly_expenses ?? '') }}" step="0.01" min="0">
                                    @error('monthly_expenses')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>المبلغ المطلوب (ر.س)</strong></label>
                                    <input type="number" name="requested_amount" class="form-control @error('requested_amount') is-invalid @enderror"
                                           value="{{ old('requested_amount', $socialCase->requested_amount ?? '') }}" step="0.01" min="0">
                                    @error('requested_amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Health Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-heartbeat"></i> المعلومات الصحية
                            </h6>

                            <div class="mb-3">
                                <label class="form-label"><strong>الحالات الصحية</strong></label>
                                <textarea name="health_conditions" class="form-control @error('health_conditions') is-invalid @enderror"
                                          rows="2" placeholder="أمراض مزمنة، أمراض وراثية، إلخ">{{ old('health_conditions', $socialCase->health_conditions ?? '') }}</textarea>
                                @error('health_conditions')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>هل هناك إعاقة؟</strong></label>
                                    <select name="has_disability" class="form-select @error('has_disability') is-invalid @enderror" onchange="toggleDisabilityField()">
                                        <option value="">-- اختر --</option>
                                        <option value="0" {{ old('has_disability', $socialCase->has_disability ?? '') == 0 ? 'selected' : '' }}>لا</option>
                                        <option value="1" {{ old('has_disability', $socialCase->has_disability ?? '') == 1 ? 'selected' : '' }}>نعم</option>
                                    </select>
                                    @error('has_disability')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3" id="disability-desc-field" style="display: {{ old('has_disability', $socialCase->has_disability ?? '') == 1 ? 'block' : 'none' }};">
                                    <label class="form-label"><strong>وصف الإعاقة</strong></label>
                                    <input type="text" name="disability_description" class="form-control @error('disability_description') is-invalid @enderror"
                                           value="{{ old('disability_description', $socialCase->disability_description ?? '') }}">
                                    @error('disability_description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>الاحتياجات الخاصة</strong></label>
                                <textarea name="special_needs" class="form-control @error('special_needs') is-invalid @enderror"
                                          rows="2" placeholder="متطلبات خاصة، احتياجات تعليمية، إلخ">{{ old('special_needs', $socialCase->special_needs ?? '') }}</textarea>
                                @error('special_needs')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Section 5: Assistance -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-hands-helping"></i> نوع المساعدة
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>نوع المساعدة</strong></label>
                                    <select name="assistance_type" class="form-select @error('assistance_type') is-invalid @enderror" required onchange="toggleOtherAssistance()">
                                        <option value="">-- اختر نوع المساعدة --</option>
                                        <option value="cash" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'cash' ? 'selected' : '' }}>مساعدة مالية</option>
                                        <option value="monthly_salary" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'monthly_salary' ? 'selected' : '' }}>راتب شهري</option>
                                        <option value="medicine" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'medicine' ? 'selected' : '' }}>أدوية وعلاج</option>
                                        <option value="treatment" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'treatment' ? 'selected' : '' }}>علاج طبي متخصص</option>
                                        <option value="other" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'other' ? 'selected' : '' }}>أخرى</option>
                                    </select>
                                    @error('assistance_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3" id="other-assistance-field" style="display: {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'other' ? 'block' : 'none' }};">
                                    <label class="form-label"><strong>تفاصيل أخرى</strong></label>
                                    <input type="text" name="assistance_other" class="form-control @error('assistance_other') is-invalid @enderror"
                                           value="{{ old('assistance_other', $socialCase->assistance_other ?? '') }}">
                                    @error('assistance_other')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><strong>الوصف التفصيلي</strong></label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $socialCase->description ?? '') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Hidden field -->
                        @if(!isset($socialCase))
                            <input type="hidden" name="researcher_id" value="{{ auth()->id() }}">
                        @else
                            <input type="hidden" name="researcher_id" value="{{ $socialCase->researcher_id }}">
                        @endif

                        <!-- Submit Buttons -->
                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-save"></i> {{ isset($socialCase) ? 'تحديث البيانات' : 'حفظ الحالة' }}
                            </button>
                            <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleDisabilityField() {
        const hasDisability = document.querySelector('select[name="has_disability"]').value;
        const disabilityField = document.getElementById('disability-desc-field');
        disabilityField.style.display = hasDisability == 1 ? 'block' : 'none';
    }

    function toggleOtherAssistance() {
        const assistanceType = document.querySelector('select[name="assistance_type"]').value;
        const otherField = document.getElementById('other-assistance-field');
        otherField.style.display = assistanceType == 'other' ? 'block' : 'none';
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleDisabilityField();
        toggleOtherAssistance();
    });
</script>

@endsection
