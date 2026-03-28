@extends('layouts.modern')

@section('content')
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2><i class="fas fa-list"></i> طلبات تعديل المصروفات</h2>
        </div>
    </div>

    <!-- الإحصاءات -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">طلبات معلقة</h6>
                    <h3>{{ $pendingCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">موافقات</h6>
                    <h3>{{ $approvedCount }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">رفوضات</h6>
                    <h3>{{ $rejectedCount }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- الجدول -->
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">قائمة الطلبات</h5>
        </div>
        <div class="card-body">
            <table class="table table-hover datatable" id="editRequestsTable">
                <thead class="table-light">
                    <tr>
                        <th>رقم المصروف</th>
                        <th>الطالب</th>
                        <th>المبلغ الأصلي</th>
                        <th>المبلغ الجديد</th>
                        <th>التاريخ</th>
                        <th>الحالة</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#editRequestsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("api.expense-edit-requests.data") }}',
        columns: [
            { data: 'expense_id' },
            { data: 'requester_name' },
            { data: 'expense_amount' },
            { data: 'requested_amount' },
            { data: 'requested_at' },
            { data: 'status_badge', orderable: false },
            { data: 'actions', orderable: false }
        ]
    });
});
</script>
@endsection
