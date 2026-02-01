@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-user-circle"></i> حسابي الشخصي
            </h1>
            <p class="text-muted mb-0">إدارة معلومات الحساب وكلمة المرور والصورة الشخصية</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Profile Picture Section -->
        <div class="col-12 col-lg-4" data-aos="fade-up">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-image"></i> الصورة الشخصية
                    </h5>
                </div>
                <div class="card-body text-center">
                    <!-- Profile Picture Preview -->
                    <div class="mb-4">
                        <div style="width: 150px; height: 150px; margin: 0 auto; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                            @if($user->profile_picture)
                                <img src="{{ asset('storage/' . $user->profile_picture) }}" alt="صورة {{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <div style="color: white; font-size: 3rem;">
                                    <i class="fas fa-user"></i>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Upload Form -->
                    <form action="{{ route('profile.update-picture') }}" method="POST" enctype="multipart/form-data" id="profilePictureForm">
                        @csrf

                        <div class="mb-4">
                            <div class="upload-zone" id="pictureUploadZone" style="border: 3px dashed #667eea; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #f8f9ff;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #667eea; display: block; margin-bottom: 10px;"></i>
                                <p style="margin: 0 0 5px 0; color: #667eea; font-weight: 600;">اسحب الصورة هنا أو انقر للاختيار</p>
                                <small style="color: #999;">صيغ مدعومة: PNG, JPG, JPEG, GIF (أقصى: 2MB)</small>
                                <input type="file" name="profile_picture" id="pictureInput" class="form-control" style="display: none;" accept="image/*">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 12px; font-weight: 600;">
                            <i class="fas fa-save"></i> حفظ الصورة
                        </button>
                    </form>

                    @error('profile_picture')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Password & Account Info Section -->
        <div class="col-12 col-lg-8" data-aos="fade-up" data-aos-delay="100">
            <!-- Account Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-user"></i> معلومات الحساب
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-600">الاسم</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" value="{{ $user->name }}" disabled style="border-left: none;">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-600">البريد الإلكتروني</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" class="form-control" value="{{ $user->email }}" disabled style="border-left: none;">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-600">الهاتف</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="text" class="form-control" value="{{ $user->phone ?? 'غير محدد' }}" disabled style="border-left: none;">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label fw-600">الدور</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-shield-alt"></i>
                                </span>
                                <input type="text" class="form-control" value="{{ $user->getRoleNames()->first() ?? 'لا يوجد' }}" disabled style="border-left: none;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-lock"></i> تغيير كلمة المرور
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('profile.update-password') }}" method="POST">
                        @csrf

                        <div class="mb-4">
                            <label class="form-label fw-600">كلمة المرور الحالية</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required style="border-left: none;">
                            </div>
                            @error('current_password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-600">كلمة المرور الجديدة</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8" style="border-left: none;">
                            </div>
                            <small class="text-muted d-block mt-1">يجب أن تكون كلمة المرور 8 أحرف على الأقل</small>
                            @error('password')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-600">تأكيد كلمة المرور الجديدة</label>
                            <div class="input-group">
                                <span class="input-group-text" style="background: #f8f9ff; border-right: none;">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" name="password_confirmation" class="form-control" required style="border-left: none;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100" style="padding: 12px; font-weight: 600;">
                            <i class="fas fa-save"></i> تحديث كلمة المرور
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Profile Picture Upload
    const uploadZone = document.getElementById('pictureUploadZone');
    const pictureInput = document.getElementById('pictureInput');

    uploadZone.addEventListener('click', () => pictureInput.click());

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
            pictureInput.files = files;
            document.getElementById('profilePictureForm').submit();
        }
    });

    pictureInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            document.getElementById('profilePictureForm').submit();
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
