@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-exchange-alt"></i> إنشاء طلب تحويل عهدة
            </h1>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-8" data-aos="fade-up">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-file-alt"></i> نموذج التحويل
                    </h5>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger" role="alert">
                            <strong>خطأ:</strong>
                            <ul class="mb-0" style="padding-right: 20px;">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('custody-transfers.store') }}" method="POST">
                        @csrf

                        <!-- Custody Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-600">
                                <i class="fas fa-hand-holding-usd"></i> اختر العهدة
                            </label>
                            <select name="custody_id" class="form-select @error('custody_id') is-invalid @enderror" required onchange="updateCustodyInfo()">
                                <option value="">-- اختر عهدة --</option>
                                @foreach ($custodies as $custody)
                                    <option value="{{ $custody->id }}" data-remaining="{{ $custody->remaining_balance }}">
                                        عهدة #{{ $custody->id }} - الرصيد المتبقي: {{ $custody->remaining_balance }} ر.س
                                    </option>
                                @endforeach
                            </select>
                            @error('custody_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle"></i> اختر من العهد المقبولة فقط
                            </small>
                        </div>

                        <!-- Receiving Agent Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-600">
                                <i class="fas fa-user-tie"></i> المندوب المستقبل
                            </label>
                            <select name="to_agent_id" class="form-select @error('to_agent_id') is-invalid @enderror" required>
                                <option value="">-- اختر مندوب --</option>
                                @foreach ($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                            @error('to_agent_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Amount Input -->
                        <div class="mb-4">
                            <label class="form-label fw-600">
                                <i class="fas fa-coins"></i> المبلغ
                            </label>
                            <input type="number" name="amount" step="0.01" class="form-control @error('amount') is-invalid @enderror" required placeholder="أدخل المبلغ" value="{{ old('amount') }}">
                            @error('amount')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <small class="text-muted d-block mt-2" id="remaining-info">
                                <i class="fas fa-info-circle"></i> اختر عهدة أولاً
                            </small>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="form-label fw-600">
                                <i class="fas fa-sticky-note"></i> ملاحظات
                            </label>
                            <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="أضف أي ملاحظات هنا...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Submit Buttons -->
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-paper-plane"></i> إرسال الطلب
                            </button>
                            <a href="{{ route('custody-transfers.index') }}" class="btn btn-secondary flex-grow-1">
                                <i class="fas fa-arrow-left"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Help Panel -->
        <div class="col-12 col-lg-4" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-lightbulb"></i> معلومات مهمة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info" style="border: none; border-right: 4px solid #667eea;">
                        <p class="small mb-0">
                            <strong>خطوات التحويل:</strong>
                        </p>
                        <ol class="small" style="padding-right: 20px; margin: 10px 0 0 0;">
                            <li>اختر العهدة التي تريد تحويل جزء منها</li>
                            <li>اختر المندوب المستقبل للتحويل</li>
                            <li>أدخل المبلغ المراد تحويله</li>
                            <li>أضف ملاحظات إن لزم الأمر</li>
                            <li>أرسل الطلب للموافقة</li>
                        </ol>
                    </div>

                    <div class="alert alert-warning" style="border: none; border-right: 4px solid #f59e0b;">
                        <p class="small mb-0">
                            <strong><i class="fas fa-exclamation-triangle"></i> تنبيه:</strong>
                        </p>
                        <p class="small mb-0" style="margin-top: 8px;">
                            المندوب المستقبل يجب أن يوافق على التحويل لكي ينفذ. بعد الموافقة، سيتم خصم المبلغ من عهدتك تلقائياً.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function updateCustodyInfo() {
        const select = document.querySelector('select[name="custody_id"]');
        const selectedOption = select.selectedOptions[0];
        const remainingInfo = document.getElementById('remaining-info');

        if (selectedOption && selectedOption.value) {
            const remaining = selectedOption.getAttribute('data-remaining');
            remainingInfo.innerHTML = `<i class="fas fa-info-circle"></i> الرصيد المتبقي: <strong>${remaining} ر.س</strong>`;
        } else {
            remainingInfo.innerHTML = `<i class="fas fa-info-circle"></i> اختر عهدة أولاً`;
        }
    }
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
