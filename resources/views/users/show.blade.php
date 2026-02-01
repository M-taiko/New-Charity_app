@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>تفاصيل المستخدم</h5>
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

                    <div class="d-flex gap-2">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">رجوع</a>
                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary">تعديل</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
