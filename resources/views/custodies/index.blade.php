@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>العهد</h2>
        </div>
        <div class="col-md-6 text-end">
            @can('create_custody')
            <a href="{{ route('custodies.create') }}" class="btn btn-primary">إنشاء عهدة جديدة</a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="custodiesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>المندوب</th>
                                <th>المبلغ</th>
                                <th>المصروف</th>
                                <th>المتبقي</th>
                                <th>نسبة الإنفاق</th>
                                <th>الحالة</th>
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
        $('#custodiesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.custodies.data") }}',
            columns: [
                { data: 'id' },
                { data: 'agent_name' },
                { data: 'amount' },
                { data: 'spent' },
                { data: 'remaining' },
                { data: 'spent_percent' },
                { data: 'status_label' },
                { data: 'actions' }
            ],
            columnDefs: [
                { targets: 6, orderable: false, searchable: false },
                { targets: 7, orderable: false, searchable: false }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });
</script>
@endpush
@endsection
