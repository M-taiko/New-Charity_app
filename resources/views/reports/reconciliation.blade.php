@extends('layouts.modern')

@section('content')
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="mb-0">
                <i class="fas fa-balance-scale"></i> تقرير مطابقة الخزينة
            </h2>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-print"></i> طباعة
            </button>
        </div>
    </div>

    <!-- Reconciliation Status Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card {{ $reconciliation['is_reconciled'] ? 'border-success' : 'border-danger' }} shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h5 class="text-muted">الرصيد الفعلي (كل الخزائن)</h5>
                                <h3 class="text-primary">{{ number_format($reconciliation['actual_balance'], 2) }} ج.م</h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h5 class="text-muted">الرصيد المتوقع (المحسوب)</h5>
                                <h3 class="text-info">{{ number_format($reconciliation['expected_balance'], 2) }} ج.م</h3>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h5 class="text-muted">الفرق</h5>
                                <h3 class="{{ $reconciliation['is_reconciled'] ? 'text-success' : 'text-danger' }}">
                                    {{ number_format($reconciliation['difference'], 2) }} ج.م
                                </h3>
                                @if ($reconciliation['is_reconciled'])
                                    <span class="badge bg-success mt-2">
                                        <i class="fas fa-check"></i> متطابق
                                    </span>
                                @else
                                    <span class="badge bg-danger mt-2">
                                        <i class="fas fa-exclamation-triangle"></i> غير متطابق
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Per-Treasury Reconciliation -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-vault"></i> المطابقة لكل خزينة</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الخزينة</th>
                                    <th class="text-end">تبرعات داخلة</th>
                                    <th class="text-end">عهد مصروفة</th>
                                    <th class="text-end">مرتجعات عهد</th>
                                    <th class="text-end">مصاريف مباشرة</th>
                                    <th class="text-end">تحويلات من/إلى خزائن (صافي)</th>
                                    <th class="text-end">الرصيد المتوقع</th>
                                    <th class="text-end">الرصيد الفعلي</th>
                                    <th class="text-end">الفرق</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reconciliation['treasuries'] as $t)
                                    <tr>
                                        <td><strong>{{ $t['treasury']->name }}</strong></td>
                                        <td class="text-end text-success">+{{ number_format($t['donations'], 2) }}</td>
                                        <td class="text-end text-danger">-{{ number_format($t['issued'], 2) }}</td>
                                        <td class="text-end text-success">+{{ number_format($t['returned'], 2) }}</td>
                                        <td class="text-end text-danger">-{{ number_format($t['direct_expenses'], 2) }}</td>
                                        <td class="text-end">{{ number_format($t['transfers_in'] - $t['transfers_out'], 2) }}</td>
                                        <td class="text-end">{{ number_format($t['expected_balance'], 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format($t['actual_balance'], 2) }}</td>
                                        <td class="text-end">
                                            @if ($t['is_reconciled'])
                                                <span class="badge bg-success">متطابقة</span>
                                            @else
                                                <span class="badge bg-danger">{{ number_format($t['difference'], 2) }} ج.م</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>الإجمالي</td>
                                    <td class="text-end">{{ number_format($reconciliation['total_donations'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['total_custodies_issued'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['total_custodies_returned'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['total_direct_expenses'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['total_transfers_in'] - $reconciliation['total_transfers_out'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['expected_balance'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['actual_balance'], 2) }}</td>
                                    <td class="text-end">{{ number_format($reconciliation['difference'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Breakdown -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow mb-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus"></i> المدخلات النقدية</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tbody>
                            <tr>
                                <td>إجمالي التبرعات الداخلة للخزينة</td>
                                <td class="text-end">
                                    <strong>{{ number_format($reconciliation['total_donations'], 2) }} ج.م</strong>
                                </td>
                            </tr>
                            <tr>
                                <td>إجمالي المرتجعات من العهد</td>
                                <td class="text-end">
                                    <strong>{{ number_format($reconciliation['total_custodies_returned'], 2) }} ج.م</strong>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>المجموع</th>
                                <th class="text-end">
                                    {{ number_format($reconciliation['total_donations'] + $reconciliation['total_custodies_returned'], 2) }} ج.م
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow mb-3">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-minus"></i> المخرجات النقدية</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tbody>
                            <tr>
                                <td>إجمالي العهدات الصادرة (للمندوبين)</td>
                                <td class="text-end">
                                    <strong>{{ number_format($reconciliation['total_custodies_issued'], 2) }} ج.م</strong>
                                </td>
                            </tr>
                            <tr>
                                <td>المصروفات المباشرة من الخزينة</td>
                                <td class="text-end">
                                    <strong>{{ number_format($reconciliation['total_direct_expenses'], 2) }} ج.م</strong>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>المجموع</th>
                                <th class="text-end">
                                    {{ number_format($reconciliation['total_custodies_issued'] + $reconciliation['total_direct_expenses'], 2) }} ج.م
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Custody Breakdown -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-wallet"></i> حالة العهدات</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>البيان</th>
                                <th class="text-end">المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>إجمالي العهدات الصادرة</td>
                                <td class="text-end">{{ number_format($reconciliation['total_custodies_issued'], 2) }} ج.م</td>
                            </tr>
                            <tr>
                                <td style="padding-right: 30px">
                                    - تم صرفه من العهد (مصروفات المندوبين)
                                </td>
                                <td class="text-end">{{ number_format($reconciliation['total_custody_expenses'], 2) }} ج.م</td>
                            </tr>
                            <tr>
                                <td style="padding-right: 30px">
                                    - الأرصدة المتبقية مع المندوبين
                                </td>
                                <td class="text-end">{{ number_format($reconciliation['active_custody_balance'], 2) }} ج.م</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Equation -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow bg-light">
                <div class="card-body">
                    <h5 class="mb-3">معادلة المطابقة:</h5>
                    <p class="mb-2">
                        <strong>الرصيد المتوقع = التبرعات الداخلة + المرتجعات + صافي التحويلات من خزائن أخرى − العهد المصروفة − المصاريف المباشرة</strong>
                    </p>
                    <p class="mb-0">
                        <code>
                            {{ number_format($reconciliation['total_donations'], 2) }} +
                            {{ number_format($reconciliation['total_custodies_returned'], 2) }} +
                            {{ number_format($reconciliation['total_transfers_in'] - $reconciliation['total_transfers_out'], 2) }} -
                            {{ number_format($reconciliation['total_custodies_issued'], 2) }} -
                            {{ number_format($reconciliation['total_direct_expenses'], 2) }} =
                            <strong>{{ number_format($reconciliation['expected_balance'], 2) }}</strong> ج.م
                        </code>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow">
                <div class="card-header {{ $reconciliation['is_reconciled'] ? 'bg-success' : 'bg-warning' }} text-white">
                    <h5 class="mb-0">
                        <i class="fas {{ $reconciliation['is_reconciled'] ? 'fa-check-circle' : 'fa-exclamation-circle' }}"></i>
                        النتيجة
                    </h5>
                </div>
                <div class="card-body">
                    @if ($reconciliation['is_reconciled'])
                        <div class="alert alert-success mb-0">
                            <h5 class="alert-heading"><i class="fas fa-check"></i> الخزينة متطابقة!</h5>
                            <p class="mb-0">
                                الرصيد الفعلي في الخزينة يطابق الحسابات المحاسبية تماماً.
                                جميع العمليات تم تسجيلها بشكل صحيح.
                            </p>
                        </div>
                    @else
                        <div class="alert alert-danger mb-0">
                            <h5 class="alert-heading"><i class="fas fa-exclamation"></i> تحذير: عدم تطابق!</h5>
                            <p class="mb-2">
                                هناك فرق بين الرصيد الفعلي والمتوقع قدره:
                                <strong class="text-danger">{{ number_format(abs($reconciliation['difference']), 2) }} ج.م</strong>
                            </p>
                            <p class="mb-0">
                                يرجى مراجعة العمليات المحاسبية والتأكد من صحة تسجيل المعاملات.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .card {
        border-radius: 8px;
    }

    .card-header {
        border-radius: 8px 8px 0 0;
    }

    code {
        background-color: #f5f5f5;
        padding: 10px;
        border-radius: 4px;
        display: block;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>
@endsection
