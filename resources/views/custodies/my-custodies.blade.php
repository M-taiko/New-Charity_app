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
    </div>

    <!-- Custodies List -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> جميع العهدات
                    </h5>
                </div>
                <div class="card-body">
                    @if($custodies->isEmpty())
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
                                    @foreach($custodies as $custody)
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
</div>

<!-- Modals for transactions -->
@foreach($custodies as $custody)
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
@endsection
