@extends('layouts.modern')

@section('content')
<div class="container-fluid">

    <!-- Header -->
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h1 style="margin:0; font-size:2rem; font-weight:700;">
                    <i class="fas fa-sitemap"></i> إدارة التوجيهات المحاسبية
                </h1>
                <button class="btn btn-primary" onclick="openAddRoot()">
                    <i class="fas fa-plus-circle"></i> إضافة مستوى أول
                </button>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    <!-- Hierarchy Legend -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0" style="background:#f8f9fa;">
                <div class="card-body py-2 d-flex gap-3 flex-wrap" style="font-size:.82rem;">
                    <span><span class="badge bg-primary">م1</span> النوع الرئيسي (زكاة / صدقات)</span>
                    <span><span class="badge bg-info">م2</span> القائمة الفرعية</span>
                    <span><span class="badge bg-warning text-dark">م3</span> بيان فرعي (اختياري)</span>
                    <span><i class="fas fa-tag" style="color:#6366f1;"></i> البند / التوجيه النهائي</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tree View -->
    <div class="row g-3" data-aos="fade-up">
        @forelse($roots as $root)
        <div class="col-12">
            <div class="card" style="border-right: 4px solid #6366f1;">
                <div class="card-header d-flex justify-content-between align-items-center py-2"
                     style="background: linear-gradient(135deg,#f0f0ff,#e8e8ff);">
                    <div>
                        <span class="badge bg-primary me-2">م1</span>
                        <strong style="font-size:1rem;">{{ $root->name }}</strong>
                        <code class="ms-2 text-muted" style="font-size:.8rem;">{{ $root->code }}</code>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-success"
                                onclick="openAddChild({{ $root->id }}, '{{ addslashes($root->name) }}', 2)">
                            <i class="fas fa-plus"></i> إضافة م2
                        </button>
                        <button class="btn btn-sm btn-outline-primary"
                                onclick="openAddItem({{ $root->id }}, '{{ addslashes($root->name) }}')">
                            <i class="fas fa-tag"></i> بند مباشر
                        </button>
                        <form action="{{ route('expense-categories.destroy', $root->id) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('حذف هذا التصنيف وكل ما بداخله؟')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>

                @foreach($root->children as $level2)
                <div class="px-4 py-2 border-bottom" style="background:#fafafa;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-info me-2">م2</span>
                            <strong>{{ $level2->name }}</strong>
                            <code class="ms-2 text-muted" style="font-size:.75rem;">{{ $level2->code }}</code>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="openAddChild({{ $level2->id }}, '{{ addslashes($level2->name) }}', 3)">
                                <i class="fas fa-plus"></i> إضافة م3
                            </button>
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="openAddItem({{ $level2->id }}, '{{ addslashes($level2->name) }}')">
                                <i class="fas fa-tag"></i> بند
                            </button>
                            <form action="{{ route('expense-categories.destroy', $level2->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('حذف هذا التصنيف؟')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>

                    {{-- المستوى 3 --}}
                    @foreach($level2->children as $level3)
                    <div class="ms-4 mt-2 p-2 rounded" style="background:#f0f4ff; border-right:3px solid #a5b4fc;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-warning text-dark me-2">م3</span>
                                <strong>{{ $level3->name }}</strong>
                                <code class="ms-2 text-muted" style="font-size:.75rem;">{{ $level3->code }}</code>
                            </div>
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="openAddItem({{ $level3->id }}, '{{ addslashes($level3->name) }}')">
                                    <i class="fas fa-tag"></i> إضافة بند
                                </button>
                                <form action="{{ route('expense-categories.destroy', $level3->id) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('حذف هذا التصنيف؟')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>

                        {{-- بنود المستوى 3 --}}
                        @foreach($level3->items as $item)
                        @include('expense-items.item-row', compact('item'))
                        @endforeach
                    </div>
                    @endforeach

                    {{-- بنود مباشرة للمستوى 2 --}}
                    @foreach($level2->items as $item)
                    @include('expense-items.item-row', compact('item'))
                    @endforeach
                </div>
                @endforeach

                {{-- بنود مباشرة للمستوى 1 --}}
                @foreach($root->items as $item)
                <div class="px-4 py-1 border-bottom" style="background:#fafafa;">
                    @include('expense-items.item-row', compact('item'))
                </div>
                @endforeach

            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="fas fa-sitemap fa-3x mb-3 d-block" style="opacity:.2;"></i>
                    لا توجد تصنيفات بعد. ابدأ بإضافة المستوى الأول (مثل: زكاة أو صدقات)
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Category/SubCategory Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#6366f1,#8b5cf6); color:white;">
                <h5 class="modal-title" id="modalTitle"><i class="fas fa-sitemap"></i> إضافة تصنيف</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('expense-categories.store') }}" method="POST">
                @csrf
                <input type="hidden" name="parent_id" id="modalParentId" value="">
                <div class="modal-body">
                    <div id="parentInfo" class="alert alert-info d-none mb-3" style="font-size:.85rem;"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الاسم <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: زكاة الفطر">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الكود <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required placeholder="مثال: ZAKAT_FITR" style="direction:ltr; text-transform:uppercase;">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold">الترتيب</label>
                        <input type="number" name="order" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#10b981,#059669); color:white;">
                <h5 class="modal-title"><i class="fas fa-tag"></i> إضافة بند (توجيه نهائي)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('expense-items.store') }}" method="POST">
                @csrf
                <input type="hidden" name="expense_category_id" id="itemCategoryId">
                <div class="modal-body">
                    <div id="itemParentInfo" class="alert alert-success mb-3" style="font-size:.85rem;"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">اسم البند <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: زواج">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">الكود <span class="text-danger">*</span></label>
                        <input type="text" name="code" class="form-control" required placeholder="مثال: WEDDING" style="direction:ltr; text-transform:uppercase;">
                    </div>
                    <div class="row g-2">
                        <div class="col">
                            <label class="form-label fw-bold">المبلغ الافتراضي</label>
                            <input type="number" name="default_amount" class="form-control" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col">
                            <label class="form-label fw-bold">الترتيب</label>
                            <input type="number" name="order" class="form-control" value="1" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> حفظ البند</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openAddRoot() {
    document.getElementById('modalParentId').value = '';
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-sitemap"></i> إضافة مستوى أول';
    document.getElementById('parentInfo').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
}

function openAddChild(parentId, parentName, level) {
    document.getElementById('modalParentId').value = parentId;
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-sitemap"></i> إضافة مستوى ' + level;
    const info = document.getElementById('parentInfo');
    info.classList.remove('d-none');
    info.innerHTML = '<i class="fas fa-level-down-alt"></i> سيُضاف تحت: <strong>' + parentName + '</strong>';
    new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
}

function openAddItem(categoryId, categoryName) {
    document.getElementById('itemCategoryId').value = categoryId;
    document.getElementById('itemParentInfo').innerHTML = '<i class="fas fa-sitemap"></i> سيُضاف تحت: <strong>' + categoryName + '</strong>';
    new bootstrap.Modal(document.getElementById('addItemModal')).show();
}

document.getElementById('addCategoryModal').addEventListener('hidden.bs.modal', function() {
    this.querySelector('form').reset();
    document.getElementById('modalParentId').value = '';
    document.getElementById('parentInfo').classList.add('d-none');
});
</script>
@endpush
@endsection
