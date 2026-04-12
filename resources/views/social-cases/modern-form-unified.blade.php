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
                        <i class="fas fa-form"></i> بيانات الحالة الاجتماعية - النموذج الكامل
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($socialCase) ? route('social_cases.update', $socialCase) : route('social_cases.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @if(isset($socialCase))
                            @method('PUT')
                        @endif

                        <!-- SECTION 1: Basic Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-user-circle"></i> البيانات الأساسية
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>اسم الحالة <span class="text-danger">*</span></strong></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                           value="{{ old('name', $socialCase->name ?? '') }}" required placeholder="اسم الشخص المحتاج">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>رقم الهاتف <span class="text-danger">*</span></strong></label>
                                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                           value="{{ old('phone', $socialCase->phone ?? '') }}" required placeholder="رقم هاتفه/ها">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>تابعة لمن <span class="text-danger">*</span></strong></label>
                                    <input type="text" name="affiliated_to" class="form-control @error('affiliated_to') is-invalid @enderror"
                                           value="{{ old('affiliated_to', $socialCase->affiliated_to ?? '') }}" required placeholder="جمعية / جهة / مدرسة...">
                                    @error('affiliated_to')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>حالة الحالة <span class="text-danger">*</span></strong></label>
                                    <select name="case_intake_status" class="form-select @error('case_intake_status') is-invalid @enderror" required>
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

                        <!-- SECTION 2: Personal Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-id-card"></i> البيانات الشخصية
                            </h6>

                            <div class="row">
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
                            </div>
                        </div>

                        <!-- SECTION 3: Housing Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-map-marker-alt"></i> معلومات السكن
                            </h6>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><strong>العنوان <span class="text-danger">*</span></strong></label>
                                    <textarea name="address" class="form-control @error('address') is-invalid @enderror" rows="2" placeholder="العنوان الكامل">{{ old('address', $socialCase->address ?? '') }}</textarea>
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>نوع السكن <span class="text-danger">*</span></strong></label>
                                    <select name="house_type" class="form-select @error('house_type') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        <option value="owned" {{ old('house_type', $socialCase->house_type ?? '') == 'owned' ? 'selected' : '' }}>ملك خاص</option>
                                        <option value="rented" {{ old('house_type', $socialCase->house_type ?? '') == 'rented' ? 'selected' : '' }}>إيجار</option>
                                        <option value="borrowed" {{ old('house_type', $socialCase->house_type ?? '') == 'borrowed' ? 'selected' : '' }}>معار</option>
                                        <option value="shelter" {{ old('house_type', $socialCase->house_type ?? '') == 'shelter' ? 'selected' : '' }}>ملجأ</option>
                                    </select>
                                    @error('house_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>حالة السكن <span class="text-danger">*</span></strong></label>
                                    <select name="house_condition" class="form-select @error('house_condition') is-invalid @enderror">
                                        <option value="">-- اختر --</option>
                                        <option value="excellent" {{ old('house_condition', $socialCase->house_condition ?? '') == 'excellent' ? 'selected' : '' }}>ممتازة</option>
                                        <option value="good" {{ old('house_condition', $socialCase->house_condition ?? '') == 'good' ? 'selected' : '' }}>جيدة</option>
                                        <option value="fair" {{ old('house_condition', $socialCase->house_condition ?? '') == 'fair' ? 'selected' : '' }}>متوسطة</option>
                                        <option value="poor" {{ old('house_condition', $socialCase->house_condition ?? '') == 'poor' ? 'selected' : '' }}>سيئة</option>
                                    </select>
                                    @error('house_condition')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 4: Financial Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-money-bill-wave"></i> المعلومات المالية
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>الدخل الشهري (ج.م) <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="monthly_income" class="form-control @error('monthly_income') is-invalid @enderror"
                                           value="{{ old('monthly_income', $socialCase->monthly_income ?? '') }}" step="0.01" min="0" placeholder="0.00">
                                    @error('monthly_income')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>مصدر الدخل <span class="text-danger">*</span></strong></label>
                                    <input type="text" name="income_source" class="form-control @error('income_source') is-invalid @enderror"
                                           value="{{ old('income_source', $socialCase->income_source ?? '') }}" placeholder="عمل / معاش / إعانة...">
                                    @error('income_source')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>المصروفات الشهرية (ج.م) <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="monthly_expenses" class="form-control @error('monthly_expenses') is-invalid @enderror"
                                           value="{{ old('monthly_expenses', $socialCase->monthly_expenses ?? '') }}" step="0.01" min="0" placeholder="0.00" onchange="checkExpenseWarning()">
                                    @error('monthly_expenses')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 5: Family Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-users"></i> معلومات الأسرة
                            </h6>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>تكوين الأسرة <span class="text-danger">*</span></strong></label>
                                    <input type="text" name="family_composition" class="form-control @error('family_composition') is-invalid @enderror"
                                           value="{{ old('family_composition', $socialCase->family_composition ?? '') }}" placeholder="مثال: أم + 3 أطفال">
                                    @error('family_composition')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>عدد الأطفال <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="children_count" class="form-control @error('children_count') is-invalid @enderror"
                                           value="{{ old('children_count', $socialCase->children_count ?? '') }}" min="0" placeholder="0">
                                    @error('children_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><strong>عدد الأشخاص ذوي الإعاقة <span class="text-danger">*</span></strong></label>
                                    <input type="number" name="disabled_count" class="form-control @error('disabled_count') is-invalid @enderror"
                                           value="{{ old('disabled_count', $socialCase->disabled_count ?? '') }}" min="0" placeholder="0" onchange="toggleDisabilityField()">
                                    @error('disabled_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row" id="disability_type_field" style="display: none;">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><strong>نوع الإعاقة</strong></label>
                                    <input type="text" name="disability_type" class="form-control @error('disability_type') is-invalid @enderror"
                                           value="{{ old('disability_type', $socialCase->disability_type ?? '') }}" placeholder="حركية / بصرية / سمعية / ذهنية / أخرى">
                                    @error('disability_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 6: Health Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-heartbeat"></i> المعلومات الصحية
                            </h6>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><strong>الحالة الصحية</strong></label>
                                    <textarea name="health_conditions" class="form-control @error('health_conditions') is-invalid @enderror" rows="2" placeholder="أمراض مزمنة، حالات صحية خاصة...">{{ old('health_conditions', $socialCase->health_conditions ?? '') }}</textarea>
                                    @error('health_conditions')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 7: Assistance Information -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-hand-holding-heart"></i> نوع المساعدة المطلوبة
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>نوع المساعدة <span class="text-danger">*</span></strong></label>
                                    <select name="assistance_type" class="form-select @error('assistance_type') is-invalid @enderror" onchange="toggleOtherAssistance()">
                                        <option value="">-- اختر --</option>
                                        <option value="financial" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'financial' ? 'selected' : '' }}>مساعدة مالية</option>
                                        <option value="medical" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'medical' ? 'selected' : '' }}>مساعدة طبية</option>
                                        <option value="food" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'food' ? 'selected' : '' }}>مساعدة غذائية</option>
                                        <option value="education" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'education' ? 'selected' : '' }}>مساعدة تعليمية</option>
                                        <option value="housing" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'housing' ? 'selected' : '' }}>مساعدة سكنية</option>
                                        <option value="other" {{ old('assistance_type', $socialCase->assistance_type ?? '') == 'other' ? 'selected' : '' }}>أخرى</option>
                                    </select>
                                    @error('assistance_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3" id="other_assistance_field" style="display: none;">
                                    <label class="form-label"><strong>نوع المساعدة الأخرى</strong></label>
                                    <input type="text" name="other_assistance" class="form-control @error('other_assistance') is-invalid @enderror"
                                           value="{{ old('other_assistance', $socialCase->other_assistance ?? '') }}" placeholder="حدد نوع المساعدة">
                                    @error('other_assistance')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><strong>سبب المساعدة</strong></label>
                                    <textarea name="assistance_reason" class="form-control @error('assistance_reason') is-invalid @enderror" rows="2" placeholder="شرح تفصيلي لسبب المساعدة">{{ old('assistance_reason', $socialCase->assistance_reason ?? '') }}</textarea>
                                    @error('assistance_reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 8: General Notes -->
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-file-alt"></i> ملاحظات عامة
                            </h6>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label"><strong>الوصف والملاحظات</strong></label>
                                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" placeholder="ملاحظات إضافية عن الحالة">{{ old('description', $socialCase->description ?? '') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 9: Documents (if editing) -->
                        @if(isset($socialCase))
                        <div class="mb-4">
                            <h6 style="color: #4facfe; font-weight: 700; border-bottom: 2px solid #4facfe; padding-bottom: 10px; margin-bottom: 20px;">
                                <i class="fas fa-file-upload"></i> المستندات والصور
                            </h6>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><strong>اختر نوع المستند</strong></label>
                                    <select id="document_type_select" class="form-select">
                                        <option value="">-- اختر --</option>
                                        <option value="national_id">هوية شخصية</option>
                                        <option value="passport">جواز سفر</option>
                                        <option value="family_book">دفتر عائلة</option>
                                        <option value="birth_certificate">شهادة ميلاد</option>
                                        <option value="marriage_certificate">شهادة زواج</option>
                                        <option value="divorce_certificate">شهادة طلاق</option>
                                        <option value="school_certificate">شهادة مدرسية</option>
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
                                    <label class="form-label"><strong>رفع الملف</strong></label>
                                    <input type="file" id="document_file_input" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                </div>
                            </div>

                            <button type="button" class="btn btn-info btn-sm mb-3" onclick="addDocument()">
                                <i class="fas fa-plus"></i> إضافة مستند
                            </button>

                            <input type="hidden" name="documents" id="documents_input" value="[]">

                            <div id="documents_list" class="list-group"></div>
                        </div>
                        @endif

                        <!-- Submit Buttons -->
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-success" style="background: linear-gradient(135deg, #4caf50 0%, #8bc34a 100%); border: none;">
                                <i class="fas fa-save"></i> {{ isset($socialCase) ? 'تحديث البيانات' : 'إضافة الحالة' }}
                            </button>
                            <a href="{{ isset($socialCase) ? route('social_cases.index') : route('social_cases.index') }}" class="btn btn-secondary">
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
    function toggleNationalityField() {
        const egyptian = document.getElementById('nationality_egyptian').checked;
        const nationalIdField = document.getElementById('national_id_field');
        const passportField = document.getElementById('passport_field');
        const idRequired = document.getElementById('id_required');

        if (egyptian) {
            nationalIdField.style.display = 'block';
            passportField.style.display = 'none';
            document.getElementById('national_id_input').required = true;
            document.getElementById('passport_input').required = false;
            idRequired.textContent = '*';
            document.getElementById('national_id_hint').textContent = 'أدخل 14 رقم فقط للمصريين';
        } else {
            nationalIdField.style.display = 'none';
            passportField.style.display = 'block';
            document.getElementById('national_id_input').required = false;
            document.getElementById('passport_input').required = true;
            idRequired.textContent = '';
            document.getElementById('national_id_hint').textContent = '';
        }
    }

    function toggleDisabilityField() {
        const disabledCount = parseInt(document.querySelector('input[name="disabled_count"]').value) || 0;
        const field = document.getElementById('disability_type_field');
        field.style.display = disabledCount > 0 ? 'flex' : 'none';
    }

    function toggleOtherAssistance() {
        const assistanceType = document.querySelector('select[name="assistance_type"]').value;
        const field = document.getElementById('other_assistance_field');
        field.style.display = assistanceType === 'other' ? 'block' : 'none';
    }

    function checkExpenseWarning() {
        const monthlyIncome = parseFloat(document.querySelector('input[name="monthly_income"]').value) || 0;
        const monthlyExpenses = parseFloat(document.querySelector('input[name="monthly_expenses"]').value) || 0;

        if (monthlyExpenses > monthlyIncome) {
            console.warn('تنبيه: المصروفات أكثر من الدخل');
        }
    }

    function addDocument() {
        const typeSelect = document.getElementById('document_type_select');
        const fileInput = document.getElementById('document_file_input');
        const documentType = typeSelect.value;

        if (!documentType || !fileInput.files.length) {
            alert('يرجى اختيار نوع المستند والملف');
            return;
        }

        const file = fileInput.files[0];
        const reader = new FileReader();

        reader.onload = function(e) {
            const documents = JSON.parse(document.getElementById('documents_input').value);
            documents.push({
                name: file.name,
                type: documentType,
                file: e.target.result
            });

            document.getElementById('documents_input').value = JSON.stringify(documents);
            renderDocuments();

            typeSelect.value = '';
            fileInput.value = '';
        };

        reader.readAsDataURL(file);
    }

    function renderDocuments() {
        const documents = JSON.parse(document.getElementById('documents_input').value);
        const list = document.getElementById('documents_list');
        list.innerHTML = '';

        documents.forEach((doc, index) => {
            const item = document.createElement('div');
            item.className = 'list-group-item d-flex align-items-center justify-content-between';
            item.innerHTML = `
                <div>
                    <strong>${doc.name}</strong>
                    <small class="text-muted d-block">${doc.type}</small>
                </div>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeDocument(${index})">
                    <i class="fas fa-trash"></i> حذف
                </button>
            `;
            list.appendChild(item);
        });
    }

    function removeDocument(index) {
        let documents = JSON.parse(document.getElementById('documents_input').value);
        documents.splice(index, 1);
        document.getElementById('documents_input').value = JSON.stringify(documents);
        renderDocuments();
    }

    document.addEventListener('DOMContentLoaded', function() {
        toggleNationalityField();
        toggleDisabilityField();
        toggleOtherAssistance();
        renderDocuments();
    });
</script>

@endsection
