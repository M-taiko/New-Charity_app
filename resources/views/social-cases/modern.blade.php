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
                        <table class="table table-hover mb-0 table-sm" id="casesTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 30%;"><i class="fas fa-user"></i> الاسم</th>
                                    <th style="width: 15%;"><i class="fas fa-phone"></i> الهاتف</th>
                                    <th style="width: 15%;"><i class="fas fa-user-tie"></i> الباحث</th>
                                    <th style="width: 12%;"><i class="fas fa-hands-helping"></i> المساعدة</th>
                                    <th style="width: 10%;"><i class="fas fa-info-circle"></i> الحالة</th>
                                    <th style="width: 8%;"><i class="fas fa-users"></i> الأقارب</th>
                                    <th style="width: 10%;">الإجراءات</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Family Members Modal -->
<div class="modal fade" id="familyMembersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" id="familyMembersModalContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Case Details Modal -->
<div class="modal fade" id="caseDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" id="caseDetailsModalContent">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

@push('styles')
<style>
    #casesTable thead th {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        font-weight: 700;
        color: #2c3e50;
        border-bottom: 2px solid #4facfe;
    }

    #casesTable tbody tr {
        border-left: 4px solid #4facfe;
        transition: all 0.3s ease;
    }

    #casesTable tbody tr:hover {
        background-color: rgba(79, 172, 254, 0.08) !important;
        box-shadow: inset -1px 0 0 rgba(79, 172, 254, 0.3);
    }

    #casesTable tbody td {
        padding: 12px 10px;
        border-color: #ecf0f1;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(79, 172, 254, 0.05);
    }

    #casesTable .btn-group .btn {
        border-radius: 4px !important;
        font-size: 0.8rem;
        padding: 0.35rem 0.6rem;
    }

    /* Smooth animations */
    #casesTable tbody tr {
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* DataTables pagination and info styling */
    .dataTables_info {
        color: #7f8c8d;
        font-size: 0.9rem;
    }

    .dataTables_paginate {
        margin-top: 1.5rem;
    }

    .paginate_button {
        border-radius: 4px !important;
        margin: 0 2px;
    }

    .paginate_button.current {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%) !important;
        border: none !important;
    }
</style>
@endpush

