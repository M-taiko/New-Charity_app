@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-people-group"></i> الحالات الاجتماعية
                    </h1>
                </div>
                <a href="{{ route('social_cases.create') }}" class="btn btn-primary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <i class="fas fa-plus-circle"></i> إضافة حالة اجتماعية جديدة
                </a>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up">
            <div class="stat-card info">
                <div class="stat-icon"><i class="fas fa-list"></i></div>
                <div class="stat-label">إجمالي الحالات</div>
                <div class="stat-number" style="color: var(--info);">{{ \App\Models\SocialCase::count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-card warning">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-label">حالات معلقة</div>
                <div class="stat-number" style="color: var(--warning);">{{ \App\Models\SocialCase::where('status', 'pending')->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-card success">
                <div class="stat-icon"><i class="fas fa-check"></i></div>
                <div class="stat-label">حالات موافق عليها</div>
                <div class="stat-number" style="color: var(--success);">{{ \App\Models\SocialCase::where('status', 'approved')->count() }}</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-card danger">
                <div class="stat-icon"><i class="fas fa-times"></i></div>
                <div class="stat-label">حالات مرفوضة</div>
                <div class="stat-number" style="color: var(--danger);">{{ \App\Models\SocialCase::where('status', 'rejected')->count() }}</div>
            </div>
        </div>
    </div>

    <div class="row" data-aos="fade-up" data-aos-delay="400">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 style="margin: 0;">
                        <i class="fas fa-table"></i> قائمة الحالات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="casesTable">
                            <thead>
                                <tr>
                                    <th>الاسم</th>
                                    <th>الهاتف</th>
                                    <th>الباحث</th>
                                    <th>نوع المساعدة</th>
                                    <th>الحالة</th>
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
</div>

@push('scripts')
<script>
    const userRoles = {!! json_encode(auth()->user()->getRoleNames()) !!};
    const isManager = userRoles.includes('مدير');
    const isAccountant = userRoles.includes('محاسب');
    const canManage = isManager || isAccountant;

    $(document).ready(function() {
        $('#casesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.social_cases.data") }}',
            columns: [
                { data: 'name' },
                { data: 'phone' },
                { data: 'researcher_name' },
                { data: 'assistance_type' },
                { data: 'status_label' },
                {
                    data: 'created_at',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('ar');
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        let buttons = `<div class="btn-group btn-group-sm" role="group">
                                        <a href="/social-cases/${row.id}" class="btn btn-outline-primary" title="عرض">
                                            <i class="fas fa-eye"></i>
                                        </a>`;

                        if (canManage) {
                            buttons += `<a href="/social-cases/${row.id}/edit" class="btn btn-outline-warning" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>`;

                            const isActive = row.is_active == 1 || row.is_active === true;
                            const toggleIcon = isActive ? 'fa-check-circle' : 'fa-times-circle';
                            const toggleTitle = isActive ? 'إيقاف النشاط' : 'تنشيط';
                            const toggleColor = isActive ? 'btn-outline-success' : 'btn-outline-secondary';

                            buttons += `<form action="/social-cases/${row.id}/toggle-active" method="POST" style="display: inline;">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            <button type="submit" class="btn ${toggleColor}" title="${toggleTitle}">
                                                <i class="fas ${toggleIcon}"></i>
                                            </button>
                                        </form>`;
                        }

                        buttons += `</div>`;
                        return buttons;
                    },
                    orderable: false,
                    searchable: false
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
