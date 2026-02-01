@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-hand-holding-heart"></i> تفاصيل العهدة
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
                        <i class="fas fa-info-circle"></i> بيانات العهدة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>اسم الوكيل:</strong></label>
                            <p>{{ $custody->agent->name ?? '-' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>رقم الهاتف:</strong></label>
                            <p>{{ $custody->agent->phone ?? '-' }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ:</strong></label>
                        <p class="text-primary" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($custody->amount, 2) }} ر.س</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>الملاحظات:</strong></label>
                        <p>{{ $custody->notes ?? '-' }}</p>
                    </div>

                    <hr>

                    @php
                        $actualSpent = $custody->getTotalSpent() - $custody->returned;
                        $remaining = $custody->amount - $actualSpent;
                        $spendingPercent = $custody->amount > 0 ? round(($actualSpent / $custody->amount) * 100) : 0;
                    @endphp

                    <div class="row">
                        <div class="col-md-6">
                            <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-left: 4px solid #667eea; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">المبلغ المصروف الفعلي</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #667eea;">{{ number_format($actualSpent, 2) }} ر.س</h3>
                                <small style="color: #999; font-size: 0.8rem;">الإجمالي: {{ number_format($custody->getTotalSpent(), 2) }} - المردود: {{ number_format($custody->returned, 2) }}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border-left: 4px solid #4caf50; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">المبلغ المتبقي</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #4caf50;">{{ number_format($remaining, 2) }} ر.س</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Spending Percentage -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div style="background: white; border: 1px solid #e5e7eb; padding: 15px; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; font-size: 0.9rem; color: #666;">
                                    <strong>نسبة الإنفاق الفعلية:</strong> {{ $spendingPercent }}%
                                </p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" style="width: {{ $spendingPercent }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($custody->returned > 0)
                    <div class="row mt-3">
                        <div class="col-12">
                            <div style="background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(251, 140, 0, 0.1)); border-left: 4px solid #ff9800; padding: 15px; border-radius: 4px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">المبالغ المردودة للخزينة</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #ff9800;">{{ number_format($custody->returned, 2) }} ر.س</h3>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="d-flex gap-2" style="margin-top: 2rem;">
                        @if(auth()->user()->hasRole('مندوب'))
                            <a href="{{ route('agent.transactions') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> رجوع
                            </a>
                        @else
                            <a href="{{ route('custodies.index') }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> رجوع
                            </a>
                        @endif
                        @can('manage_custodies')
                            <a href="{{ route('custodies.edit', $custody->id) }}" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                        @endcan
                        @can('receive_custody')
                            @if($custody->status === 'pending')
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#acceptModal">
                                    <i class="fas fa-check-circle"></i> قبول العهدة
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle"></i> رفض العهدة
                                </button>
                            @endif
                            @if($custody->status === 'accepted')
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#returnModal">
                                    <i class="fas fa-undo"></i> رد العهدة
                                </button>
                            @endif
                        @endcan

                        @can('approve_custody')
                            @if($custody->status === 'pending_return' && $custody->pending_return > 0)
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#approveReturnModal">
                                    <i class="fas fa-check-circle"></i> الموافقة على الرد
                                </button>
                            @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Status Card -->
            <div class="card mb-3" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-chart-pie" style="color: #667eea;"></i> معلومات الحالة
                    </h6>
                    <div style="font-size: 0.9rem; line-height: 2;">
                        <div class="mb-3">
                            <strong>الحالة:</strong><br>
                            @switch($custody->status)
                                @case('pending')
                                    <span class="badge bg-warning">قيد الانتظار</span>
                                    @break
                                @case('accepted')
                                    <span class="badge bg-success">موافق عليه</span>
                                    @break
                                @case('rejected')
                                    <span class="badge bg-danger">مرفوض</span>
                                    @break
                            @endswitch
                        </div>
                        <div class="mb-3">
                            <strong>نسبة الإنفاق:</strong><br>
                            <div class="progress" style="height: 8px; margin-top: 5px;">
                                <div class="progress-bar" style="width: {{ ($custody->getTotalSpent() / $custody->amount) * 100 }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                            </div>
                            <small style="color: #666;">{{ round(($custody->getTotalSpent() / $custody->amount) * 100) }}%</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agent Summary Card (Only for agents) -->
            @if(auth()->user()->hasRole('مندوب') && auth()->user()->id === $custody->agent_id)
                <div class="card" style="background: linear-gradient(135deg, #fff5e1 0%, #ffe0b2 100%); border: 1px solid #ffcc80;">
                    <div class="card-body">
                        <h6 class="card-title mb-3">
                            <i class="fas fa-briefcase" style="color: #f57c00;"></i> ملخص عهدتك
                        </h6>
                        <div style="font-size: 0.9rem;">
                            <!-- Total Custody -->
                            <div class="mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">إجمالي العهدة</p>
                                <h4 style="margin: 0.5rem 0 0; color: #f57c00; font-weight: 700;">{{ number_format($custody->amount, 2) }} ر.س</h4>
                            </div>

                            <!-- Total Expenses -->
                            <div class="mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">إجمالي المصروفات</p>
                                <h4 style="margin: 0.5rem 0 0; color: #e53935; font-weight: 700;">{{ number_format($custody->getTotalSpent(), 2) }} ر.س</h4>
                            </div>

                            <!-- Remaining Balance -->
                            <div class="mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">المبلغ المتبقي</p>
                                <h4 style="margin: 0.5rem 0 0; color: #43a047; font-weight: 700;">{{ number_format($custody->amount - $custody->getTotalSpent(), 2) }} ر.س</h4>
                            </div>

                            <!-- Spending Percentage -->
                            <div>
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">نسبة الإنفاق</p>
                                <div style="margin-top: 0.5rem;">
                                    <div class="progress" style="height: 10px; margin-bottom: 0.5rem;">
                                        @php
                                            $spendingPercentage = ($custody->getTotalSpent() / $custody->amount) * 100;
                                        @endphp
                                        <div class="progress-bar" style="width: {{ $spendingPercentage }}%; background: linear-gradient(135deg, #f57c00 0%, #ff6f00 100%);"></div>
                                    </div>
                                    <p style="margin: 0; font-weight: bold; color: #f57c00;">{{ round($spendingPercentage) }}%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Accept Custody Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-check-circle"></i> قبول العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.accept', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> هل تريد قبول هذه العهدة؟
                    </div>
                    <p><strong>الوكيل:</strong> {{ $custody->agent->name }}</p>
                    <p><strong>المبلغ:</strong> {{ number_format($custody->amount, 2) }} ر.س</p>
                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-arrow-left"></i> عند القبول، سيتم:
                    </p>
                    <ul style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <li>خصم المبلغ من رصيد الخزينة</li>
                        <li>تحويل العهدة لحساب الوكيل</li>
                        <li>إرسال إخطار للمحاسب</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> تأكيد القبول
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Custody Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #ff6b6b 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-times-circle"></i> رفض العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.reject', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> هل تريد رفض هذه العهدة؟
                    </div>
                    <p><strong>الوكيل:</strong> {{ $custody->agent->name }}</p>
                    <p><strong>المبلغ:</strong> {{ number_format($custody->amount, 2) }} ر.س</p>
                    <div class="mb-3">
                        <label class="form-label"><strong>سبب الرفض</strong></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="أدخل سبب الرفض..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> تأكيد الرفض
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Return Custody Modal -->
<div class="modal fade" id="returnModal" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" style="z-index: 1070;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%); border: none;">
                <h5 class="modal-title" style="color: white;" id="returnModalLabel"><i class="fas fa-undo"></i> رد العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('custodies.return', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> أدخل المبلغ المراد رده من العهدة
                    </div>
                    <p><strong>الوكيل:</strong> {{ $custody->agent->name }}</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>إجمالي العهدة:</strong><br>
                            <span style="color: #667eea; font-weight: bold;">{{ number_format($custody->amount, 2) }} ر.س</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>تم صرفه:</strong><br>
                            <span style="color: #e53935; font-weight: bold;">{{ number_format($custody->getTotalSpent(), 2) }} ر.س</span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p><strong>المتبقي من العهدة:</strong><br>
                        <span style="color: #4caf50; font-weight: bold; font-size: 1.1rem;">{{ number_format($custody->amount - $custody->getTotalSpent(), 2) }} ر.س</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ المراد رده</strong></label>
                        <input type="number" name="returned_amount" class="form-control" step="0.01" min="0.01" max="{{ $custody->amount - $custody->getTotalSpent() }}" required placeholder="أدخل المبلغ...">
                        <small class="text-muted">يجب ألا يتجاوز {{ number_format($custody->amount - $custody->getTotalSpent(), 2) }} ر.س</small>
                    </div>
                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-arrow-left"></i> عند الرد، سيتم:
                    </p>
                    <ul style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <li>إضافة المبلغ إلى رصيد الخزينة</li>
                        <li>تحديث حالة العهدة</li>
                        <li>تسجيل عملية الرد</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-check"></i> تأكيد الرد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Return Modal -->
<div class="modal fade" id="approveReturnModal" tabindex="-1" aria-labelledby="approveReturnModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog" style="z-index: 1070;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%); border: none;">
                <h5 class="modal-title" style="color: white;" id="approveReturnModalLabel"><i class="fas fa-check-double"></i> الموافقة على رد العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('custodies.approveReturn', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> هل تواافق على رد المبلغ المعلق من العهدة؟
                    </div>
                    <p><strong>الوكيل:</strong> {{ $custody->agent->name }}</p>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>إجمالي العهدة:</strong><br>
                            <span style="color: #667eea; font-weight: bold;">{{ number_format($custody->amount, 2) }} ر.س</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>المبلغ المعلق:</strong><br>
                            <span style="color: #ffa726; font-weight: bold; font-size: 1.1rem;">{{ number_format($custody->pending_return, 2) }} ر.س</span></p>
                        </div>
                    </div>
                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-arrow-right"></i> عند الموافقة، سيتم:
                    </p>
                    <ul style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <li>إضافة {{ number_format($custody->pending_return, 2) }} ر.س إلى رصيد الخزينة</li>
                        <li>تحديث حالة العهدة</li>
                        <li>إرسال إشعار للمندوب بقبول الرد</li>
                        <li>تسجيل العملية في السجل</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> الموافقة
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .modal-backdrop {
        display: none !important;
    }

    .modal {
        z-index: 1060 !important;
    }

    .modal.show {
        z-index: 1060 !important;
    }
</style>
@endsection
