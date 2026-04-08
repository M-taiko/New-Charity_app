@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-bullhorn"></i> الرسائل العاجلة
            </h1>
            <p class="text-muted mt-1">الرسائل العاجلة تظهر كشاشة حاجبة لجميع المستخدمين عند الدخول</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Send New Broadcast -->
        <div class="col-lg-5" data-aos="fade-up">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); border: none;">
                    <h5 style="margin: 0; color: white;"><i class="fas fa-paper-plane"></i> إرسال رسالة عاجلة جديدة</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('broadcasts.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">العنوان <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" placeholder="مثال: إشعار عاجل - توقف الخدمة" required>
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">الرسالة <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror"
                                      rows="4" placeholder="اكتب نص الرسالة هنا..." required>{{ old('message') }}</textarea>
                            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">مستوى التنبيه</label>
                            <select name="level" class="form-select">
                                <option value="info">معلومة (أزرق)</option>
                                <option value="warning" selected>تحذير (برتقالي)</option>
                                <option value="danger">خطر (أحمر)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">تنتهي في (اختياري)</label>
                            <input type="datetime-local" name="expires_at" class="form-control"
                                   value="{{ old('expires_at') }}">
                            <small class="text-muted">اتركها فارغة للرسائل الدائمة حتى يتم إيقافها يدوياً</small>
                        </div>
                        <div class="alert alert-warning" style="font-size: .875rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            إرسال رسالة جديدة سيوقف الرسالة السابقة تلقائياً.
                        </div>
                        <button type="submit" class="btn btn-danger w-100">
                            <i class="fas fa-bullhorn"></i> إرسال الرسالة العاجلة
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;"><i class="fas fa-history"></i> سجل الرسائل السابقة</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>العنوان</th>
                                    <th>المستوى</th>
                                    <th>المرسِل</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($broadcasts as $b)
                                <tr>
                                    <td>
                                        <strong style="font-size: .875rem;">{{ $b->title }}</strong>
                                        <div class="text-muted" style="font-size: .75rem;">{{ Str::limit($b->message, 60) }}</div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $b->level }}">
                                            {{ match($b->level) { 'info' => 'معلومة', 'warning' => 'تحذير', 'danger' => 'خطر', default => $b->level } }}
                                        </span>
                                    </td>
                                    <td style="font-size: .8rem;">{{ $b->creator->name }}</td>
                                    <td>
                                        @if($b->is_active && (!$b->expires_at || $b->expires_at->isFuture()))
                                            <span class="badge bg-success"><i class="fas fa-circle"></i> نشطة</span>
                                        @else
                                            <span class="badge bg-secondary">موقوفة</span>
                                        @endif
                                    </td>
                                    <td style="font-size: .8rem;">{{ $b->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @if($b->is_active)
                                        <form action="{{ route('broadcasts.deactivate', $b) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                    onclick="return confirm('إيقاف الرسالة؟')">
                                                <i class="fas fa-stop"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">لا توجد رسائل سابقة</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($broadcasts->hasPages())
                <div class="card-footer">{{ $broadcasts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
