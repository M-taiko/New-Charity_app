@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-hand-holding-heart"></i> تفاصيل العهدة
            </h1>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">
                <i class="fas fa-print"></i> طباعة
            </button>
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
                        <p class="text-primary" style="font-size: 1.2rem; font-weight: bold;">{{ number_format($custody->amount, 2) }} ج.م</p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>الملاحظات:</strong></label>
                        <p>{{ $custody->notes ?? '-' }}</p>
                    </div>

                    <hr>

                    @php
                        $totalSpent = $custody->getTotalSpent();
                        $remaining = $custody->getRemainingBalance();
                        $spendingPercent = $custody->amount > 0 ? round(($totalSpent / $custody->amount) * 100) : 0;
                        $returnedPercent = $custody->amount > 0 ? round(($custody->returned / $custody->amount) * 100) : 0;
                    @endphp

                    <div class="row">
                        <div class="col-md-4">
                            <div style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border-left: 4px solid #667eea; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">إجمالي المصروفات</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #667eea;">{{ number_format($totalSpent, 2) }} ج.م</h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background: linear-gradient(135deg, rgba(255, 152, 0, 0.1), rgba(251, 140, 0, 0.1)); border-left: 4px solid #ff9800; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">المبالغ المردودة</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #ff9800;">{{ number_format($custody->returned, 2) }} ج.م</h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border-left: 4px solid #4caf50; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">المبلغ المتبقي</p>
                                <h3 style="margin: 5px 0 0; font-size: 1.5rem; font-weight: bold; color: #4caf50;">{{ number_format($remaining, 2) }} ج.م</h3>
                            </div>
                        </div>
                    </div>

                    <!-- Spending and Return Percentages -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div style="background: white; border: 1px solid #e5e7eb; padding: 15px; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; font-size: 0.9rem; color: #666;">
                                    <strong>نسبة الإنفاق:</strong> {{ $spendingPercent }}%
                                </p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" style="width: {{ $spendingPercent }}%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="background: white; border: 1px solid #e5e7eb; padding: 15px; border-radius: 4px;">
                                <p style="margin: 0 0 10px 0; font-size: 0.9rem; color: #666;">
                                    <strong>نسبة المردود:</strong> {{ $returnedPercent }}%
                                </p>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar" style="width: {{ $returnedPercent }}%; background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2" style="margin-top: 2rem; flex-wrap: wrap;">
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
                        @role('مندوب')
                            @if(auth()->user()->id === $custody->agent_id && $custody->status === 'accepted')
                                <a href="{{ route('custody-transfers.create') }}" class="btn btn-info">
                                    <i class="fas fa-exchange-alt"></i> تحويل إلى مندوب آخر
                                </a>
                            @endif
                            <a href="{{ route('custodies.create') }}" class="btn btn-success">
                                <i class="fas fa-plus-circle"></i> طلب عهدة جديدة
                            </a>
                        @endrole
                        {{-- Workflow 1: Accountant approves agent request --}}
                        @can('approve_custody')
                            @if($custody->status === 'pending' && $custody->initiated_by === 'agent')
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#acceptModal">
                                    <i class="fas fa-check-circle"></i> الموافقة على الطلب
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle"></i> رفض الطلب
                                </button>
                            @elseif($custody->status === 'pending' && $custody->initiated_by === 'accountant')
                                {{-- Auto-approve accountant-created custodies --}}
                                <form action="{{ route('custodies.accept', $custody->id) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="auto_approve" value="1">
                                    <button type="submit" class="btn btn-success" onclick="return confirm('هل تريد الموافقة على هذه العهدة؟')">
                                        <i class="fas fa-check-circle"></i> الموافقة
                                    </button>
                                </form>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="fas fa-times-circle"></i> رفض
                                </button>
                            @endif
                        @endcan

                        {{-- Workflow 2: Agent accepts/rejects accountant-sent custody --}}
                        @role('مندوب')
                            @if(auth()->user()->id === $custody->agent_id && $custody->status === 'pending' && $custody->initiated_by === 'accountant')
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#agentAcceptModal">
                                    <i class="fas fa-check-circle"></i> قبول العهدة
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#agentRejectModal">
                                    <i class="fas fa-times-circle"></i> رفض العهدة
                                </button>
                            @endif

                            {{-- Confirm receipt button for agent-initiated accepted custodies --}}
                            @if(auth()->user()->id === $custody->agent_id && $custody->status === 'accepted' && $custody->initiated_by === 'agent')
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#receiveModal">
                                    <i class="fas fa-hand-holding-usd"></i> تأكيد استقبال العهدة
                                </button>
                            @endif

                            {{-- Transfer and return buttons for active custodies --}}
                            @if(auth()->user()->id === $custody->agent_id && in_array($custody->status, ['accepted', 'active']))
                                @if($custody->status === 'active' || ($custody->status === 'accepted' && $custody->initiated_by === 'accountant'))
                                    <a href="{{ route('custody-transfers.create') }}" class="btn btn-info">
                                        <i class="fas fa-exchange-alt"></i> تحويل إلى مندوب آخر
                                    </a>
                                @endif

                                @if($custody->getRemainingBalance() > 0)
                                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#returnModal">
                                        <i class="fas fa-undo"></i> رد العهدة
                                    </button>
                                @endif
                            @endif
                        @endrole

                        @can('approve_custody')
                            @if($custody->pending_return > 0)
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#approveReturnModal">
                                    <i class="fas fa-check-circle"></i> الموافقة على الرد
                                </button>
                            @endif
                        @endcan

                        @can('manage_treasury')
                            @if(in_array($custody->status, ['active', 'accepted', 'partially_returned']))
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#externalDonationModal">
                                    <i class="fas fa-plus-circle"></i> إضافة تبرع / استرداد
                                </button>
                            @endif
                        @endcan

                        @can('approve_custody')
                            @if(in_array($custody->status, ['active', 'accepted', 'partially_returned']) && $custody->getRemainingBalance() > 0)
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#directReturnModal">
                                    <i class="fas fa-vault"></i> رد مباشر للخزينة
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
                            @php
                                $custodyRemaining = $custody->getRemainingBalance();
                            @endphp
                            @if($custodyRemaining <= 0 && $custody->status === 'accepted')
                                <span class="badge bg-dark">عهدة مستوفاة</span>
                            @else
                                @switch($custody->status)
                                    @case('pending')
                                        <span class="badge bg-warning">قيد الانتظار</span>
                                        @break
                                    @case('accepted')
                                        <span class="badge bg-success">عهدة نشطة</span>
                                        @break
                                    @case('rejected')
                                        <span class="badge bg-danger">مرفوض</span>
                                        @break
                                    @case('pending_return')
                                        <span class="badge bg-info">انتظار موافقة رد العهدة</span>
                                        @break
                                    @case('partially_returned')
                                        <span class="badge bg-primary">تم رد جزء من العهدة</span>
                                        @break
                                    @case('closed')
                                        <span class="badge bg-secondary">عهدة مغلقة</span>
                                        @break
                                    @default
                                        <span class="badge bg-secondary">{{ $custody->status }}</span>
                                @endswitch
                            @endif
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
                                <h4 style="margin: 0.5rem 0 0; color: #f57c00; font-weight: 700;">{{ number_format($custody->amount, 2) }} ج.م</h4>
                            </div>

                            <!-- Total Expenses -->
                            <div class="mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">إجمالي المصروفات</p>
                                <h4 style="margin: 0.5rem 0 0; color: #e53935; font-weight: 700;">{{ number_format($custody->getTotalSpent(), 2) }} ج.م</h4>
                            </div>

                            <!-- Remaining Balance -->
                            <div class="mb-3" style="padding-bottom: 1rem; border-bottom: 1px solid rgba(245, 124, 0, 0.2);">
                                <p style="margin: 0; color: #666; font-size: 0.8rem;">المبلغ المتبقي</p>
                                <h4 style="margin: 0.5rem 0 0; color: #43a047; font-weight: 700;">{{ number_format($custody->getRemainingBalance(), 2) }} ج.م</h4>
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
<div class="modal fade" id="acceptModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-lg" style="z-index: 1070;">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-check-circle"></i> قبول العهدة وتوزيع الأموال</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.accept', $custody->id) }}" method="POST" onsubmit="return validateTreasuryDistribution()">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> حدد من أي خزائن سيتم الصرف والمبالغ من كل خزينة
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>بيانات العهدة:</strong></label>
                        <p><strong>الوكيل:</strong> {{ $custody->agent->name }}</p>
                        <p><strong>المبلغ الإجمالي:</strong> <span class="text-primary fw-bold" style="font-size: 1.1rem;">{{ number_format($custody->amount, 2) }} ج.م</span></p>
                    </div>

                    @php
                        $treasuries = \App\Models\Treasury::all();
                        $requiredAmount = $custody->amount;
                    @endphp

                    <div class="mb-3">
                        <label class="form-label"><strong>توزيع الصرف على الخزائن:</strong></label>
                        <div id="treasuriesDistributionContainer" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
                            @foreach($treasuries as $treasury)
                            <div class="treasury-item mb-3" data-treasury-id="{{ $treasury->id }}" data-balance="{{ $treasury->balance }}">
                                <div class="row align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label mb-2" style="font-weight: 600;">{{ $treasury->name }}</label>
                                        <small class="d-block text-muted mb-2">الرصيد المتاح: <span class="fw-bold text-info">{{ number_format($treasury->balance, 2) }} ج.م</span></small>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="number"
                                               name="treasury_amounts[{{ $treasury->id }}]"
                                               class="form-control treasury-amount"
                                               data-treasury-id="{{ $treasury->id }}"
                                               min="0"
                                               step="0.01"
                                               value="0"
                                               oninput="updateDistributionTotal()"
                                               placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 1px solid rgba(102, 126, 234, 0.3);" id="distributionSummary">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">المبلغ الإجمالي المطلوب:</p>
                                    <h5 style="margin: 5px 0 0; color: #667eea;">{{ number_format($custody->amount, 2) }} ج.م</h5>
                                </div>
                                <div class="col-md-6">
                                    <p style="margin: 0; color: #666; font-size: 0.9rem;">المبلغ المدخل:</p>
                                    <h5 id="totalEnteredAmount" style="margin: 5px 0 0; color: #4caf50;">0.00 ج.م</h5>
                                </div>
                            </div>
                            <div id="distributionStatus" style="margin-top: 15px; padding: 10px; border-radius: 4px; background: white; text-align: center;">
                                <span class="text-warning"><i class="fas fa-exclamation-circle"></i> لم تدخل المبالغ بعد</span>
                            </div>
                        </div>
                    </div>

                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> ملاحظات:
                    </p>
                    <ul style="font-size: 0.85rem; color: #666; margin-top: 0.5rem;">
                        <li>يجب أن يساوي مجموع الصرف المبلغ الإجمالي للعهدة</li>
                        <li>لا يمكن الصرف أكثر من رصيد الخزينة</li>
                        <li>كل صرف سيتم تسجيله بشكل منفصل في السجل</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success" id="acceptSubmitBtn" disabled>
                        <i class="fas fa-check"></i> تأكيد القبول والصرف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDistributionTotal() {
    const inputs = document.querySelectorAll('.treasury-amount');
    let total = 0;
    let hasError = false;
    const requiredAmount = {{ $custody->amount }};
    const submitBtn = document.getElementById('acceptSubmitBtn');
    const statusDiv = document.getElementById('distributionStatus');
    const totalDisplay = document.getElementById('totalEnteredAmount');

    // Calculate total
    inputs.forEach(input => {
        const amount = parseFloat(input.value) || 0;
        const treasuryId = input.dataset.treasuryId;
        const treasuryItem = document.querySelector(`[data-treasury-id="${treasuryId}"]`);
        const balance = parseFloat(treasuryItem.dataset.balance);

        // Check if exceeds treasury balance
        if (amount > balance) {
            input.classList.add('is-invalid');
            hasError = true;
        } else {
            input.classList.remove('is-invalid');
        }

        total += amount;
    });

    totalDisplay.textContent = total.toFixed(2) + ' ج.م';

    // Check if total matches required amount
    if (hasError) {
        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> يوجد خزينة بمبلغ أكبر من رصيدها</span>';
        submitBtn.disabled = true;
    } else if (Math.abs(total - requiredAmount) < 0.01) {
        statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> المبالغ صحيحة وجاهزة للموافقة</span>';
        submitBtn.disabled = false;
    } else if (total < requiredAmount) {
        const remaining = (requiredAmount - total).toFixed(2);
        statusDiv.innerHTML = `<span class="text-warning"><i class="fas fa-exclamation-circle"></i> ينقص ${remaining} ج.م</span>`;
        submitBtn.disabled = true;
    } else {
        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> المبلغ المدخل أكثر من المطلوب</span>';
        submitBtn.disabled = true;
    }
}

