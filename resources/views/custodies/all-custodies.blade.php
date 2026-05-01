@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-clipboard-list"></i> متابعة جميع العهدات
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        عرض ومتابعة جميع عهدات المندوبين
                    </p>
                </div>
                <a href="{{ route('custodies.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> إنشاء عهدة جديدة
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-list text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">إجمالي العهدات</div>
                            <h5 class="mb-0">{{ $stats['total_custodies'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);">
                                <i class="fas fa-check-circle text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">نشطة</div>
                            <h5 class="mb-0">{{ $stats['active_custodies'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                <i class="fas fa-clock text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">انتظار</div>
                            <h5 class="mb-0">{{ $stats['pending_custodies'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                                <i class="fas fa-times-circle text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">مرفوضة</div>
                            <h5 class="mb-0">{{ $stats['rejected_custodies'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
                                <i class="fas fa-archive text-white" style="font-size: 1.2rem;"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">مغلقة</div>
                            <h5 class="mb-0">{{ $stats['closed_custodies'] }}</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm" id="remainingCard" style="cursor: pointer; transition: all 0.3s ease;" onclick="toggleRemainingBreakdown()">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle p-3" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                                    <i class="fas fa-wallet text-white" style="font-size: 1.2rem;"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="text-muted small">المتبقي</div>
                                <h6 class="mb-0" style="font-size: 0.9rem;">{{ number_format($stats['total_remaining'], 0) }} ج.م</h6>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-chevron-down" id="remainingChevron" style="font-size: 0.9rem; color: #3b82f6;"></i>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 0.7rem;">
                        <i class="fas fa-click"></i> اضغط لتفصيل المندوبين
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #667eea !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">إجمالي المبالغ</h6>
                    <h4 class="mb-0" style="color: #667eea;">{{ number_format($stats['total_amount'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #ef4444 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">إجمالي المصروف</h6>
                    <h4 class="mb-0" style="color: #ef4444;">{{ number_format($stats['total_spent'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #4caf50 !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">إجمالي المرتجع</h6>
                    <h4 class="mb-0" style="color: #4caf50;">{{ number_format($stats['total_returned'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm" style="border-right: 4px solid #f59e0b !important;">
                <div class="card-body">
                    <h6 class="text-muted mb-2">انتظار الرد</h6>
                    <h4 class="mb-0" style="color: #f59e0b;">{{ number_format($stats['pending_returns'], 2) }} ج.م</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Breakdown Summary -->
    <div class="row mb-4" data-aos="fade-up">
        <div class="col-12">
            <h5 class="mb-3">
                <i class="fas fa-chart-pie"></i> تفصيل المبالغ حسب الحالة
            </h5>
        </div>

        <!-- Active Custodies -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;">
                    <h6 style="color: white; margin: 0;">
                        <i class="fas fa-check-circle"></i> النشطة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">المبلغ الكلي</small>
                        <h6 class="mb-0">{{ number_format($stats['active_amount'], 2) }} ج.م</h6>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">المصروف</small>
                        <h6 class="mb-0 text-danger">{{ number_format($stats['active_spent'], 2) }} ج.م</h6>
                    </div>
                    <div class="pt-2 border-top">
                        <small class="text-muted">المتبقي</small>
                        <h6 class="mb-0 text-success">{{ number_format($stats['active_remaining'], 2) }} ج.م</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Custodies -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border: none;">
                    <h6 style="color: white; margin: 0;">
                        <i class="fas fa-clock"></i> المعلقة
                    </h6>
                </div>
                <div class="card-body">
                    <div>
                        <small class="text-muted">المبلغ المعلق</small>
                        <h6 class="mb-0">{{ number_format($stats['pending_amount'], 2) }} ج.م</h6>
                    </div>
                    <p class="mb-0 mt-2 small text-muted">في انتظار الموافقة أو الرد</p>
                </div>
            </div>
        </div>

        <!-- Rejected Custodies -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none;">
                    <h6 style="color: white; margin: 0;">
                        <i class="fas fa-times-circle"></i> المرفوضة
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">المبلغ المرفوض</small>
                        <h6 class="mb-0">{{ number_format($stats['rejected_amount'], 2) }} ج.م</h6>
                    </div>
                    <div>
                        <small class="text-muted">المصروف</small>
                        <h6 class="mb-0 text-danger">{{ number_format($stats['rejected_spent'], 2) }} ج.م</h6>
                    </div>
                </div>
            </div>
        </div>

        <!-- Partially Returned Custodies -->
        <div class="col-md-3 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                    <h6 style="color: white; margin: 0;">
                        <i class="fas fa-undo"></i> المرتجع جزئياً
                    </h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <small class="text-muted">المبلغ الكلي</small>
                        <h6 class="mb-0">{{ number_format($stats['partially_returned_amount'], 2) }} ج.م</h6>
                    </div>
                    <div class="mb-2">
                        <small class="text-muted">المصروف</small>
                        <h6 class="mb-0 text-danger">{{ number_format($stats['partially_returned_spent'], 2) }} ج.م</h6>
                    </div>
                    <div class="pt-2 border-top">
                        <small class="text-muted">المرتجع</small>
                        <h6 class="mb-0 text-success">{{ number_format($stats['partially_returned_returned'], 2) }} ج.م</h6>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agents Remaining Breakdown (Hidden by default) -->
    <div id="remainingBreakdown" style="display: none; margin-bottom: 2rem;" data-aos="fade-up">
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border: none;">
                        <h5 style="color: white; margin: 0;">
                            <i class="fas fa-user-group"></i> تفصيل المتبقي لكل مندوب
                        </h5>
                    </div>
                    <div class="card-body">
                        @if($agentsSummary->isEmpty())
                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle"></i> لا توجد عهدات نشطة حالياً
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="30%">المندوب</th>
                                            <th width="10%" class="text-center">عدد العهدات</th>
                                            <th width="15%" class="text-end">إجمالي المبالغ</th>
                                            <th width="12%" class="text-end">المصروف</th>
                                            <th width="12%" class="text-end">المرتجع</th>
                                            <th width="15%" class="text-end text-success fw-bold">المتبقي</th>
                                            <th width="6%" class="text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($agentsSummary as $item)
                                        <tr style="cursor: pointer;" onclick="toggleAgentDetail({{ $item['agent']->id }})">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar me-2" style="width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                        {{ substr($item['agent']->name, 0, 2) }}
                                                    </div>
                                                    <span>{{ $item['agent']->name }}</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">{{ $item['count'] }}</span>
                                            </td>
                                            <td class="text-end">{{ number_format($item['total_amount'], 2) }} ج.م</td>
                                            <td class="text-end text-danger">{{ number_format($item['total_spent'], 2) }} ج.م</td>
                                            <td class="text-end text-warning">{{ number_format($item['total_returned'], 2) }} ج.م</td>
                                            <td class="text-end text-success fw-bold">{{ number_format($item['total_remaining'], 2) }} ج.م</td>
                                            <td class="text-center">
                                                <i class="fas fa-chevron-down" id="agentChevron{{ $item['agent']->id }}" style="transition: transform 0.3s;"></i>
                                            </td>
                                        </tr>

                                        <!-- Agent Detail Row (Hidden by default) -->
                                        <tr id="agentDetail{{ $item['agent']->id }}" style="display: none;">
                                            <td colspan="7">
                                                <table class="table table-sm table-bordered mb-0" style="background-color: #f8f9fa;">
                                                    <thead style="background-color: #e9ecef;">
                                                        <tr>
                                                            <th width="10%">#</th>
                                                            <th width="15%">تاريخ الإنشاء</th>
                                                            <th width="15%">الحالة</th>
                                                            <th width="15%" class="text-end">المبلغ</th>
                                                            <th width="12%" class="text-end">المصروف</th>
                                                            <th width="12%" class="text-end">المرتجع</th>
                                                            <th width="12%" class="text-end text-success fw-bold">المتبقي</th>
                                                            <th width="9%"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($item['custodies'] as $custody)
                                                        <tr>
                                                            <td><strong>#{{ $custody['id'] }}</strong></td>
                                                            <td>{{ $custody['created_at'] }}</td>
                                                            <td>
                                                                @php
                                                                    $statusLabels = [
                                                                        'pending' => ['label' => 'انتظار', 'class' => 'bg-warning'],
                                                                        'accepted' => ['label' => 'مقبولة', 'class' => 'bg-info'],
                                                                        'active' => ['label' => 'نشطة', 'class' => 'bg-success'],
                                                                        'rejected' => ['label' => 'مرفوضة', 'class' => 'bg-danger'],
                                                                        'partially_returned' => ['label' => 'مرتجع جزئياً', 'class' => 'bg-primary'],
                                                                        'closed' => ['label' => 'مغلقة', 'class' => 'bg-secondary'],
                                                                    ];
                                                                    $status = $statusLabels[$custody['status']] ?? ['label' => $custody['status'], 'class' => 'bg-secondary'];
                                                                @endphp
                                                                <span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span>
                                                            </td>
                                                            <td class="text-end">{{ number_format($custody['amount'], 2) }} ج.م</td>
                                                            <td class="text-end text-danger">{{ number_format($custody['spent'], 2) }} ج.م</td>
                                                            <td class="text-end text-warning">{{ number_format($custody['returned'], 2) }} ج.م</td>
                                                            <td class="text-end text-success fw-bold">{{ number_format($custody['remaining'], 2) }} ج.م</td>
                                                            <td>
                                                                <a href="{{ route('custodies.show', $custody['id']) }}" class="btn btn-sm btn-outline-primary" title="عرض تفاصيل">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
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

    <!-- Filter and Search -->
    <div class="row mb-3" data-aos="fade-up">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="بحث في العهدات...">
            </div>
        </div>
        <div class="col-md-3">
            <select id="agentFilter" class="form-select">
                <option value="">جميع المندوبين</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select id="statusFilter" class="form-select">
                <option value="">جميع الحالات</option>
                <option value="pending">في الانتظار</option>
                <option value="accepted">مقبولة</option>
                <option value="active">نشطة</option>
                <option value="rejected">مرفوضة</option>
                <option value="partially_returned">مرتجع جزئياً</option>
                <option value="closed">مغلقة</option>
            </select>
        </div>
        <div class="col-md-3">
            <div class="form-check form-switch pt-2">
                <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked style="width: 2rem; height: 1rem; cursor: pointer;">
                <label class="form-check-label ms-2" for="autoRefreshToggle" style="cursor: pointer;">
                    <i class="fas fa-sync-alt"></i> التحديث التلقائي
                </label>
            </div>
        </div>
    </div>

    <!-- Auto-refresh status indicator -->
    <div class="row mb-3">
        <div class="col-12">
            <small class="text-muted">
                <i class="fas fa-info-circle"></i>
                <span id="refreshStatus">سيتم التحديث التلقائي كل 15 ثانية</span>
                <span id="lastUpdateTime" style="display: none;"></span>
            </small>
        </div>
    </div>

    <!-- Custodies Table -->
    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-list"></i> جميع العهدات
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="custodiesTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المندوب</th>
                                    <th>التاريخ</th>
                                    <th>المبلغ</th>
                                    <th>المصروف</th>
                                    <th>المرتجع</th>
                                    <th>المتبقي</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($custodies as $custody)
                                <tr data-agent-id="{{ $custody->agent_id }}" data-status="{{ $custody->status }}">
                                    <td>{{ $custody->id }}</td>
                                    <td>
                                        @if($custody->agent)
                                        <div class="d-flex align-items-center">
                                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                {{ substr($custody->agent->name, 0, 2) }}
                                            </div>
                                            <span class="ms-2">{{ $custody->agent->name }}</span>
                                        </div>
                                        @else
                                        <span class="text-muted">-</span>
                                        @endif
                                    </td>
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
                                                <span class="badge bg-warning">
                                                    @if($custody->initiated_by === 'agent')
                                                        طلب انتظار
                                                    @else
                                                        انتظار قبول المندوب
                                                    @endif
                                                </span>
                                                @break
                                            @case('accepted')
                                                <span class="badge bg-info">مقبولة</span>
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
                    <i class="fas fa-history"></i> حركات العهدة #{{ $custody->id }} - {{ $custody->agent?->name ?? '-' }}
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
                                    <small class="text-muted">{{ $custody->created_at->format('Y-m-d') }}</small>
                                </div>
                                <p class="mb-0">تم إنشاء عهدة للمندوب {{ $custody->agent?->name ?? '-' }} بقيمة {{ number_format($custody->amount, 2) }} ج.م</p>
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
                                    <small class="text-muted">{{ $transaction->transaction_date->format('Y-m-d') }}</small>
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
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6><i class="fas fa-shopping-cart text-warning"></i> مصروف</h6>
                                        <small class="text-muted">{{ $expense->expense_date->format('Y-m-d') }}</small>
                                    </div>
                                    @if($expense->attachment)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            onclick="viewExpenseAttachment({{ $expense->id }}, '{{ $expense->attachment }}')"
                                            style="font-size: 0.75rem;">
                                        <i class="fas fa-paperclip"></i> عرض المرفق
                                    </button>
                                    @endif
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const agentFilter = document.getElementById('agentFilter');
    const statusFilter = document.getElementById('statusFilter');
    const autoRefreshToggle = document.getElementById('autoRefreshToggle');
    const table = document.getElementById('custodiesTable');
    const refreshStatus = document.getElementById('refreshStatus');
    const lastUpdateTime = document.getElementById('lastUpdateTime');

    let autoRefreshInterval = null;
    const REFRESH_INTERVAL = 15000; // 15 seconds

    function filterTable() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedAgent = agentFilter.value;
        const selectedStatus = statusFilter.value;
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const agentId = row.dataset.agentId;
            const status = row.dataset.status;

            const matchesSearch = text.includes(searchTerm);
            const matchesAgent = !selectedAgent || agentId === selectedAgent;
            const matchesStatus = !selectedStatus || status === selectedStatus;

            if (matchesSearch && matchesAgent && matchesStatus) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function updateLastUpdateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('ar-EG');
        lastUpdateTime.textContent = ` - آخر تحديث: ${timeString}`;
        lastUpdateTime.style.display = 'inline';
    }

    function refreshTableData() {
        // Preserve current filter values
        const searchTerm = searchInput.value;
        const selectedAgent = agentFilter.value;
        const selectedStatus = statusFilter.value;

        fetch('{{ route("api.custodies.data") }}', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.stats && data.custodies) {
                // Update table rows
                const tbody = table.querySelector('tbody');
                tbody.innerHTML = '';

                data.custodies.forEach(custody => {
                    const statusBadge = getStatusBadge(custody.status, custody.initiated_by);

                    const row = document.createElement('tr');
                    row.dataset.agentId = custody.agent_id;
                    row.dataset.status = custody.status;

                    row.innerHTML = `
                        <td>${custody.id}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    ${custody.agent_name.substring(0, 2)}
                                </div>
                                <span class="ms-2">${custody.agent_name}</span>
                            </div>
                        </td>
                        <td>${custody.created_at}</td>
                        <td>${custody.amount} ج.م</td>
                        <td><span class="badge bg-danger">${custody.spent} ج.م</span></td>
                        <td><span class="badge bg-success">${custody.returned} ج.م</span></td>
                        <td><span class="badge bg-primary">${custody.remaining} ج.م</span></td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="/custodies/${custody.id}" class="btn btn-sm btn-outline-primary" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#transactionsModal${custody.id}" title="عرض الحركات">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                        </td>
                    `;

                    tbody.appendChild(row);
                });

                filterTable(); // Reapply filters after update
                updateLastUpdateTime();

                // Update statistics cards
                updateStatistics(data.stats);
            }
        })
        .catch(error => console.log('Refresh error:', error));
    }

    function getStatusBadge(status, initiatedBy) {
        const badges = {
            'pending': {
                bg: 'bg-warning',
                text: initiatedBy === 'agent' ? 'طلب انتظار' : 'انتظار قبول المندوب'
            },
            'accepted': { bg: 'bg-info', text: 'مقبولة' },
            'active': { bg: 'bg-success', text: 'نشطة' },
            'rejected': { bg: 'bg-danger', text: 'مرفوضة' },
            'partially_returned': { bg: 'bg-info', text: 'مرتجع جزئياً' },
            'closed': { bg: 'bg-secondary', text: 'مغلقة' }
        };

        const badge = badges[status] || { bg: 'bg-secondary', text: status };
        return `<span class="badge ${badge.bg}">${badge.text}</span>`;
    }

    function updateStatistics(stats) {
        // Update main statistics if needed
        // Could be expanded to update stat cards dynamically
    }

    function startAutoRefresh() {
        if (autoRefreshInterval) clearInterval(autoRefreshInterval);

        refreshStatus.textContent = 'سيتم التحديث التلقائي كل 15 ثانية';

        autoRefreshInterval = setInterval(() => {
            refreshTableData();
        }, REFRESH_INTERVAL);
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        refreshStatus.textContent = 'التحديث التلقائي معطل';
        lastUpdateTime.style.display = 'none';
    }

    // Auto-refresh toggle
    autoRefreshToggle.addEventListener('change', function() {
        if (this.checked) {
            startAutoRefresh();
        } else {
            stopAutoRefresh();
        }
    });

    // Initial setup
    searchInput.addEventListener('input', filterTable);
    agentFilter.addEventListener('change', filterTable);
    statusFilter.addEventListener('change', filterTable);

    // Start auto-refresh by default
    startAutoRefresh();
});

// View expense attachment in modal
function viewExpenseAttachment(expenseId, attachment) {
    const extension = attachment.split('.').pop().toLowerCase();
    const isImage = ['jpg', 'jpeg', 'png', 'gif'].includes(extension);
    const isPdf = extension === 'pdf';
    const attachmentUrl = `/storage/app/public/${attachment}`;
    const downloadUrl = `/expenses/${expenseId}/download-attachment`;

    let modalContent = '';

    if (isImage) {
        modalContent = `
            <img src="${attachmentUrl}" alt="Expense Attachment" class="img-fluid" style="max-height: 500px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        `;
    } else if (isPdf) {
        modalContent = `
            <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-file-pdf" style="font-size: 5rem; color: #f5576c; margin-bottom: 1rem;"></i>
                <h5 style="margin-bottom: 1rem;">ملف PDF</h5>
                <p class="text-muted" style="margin-bottom: 1.5rem;">${attachment.split('/').pop()}</p>
                <a href="${downloadUrl}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <i class="fas fa-download"></i> تحميل الملف
                </a>
            </div>
        `;
    } else {
        modalContent = `
            <div style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); padding: 3rem; border-radius: 12px; text-align: center;">
                <i class="fas fa-file-alt" style="font-size: 5rem; color: #667eea; margin-bottom: 1rem;"></i>
                <h5 style="margin-bottom: 1rem;">مستند</h5>
                <p class="text-muted" style="margin-bottom: 1.5rem;">${attachment.split('/').pop()}</p>
                <a href="${downloadUrl}" class="btn btn-primary" target="_blank" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <i class="fas fa-download"></i> تحميل الملف
                </a>
            </div>
        `;
    }

    document.getElementById('expenseAttachmentModalContent').innerHTML = modalContent;
    document.getElementById('expenseAttachmentDownloadBtn').href = downloadUrl;

    var attachmentModal = new bootstrap.Modal(document.getElementById('expenseAttachmentModal'));
    attachmentModal.show();
}
</script>

<!-- Expense Attachment Modal -->
<div class="modal fade" id="expenseAttachmentModal" tabindex="-1" aria-labelledby="expenseAttachmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                <h5 class="modal-title" id="expenseAttachmentModalLabel" style="color: white;">
                    <i class="fas fa-paperclip"></i> مرفق المصروف
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" style="padding: 2rem;" id="expenseAttachmentModalContent">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                <a href="#" id="expenseAttachmentDownloadBtn" class="btn btn-success" target="_blank">
                    <i class="fas fa-download"></i> تحميل
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle remaining breakdown visibility
function toggleRemainingBreakdown() {
    const breakdownEl = document.getElementById('remainingBreakdown');
    const chevronEl = document.getElementById('remainingChevron');
    const cardEl = document.getElementById('remainingCard');

    const isHidden = breakdownEl.style.display === 'none';

    if (isHidden) {
        breakdownEl.style.display = 'block';
        chevronEl.classList.remove('fa-chevron-down');
        chevronEl.classList.add('fa-chevron-up');
        cardEl.style.boxShadow = '0 10px 25px rgba(59, 130, 246, 0.15)';
    } else {
        breakdownEl.style.display = 'none';
        chevronEl.classList.remove('fa-chevron-up');
        chevronEl.classList.add('fa-chevron-down');
        cardEl.style.boxShadow = '';
    }
}

// Toggle agent detail row visibility
function toggleAgentDetail(agentId) {
    const detailEl = document.getElementById('agentDetail' + agentId);
    const chevronEl = document.getElementById('agentChevron' + agentId);

    const isHidden = detailEl.style.display === 'none';

    if (isHidden) {
        detailEl.style.display = 'table-row';
        chevronEl.style.transform = 'rotate(180deg)';
    } else {
        detailEl.style.display = 'none';
        chevronEl.style.transform = 'rotate(0deg)';
    }
}

// Prevent event bubbling for expand/collapse
document.addEventListener('DOMContentLoaded', function() {
    const detailRows = document.querySelectorAll('tr[id^="agentDetail"]');
    detailRows.forEach(row => {
        row.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
});
</script>
@endsection
