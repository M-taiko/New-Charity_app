@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-vault"></i> تفاصيل الخزينة
                    </h1>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-info-circle"></i> حالة الخزينة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>الرصيد الحالي:</strong></label>
                            <p class="text-success" style="font-size: 1.5rem; font-weight: bold;">{{ number_format($treasury->balance, 2) }} ر.س</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>إجمالي الدخل:</strong></label>
                            <p class="text-primary" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($treasury->total_income, 2) }} ر.س</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>إجمالي المصروفات:</strong></label>
                            <p class="text-danger" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($treasury->total_expenses, 2) }} ر.س</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>آخر تحديث:</strong></label>
                            <p>{{ $treasury->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2" style="margin-top: 2rem;">
                        <a href="{{ route('treasury.index') }}" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> رجوع
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-chart-pie" style="color: #667eea;"></i> ملخص الخزينة
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="mb-3">
                            <strong>نسبة الإنفاق:</strong><br>
                            <div class="progress" style="height: 8px; margin-top: 5px;">
                                <div class="progress-bar" style="width: {{ ($treasury->total_expenses / ($treasury->total_income + 0.01)) * 100 }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                            </div>
                            <small style="color: #666;">{{ round(($treasury->total_expenses / ($treasury->total_income + 0.01)) * 100) }}%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
