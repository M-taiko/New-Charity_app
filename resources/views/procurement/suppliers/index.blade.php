@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center">
            <h1 style="margin:0;font-size:2rem;font-weight:700;"><i class="fas fa-truck"></i> الموردون</h1>
        </div>
    </div>

    <div class="row g-4">
        <!-- Add Supplier -->
        <div class="col-lg-4" data-aos="fade-up">
            <div class="card">
                <div class="card-header" style="background:linear-gradient(135deg,#4facfe,#00f2fe);border:none;">
                    <h5 style="margin:0;color:white;"><i class="fas fa-plus"></i> إضافة مورد</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('suppliers.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">اسم المورد <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">رقم الهاتف</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">البريد الإلكتروني</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">العنوان</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-save"></i> حفظ المورد
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Suppliers List -->
        <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header"><h5 style="margin:0;"><i class="fas fa-list"></i> قائمة الموردين</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>الهاتف</th>
                                    <th>البريد</th>
                                    <th>طلبات الشراء</th>
                                    <th>الحالة</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($suppliers as $s)
                                <tr>
                                    <td><strong>{{ $s->name }}</strong>
                                        @if($s->address)<br><small class="text-muted">{{ $s->address }}</small>@endif
                                    </td>
                                    <td style="font-size:.85rem;">{{ $s->phone ?? '—' }}</td>
                                    <td style="font-size:.85rem;">{{ $s->email ?? '—' }}</td>
                                    <td><span class="badge bg-primary">{{ $s->purchase_requests_count }}</span></td>
                                    <td>
                                        @if($s->is_active)
                                            <span class="badge bg-success">نشط</span>
                                        @else
                                            <span class="badge bg-secondary">معطل</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($s->is_active)
                                        <form action="{{ route('suppliers.destroy', $s) }}" method="POST" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('تعطيل هذا المورد؟')">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">لا يوجد موردون بعد</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
