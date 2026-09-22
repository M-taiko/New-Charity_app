@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-vault"></i> {{ $treasury->name }}
                    </h1>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('treasury.edit', $treasury) }}" class="btn btn-primary">
                        <i class="fas fa-edit"></i> تعديل
                    </a>
                    @if($treasuries = \App\Models\Treasury::where('id', '!=', $treasury->id)->count() > 0)
                    <a href="{{ route('treasury.transfer') }}" class="btn btn-warning">
                        <i class="fas fa-exchange-alt"></i> تحويل
                    </a>
                    @endif
                    <a href="{{ route('treasury.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> عودة
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">الرصيد الحالي</h6>
                    <h3 style="color: #4caf50; margin: 10px 0 0 0;">{{ number_format($treasury->balance, 2) }} ج.م</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(13, 71, 161, 0.1)); border: 1px solid rgba(33, 150, 243, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">عدد الحركات</h6>
                    <h3 style="color: #2196f3; margin: 10px 0 0 0;">{{ $stats['total_transactions'] }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(139, 195, 74, 0.1)); border: 1px solid rgba(76, 175, 80, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">إجمالي الدخل</h6>
                    <h3 style="color: #4caf50; margin: 10px 0 0 0;">{{ number_format($stats['total_in'], 2) }} ج.م</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card" style="background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(211, 47, 47, 0.1)); border: 1px solid rgba(244, 67, 54, 0.3);">
                <div class="card-body text-center">
                    <h6 style="color: #666; margin: 0;">إجمالي الصرف</h6>
                    <h3 style="color: #f44336; margin: 10px 0 0 0;">{{ number_format($stats['total_out'], 2) }} ج.م</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Treasury Info Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, rgba(33, 150, 243, 0.1), rgba(13, 71, 161, 0.1)); border: none;">
                    <h5 style="margin: 0;">
                        <i class="fas fa-info-circle"></i> معلومات الخزينة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>الاسم:</strong>
                            <p>{{ $treasury->name }}</p>
                        </div>
                        <div class="col-md-4">
                            <strong>تاريخ الإنشاء:</strong>
                            <p>{{ $treasury->created_at->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="col-md-4">
                            <strong>آخر تحديث:</strong>
                            <p>{{ $treasury->updated_at->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>
                    @if($treasury->notes)
                    <div class="row">
                        <div class="col-12">
                            <strong>الملاحظات:</strong>
                            <p>{{ $treasury->notes }}</p>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-exchange-alt"></i> حركات الخزينة
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>التاريخ</th>
                                    <th>النوع</th>
                                    <th>الوصف</th>
                                    <th>المبلغ</th>
                                    <th>المصدر/الملاحظات</th>
                                    <th>المستخدم</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $index => $transaction)
                                <tr>
                                    <td>{{ $transactions->currentPage() * $transactions->perPage() - $transactions->perPage() + $index + 1 }}</td>
                                    <td>{{ $transaction->created_at->format('Y-m-d H:i') }}</td>
                                    <td>
                                        @php
                                            $typeLabels = [
                                                'donation' => ['text' => 'تبرع', 'color' => 'success'],
                                                'expense' => ['text' => 'مصروف', 'color' => 'danger'],
                                                'custody_out' => ['text' => 'عهدة صرف', 'color' => 'info'],
                                                'custody_return' => ['text' => 'عهدة إرجاع', 'color' => 'primary'],
                                                'transfer_in' => ['text' => 'تحويل وارد', 'color' => 'primary'],
                                                'transfer_out' => ['text' => 'تحويل صادر', 'color' => 'warning'],
                                            ];
                                            $type = $typeLabels[$transaction->type] ?? ['text' => 'غير محدد', 'color' => 'secondary'];
                                        @endphp
                                        <span class="badge bg-{{ $type['color'] }}">{{ $type['text'] }}</span>
                                    </td>
                                    <td>{{ $transaction->description }}</td>
                                    <td>
                                        <strong style="color: {{ in_array($transaction->type, ['donation', 'custody_return', 'transfer_in']) ? '#4caf50' : '#f44336' }}">
                                            {{ in_array($transaction->type, ['donation', 'custody_return', 'transfer_in']) ? '+' : '-' }}
                                            {{ number_format($transaction->amount, 2) }} ج.م
                                        </strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">{{ $transaction->source ?? '-' }}</small>
                                    </td>
                                    <td>
                                        @if($transaction->user)
                                            <small>{{ $transaction->user->name }}</small>
                                        @else
                                            <small class="text-muted">-</small>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <br>لا توجد حركات لهذه الخزينة حتى الآن
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($transactions->count() > 0)
                    <div class="d-flex justify-content-center mt-4">
                        {{ $transactions->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