@push('scripts')
<script>
    const userRoles = {!! json_encode(auth()->user()->getRoleNames()) !!};
    const isManager = userRoles.includes('مدير');
    const isAccountant = userRoles.includes('محاسب');
    const canManage = isManager || isAccountant;

    // Load and display case details
    function viewCaseDetails(caseId) {
        fetch(`/social-cases/${caseId}`)
            .then(response => response.text())
            .then(html => {
                // Extract main content from the response
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const mainContent = doc.querySelector('[data-aos]') || doc.querySelector('.row');

                if (mainContent) {
                    const modalContent = `
                        <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none; padding: 1.5rem;">
                            <h5 class="modal-title" style="color: white; font-weight: 700; font-size: 1.3rem;">
                                <i class="fas fa-details"></i> تفاصيل الحالة الاجتماعية
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            ${mainContent.innerHTML}
                        </div>
                    `;
                    document.getElementById('caseDetailsModalContent').innerHTML = modalContent;
                    new bootstrap.Modal(document.getElementById('caseDetailsModal')).show();
                }
            })
            .catch(error => {
                alert('حدث خطأ في تحميل التفاصيل');
                console.error('Error:', error);
            });
    }

    // Load family members via AJAX
    function loadFamilyMembers(caseId, caseName) {
        fetch(`/api/social-cases/${caseId}/family-members`)
            .then(response => response.json())
            .then(data => {
                displayFamilyMembersModal(data.family_members, caseName);
            })
            .catch(error => {
                alert('حدث خطأ في تحميل بيانات الأقارب');
                console.error('Error:', error);
            });
    }

    // Display family members modal
    function displayFamilyMembersModal(familyMembers, caseName) {
        let tableRows = '';
        if (familyMembers && familyMembers.length > 0) {
            familyMembers.forEach((member, index) => {
                const gender = member.gender === 'male' ? '<i class="fas fa-male text-primary"></i> ذكر' : '<i class="fas fa-female text-danger"></i> أنثى';
                tableRows += `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${member.name}</strong></td>
                        <td><span class="badge bg-primary">${member.relationship}</span></td>
                        <td>${gender}</td>
                        <td>${member.phone || '-'}</td>
                    </tr>
                `;
            });
        } else {
            tableRows = '<tr><td colspan="5" class="text-center">لا توجد بيانات</td></tr>';
        }

        const modalHtml = `
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                <h5 class="modal-title" style="color: white;">
                    <i class="fas fa-users"></i> أفراد عائلة: ${caseName}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>صلة القرابة</th>
                                <th>النوع</th>
                                <th>رقم الهاتف</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
            </div>
        `;

        document.getElementById('familyMembersModalContent').innerHTML = modalHtml;
        new bootstrap.Modal(document.getElementById('familyMembersModal')).show();
    }

    $(document).ready(function() {
        $('#casesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("api.social_cases.data") }}',
            columns: [
                {
                    data: 'name',
                    render: function(data, type, row) {
                        return `<div style="padding: 8px 0;">
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">
                                        <i class="fas fa-user-circle" style="color: #4facfe; margin-left: 6px;"></i>${data}
                                    </div>
                                    <small style="color: #7f8c8d;">الرقم: #${row.id}</small>
                                </div>`;
                    }
                },
                {
                    data: 'phone',
                    render: function(data) {
                        return data ? `<a href="tel:${data}" style="color: #3498db; text-decoration: none;">
                                    <i class="fas fa-phone" style="margin-left: 4px;"></i>${data}
                                </a>` : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'researcher_name',
                    render: function(data) {
                        return `<div style="padding: 4px 0;">
                                    <i class="fas fa-user-tie" style="color: #9b59b6; margin-left: 4px;"></i>
                                    <strong style="color: #2c3e50;">${data}</strong>
                                </div>`;
                    }
                },
                {
                    data: 'assistance_type',
                    render: function(data) {
                        const types = {
                            'cash': { label: 'مساعدة مالية', color: '#27ae60', icon: 'fa-money-bill' },
                            'monthly_salary': { label: 'راتب شهري', color: '#2980b9', icon: 'fa-calendar-alt' },
                            'medicine': { label: 'أدوية', color: '#e74c3c', icon: 'fa-pills' },
                            'treatment': { label: 'علاج طبي', color: '#e67e22', icon: 'fa-hospital' },
                            'other': { label: 'أخرى', color: '#95a5a6', icon: 'fa-ellipsis-h' }
                        };
                        const type = types[data] || types['other'];
                        return `<span style="background: ${type.color}20; color: ${type.color}; padding: 6px 10px; border-radius: 20px; font-weight: 500;">
                                    <i class="fas ${type.icon}" style="margin-left: 4px;"></i>${type.label}
                                </span>`;
                    }
                },
                {
                    data: 'status_label',
                    render: function(data) {
                        return data;
                    }
                },
                {
                    data: 'family_count',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return `<button class="btn btn-sm" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border: none; font-weight: 500;" onclick="loadFamilyMembers(${row.id}, '${row.name}')" title="عرض الأقارب">
                                        <i class="fas fa-users" style="margin-left: 4px;"></i>${data} أفراد
                                    </button>`;
                        }
                        return '<span class="text-muted" style="font-size: 0.85rem;">لا يوجد</span>';
                    },
                    orderable: false,
                    searchable: false
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        let buttons = `<div class="btn-group btn-group-sm" role="group">
                                        <a href="/social-cases/${row.id}" class="btn btn-outline-primary" title="عرض التفاصيل" style="border-radius: 4px 0 0 4px;">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>`;

                        if (canManage) {
                            buttons += `<a href="/social-cases/${row.id}/edit" class="btn btn-outline-warning" title="تعديل">
                                            <i class="fas fa-edit"></i>
                                        </a>`;

                            const isActive = row.is_active == 1 || row.is_active === true;
                            const toggleIcon = isActive ? 'fa-check-circle' : 'fa-times-circle';
                            const toggleTitle = isActive ? 'إيقاف' : 'تفعيل';
                            const toggleColor = isActive ? 'btn-outline-success' : 'btn-outline-danger';

                            buttons += `<form action="/social-cases/${row.id}/toggle-active" method="POST" style="display: inline;">
                                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                            <button type="submit" class="btn ${toggleColor}" title="${toggleTitle}" style="border-radius: 0 4px 4px 0;">
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
            },
            rowCallback: function(row, data, index) {
                $(row).css('border-left', '4px solid #4facfe');
                $(row).find('td').css('vertical-align', 'middle');
                $(row).css('cursor', 'pointer');

                // Add click event to row (except buttons)
                $(row).on('click', function(e) {
                    // Don't trigger if clicking on buttons or links
                    if (!$(e.target).closest('.btn, a, form').length) {
                        viewCaseDetails(data.id);
                    }
                });
            }
        });
    });
</script>
@endpush
@endsection
