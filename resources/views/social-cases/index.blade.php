@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>الحالات الاجتماعية</h2>
        </div>
        <div class="col-md-6 text-end">
            @can('create_social_case')
            <a href="{{ route('social_cases.create') }}" class="btn btn-primary">إنشاء حالة جديدة</a>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table class="table table-hover" id="casesTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>الهاتف</th>
                                <th>الباحث</th>
                                <th>نوع المساعدة</th>
                                <th>الحالة</th>
                                <th>التاريخ</th>
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
        $('#casesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.social_cases.data") }}',
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'phone' },
                { data: 'researcher_name' },
                { data: 'assistance_type' },
                { data: 'status_label' },
                { data: 'created_at', render: function(data) { return new Date(data).toLocaleDateString('ar'); } }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });
    });
</script>
@endpush
@endsection
