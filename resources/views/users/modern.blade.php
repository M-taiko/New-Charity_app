@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-users"></i> المستخدمون
                    </h1>
                </div>
                @can('manage_users')
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> إضافة مستخدم
                </a>
                @endcan
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card primary">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-label">إجمالي المستخدمين</div>
                <div class="stat-number" style="color: var(--primary);">{{ \App\Models\User::count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-label">المستخدمون النشطون</div>
                <div class="stat-number" style="color: var(--success);">{{ \App\Models\User::where('is_active', true)->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-user-slash"></i></div>
                <div class="stat-label">المستخدمون المعطلون</div>
                <div class="stat-number" style="color: var(--danger);">{{ \App\Models\User::where('is_active', false)->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-shield-alt"></i></div>
                <div class="stat-label">عدد الأدوار</div>
                <div class="stat-number" style="color: var(--info);">{{ \Spatie\Permission\Models\Role::count() }}</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4" data-aos="fade-up" data-aos-delay="350">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-600">بحث بالاسم أو البريد</label>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="ابحث...">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-600">فلترة بالدور</label>
                            <select id="roleFilter" class="form-select form-select-sm">
                                <option value="">-- جميع الأدوار --</option>
                                @foreach(\Spatie\Permission\Models\Role::all() as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-600">فلترة بالحالة</label>
                            <select id="statusFilter" class="form-select form-select-sm">
                                <option value="">-- جميع الحالات --</option>
                                <option value="active">نشط</option>
                                <option value="inactive">معطل</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <button id="resetFilters" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-redo"></i> إعادة تعيين
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> قائمة المستخدمين
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="usersTable">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>البريد الإلكتروني</th>
                                    <th>الهاتف</th>
                                    <th>الأدوار</th>
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
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        let table = $('#usersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route("api.users.data") }}',
                data: function(d) {
                    d.search = $('#searchInput').val();
                    d.role_filter = $('#roleFilter').val();
                    d.status_filter = $('#statusFilter').val();
                }
            },
            columns: [
                { data: 'name' },
                { data: 'email' },
                { data: 'phone' },
                { data: 'roles' },
                { data: 'status' },
                {
                    data: null,
                    render: function(data) {
                        return `<a href="/users/${data.id}" class="btn btn-sm btn-info"><i class="fas fa-eye"></i></a>
                                <a href="/users/${data.id}/edit" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json'
            }
        });

        // Wire up filter inputs
        $('#searchInput').on('keyup', function() {
            table.draw();
        });

        $('#roleFilter').on('change', function() {
            table.draw();
        });

        $('#statusFilter').on('change', function() {
            table.draw();
        });

        $('#resetFilters').on('click', function() {
            $('#searchInput').val('');
            $('#roleFilter').val('');
            $('#statusFilter').val('');
            table.draw();
        });
    });
</script>
@endpush
@endsection
