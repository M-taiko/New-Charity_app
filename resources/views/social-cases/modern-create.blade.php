@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-user-tie"></i> إضافة حالة اجتماعية جديدة
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
                        <i class="fas fa-plus-circle"></i> بيانات الحالة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('social_cases.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>اسم الحالة</strong></label>
                                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>رقم الهوية</strong></label>
                                <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror" value="{{ old('national_id') }}">
                                @error('national_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label"><strong>رقم الهاتف</strong></label>
                                <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><strong>نوع المساعدة</strong></label>
                                <select name="assistance_type" class="form-select @error('assistance_type') is-invalid @enderror" required>
                                    <option value="">-- اختر نوع المساعدة --</option>
                                    <option value="cash" {{ old('assistance_type') == 'cash' ? 'selected' : '' }}>مساعدة مالية</option>
                                    <option value="monthly_salary" {{ old('assistance_type') == 'monthly_salary' ? 'selected' : '' }}>راتب شهري</option>
                                    <option value="medicine" {{ old('assistance_type') == 'medicine' ? 'selected' : '' }}>أدوية وعلاج</option>
                                    <option value="treatment" {{ old('assistance_type') == 'treatment' ? 'selected' : '' }}>علاج طبي متخصص</option>
                                    <option value="other" {{ old('assistance_type') == 'other' ? 'selected' : '' }}>أخرى</option>
                                </select>
                                @error('assistance_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Hidden researcher_id field, automatically filled with current user -->
                        <input type="hidden" name="researcher_id" value="{{ auth()->id() }}">

                        <div class="mb-3">
                            <label class="form-label"><strong>الباحث الاجتماعي</strong></label>
                            <div class="form-control" style="background-color: #f5f5f5; border: 1px solid #ddd;">
                                <strong>{{ auth()->user()->name }}</strong>
                                <small style="display: block; color: #6b7280; margin-top: 0.25rem;">تم التعبئة تلقائياً من حسابك الحالي</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>الوصف</strong></label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><strong>رفع ملفات (صور، مستندات، إلخ)</strong></label>
                            <div class="border-2 border-dashed rounded p-4" style="border-color: #4facfe; background: rgba(79, 172, 254, 0.05); cursor: pointer;" id="dropZone">
                                <div class="text-center">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #4facfe; margin-bottom: 1rem;"></i>
                                    <div>
                                        <p style="margin: 0.5rem 0; font-weight: 600; color: #333;">اسحب الملفات هنا أو انقر للاختيار</p>
                                        <small style="color: #6b7280;">يمكنك رفع ملفات متعددة (صور، PDF، وثائق)</small>
                                    </div>
                                </div>
                                <input type="file" name="attachments[]" id="fileInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif,.xlsx,.xls" style="display: none;">
                            </div>
                            <div id="fileList" style="margin-top: 1rem;">
                                <!-- Files will be listed here -->
                            </div>
                            @error('attachments')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-save"></i> حفظ الحالة
                            </button>
                            <a href="{{ route('social_cases.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('fileInput');
            const fileList = document.getElementById('fileList');

            // Click to open file dialog
            dropZone.addEventListener('click', () => fileInput.click());

            // Drag and drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.backgroundColor = 'rgba(79, 172, 254, 0.15)';
                dropZone.style.borderColor = '#2196f3';
            });

            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.backgroundColor = 'rgba(79, 172, 254, 0.05)';
                dropZone.style.borderColor = '#4facfe';
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.style.backgroundColor = 'rgba(79, 172, 254, 0.05)';
                dropZone.style.borderColor = '#4facfe';

                const files = e.dataTransfer.files;
                fileInput.files = files;
                updateFileList();
            });

            // File input change
            fileInput.addEventListener('change', updateFileList);

            function updateFileList() {
                fileList.innerHTML = '';
                const files = fileInput.files;

                if (files.length === 0) {
                    return;
                }

                const ul = document.createElement('ul');
                ul.style.listStyle = 'none';
                ul.style.padding = '0';
                ul.style.margin = '0';

                for (let i = 0; i < files.length; i++) {
                    const li = document.createElement('li');
                    li.style.padding = '0.75rem';
                    li.style.backgroundColor = '#f5f5f5';
                    li.style.marginBottom = '0.5rem';
                    li.style.borderRadius = '4px';
                    li.style.display = 'flex';
                    li.style.alignItems = 'center';
                    li.style.justifyContent = 'space-between';

                    const fileInfo = document.createElement('div');
                    fileInfo.style.display = 'flex';
                    fileInfo.style.alignItems = 'center';
                    fileInfo.style.gap = '0.75rem';
                    fileInfo.style.flex = '1';

                    const icon = document.createElement('i');
                    icon.className = 'fas fa-file';
                    icon.style.color = '#4facfe';
                    icon.style.fontSize = '1rem';

                    const fileName = document.createElement('div');
                    fileName.innerHTML = `<strong>${files[i].name}</strong><br><small style="color: #6b7280;">${(files[i].size / 1024).toFixed(2)} KB</small>`;

                    fileInfo.appendChild(icon);
                    fileInfo.appendChild(fileName);

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    removeBtn.style.background = 'none';
                    removeBtn.style.border = 'none';
                    removeBtn.style.color = '#e53935';
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.style.padding = '0.5rem';

                    removeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const dataTransfer = new DataTransfer();
                        for (let j = 0; j < files.length; j++) {
                            if (i !== j) {
                                dataTransfer.items.add(files[j]);
                            }
                        }
                        fileInput.files = dataTransfer.files;
                        updateFileList();
                    });

                    li.appendChild(fileInfo);
                    li.appendChild(removeBtn);
                    ul.appendChild(li);
                }

                fileList.appendChild(ul);
            }
        </script>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 1px solid rgba(79, 172, 254, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #4facfe;"></i> معلومات مهمة
                    </h6>
                    <ul style="font-size: 0.9rem; line-height: 1.8;">
                        <li>أدخل البيانات الأساسية للحالة بدقة</li>
                        <li>الباحث الاجتماعي: يتم ملؤه تلقائياً من حسابك</li>
                        <li>أضف وصفاً تفصيلياً للحالة</li>
                        <li>يمكنك رفع ملفات الدعم (صور، مستندات)</li>
                        <li>ستكون الحالة في حالة "قيد الانتظار" حتى الموافقة</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
