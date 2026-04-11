@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                {{ isset($employee) ? 'تعديل بيانات الموظف' : 'إضافة موظف جديد' }}
            </h1>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8 offset-lg-2">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-user-edit"></i> بيانات الموظف
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ isset($employee) ? route('hr.employees.update', $employee) : route('hr.employees.store') }}" method="POST">
                        @csrf
                        @if(isset($employee))
                            @method('PUT')
                        @endif

                        <div class="mb-3">
                            <label class="form-label"><strong>المستخدم <span class="text-danger">*</span></strong></label>
                            @if(isset($employee))
                                <input type="text" class="form-control" value="{{ $employee->user->name }}" disabled>
                                <input type="hidden" name="user_id" value="{{ $employee->user_id }}">
                            @else
                                <select name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                    <option value="">-- اختر مستخدم --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            @endif
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>القسم <span class="text-danger">*</span></strong></label>
                                <input type="text" name="department" class="form-control @error('department') is-invalid @enderror"
                                       value="{{ old('department', $employee->department ?? '') }}" required>
                                @error('department')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>المسمى الوظيفي <span class="text-danger">*</span></strong></label>
                                <input type="text" name="job_title" class="form-control @error('job_title') is-invalid @enderror"
                                       value="{{ old('job_title', $employee->job_title ?? '') }}" required>
                                @error('job_title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>تاريخ التعيين <span class="text-danger">*</span></strong></label>
                                <input type="date" name="hire_date" class="form-control @error('hire_date') is-invalid @enderror"
                                       value="{{ old('hire_date', $employee->hire_date ?? '') }}" required>
                                @error('hire_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><strong>الراتب (ج.م) <span class="text-danger">*</span></strong></label>
                                <input type="number" step="0.01" name="salary" class="form-control @error('salary') is-invalid @enderror"
                                       value="{{ old('salary', $employee->salary ?? '') }}" required>
                                @error('salary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        @if(isset($employee))
                        <div class="mb-3">
                            <label class="form-label"><strong>الحالة</strong></label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                                <option value="active" {{ $employee->status === 'active' ? 'selected' : '' }}>نشط</option>
                                <option value="inactive" {{ $employee->status === 'inactive' ? 'selected' : '' }}>غير نشط</option>
                                <option value="on_leave" {{ $employee->status === 'on_leave' ? 'selected' : '' }}>في إجازة</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endif

                        <div class="d-flex gap-2" style="margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                                <i class="fas fa-save"></i> {{ isset($employee) ? 'تحديث البيانات' : 'حفظ الموظف' }}
                            </button>
                            <a href="{{ route('hr.employees.index') }}" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
