@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-user"></i> تفاصيل المستخدم
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-info-circle"></i> بيانات المستخدم
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>الاسم:</strong></label>
                            <p>{{ $user->name }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>البريد الإلكتروني:</strong></label>
                            <p>{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهاتف:</strong></label>
                            <p>{{ $user->phone ?? '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>الحالة:</strong></label>
                            <p>
                                @if($user->is_active)
                                    <span class="badge bg-success">نشط</span>
                                @else
                                    <span class="badge bg-danger">معطل</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>الأدوار:</strong></label>
                        <p>
                            @foreach($user->getRoleNames() as $role)
                                <span class="badge bg-info">{{ $role }}</span>
                            @endforeach
                        </p>
                    </div>

                    <div class="d-flex gap-2" style="margin-top: 2rem;">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> رجوع
                        </a>
                        @can('manage_users')
                            <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); border: none;">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(250, 112, 154, 0.1), rgba(254, 225, 64, 0.1)); border: 1px solid rgba(250, 112, 154, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-info-circle" style="color: #fa709a;"></i> معلومات المستخدم
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="mb-3">
                            <strong>معرف المستخدم:</strong><br>
                            <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;">{{ $user->id }}</code>
                        </div>
                        <div class="mb-3">
                            <strong>تاريخ التسجيل:</strong><br>
                            {{ $user->created_at->format('Y-m-d') }}
                        </div>
                        <div class="mb-3">
                            <strong>عدد الأدوار:</strong><br>
                            <span style="font-size: 1.1rem; font-weight: bold; color: #fa709a;">{{ $user->roles->count() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
