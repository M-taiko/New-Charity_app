@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>المصروفات</h2>
        </div>
        <div class="col-md-6 text-end">
            @can('spend_money')
            <a href="{{ route('expenses.create') }}" class="btn btn-primary">تسجيل مصروف جديد</a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="expensesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المستخدم</th>
                                <th>نوع المصروف</th>
                                <th>الحالة</th>
                                <th>المبلغ</th>
                                <th>التاريخ</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('#expensesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.expenses.data") }}',
            columns: [
                { data: 'id' },
                { data: 'user_name' },
                { data: 'type_label' },
                { data: 'case_name' },
                { data: 'amount' },
                { data: 'created_at', render: function(data) { return new Date(data).toLocaleDateString('ar'); } },
                { data: 'actions', orderable: false }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });
</script>
@endpush
@endsection
