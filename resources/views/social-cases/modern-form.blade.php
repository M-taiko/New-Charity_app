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

                        <!-- All Data: Full Details (shown on both create and edit) -->
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
                                    <label class="form-label"><strong>الجنسية <span class="text-danger">*</span></strong></label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" name="nationality" id="nationality_egyptian" value="egyptian"
                                               class="btn-check" {{ old('nationality', $socialCase->nationality ?? '') == 'egyptian' ? 'checked' : '' }}
                                               onchange="toggleNationalityField()">
                                        <label class="btn btn-outline-primary" for="nationality_egyptian">
                                            <i class="fas fa-flag"></i> مصري
                                        </label>

                                        <input type="radio" name="nationality" id="nationality_other" value="other"
                                               class="btn-check" {{ old('nationality', $socialCase->nationality ?? '') == 'other' ? 'checked' : '' }}
                                               onchange="toggleNationalityField()">
                                        <label class="btn btn-outline-primary" for="nationality_other">
                                            <i class="fas fa-globe"></i> أخرى
                                        </label>
                                    </div>
                                    @error('nationality')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3" id="national_id_field">
                                    <label class="form-label"><strong>رقم الهوية الوطنية <span class="text-danger" id="id_required">*</span></strong></label>
                                    <input type="text" id="national_id_input" name="national_id" class="form-control @error('national_id') is-invalid @enderror"
                                           value="{{ old('national_id', $socialCase->national_id ?? '') }}"
                                           placeholder="14 رقم للمصريين" maxlength="14" pattern="\d{14}">
                                    <small class="text-muted d-block mt-1" id="national_id_hint">أدخل 14 رقم فقط للمصريين</small>
                                    @error('national_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3" id="passport_field" style="display: none;">
                                    <label class="form-label"><strong>رقم الهوية/جواز <span class="text-danger">*</span></strong></label>
                                    <input type="text" id="passport_input" name="national_id" class="form-control @error('national_id') is-invalid @enderror"
                                           value="{{ old('national_id', $socialCase->national_id ?? '') }}"
                                           placeholder="رقم جواز السفر أو الهوية">
                                    @error('national_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>رقم الهاتف</strong></label>
                                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone', $socialCase->phone ?? '') }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
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
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>عدد أفراد الأسرة</strong></label>
                                    <input type="number" id="family_members_count" name="family_members_count" class="form-control @error('family_members_count') is-invalid @enderror"
                                           value="{{ old('family_members_count', $socialCase->family_members_count ?? '') }}" min="0" max="20" onchange="updateFamilyMembersFields()" oninput="updateFamilyMembersFields()">
                                    @error('family_members_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>تابعة لمن</strong></label>
                                    <input type="text" name="affiliated_to" class="form-control @error('affiliated_to') is-invalid @enderror"
                                           value="{{ old('affiliated_to', $socialCase->affiliated_to ?? '') }}">
                                    @error('affiliated_to')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>حالة الحالة</strong></label>
                                    <select name="case_intake_status" class="form-select @error('case_intake_status') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        <option value="searched_by_phone" {{ old('case_intake_status', $socialCase->case_intake_status ?? '') == 'searched_by_phone' ? 'selected' : '' }}>تم البحث بالهاتف</option>
                                        <option value="completed_externally" {{ old('case_intake_status', $socialCase->case_intake_status ?? '') == 'completed_externally' ? 'selected' : '' }}>تم التنفيذ من الخارج</option>
                                        <option value="needs_research" {{ old('case_intake_status', $socialCase->case_intake_status ?? '') == 'needs_research' ? 'selected' : '' }}>تحتاج لبحث</option>
                                    </select>
                                    @error('case_intake_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Section 2: Location Information (Phase 2 only) -->
                        @if(isset($socialCase))
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
                        @endif

                        <!-- Section 2.5: Family Members Details (Phase 2 only) -->
                        @if(isset($socialCase))
                        <div class="mb-4" id="family-members-section" style="display: none;">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-users"></i> بيانات أفراد العائلة
                            </h6>
                            <div id="family-members-container">
                                <!-- Dynamic fields will be inserted here -->
                            </div>
                        </div>

                        <!-- Section 3: Financial Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-wallet"></i> المعلومات المالية
                            </h6>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>الدخل الشهري (ج.م)</strong></label>
                                    <input type="number" id="monthly_income" name="monthly_income" class="form-control @error('monthly_income') is-invalid @enderror"
                                           value="{{ old('monthly_income', $socialCase->monthly_income ?? '') }}" step="0.01" min="0" onchange="checkExpenseWarning()" oninput="checkExpenseWarning()">
                                    @error('monthly_income')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>المصروفات الشهرية (ج.م)</strong></label>
                                    <input type="number" id="monthly_expenses" name="monthly_expenses" class="form-control @error('monthly_expenses') is-invalid @enderror"
                                           value="{{ old('monthly_expenses', $socialCase->monthly_expenses ?? '') }}" step="0.01" min="0" onchange="checkExpenseWarning()" oninput="checkExpenseWarning()">
                                    @error('monthly_expenses')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div id="expense-warning" style="display: none;" class="alert alert-danger mt-2 mb-0">
                                        <i class="fas fa-exclamation-triangle"></i> تحذير: المصروفات أعلى من الدخل!
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>المبلغ المطلوب (ج.م)</strong></label>
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
                        @endif

                        <!-- Section 6: Documents -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-file-upload"></i> المستندات والصور
                            </h6>

                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle"></i> يمكنك رفع صور ومستندات مختلفة للحالة (الطاقة الشخصية، صورة شخصية، بطاقات العائلة، صور البيت، إلخ)
                            </div>

                            <!-- Existing Documents (if editing) -->
                            @if(isset($socialCase) && $socialCase->documents->count() > 0)
                            <div class="mb-4">
                                <h6 style="color: #6366f1; margin-bottom: 15px;">
                                    <i class="fas fa-list"></i> المستندات المرفوعة ({{ $socialCase->documents->count() }})
                                </h6>
                                <div class="row">
                                    @foreach($socialCase->documents as $doc)
                                    <div class="col-md-6 mb-3">
                                        <div class="card border" style="position: relative;">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start gap-2">
                                                    <div style="flex-grow: 1;">
                                                        <h6 class="mb-1">{{ $doc->name }}</h6>
                                                        <small class="text-muted">{{ $doc->file_type ?? 'ملف' }}</small>
                                                    </div>
                                                    <a href="{{ asset('storage/' . $doc->file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            <!-- Document Upload Form -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>وصف المستند</strong></label>
                                    <select id="doc_type_select" class="form-select">
                                        <option value="">اختر نوع المستند</option>
                                        <option value="national_id">الهوية الشخصية</option>
                                        <option value="passport">جواز السفر</option>
                                        <option value="family_book">دفتر العائلة</option>
                                        <option value="birth_certificate">شهادة الميلاد</option>
                                        <option value="marriage_certificate">شهادة الزواج</option>
                                        <option value="divorce_certificate">شهادة الطلاق</option>
                                        <option value="school_certificate">شهادة دراسية</option>
                                        <option value="medical_report">تقرير طبي</option>
                                        <option value="disability_certificate">شهادة إعاقة</option>
                                        <option value="personal_photo">صورة شخصية</option>
                                        <option value="family_photo">صورة عائلية</option>
                                        <option value="house_exterior">صورة البيت من الخارج</option>
                                        <option value="house_interior">صورة البيت من الداخل</option>
                                        <option value="work_certificate">شهادة عمل</option>
                                        <option value="income_certificate">شهادة دخل</option>
                                        <option value="utility_bill">فاتورة كهرباء/ماء</option>
                                        <option value="other">أخرى</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>ملف المستند</strong></label>
                                    <input type="file" id="document_file" class="form-control" accept="image/*,.pdf,.doc,.docx">
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary" onclick="addDocument()">
                                    <i class="fas fa-plus"></i> إضافة مستند
                                </button>
                            </div>

                            <!-- Documents List -->
                            <div id="documents-list" class="row" style="margin-top: 20px;">
                                <!-- Added documents will appear here -->
                            </div>

                            <!-- Hidden input to store documents -->
                            <input type="hidden" id="documents_json" name="documents" value="[]">
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
    // Existing family members data (if editing)
    const existingFamilyMembers = @json(isset($socialCase) && $socialCase->familyMembers ? $socialCase->familyMembers->toArray() : []);

    // Toggle nationality field (Egyptian national_id vs other passport)
    function toggleNationalityField() {
        const nationality = document.querySelector('input[name="nationality"]:checked').value;
        const nationalIdField = document.getElementById('national_id_field');
        const passportField = document.getElementById('passport_field');
        const nationalIdInput = document.getElementById('national_id_input');
        const passportInput = document.getElementById('passport_input');

        if (nationality === 'egyptian') {
            nationalIdField.style.display = 'block';
            passportField.style.display = 'none';
            nationalIdInput.disabled = false;
            passportInput.disabled = true;
            document.getElementById('national_id_hint').textContent = 'أدخل 14 رقم فقط للمصريين';
        } else {
            nationalIdField.style.display = 'none';
            passportField.style.display = 'block';
            nationalIdInput.disabled = true;
            passportInput.disabled = false;
            document.getElementById('national_id_hint').textContent = 'أدخل رقم جواز السفر أو الهوية';
        }
    }

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

    // Update family members fields dynamically
    function updateFamilyMembersFields() {
        const count = parseInt(document.getElementById('family_members_count').value) || 0;
        const container = document.getElementById('family-members-container');
        const section = document.getElementById('family-members-section');

        // Clear existing fields
        container.innerHTML = '';

        if (count === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';

        // Add fields for each family member
        for (let i = 0; i < count; i++) {
            const memberDiv = document.createElement('div');
            memberDiv.className = 'card mb-3';
            memberDiv.style.background = 'rgba(79, 172, 254, 0.05)';
            memberDiv.style.border = '1px solid rgba(79, 172, 254, 0.2)';

            // Get existing member data if available
            const existingMember = existingFamilyMembers[i] || {};
            const memberName = existingMember.name || '';
            const memberRelationship = existingMember.relationship || '';
            const memberGender = existingMember.gender || '';
            const memberPhone = existingMember.phone || '';

            memberDiv.innerHTML = `
                <div class="card-body">
                    <h6 class="mb-3" style="color: #4facfe;">
                        <i class="fas fa-user"></i> الفرد رقم ${i + 1}
                    </h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">الاسم</label>
                            <input type="text" name="family_members[${i}][name]"
                                   class="form-control"
                                   placeholder="اسم الفرد"
                                   value="${memberName}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">صلة القرابة</label>
                            <select name="family_members[${i}][relationship]"
                                    class="form-select">
                                <option value="">-- اختر --</option>
                                <option value="زوج" ${memberRelationship === 'زوج' ? 'selected' : ''}>زوج</option>
                                <option value="زوجة" ${memberRelationship === 'زوجة' ? 'selected' : ''}>زوجة</option>
                                <option value="ابن" ${memberRelationship === 'ابن' ? 'selected' : ''}>ابن</option>
                                <option value="ابنة" ${memberRelationship === 'ابنة' ? 'selected' : ''}>ابنة</option>
                                <option value="أب" ${memberRelationship === 'أب' ? 'selected' : ''}>أب</option>
                                <option value="أم" ${memberRelationship === 'أم' ? 'selected' : ''}>أم</option>
                                <option value="أخ" ${memberRelationship === 'أخ' ? 'selected' : ''}>أخ</option>
                                <option value="أخت" ${memberRelationship === 'أخت' ? 'selected' : ''}>أخت</option>
                                <option value="جد" ${memberRelationship === 'جد' ? 'selected' : ''}>جد</option>
                                <option value="جدة" ${memberRelationship === 'جدة' ? 'selected' : ''}>جدة</option>
                                <option value="عم" ${memberRelationship === 'عم' ? 'selected' : ''}>عم</option>
                                <option value="عمة" ${memberRelationship === 'عمة' ? 'selected' : ''}>عمة</option>
                                <option value="خال" ${memberRelationship === 'خال' ? 'selected' : ''}>خال</option>
                                <option value="خالة" ${memberRelationship === 'خالة' ? 'selected' : ''}>خالة</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">النوع</label>
                            <select name="family_members[${i}][gender]"
                                    class="form-select">
                                <option value="">-- اختر --</option>
                                <option value="male" ${memberGender === 'male' ? 'selected' : ''}>ذكر</option>
                                <option value="female" ${memberGender === 'female' ? 'selected' : ''}>أنثى</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" name="family_members[${i}][phone]"
                                   class="form-control"
                                   placeholder="اختياري"
                                   value="${memberPhone}">
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(memberDiv);
        }
    }

    // Check expense warning
    function checkExpenseWarning() {
        const incomeField = document.getElementById('monthly_income');
        const expensesField = document.getElementById('monthly_expenses');
        const warning = document.getElementById('expense-warning');

        if (!incomeField || !expensesField) return;

        const income = parseFloat(incomeField.value) || 0;
        const expenses = parseFloat(expensesField.value) || 0;

        if (expenses > income && income > 0) {
            warning.style.display = 'block';
            expensesField.classList.add('border-danger');
        } else {
            warning.style.display = 'none';
            expensesField.classList.remove('border-danger');
        }
    }

    // Documents management
    let uploadedDocuments = [];

    function addDocument() {
        const docTypeSelect = document.getElementById('doc_type_select');
        const fileInput = document.getElementById('document_file');

        if (!docTypeSelect.value) {
            alert('يرجى اختيار نوع المستند');
            return;
        }

        if (!fileInput.files[0]) {
            alert('يرجى اختيار ملف');
            return;
        }

        const file = fileInput.files[0];
        const docType = docTypeSelect.value;
        const docTypeText = docTypeSelect.options[docTypeSelect.selectedIndex].text;

        // Create a FileReader to read the file
        const reader = new FileReader();
        reader.onload = function(e) {
            uploadedDocuments.push({
                name: docTypeText,
                type: docType,
                file: file,
                fileData: e.target.result // Base64 encoded file
            });

            // Update the display
            renderDocuments();

            // Reset the form
            docTypeSelect.value = '';
            fileInput.value = '';
        };
        reader.readAsDataURL(file);
    }

    function renderDocuments() {
        const container = document.getElementById('documents-list');
        container.innerHTML = '';

        uploadedDocuments.forEach((doc, index) => {
            const docDiv = document.createElement('div');
            docDiv.className = 'col-md-6 mb-3';
            docDiv.innerHTML = `
                <div class="card border border-success" style="background: rgba(34, 197, 94, 0.05);">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div style="flex-grow: 1;">
                                <h6 class="mb-1" style="color: #22c55e;">
                                    <i class="fas fa-file-alt"></i> ${doc.name}
                                </h6>
                                <small class="text-muted">${doc.file.name}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeDocument(${index})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(docDiv);
        });

        // Update hidden input with documents data
        const documentsData = uploadedDocuments.map(doc => ({
            name: doc.name,
            type: doc.type,
            file: doc.fileData,
            fileName: doc.file.name
        }));
        document.getElementById('documents_json').value = JSON.stringify(documentsData);
    }

    function removeDocument(index) {
        uploadedDocuments.splice(index, 1);
        renderDocuments();
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're in Phase 2 edit mode
        if (document.getElementById('nationality_egyptian')) {
            toggleNationalityField();
        }

        toggleDisabilityField();
        toggleOtherAssistance();
        updateFamilyMembersFields();
        checkExpenseWarning();
    });
</script>

@endsection