function validateTreasuryDistribution() {
    const inputs = document.querySelectorAll('.treasury-amount');
    let total = 0;
    const requiredAmount = {{ $custody->amount }};

    inputs.forEach(input => {
        const amount = parseFloat(input.value) || 0;
        const treasuryId = input.dataset.treasuryId;
        const treasuryItem = document.querySelector(`[data-treasury-id="${treasuryId}"]`);
        const balance = parseFloat(treasuryItem.dataset.balance);

        if (amount > balance) {
            alert(`المبلغ المدخل في خزينة يتجاوز الرصيد المتاح`);
            return false;
        }
        total += amount;
    });

    if (Math.abs(total - requiredAmount) > 0.01) {
        alert(`مجموع المبالغ المدخلة يجب أن يساوي ${requiredAmount.toFixed(2)} ج.م`);
        return false;
    }

    return true;
}

// Initialize on modal open
document.getElementById('acceptModal').addEventListener('shown.bs.modal', function() {
    updateDistributionTotal();
});
</script>

<script>
function updateAcceptTreasuryInfo() {
    const select = document.getElementById('acceptTreasurySelect');
    const infoCard = document.getElementById('treasuryInfoCard');
    const balanceDisplay = document.getElementById('selectedTreasuryBalance');
    const statusMessage = document.getElementById('treasuryStatusMessage');
    const submitBtn = document.getElementById('acceptSubmitBtn');
    const requiredAmount = {{ $custody->amount }};

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.dataset.balance) || 0;

        balanceDisplay.textContent = balance.toFixed(2) + ' ج.م';
        infoCard.style.display = 'block';

        if (balance >= requiredAmount) {
            statusMessage.innerHTML = '<span style="color: #4caf50;"><i class="fas fa-check-circle"></i> رصيد كافي لقبول العهدة</span>';
            submitBtn.disabled = false;
        } else {
            const shortfall = requiredAmount - balance;
            statusMessage.innerHTML = '<span style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> رصيد غير كافي! ينقص ' + shortfall.toFixed(2) + ' ج.م</span>';
            submitBtn.disabled = true;
        }
    } else {
        infoCard.style.display = 'none';
        submitBtn.disabled = true;
    }
}
</script>

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
                    <p><strong>المبلغ:</strong> {{ number_format($custody->amount, 2) }} ج.م</p>
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
                            <span style="color: #667eea; font-weight: bold;">{{ number_format($custody->amount, 2) }} ج.م</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>تم صرفه:</strong><br>
                            <span style="color: #e53935; font-weight: bold;">{{ number_format($custody->getTotalSpent(), 2) }} ج.م</span></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p><strong>المتبقي من العهدة:</strong><br>
                        <span style="color: #4caf50; font-weight: bold; font-size: 1.1rem;">{{ number_format($custody->getRemainingBalance(), 2) }} ج.م</span></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ المراد رده</strong></label>
                        <input type="number" name="returned_amount" class="form-control" step="0.01" min="0.01" max="{{ $custody->getRemainingBalance() }}" required placeholder="أدخل المبلغ...">
                        <small class="text-muted">يجب ألا يتجاوز {{ number_format($custody->getRemainingBalance(), 2) }} ج.م</small>
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
                            <span style="color: #667eea; font-weight: bold;">{{ number_format($custody->amount, 2) }} ج.م</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>المبلغ المعلق:</strong><br>
                            <span style="color: #ffa726; font-weight: bold; font-size: 1.1rem;">{{ number_format($custody->pending_return, 2) }} ج.م</span></p>
                        </div>
                    </div>
                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-arrow-right"></i> عند الموافقة، سيتم:
                    </p>
                    <ul style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <li>إضافة {{ number_format($custody->pending_return, 2) }} ج.م إلى رصيد الخزينة</li>
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

