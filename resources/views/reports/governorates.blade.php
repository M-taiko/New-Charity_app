@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h1 style="margin: 0; font-size: 2rem; font-weight: 700;">
                        <i class="fas fa-map-marked-alt"></i> الحالات حسب المحافظة
                    </h1>
                    <p style="margin: 0.5rem 0 0 0; color: #6b7280; font-size: 0.95rem;">
                        عدد الحالات بحالتها وإجمالي المصروف عليها — القيم المكتوبة بشكل مختلف تُجمَّع بعد التسوية، والقيمة الفارغة تظهر كـ"غير محدد"
                    </p>
                </div>
                <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-right"></i> رجوع للتقارير
                </a>
            </div>
        </div>
    </div>

    {{-- فلاتر: نطاق التاريخ + التوجيه (المساعد المشترك) --}}
    <div class="row mb-3">
        <div class="col-12">
            <form method="GET" action="{{ route('reports.governorates') }}" class="card">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> من تاريخ</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-calendar"></i> إلى تاريخ</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-sitemap"></i> التوجيه: المستوى الأول</label>
                            <select name="direction" id="gov_cat1" class="form-select form-select-sm">
                                <option value="">الكل</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1">المستوى الثاني</label>
                            <select name="direction_level2" id="gov_cat2" class="form-select form-select-sm" data-role="cascade-only">
                                <option value="">الكل</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1">المستوى الثالث</label>
                            <select name="direction_level3" id="gov_cat3" class="form-select form-select-sm" data-role="cascade-only">
                                <option value="">الكل</option>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-md-2">
                            <label class="form-label small mb-1"><i class="fas fa-tag"></i> البند</label>
                            <select name="item" id="gov_item" class="form-select form-select-sm">
                                <option value="">الكل</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-1">
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-filter"></i> تطبيق
                            </button>
                        </div>
                        <div class="col-6 col-md-1">
                            <a href="{{ route('reports.governorates') }}" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-times"></i> إعادة
                            </a>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> نطاق التاريخ والتوجيه يطبقان على مبالغ المصروفات فقط — عدد الحالات لا يتأثر بهما.</small>
                </div>
            </form>
        </div>
    </div>

    <div class="row" data-aos="fade-up">
        <div class="col-12">
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); border: none;">
                    <h5 style="margin: 0; color: white;">
                        <i class="fas fa-table"></i> تفصيل المحافظات
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>المحافظة</th>
                                    <th class="text-center">عدد الحالات</th>
                                    <th class="text-center">موافق عليها</th>
                                    <th class="text-center">معلقة</th>
                                    <th class="text-center">مرفوضة</th>
                                    <th class="text-end">إجمالي المصروف</th>
                                    <th class="text-end">المتوسط لكل حالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($governorates as $row)
                                    @php
                                        // "غير محدد" ليس رابطاً؛ بقية الصفوف تفتح قائمة الحالات مصفاة بالمحافظة
                                        $isUnspecified = $row['label'] === 'غير محدد';
                                        $rowUrl = $isUnspecified ? null : route('social_cases.index', ['city' => $row['label']]);
                                    @endphp
                                    <tr @if($rowUrl) style="cursor: pointer;" onclick="window.location='{{ $rowUrl }}';" @endif>
                                        <td>
                                            <strong>{{ $row['label'] }}</strong>
                                            @if(!$row['standard'])
                                                <span class="badge bg-warning text-dark">قيمة غير معيارية</span>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $row['total'] }}</td>
                                        <td class="text-center"><span class="badge bg-success">{{ $row['approved'] }}</span></td>
                                        <td class="text-center"><span class="badge bg-warning text-dark">{{ $row['pending'] }}</span></td>
                                        <td class="text-center"><span class="badge bg-danger">{{ $row['rejected'] }}</span></td>
                                        <td class="text-end" dir="ltr">{{ number_format($row['expenses_total'], 2) }} ج.م</td>
                                        <td class="text-end" dir="ltr">{{ number_format($row['average'], 2) }} ج.م</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">لا توجد حالات مسجلة بعد</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($governorates) > 0)
                                <tfoot class="table-light fw-bold">
                                    <tr>
                                        <td>الإجمالي</td>
                                        <td class="text-center">{{ $grandTotal['total'] }}</td>
                                        <td class="text-center">{{ $grandTotal['approved'] }}</td>
                                        <td class="text-center">{{ $grandTotal['pending'] }}</td>
                                        <td class="text-center">{{ $grandTotal['rejected'] }}</td>
                                        <td class="text-end" dir="ltr">{{ number_format($grandTotal['expenses_total'], 2) }} ج.م</td>
                                        <td class="text-end" dir="ltr">{{ number_format($grandTotal['average'], 2) }} ج.م</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // T34: قوائم التوجيه المتتالية للفلاتر (نفس نقاط API في قائمة المصروفات)
    $(document).ready(async function() {
        const selectedDirection = {{ $directionId ?: 'null' }};
        const selectedItem = {{ $directionItemId ?: 'null' }};

        async function loadOptions(url, $select) {
            try {
                const res = await fetch(url);
                const rows = await res.json();
                rows.forEach(r => $select.append(`<option value="${r.id}">${r.name}</option>`));
            } catch (e) {
                console.error('خطأ في تحميل خيارات التوجيه', e);
            }
        }

        async function loadChildren(level, parentId) {
            for (let l = level + 1; l <= 3; l++) {
                $('#gov_cat' + l).find('option:not(:first)').remove();
            }
            $('#gov_item').find('option:not(:first)').remove();
            if (!parentId) return;
            if (level < 3) {
                await loadOptions(`/api/expense-categories/${parentId}/children`, $('#gov_cat' + (level + 1)));
            }
            await loadOptions(`/api/expense-categories/${parentId}/items`, $('#gov_item'));
        }

        await loadOptions('{{ route('api.expense-categories.roots') }}', $('#gov_cat1'));

        // استعادة اختيار التوجيه من الرابط: املأ القوائم نزولاً ثم اختر القيم
        if (selectedDirection) {
            try {
                const ancestors = await (await fetch(`/api/expense-categories/${selectedDirection}/ancestors`)).json();
                if (ancestors[0]) {
                    $('#gov_cat1').val(ancestors[0]);
                    await loadChildren(1, ancestors[0]);
                }
                if (ancestors[1]) {
                    $('#gov_cat2').val(ancestors[1]);
                    await loadChildren(2, ancestors[1]);
                }
                if (ancestors[2]) {
                    $('#gov_cat3').val(ancestors[2]);
                    await loadChildren(3, ancestors[2]);
                }
                if (selectedItem) {
                    $('#gov_item').val(selectedItem);
                }
            } catch (e) {
                console.error('تعذر استعادة التوجيه المختار', e);
            }
        }

        $('#gov_cat1').on('change', function() { loadChildren(1, this.value); });
        $('#gov_cat2').on('change', function() { loadChildren(2, this.value); });
        $('#gov_cat3').on('change', function() { loadChildren(3, this.value); });

        // عند الإرسال: direction يحمل أعمق فئة مختارة؛ قوائم الوسائط لا ترسل
        $('form').on('submit', function() {
            const deepest = $('#gov_cat3').val() || $('#gov_cat2').val() || $('#gov_cat1').val() || '';
            $(this).find('input[name="direction"]').remove();
            $(this).append(`<input type="hidden" name="direction" value="${deepest}">`);
            $('#gov_cat2, #gov_cat3').prop('disabled', true);
        });
    });
</script>
@endpush
@endsection
