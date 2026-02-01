@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-12">
            <h1>لوحة التحكم</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="number">{{ number_format($treasury->balance ?? 0, 2) }}</div>
                <div class="label">رصيد الخزينة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="number">{{ $activeCustodies }}</div>
                <div class="label">العهد النشطة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="number">{{ $pendingCases }}</div>
                <div class="label">الحالات المعلقة</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="number">{{ number_format($todayExpenses, 2) }}</div>
                <div class="label">مصروفات اليوم</div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>آخر العمليات</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(\App\Models\TreasuryTransaction::latest()->limit(5)->get() as $transaction)
                                    <tr>
                                        <td>
                                            @switch($transaction->type)
                                                @case('donation')
                                                    <span class="badge bg-success">تبرع</span>
                                                    @break
                                                @case('expense')
                                                    <span class="badge bg-danger">مصروف</span>
                                                    @break
                                                @case('custody_out')
                                                    <span class="badge bg-info">عهدة صرف</span>
                                                    @break
                                                @case('custody_return')
                                                    <span class="badge bg-primary">عهدة إرجاع</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>{{ number_format($transaction->amount, 2) }}</td>
                                        <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">لا توجد عمليات</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>التنبيهات</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @forelse(\App\Models\Notification::where('user_id', auth()->id())->where('is_read', false)->latest()->limit(5)->get() as $notification)
                            <a href="#" class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">{{ $notification->title }}</h6>
                                    <small>{{ $notification->created_at->diffForHumans() }}</small>
                                </div>
                                <p class="mb-1">{{ $notification->message }}</p>
                            </a>
                        @empty
                            <p class="text-muted text-center">لا توجد تنبيهات</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
