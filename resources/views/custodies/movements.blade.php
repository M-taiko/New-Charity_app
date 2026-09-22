@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700; color: var(--dark);">
                        <i class="fas fa-exchange-alt"></i> حركات العهد
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        سجل كل حركات الاستلام والتسليم والتحويل والصرف
                    </p>
                    <small class="text-muted">تظهر هنا الحركات المسجلة في النظام فقط.</small>
                </div>
                <button onclick="window.print()" class="btn btn-outline-secondary btn-sm no-print">
                    <i class="fas fa-print"></i> طباعة
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Cards (affected by filters) -->
    <div class="row g-4 mb-4" data-aos="fade-up">
        <div class="col-12 col-sm-6 col-lg">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
                <div class="stat-label">المستلم من الخزينة</div>
                <div class="stat-number" id="sumReceived">0.00</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                <div class="stat-icon"><i class="fas fa-undo"></i></div>
                <div class="stat-label">المردود للخزينة</div>
                <div class="stat-number" id="sumReturned">0.00</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <div class="stat-card" style="background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: white;">
                <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="stat-label">المحوّل بين المناديب</div>
                <div class="stat-number" id="sumTransferred">0.00</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg">
            <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white;">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-label">المصروف</div>
                <div class="stat-number" id="sumSpent">0.00</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4 no-print" data-aos="fade-up" data-aos-delay="100">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-bold">من تاريخ</label>
                            <input type="date" class="form-control" id="filterDateFrom">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-bold">إلى تاريخ</label>
                            <input type="date" class="form-control" id="filterDateTo">
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-bold">المندوب (طرف)</label>
                            <select class="form-select" id="filterAgent">
                                <option value="">الكل</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-bold">نوع الحركة</label>
                            <select class="form-select" id="filterType">
                                <option value="">الكل</option>
                                <option value="custody_out">صرف عهدة</option>
                                <option value="custody_return">رد عهدة</option>
                                <option value="custody_transfer_out">تحويل بين مندوبين</option>
                                <option value="expense">مصروف</option>
                                <option value="recovery">تحصيل</option>
                                <option value="custody_close">إغلاق عهدة</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label fw-bold">رقم العهدة</label>
                            <input type="number" min="1" class="form-control" id="filterCustody">
                        </div>
                        <div class="col-12 col-md-2 d-flex flex-column gap-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="filterClosures">
                                <label class="form-check-label" for="filterClosures">إظهار الإغلاقات الصفرية</label>
                            </div>
                            <button type="button" class="btn btn-primary" id="applyFilters">
                                <i class="fas fa-filter"></i> تطبيق
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="row" data-aos="fade-up" data-aos-delay="200">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> سجل الحركات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="movementsTable">
                            <thead>
                                <tr>
                                    <th>التاريخ والوقت</th>
                                    <th>نوع الحركة</th>
                                    <th>من</th>
                                    <th>إلى</th>
                                    <th>المبلغ</th>
                                    <th>العهدة</th>
                                    <th>المصروف</th>
                                    <th>الوصف</th>
                                    <th>بواسطة</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        const money = v => parseFloat(v ?? 0).toLocaleString('ar', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ج.م';

        const table = $('#movementsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("api.custody-movements.data") }}',
                data: function(d) {
                    d.date_from = $('#filterDateFrom').val();
                    d.date_to = $('#filterDateTo').val();
                    d.agent_id = $('#filterAgent').val();
                    d.type = $('#filterType').val();
                    d.custody_id = $('#filterCustody').val();
                    d.show_closures = $('#filterClosures').is(':checked') ? 1 : 0;
                }
            },
            columns: [
                {
                    data: 'transaction_date',
                    render: function(data) { return data ?? '-'; }
                },
                { data: 'movement_label' },
                { data: 'from_party' },
                { data: 'to_party' },
                {
                    data: 'amount',
                    render: function(data) {
                        return '<strong style="color: var(--danger);">' + money(data) + '</strong>';
                    }
                },
                { data: 'custody_link' },
                { data: 'expense_link' },
                {
                    data: 'description',
                    render: function(data) { return data ?? '-'; }
                },
                { data: 'performed_by' }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });

        table.on('xhr', function(e, settings, json) {
            if (json && json.summary) {
                $('#sumReceived').text(money(json.summary.received));
                $('#sumReturned').text(money(json.summary.returned));
                $('#sumTransferred').text(money(json.summary.transferred));
                $('#sumSpent').text(money(json.summary.spent));
            }
        });

        $('#applyFilters').on('click', function() {
            table.ajax.reload();
        });
    });
</script>
@endpush
@endsection
