@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                    <i class="fas fa-plus-circle"></i> مهمة جديدة
                </h1>
                <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin:0;"><i class="fas fa-edit"></i> تفاصيل المهمة</h5>
                </div>
                <div class="card-body">
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('tasks.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-bold">عنوان المهمة <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="اكتب عنوان المهمة..." required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">الوصف</label>
                            <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                      rows="4" placeholder="وصف تفصيلي للمهمة...">{{ old('description') }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">تعيين إلى <span class="text-danger">*</span></label>
                                <select name="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror" required>
                                    <option value="">-- اختر الموظف --</option>
                                    @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('assigned_to') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                        @php $role = $user->getRoleNames()->first() @endphp
                                        @if($role) ({{ $role }}) @endif
                                    </option>
                                    @endforeach
                                </select>
                                @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">الأولوية <span class="text-danger">*</span></label>
                                <select name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                    <option value="low"    {{ old('priority') == 'low'    ? 'selected' : '' }}>منخفضة</option>
                                    <option value="medium" {{ old('priority','medium') == 'medium' ? 'selected' : '' }}>متوسطة</option>
                                    <option value="high"   {{ old('priority') == 'high'   ? 'selected' : '' }}>عالية</option>
                                </select>
                                @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">تاريخ الاستحقاق</label>
                                <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                       value="{{ old('due_date') }}" min="{{ date('Y-m-d') }}">
                                @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('tasks.index') }}" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> إنشاء المهمة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
