@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>الخزينة</h2>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                إضافة تبرع
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <h5>رصيد الخزينة: <strong class="text-success">{{ number_format($treasury->balance ?? 0, 2) }} ج.م</strong></h5>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">عمليات الخزينة</h5>
                </div>
                <div class="card-body">
                    <table class="table table-hover" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>النوع</th>
                                <th>المصدر</th>
                                <th>الوصف</th>
                                <th>المبلغ</th>
                                <th>المستخدم</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Donation Modal -->
<div class="modal fade" id="addDonationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إضافة تبرع</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('treasury.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">المبلغ</label>
                        <input type="number" step="0.01" name="amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المصدر</label>
                        <select name="source" class="form-select" required>
                            <option value="">اختر المصدر</option>
                            <option value="company">شركة</option>
                            <option value="external">خارجي</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الوصف</label>
                        <textarea name="description" class="form-control" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
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
                { data: 'created_at', name: 'created_at', render: function(data) { return new Date(data).toLocaleDateString('ar'); } },
                { data: 'type_label', name: 'type' },
                { data: 'source_label', name: 'source' },
                { data: 'description', name: 'description' },
                { data: 'amount', name: 'amount', render: function(data) { return parseFloat(data).toFixed(2); } },
                { data: 'user.name', name: 'user.name' }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });
</script>
@endpush
@endsection
