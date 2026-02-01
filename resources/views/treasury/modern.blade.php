@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700; color: var(--dark);">
                        <i class="fas fa-coins"></i> الخزينة
                    </h1>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                    <i class="fas fa-plus-circle"></i> إضافة تبرع
                </button>
            </div>
        </div>
    </div>

    <!-- Balance Card -->
    <div class="row mb-4">
        <div class="col-12 col-md-6" data-aos="fade-up">
            <div class="card">
                <div class="card-body text-center p-5">
                    <div style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h6 style="color: #6b7280; margin-bottom: 0.5rem;">رصيد الخزينة الحالي</h6>
                    <div style="font-size: 3rem; font-weight: 700; color: var(--success); margin-bottom: 0.5rem;">
                        {{ number_format($treasury->balance ?? 0, 0) }}
                    </div>
                    <div style="color: #6b7280;">ريال سعودي (ر.س)</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6" data-aos="fade-up" data-aos-delay="100">
            <div class="card">
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                        <div>
                            <div style="font-size: 2rem; color: var(--primary); font-weight: 700; margin-bottom: 0.25rem;">
                                {{ \App\Models\TreasuryTransaction::where('type', 'donation')->count() }}
                            </div>
                            <div style="color: #6b7280; font-size: 0.9rem;">إجمالي التبرعات</div>
                        </div>
                        <div>
                            <div style="font-size: 2rem; color: var(--danger); font-weight: 700; margin-bottom: 0.25rem;">
                                {{ \App\Models\TreasuryTransaction::where('type', 'expense')->count() }}
                            </div>
                            <div style="color: #6b7280; font-size: 0.9rem;">إجمالي المصروفات</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="200">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-history"></i> سجل العمليات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">النوع</th>
                                    <th style="width: 15%;">المصدر</th>
                                    <th style="width: 25%;">الوصف</th>
                                    <th style="width: 15%;">المبلغ</th>
                                    <th style="width: 15%;">المستخدم</th>
                                    <th style="width: 15%;">التاريخ</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Donation Modal -->
<div class="modal fade" id="addDonationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-gift"></i> إضافة تبرع جديد
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('treasury.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">المبلغ (ر.س)</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg" placeholder="أدخل المبلغ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">المصدر</label>
                        <select name="source" class="form-select form-select-lg" required>
                            <option value="">اختر المصدر</option>
                            <option value="company">شركة</option>
                            <option value="external">مصدر خارجي</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الوصف</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="أدخل وصف التبرع" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التبرع
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#transactionsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.treasury.transactions") }}',
            columns: [
                {
                    data: 'type',
                    render: function(data) {
                        const types = {
                            'donation': '<span class="badge bg-success"><i class="fas fa-gift"></i> تبرع</span>',
                            'expense': '<span class="badge bg-danger"><i class="fas fa-money-bill-wave"></i> مصروف</span>',
                            'custody_out': '<span class="badge bg-info"><i class="fas fa-arrow-up"></i> عهدة صرف</span>',
                            'custody_return': '<span class="badge bg-primary"><i class="fas fa-arrow-down"></i> عهدة إرجاع</span>'
                        };
                        return types[data] || data;
                    }
                },
                {
                    data: 'source',
                    render: function(data) {
                        const sources = {
                            'company': 'شركة',
                            'external': 'خارجي'
                        };
                        return `<span class="badge bg-light text-dark">${sources[data] || '-'}</span>`;
                    }
                },
                { data: 'description' },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--primary);">' + parseFloat(data).toLocaleString('ar') + ' ر.س</strong>';
                    }
                },
                { data: 'user.name', defaultContent: '-' },
                {
                    data: 'created_at',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar', { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });
</script>
@endpush
@endsection