{{-- Agent Accept Modal (Workflow 2) --}}
<div class="modal fade" id="agentAcceptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);">
                <h5 class="modal-title" style="color: white;">قبول العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.agent-accept', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> اختر الخزينة التي ستستقبل العهدة منها
                    </div>
                    <p>العهدة بقيمة <strong>{{ number_format($custody->amount, 2) }} ج.م</strong></p>

                    @php
                        $treasuries = \App\Models\Treasury::all();
                        $requiredAmount = $custody->amount;
                    @endphp

                    <div class="mb-3">
                        <label class="form-label"><strong>اختر الخزينة</strong></label>
                        <select name="treasury_id" id="agentAcceptTreasurySelect" class="form-select @error('treasury_id') is-invalid @enderror"
                                onchange="updateAgentAcceptTreasuryInfo()" required>
                            <option value="">-- اختر خزينة --</option>
                            @foreach($treasuries as $treasury)
                                @php
                                    $isDisabled = $treasury->balance < $requiredAmount;
                                @endphp
                                <option value="{{ $treasury->id }}"
                                        data-balance="{{ $treasury->balance }}"
                                        {{ $isDisabled ? 'disabled' : '' }}>
                                    {{ $treasury->name }}
                                    (الرصيد: {{ number_format($treasury->balance, 2) }} ج.م)
                                    @if($isDisabled)
                                        - غير متوفر
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('treasury_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);" id="agentTreasuryInfoCard" style="display: none;">
                        <div class="card-body">
                            <p style="margin: 0 0 0.5rem 0; color: #666; font-size: 0.9rem;">
                                <strong>رصيد الخزينة المختارة:</strong>
                            </p>
                            <h5 style="margin: 0; color: #4caf50;" id="agentSelectedTreasuryBalance">0.00 ج.م</h5>
                            <small class="text-muted" id="agentTreasuryStatusMessage"></small>
                        </div>
                    </div>

                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> عند القبول، سيتم:
                    </p>
                    <ul style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                        <li>خصم {{ number_format($custody->amount, 2) }} ج.م من الخزينة المختارة</li>
                        <li>استقبال العهدة في حسابك</li>
                        <li>صرف الأموال فوراً</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success" id="agentAcceptSubmitBtn" disabled>
                        <i class="fas fa-check"></i> قبول وصرف
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateAgentAcceptTreasuryInfo() {
    const select = document.getElementById('agentAcceptTreasurySelect');
    const infoCard = document.getElementById('agentTreasuryInfoCard');
    const balanceDisplay = document.getElementById('agentSelectedTreasuryBalance');
    const statusMessage = document.getElementById('agentTreasuryStatusMessage');
    const submitBtn = document.getElementById('agentAcceptSubmitBtn');
    const requiredAmount = {{ $custody->amount }};

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.dataset.balance) || 0;

        balanceDisplay.textContent = balance.toFixed(2) + ' ج.م';
        infoCard.style.display = 'block';

        if (balance >= requiredAmount) {
            statusMessage.innerHTML = '<span style="color: #4caf50;"><i class="fas fa-check-circle"></i> رصيد كافي لقبول العهدة</span>';
            submitBtn.disabled = false;
        } else {
            const shortfall = requiredAmount - balance;
            statusMessage.innerHTML = '<span style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> رصيد غير كافي! ينقص ' + shortfall.toFixed(2) + ' ج.م</span>';
            submitBtn.disabled = true;
        }
    } else {
        infoCard.style.display = 'none';
        submitBtn.disabled = true;
    }
}
</script>

