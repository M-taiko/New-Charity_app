@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-list"></i> إدارة بنود المصروفات
                    </h1>
                </div>
                <a href="{{ route('expense-items.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <i class="fas fa-plus"></i> إضافة بند جديد
                </a>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-layer-group"></i> قائمة البنود
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Filter by Category -->
                    <div class="mb-3">
                        <label class="form-label"><strong>تصفية حسب الفئة</strong></label>
                        <select id="category_filter" class="form-select">
                            <option value="">-- كل الفئات --</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- DataTable -->
                    <div class="table-responsive">
                        <table id="items_table" class="table table-hover">
                            <thead style="background-color: #f8f9fa;">
                                <tr>
                                    <th>الفئة</th>
                                    <th>اسم البند</th>
                                    <th>الكود</th>
                                    <th>المبلغ الافتراضي</th>
                                    <th>الترتيب</th>
                                    <th>الحالة</th>
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

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">تأكيد الحذف</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                هل أنت متأكد من رغبتك في حذف هذا البند؟ هذا الإجراء لا يمكن التراجع عنه.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">حذف</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    let table;

    $(document).ready(function() {
        initializeTable();

        $('#category_filter').change(function() {
            table.ajax.reload();
        });
    });

    function initializeTable() {
        table = $('#items_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("expense-items.data") }}',
                data: function(d) {
                    d.category_id = $('#category_filter').val();
                }
            },
            columns: [
                { data: 'category_name' },
                { data: 'name' },
                { data: 'code' },
                { data: 'default_amount', render: function(data) {
                    return data ? data.toLocaleString('ar-SA', {minimumFractionDigits: 2}) + ' ج.م' : '--';
                }},
                { data: 'order' },
                { data: 'status' },
                { data: 'action' }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/ar.json'
            },
            order: [[0, 'asc'], [4, 'asc']],
            pageLength: 25
        });
    }

    function editItem(id) {
        window.location.href = '/expense-items/' + id + '/edit';
    }

    function deleteItem(id) {
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const deleteForm = document.getElementById('deleteForm');
        deleteForm.action = '/expense-items/' + id;
        deleteModal.show();
    }
</script>

<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
@endpush

@endsection
