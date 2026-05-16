@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-hand-holding-usd"></i> عهداتي
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        متابعة جميع عهداتك وحركاتها
                    </p>
                </div>
                <a href="{{ route('custodies.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> طلب عهدة جديدة
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-list text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">إجمالي العهدات</div>
                            <h4 class="mb-0">{{ $stats['total_custodies'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);">
                                <i class="fas fa-check-circle text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">العهدات النشطة</div>
                            <h4 class="mb-0">{{ $stats['active_custodies'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fas fa-clock text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">في الانتظار</div>
                            <h4 class="mb-0">{{ $stats['pending_custodies'] }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                <i class="fas fa-wallet text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">الرصيد المتبقي</div>
                            <h4 class="mb-0">{{ number_format($stats['total_remaining'], 0) }} ج.م</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                <i class="fas fa-arrow-right text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">تحويلات معلقة (مُرسلة)</div>
                            <h4 class="mb-0">{{ $stats['pending_transfers_sent'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%);">
                                <i class="fas fa-arrow-left text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">تحويلات معلقة (مستقبلة)</div>
                            <h4 class="mb-0">{{ $stats['pending_transfers_received'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Custodies List -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; display: flex; justify-content: space-between; align-items: center;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> جميع العهدات
                    </h5>
                    @if($myCustodies->whereIn('status', ['accepted', 'active'])->isNotEmpty())
                    <div class="btn-group" role="group" style="gap: 0.5rem;">
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#quickDonationModal" title="إضافة تبرع خارجي سريع">
                            <i class="fas fa-gift"></i> تبرع خارجي
                        </button>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#quickRefundModal" title="استرجاع أموال سريع">
                            <i class="fas fa-undo"></i> استرجاع للخزينة
                        </button>
                    </div>
                    @endif
                </div>
                <div class="card-body">
                    @if($myCustodies->isEmpty())
                        <div class="text-center py-5">
                            <i class="fas fa-inbox text-muted" style="font-size: 4rem;"></i>
                            <p class="text-muted mt-3">لا توجد عهدات حتى الآن</p>
                            <a href="{{ route('custodies.create') }}" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> طلب عهدة جديدة
                            </a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>التاريخ</th>
                                        <th>المبلغ الأصلي</th>
                                        <th>المصروف</th>
                                        <th>المرتجع</th>
                                        <th>المتبقي</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($myCustodies as $custody)
                                    <tr>
                                        <td>{{ $custody->id }}</td>
                                        <td>{{ $custody->created_at->format('Y-m-d') }}</td>
                                        <td>{{ number_format($custody->amount, 2) }} ج.م</td>
                                        <td>
                                            <span class="badge bg-danger">
                                                {{ number_format($custody->spent, 2) }} ج.م
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                {{ number_format($custody->returned, 2) }} ج.م
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">
                                                {{ number_format($custody->getRemainingBalance(), 2) }} ج.م
                                            </span>
                                        </td>
                                        <td>
                                            @switch($custody->status)
                                                @case('pending')
                                                    @if($custody->initiated_by === 'agent')
                                                        <span class="badge bg-warning">طلب قيد الانتظار</span>
                                                    @else
                                                        <span class="badge bg-warning">في انتظار قبولك</span>
                                                    @endif
                                                    @break
                                                @case('accepted')
                                                    @if($custody->initiated_by === 'agent')
                                                        <span class="badge bg-info">تمت الموافقة</span>
                                                    @else
                                                        <span class="badge bg-success">نشطة</span>
                                                    @endif
                                                    @break
                                                @case('active')
                                                    <span class="badge bg-success">نشطة</span>
                                                    @break
                                                @case('rejected')
                                                    <span class="badge bg-danger">مرفوضة</span>
                                                    @break
                                                @case('partially_returned')
                                                    <span class="badge bg-info">مرتجع جزئياً</span>
                                                    @break
                                                @case('closed')
                                                    <span class="badge bg-secondary">مغلقة</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('custodies.show', $custody->id) }}"
                                                   class="btn btn-sm btn-outline-primary"
                                                   title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-info"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#transactionsModal{{ $custody->id }}"
                                                        title="عرض الحركات">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                @if($custody->status === 'accepted' || $custody->status === 'active')
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-success"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#donationModal{{ $custody->id }}"
                                                            title="تبرع خارجي">
                                                        <i class="fas fa-gift"></i>
                                                    </button>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-warning"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#refundModal{{ $custody->id }}"
                                                            title="استرجاع للخزينة">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Sent Transfers (معلقة) -->
    @if($sentTransfers->isNotEmpty())
    <div class="row mt-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-arrow-right"></i> التحويلات المرسلة (معلقة الاستقبال)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العهدة</th>
                                    <th>المُرسل إليه</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sentTransfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->id }}</td>
                                    <td>
                                        <a href="{{ route('custodies.show', $transfer->custody_id) }}" class="badge bg-primary">
                                            #{{ $transfer->custody_id }}
                                        </a>
                                    </td>
                                    <td>{{ $transfer->toAgent->name }}</td>
                                    <td><strong>{{ number_format($transfer->amount, 2) }} ج.م</strong></td>
                                    <td>
                                        @if($transfer->status === 'pending')
                                            <span class="badge bg-warning">قيد الانتظار</span>
                                        @elseif($transfer->status === 'approved')
                                            <span class="badge bg-success">تم الاستقبال</span>
                                        @else
                                            <span class="badge bg-danger">مرفوض</span>
                                        @endif
                                    </td>
                                    <td>{{ $transfer->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Received Transfers (معلقة) -->
    @if($receivedTransfers->isNotEmpty())
    <div class="row mt-4" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #ec4899 0%, #db2777 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-arrow-left"></i> التحويلات المستقبلة (بانتظار الموافقة)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العهدة</th>
                                    <th>المُرسل من</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($receivedTransfers as $transfer)
                                <tr>
                                    <td>{{ $transfer->id }}</td>
                                    <td>
                                        <a href="{{ route('custodies.show', $transfer->custody_id) }}" class="badge bg-primary">
                                            #{{ $transfer->custody_id }}
                                        </a>
                                    </td>
                                    <td>{{ $transfer->fromAgent->name }}</td>
                                    <td><strong>{{ number_format($transfer->amount, 2) }} ج.م</strong></td>
                                    <td>{{ $transfer->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-success" onclick="approveCustodyTransfer({{ $transfer->id }})">
                                            <i class="fas fa-check"></i> قبول
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectCustodyTransfer({{ $transfer->id }})">
                                            <i class="fas fa-times"></i> رفض
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Modals for transactions -->
@foreach($myCustodies as $custody)
<div class="modal fade" id="transactionsModal{{ $custody->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-history"></i> حركات العهدة #{{ $custody->id }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المبلغ الأصلي</div>
                            <h5 class="mb-0">{{ number_format($custody->amount, 2) }} ج.م</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المصروف</div>
                            <h5 class="mb-0 text-danger">{{ number_format($custody->spent, 2) }} ج.م</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المرتجع</div>
                            <h5 class="mb-0 text-success">{{ number_format($custody->returned, 2) }} ج.م</h5>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 border rounded">
                            <div class="text-muted small">المتبقي</div>
                            <h5 class="mb-0 text-primary">{{ number_format($custody->getRemainingBalance(), 2) }} ج.م</h5>
                        </div>
                    </div>
                </div>

                <!-- Transactions Timeline -->
                <h6 class="mb-3"><i class="fas fa-clock"></i> سجل الحركات</h6>

                @if($custody->transactions->isEmpty() && $custody->expenses->isEmpty())
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> لا توجد حركات على هذه العهدة حتى الآن
                    </div>
                @else
                    <div class="timeline">
                        <!-- Custody created -->
                        <div class="timeline-item">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6>إنشاء العهدة</h6>
                                    <small class="text-muted">{{ $custody->created_at->format('Y-m-d H:i') }}</small>
                                </div>
                                <p class="mb-0">تم إنشاء عهدة بقيمة {{ number_format($custody->amount, 2) }} ج.م</p>
                            </div>
                        </div>

                        <!-- Transactions -->
                        @foreach($custody->transactions->sortBy('transaction_date') as $transaction)
                        <div class="timeline-item">
                            <div class="timeline-marker
                                @if($transaction->type === 'custody_out') bg-danger
                                @elseif($transaction->type === 'custody_return') bg-success
                                @else bg-info
                                @endif
                            "></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6>
                                        @if($transaction->type === 'custody_out')
                                            <i class="fas fa-arrow-down text-danger"></i> صرف عهدة
                                        @elseif($transaction->type === 'custody_return')
                                            <i class="fas fa-arrow-up text-success"></i> رد عهدة
                                        @else
                                            <i class="fas fa-exchange-alt text-info"></i> {{ $transaction->type }}
                                        @endif
                                    </h6>
                                    <small class="text-muted">{{ $transaction->transaction_date->format('Y-m-d H:i') }}</small>
                                </div>
                                <p class="mb-0">{{ $transaction->description }}</p>
                                <strong>المبلغ: {{ number_format($transaction->amount, 2) }} ج.م</strong>
                            </div>
                        </div>
                        @endforeach

                        <!-- Expenses -->
                        @foreach($custody->expenses->sortBy('created_at') as $expense)
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <h6><i class="fas fa-shopping-cart text-warning"></i> مصروف</h6>
                                    <small class="text-muted">{{ $expense->created_at->format('Y-m-d H:i') }}</small>
                                </div>
                                <p class="mb-1">{{ $expense->category->name ?? 'غير محدد' }} - {{ $expense->description }}</p>
                                <strong>المبلغ: {{ number_format($expense->amount, 2) }} ج.م</strong>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        </div>
    </div>
</div>
@endforeach

<!-- External Donation Modals -->
@foreach($myCustodies as $custody)
<div class="modal fade" id="donationModal{{ $custody->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-gift"></i> تسجيل تبرع خارجي
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.external-donation', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        <strong>العهدة:</strong> #{{ $custody->id }}<br>
                        <strong>الرصيد الحالي:</strong> {{ number_format($custody->getRemainingBalance(), 2) }} ج.م
                    </p>

                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ (ج.م)</strong></label>
                        <input type="number"
                               name="amount"
                               class="form-control"
                               step="0.01"
                               min="0.01"
                               placeholder="أدخل المبلغ"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>وصف التبرع</strong></label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="مثل: تبرع من المتبرعين، استرجاع من حملة..."
                                  required></textarea>
                    </div>

                    <input type="hidden" name="type" value="external_donation">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> تسجيل التبرع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Refund to Treasury Modals -->
@foreach($myCustodies as $custody)
<div class="modal fade" id="refundModal{{ $custody->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-undo"></i> استرجاع أموال للخزينة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.external-donation', $custody->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        <strong>العهدة:</strong> #{{ $custody->id }}<br>
                        <strong>الرصيد المتاح للاسترجاع:</strong> {{ number_format($custody->getRemainingBalance(), 2) }} ج.م
                    </p>

                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ المسترجع (ج.م)</strong></label>
                        <input type="number"
                               name="amount"
                               class="form-control"
                               step="0.01"
                               min="0.01"
                               max="{{ $custody->getRemainingBalance() }}"
                               placeholder="أدخل المبلغ المراد استرجاعه"
                               required>
                        <small class="text-muted">الحد الأقصى: {{ number_format($custody->getRemainingBalance(), 2) }} ج.م</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>سبب الاسترجاع</strong></label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="مثل: استرجاع أموال غير مستخدمة، انتهاء الحملة..."
                                  required></textarea>
                    </div>

                    <input type="hidden" name="type" value="expense_refund">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> استرجاع الأموال
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

<!-- Quick External Donation Modal (Select Custody) -->
<div class="modal fade" id="quickDonationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-gift"></i> إضافة تبرع خارجي
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickDonationForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>اختر العهدة</strong></label>
                        <select id="donationCustodySelect" class="form-select" required onchange="updateDonationBalance()">
                            <option value="">-- اختر العهدة --</option>
                            @foreach($myCustodies->whereIn('status', ['accepted', 'active']) as $custody)
                                <option value="{{ $custody->id }}" data-balance="{{ $custody->getRemainingBalance() }}">
                                    #{{ $custody->id }} - الرصيد: {{ number_format($custody->getRemainingBalance(), 2) }} ج.م
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-info" id="donationBalanceInfo" style="display: none;">
                        <strong>الرصيد الحالي:</strong> <span id="donationCurrentBalance">0.00</span> ج.م
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ (ج.م)</strong></label>
                        <input type="number"
                               id="donationAmount"
                               name="amount"
                               class="form-control"
                               step="0.01"
                               min="0.01"
                               placeholder="أدخل المبلغ"
                               required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>وصف التبرع</strong></label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="مثل: تبرع من المتبرعين، استرجاع من حملة..."
                                  required></textarea>
                    </div>

                    <input type="hidden" id="donationCustodyId" name="custody_id">
                    <input type="hidden" name="type" value="external_donation">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> تسجيل التبرع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Refund Modal (Select Custody) -->
<div class="modal fade" id="quickRefundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-undo"></i> استرجاع أموال للخزينة
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickRefundForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>اختر العهدة</strong></label>
                        <select id="refundCustodySelect" class="form-select" required onchange="updateRefundBalance()">
                            <option value="">-- اختر العهدة --</option>
                            @foreach($myCustodies->whereIn('status', ['accepted', 'active']) as $custody)
                                <option value="{{ $custody->id }}" data-balance="{{ $custody->getRemainingBalance() }}">
                                    #{{ $custody->id }} - الرصيد: {{ number_format($custody->getRemainingBalance(), 2) }} ج.م
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="alert alert-info" id="refundBalanceInfo" style="display: none;">
                        <strong>الرصيد المتاح:</strong> <span id="refundCurrentBalance">0.00</span> ج.م
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>المبلغ المسترجع (ج.م)</strong></label>
                        <input type="number"
                               id="refundAmount"
                               name="amount"
                               class="form-control"
                               step="0.01"
                               min="0.01"
                               placeholder="أدخل المبلغ المراد استرجاعه"
                               required>
                        <small class="text-muted" id="refundMaxHint">الحد الأقصى: 0.00 ج.م</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>سبب الاسترجاع</strong></label>
                        <textarea name="description"
                                  class="form-control"
                                  rows="3"
                                  placeholder="مثل: استرجاع أموال غير مستخدمة، انتهاء الحملة..."
                                  required></textarea>
                    </div>

                    <input type="hidden" id="refundCustodyId" name="custody_id">
                    <input type="hidden" name="type" value="expense_refund">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> استرجاع الأموال
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDonationBalance() {
    const select = document.getElementById('donationCustodySelect');
    const custodyId = document.getElementById('donationCustodyId');
    const balanceInfo = document.getElementById('donationBalanceInfo');
    const currentBalance = document.getElementById('donationCurrentBalance');

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.dataset.balance) || 0;
        custodyId.value = select.value;
        currentBalance.textContent = balance.toFixed(2);
        balanceInfo.style.display = 'block';
    } else {
        balanceInfo.style.display = 'none';
        custodyId.value = '';
    }
}

function updateRefundBalance() {
    const select = document.getElementById('refundCustodySelect');
    const custodyId = document.getElementById('refundCustodyId');
    const amountInput = document.getElementById('refundAmount');
    const balanceInfo = document.getElementById('refundBalanceInfo');
    const currentBalance = document.getElementById('refundCurrentBalance');
    const maxHint = document.getElementById('refundMaxHint');

    if (select.value) {
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.dataset.balance) || 0;
        custodyId.value = select.value;
        currentBalance.textContent = balance.toFixed(2);
        amountInput.max = balance.toFixed(2);
        maxHint.textContent = `الحد الأقصى: ${balance.toFixed(2)} ج.م`;
        balanceInfo.style.display = 'block';
    } else {
        balanceInfo.style.display = 'none';
        custodyId.value = '';
        amountInput.max = '';
    }
}

// Handle form submission for quick donation
document.getElementById('quickDonationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const custodyId = document.getElementById('donationCustodyId').value;
    if (!custodyId) {
        alert('يرجى اختيار عهدة');
        return;
    }
    this.action = `/custodies/${custodyId}/external-donation`;
    this.submit();
});

// Handle form submission for quick refund
document.getElementById('quickRefundForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const custodyId = document.getElementById('refundCustodyId').value;
    if (!custodyId) {
        alert('يرجى اختيار عهدة');
        return;
    }
    this.action = `/custodies/${custodyId}/external-donation`;
    this.submit();
});
</script>

<style>
    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline::before {
        content: '';
        position: absolute;
        right: 20px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e5e7eb;
    }

    .timeline-item {
        position: relative;
        padding-right: 50px;
        padding-bottom: 20px;
    }

    .timeline-marker {
        position: absolute;
        right: 11px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 0 2px #e5e7eb;
    }

    .timeline-content {
        background: #f9fafb;
        padding: 15px;
        border-radius: 8px;
    }

    .timeline-content h6 {
        margin: 0 0 5px 0;
        font-size: 0.95rem;
    }

    .timeline-content p {
        margin: 0;
        color: #6b7280;
        font-size: 0.9rem;
    }
</style>

<script>
function approveCustodyTransfer(transferId) {
    if (confirm('هل تريد قبول هذا التحويل؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/custody-transfers/${transferId}/approve`;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }
}

function rejectCustodyTransfer(transferId) {
    const reason = prompt('أدخل سبب الرفض:');
    if (reason !== null && reason.trim() !== '') {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/custody-transfers/${transferId}/reject`;

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]').content;
        form.appendChild(csrf);

        const reasonInput = document.createElement('input');
        reasonInput.type = 'hidden';
        reasonInput.name = 'rejection_reason';
        reasonInput.value = reason;
        form.appendChild(reasonInput);

        document.body.appendChild(form);
        form.submit();
    } else if (reason !== null) {
        alert('يرجى إدخال سبب الرفض');
    }
}
</script>
@endsection