{{-- Agent Reject Modal (Workflow 2) --}}
<div class="modal fade" id="agentRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #ff6b6b 100%);">
                <h5 class="modal-title" style="color: white;">رفض العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.agent-reject', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">السبب (اختياري)</label>
                        <textarea name="reason" class="form-control" placeholder="يمكنك توضيح سبب الرفض..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">رفض</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Agent Receive Modal (Workflow 1) --}}
<div class="modal fade" id="receiveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);">
                <h5 class="modal-title" style="color: white;">تأكيد استقبال العهدة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.receive', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p>هل تؤكد استقبال العهدة بقيمة <strong>{{ number_format($custody->amount, 2) }} ج.م</strong>؟</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> سيتم صرف الفلوس من الخزينة عند التأكيد.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تأكيد الاستقبال</button>
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

{{-- External Donation / Expense Refund Modal --}}
@can('manage_treasury')
<div class="modal fade" id="externalDonationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-plus-circle"></i> إضافة تبرع خارجي / استرداد</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.external-donation', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info" style="font-size:.9rem;">
                        <i class="fas fa-info-circle"></i>
                        المبلغ المضاف سيزيد رصيد عهدة <strong>{{ $custody->agent->name }}</strong> مباشرة دون المرور بالخزينة
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">نوع العملية</label>
                        <select name="type" class="form-select" required>
                            <option value="external_donation">تبرع خارجي</option>
                            <option value="expense_refund">استرداد مصروف</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المبلغ (ج.م) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الوصف / المصدر <span class="text-danger">*</span></label>
                        <input type="text" name="description" class="form-control" required placeholder="مثال: تبرع من جمعية X">
                    </div>
                    <div class="mb-0">
                        <small class="text-muted">رصيد العهدة الحالي: <strong>{{ number_format($custody->amount, 2) }} ج.م</strong></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> إضافة المبلغ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

{{-- Direct Return to Treasury Modal --}}
@can('approve_custody')
<div class="modal fade" id="directReturnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                <h5 class="modal-title" style="color: white;"><i class="fas fa-vault"></i> رد مباشر للخزينة</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.directReturn', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning" style="font-size:.9rem;">
                        <i class="fas fa-exclamation-triangle"></i>
                        سيتم خصم المبلغ من عهدة <strong>{{ $custody->agent->name }}</strong> وإضافته للخزينة مباشرة دون انتظار طلب من المندوب.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المبلغ المراد إرجاعه (ج.م) <span class="text-danger">*</span></label>
                        <input type="number" name="return_amount" class="form-control" step="0.01" min="0.01"
                               max="{{ $custody->getRemainingBalance() }}" required placeholder="0.00">
                        <small class="text-muted mt-1 d-block">
                            الرصيد المتاح: <strong>{{ number_format($custody->getRemainingBalance(), 2) }} ج.م</strong>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('تأكيد رد المبلغ للخزينة مباشرة؟')">
                        <i class="fas fa-vault"></i> تأكيد الرد
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
@endsection
