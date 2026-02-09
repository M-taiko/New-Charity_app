@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                    <i class="fas fa-exchange-alt"></i> تحويلات العهدات
                </h1>
                <a href="{{ route('custody-transfers.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <i class="fas fa-plus"></i> طلب تحويل جديد
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4" data-aos="fade-up">
        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">التحويلات المرسلة</p>
                            <h4 class="mb-0" style="color: #667eea;">{{ $sentTransfersCount }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #667eea; opacity: 0.2;">
                            <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">التحويلات المستقبلة</p>
                            <h4 class="mb-0" style="color: #764ba2;">{{ $receivedTransfersCount }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #764ba2; opacity: 0.2;">
                            <i class="fas fa-arrow-left"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">قيد الانتظار</p>
                            <h4 class="mb-0" style="color: #f59e0b;">{{ $pendingTransfersCount }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #f59e0b; opacity: 0.2;">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted small mb-1">إجمالي</p>
                            <h4 class="mb-0" style="color: #10b981;">{{ $sentTransfersCount + $receivedTransfersCount }}</h4>
                        </div>
                        <div style="font-size: 2.5rem; color: #10b981; opacity: 0.2;">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="row" data-aos="fade-up" data-aos-delay="100">
        <div class="col-12">
            <ul class="nav nav-tabs" id="transferTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" type="button" role="tab" aria-controls="sent" aria-selected="true">
                        <i class="fas fa-arrow-right"></i> التحويلات المرسلة
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab" aria-controls="received" aria-selected="false">
                        <i class="fas fa-arrow-left"></i> التحويلات المستقبلة
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="transferTabContent" style="padding: 20px; border: 1px solid #e0e7ff; border-top: none;">
                <!-- Sent Transfers Tab -->
                <div class="tab-pane fade show active" id="sent" role="tabpanel" aria-labelledby="sent-tab">
                    <div class="table-responsive">
                        <table id="sentTransfersTable" class="table table-hover">
                            <thead>
                                <tr style="background: #f8f9ff;">
                                    <th>إلى</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                <!-- Received Transfers Tab -->
                <div class="tab-pane fade" id="received" role="tabpanel" aria-labelledby="received-tab">
                    <div class="table-responsive">
                        <table id="receivedTransfersTable" class="table table-hover">
                            <thead>
                                <tr style="background: #f8f9ff;">
                                    <th>من</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th>التاريخ</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">

<script>
    $(document).ready(function() {
        // Sent transfers table
        $('#sentTransfersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.custody-transfers.sent") }}',
            columns: [
                {data: 'to_agent_name', name: 'to_agent_name'},
                {data: 'amount', name: 'amount'},
                {data: 'status_badge', name: 'status', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            language: {
                "sEmptyTable": "لا توجد تحويلات",
                "sInfo": "عرض _START_ إلى _END_ من _TOTAL_ تحويل",
                "sInfoEmpty": "عرض 0 إلى 0 من 0 تحويل",
                "sInfoFiltered": "(مصفاة من _MAX_ تحويل إجمالي)",
                "sLengthMenu": "عرض _MENU_ تحويلات",
                "sLoadingRecords": "جاري التحميل...",
                "sProcessing": "جاري المعالجة...",
                "sSearch": "بحث:",
                "sZeroRecords": "لم يتم العثور على تحويلات"
            }
        });

        // Received transfers table
        $('#receivedTransfersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.custody-transfers.received") }}',
            columns: [
                {data: 'from_agent_name', name: 'from_agent_name'},
                {data: 'amount', name: 'amount'},
                {data: 'status_badge', name: 'status', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            language: {
                "sEmptyTable": "لا توجد تحويلات",
                "sInfo": "عرض _START_ إلى _END_ من _TOTAL_ تحويل",
                "sInfoEmpty": "عرض 0 إلى 0 من 0 تحويل",
                "sInfoFiltered": "(مصفاة من _MAX_ تحويل إجمالي)",
                "sLengthMenu": "عرض _MENU_ تحويلات",
                "sLoadingRecords": "جاري التحميل...",
                "sProcessing": "جاري المعالجة...",
                "sSearch": "بحث:",
                "sZeroRecords": "لم يتم العثور على تحويلات"
            }
        });
    });
</script>

<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init({
        duration: 800,
        once: true
    });
</script>
@endpush

@endsection
