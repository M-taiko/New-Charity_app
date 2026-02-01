@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-cogs"></i> إعدادات النظام
            </h1>
        </div>
    </div>

    <div class="row g-4">
        <!-- Logo Section -->
        <div class="col-12 col-lg-6" data-aos="fade-up">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-image"></i> شعار المؤسسة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data" id="logoForm">
                        @csrf

                        <!-- Logo Preview -->
                        <div class="mb-4">
                            <div class="logo-preview-container" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; border-radius: 12px; text-align: center; min-height: 250px; display: flex; align-items: center; justify-content: center;">
                                @if($settings['logo'])
                                    <div style="position: relative; width: 100%;">
                                        <img id="logoPreview" src="{{ asset('storage/' . $settings['logo']) }}" alt="شعار المؤسسة" style="max-height: 200px; max-width: 100%; object-fit: contain;">
                                        <small class="text-white mt-2 d-block">الشعار الحالي</small>
                                    </div>
                                @else
                                    <div id="logoPreview" style="color: white; text-align: center;">
                                        <i class="fas fa-image" style="font-size: 3rem; opacity: 0.5; display: block; margin-bottom: 10px;"></i>
                                        <p style="margin: 0; opacity: 0.7;">لم يتم تحميل شعار بعد</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Logo Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-600">تحميل شعار جديد</label>
                            <div class="upload-zone" id="logoUploadZone" style="border: 3px dashed #667eea; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8f9ff;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 2.5rem; color: #667eea; display: block; margin-bottom: 10px;"></i>
                                <p style="margin: 0 0 5px 0; color: #667eea; font-weight: 600;">اسحب الصورة هنا أو انقر للاختيار</p>
                                <small style="color: #999;">صيغ مدعومة: PNG, JPG, JPEG, GIF, SVG (أقصى: 2MB)</small>
                                <input type="file" name="logo" id="logoInput" class="form-control" style="display: none;" accept="image/*">
                            </div>
                        </div>

                        <!-- Save Button -->
                        <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px; font-weight: 600;">
                            <i class="fas fa-save"></i> حفظ الشعار
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Organization Info Section -->
        <div class="col-12 col-lg-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-building"></i> معلومات المؤسسة
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-600">اسم المؤسسة</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-hospital"></i>
                                </span>
                                <input type="text" name="organization_name" class="form-control" value="{{ $settings['organization_name'] }}" required style="border-left: none;">
                            </div>
                            @error('organization_name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-600">البريد الإلكتروني</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" name="organization_email" class="form-control" value="{{ $settings['organization_email'] }}" required style="border-left: none;">
                            </div>
                            @error('organization_email')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="alert alert-info" style="background: linear-gradient(135deg, #e0e7ff 0%, #f0e7ff 100%); border: none; border-right: 4px solid #667eea;">
                            <h6 style="margin-bottom: 1rem; color: #667eea;">
                                <i class="fas fa-info-circle"></i> معلومات النظام
                            </h6>
                            <ul style="margin: 0; padding-right: 20px; color: #667eea;">
                                <li>الإصدار: 1.0.0</li>
                                <li>اللغة: العربية (RTL)</li>
                                <li>قاعدة البيانات: MySQL</li>
                                <li>التطبيق: نظام إدارة المؤسسة الخيرية</li>
                            </ul>
                        </div>

                        <button type="submit" class="btn btn-success w-100" style="padding: 12px; font-weight: 600;">
                            <i class="fas fa-save"></i> حفظ المعلومات
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- System Features Section -->
    <div class="row g-4 mt-2" data-aos="fade-up" data-aos-delay="200">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card text-center">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: #667eea; margin-bottom: 15px;">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h6>الأمان</h6>
                    <p class="small text-muted mb-0">نظام أمان متقدم مع تشفير البيانات</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card text-center">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: #764ba2; margin-bottom: 15px;">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h6>واجهة متجاوبة</h6>
                    <p class="small text-muted mb-0">تعمل على جميع الأجهزة</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card text-center">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: #667eea; margin-bottom: 15px;">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h6>تقارير متقدمة</h6>
                    <p class="small text-muted mb-0">تحليلات وإحصائيات شاملة</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card text-center">
                <div class="card-body">
                    <div style="font-size: 2.5rem; color: #764ba2; margin-bottom: 15px;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6>إدارة المستخدمين</h6>
                    <p class="small text-muted mb-0">أدوار وصلاحيات متقدمة</p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const uploadZone = document.getElementById('logoUploadZone');
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');

    // Click to upload
    uploadZone.addEventListener('click', () => logoInput.click());

    // Drag and drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.style.background = '#e0e7ff';
        uploadZone.style.borderColor = '#764ba2';
    });

    uploadZone.addEventListener('dragleave', () => {
        uploadZone.style.background = '#f8f9ff';
        uploadZone.style.borderColor = '#667eea';
    });

    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.style.background = '#f8f9ff';
        uploadZone.style.borderColor = '#667eea';

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            logoInput.files = files;
            handleLogoChange();
        }
    });

    // File input change
    logoInput.addEventListener('change', handleLogoChange);

    function handleLogoChange() {
        const file = logoInput.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                logoPreview.innerHTML = `
                    <img src="${e.target.result}" alt="معاينة الشعار" style="max-height: 200px; max-width: 100%; object-fit: contain;">
                    <small class="text-white mt-2 d-block">معاينة الشعار الجديد</small>
                `;
            };
            reader.readAsDataURL(file);
        }
    }

    // Auto-submit logo form when file is selected
    logoInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('logoForm').submit();
        }
    });
</script>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>
@endpush

@endsection
