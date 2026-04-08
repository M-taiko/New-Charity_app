@extends('layouts.modern')

@section('content')
<div class="container-fluid">
    <div class="row mb-4" data-aos="fade-down">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h1 style="margin:0; font-size:2rem; font-weight:700;">
                    <i class="fas fa-tag"></i> {{ isset($expenseItem) ? 'تعديل البند' : 'إضافة بند جديد' }}
                </h1>
                <a href="{{ route('expense-items.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-right"></i> رجوع
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center" data-aos="fade-up">
        <div class="col-12 col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h6 style="margin:0;">تفاصيل البند (التوجيه النهائي)</h6>
                </div>
                <div class="card-body">
                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                    @endif

                    <form action="{{ isset($expenseItem) ? route('expense-items.update', $expenseItem->id) : route('expense-items.store') }}"
                          method="POST">
                        @csrf
                        @if(isset($expenseItem)) @method('PUT') @endif

                        {{-- Cascading Category Selection --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold">المستوى الأول <span class="text-danger">*</span></label>
                            <select id="level1" class="form-select" onchange="loadLevel(2, this.value)">
                                <option value="">-- اختر --</option>
                                @foreach($roots as $r)
                                <option value="{{ $r->id }}" {{ isset($expenseItem) && $expenseItem->category->getAncestorAtLevel(1)?->id == $r->id ? 'selected' : '' }}>
                                    {{ $r->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3" id="level2Wrap" style="{{ isset($expenseItem) ? '' : 'display:none;' }}">
                            <label class="form-label fw-bold">المستوى الثاني</label>
                            <select id="level2" class="form-select" onchange="loadLevel(3, this.value)">
                                <option value="">-- اختر --</option>
                            </select>
                        </div>

                        <div class="mb-3" id="level3Wrap" style="{{ isset($expenseItem) ? '' : 'display:none;' }}">
                            <label class="form-label fw-bold">المستوى الثالث <span class="text-muted">(اختياري)</span></label>
                            <select id="level3" class="form-select" onchange="setParent(this.value, document.getElementById('level2').value)">
                                <option value="">-- بدون مستوى ثالث --</option>
                            </select>
                        </div>

                        <input type="hidden" name="expense_category_id" id="finalCategoryId"
                               value="{{ isset($expenseItem) ? $expenseItem->expense_category_id : '' }}">

                        <hr>
                        <div class="mb-3">
                            <label class="form-label fw-bold">اسم البند <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $expenseItem->name ?? '') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">الكود <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   value="{{ old('code', $expenseItem->code ?? '') }}" required
                                   style="direction:ltr; text-transform:uppercase;" placeholder="WEDDING">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col">
                                <label class="form-label fw-bold">المبلغ الافتراضي</label>
                                <input type="number" name="default_amount" class="form-control"
                                       value="{{ old('default_amount', $expenseItem->default_amount ?? '') }}"
                                       step="0.01" min="0">
                            </div>
                            <div class="col">
                                <label class="form-label fw-bold">الترتيب</label>
                                <input type="number" name="order" class="form-control"
                                       value="{{ old('order', $expenseItem->order ?? 1) }}" min="1" required>
                            </div>
                        </div>
                        @if(isset($expenseItem))
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                       value="1" {{ $expenseItem->is_active ? 'checked' : '' }}>
                                <label class="form-check-label" for="isActive">نشط</label>
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('expense-items.index') }}" class="btn btn-secondary">إلغاء</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ isset($expenseItem) ? 'تحديث' : 'حفظ' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
const rootsUrl   = '{{ route("api.expense-categories.roots") }}';
const childrenUrl = (id) => `/api/expense-categories/${id}/children`;

async function loadLevel(targetLevel, parentId) {
    if (!parentId) {
        hideFrom(targetLevel);
        return;
    }

    const res = await fetch(childrenUrl(parentId));
    const data = await res.json();

    if (targetLevel === 2) {
        const sel = document.getElementById('level2');
        sel.innerHTML = '<option value="">-- اختر (اختياري) --</option>';
        data.forEach(c => sel.innerHTML += `<option value="${c.id}">${c.name}</option>`);
        document.getElementById('level2Wrap').style.display = data.length ? '' : 'none';
        document.getElementById('level3Wrap').style.display = 'none';
        // Set final category to level1 by default
        setFinal(parentId);
    } else if (targetLevel === 3) {
        const sel = document.getElementById('level3');
        sel.innerHTML = '<option value="">-- بدون مستوى ثالث --</option>';
        data.forEach(c => sel.innerHTML += `<option value="${c.id}">${c.name}</option>`);
        document.getElementById('level3Wrap').style.display = data.length ? '' : 'none';
        // Set final category to level2 by default
        setFinal(parentId);
    }
}

function setParent(level3Id, level2Id) {
    setFinal(level3Id || level2Id);
}

function setFinal(id) {
    document.getElementById('finalCategoryId').value = id;
}

function hideFrom(level) {
    if (level <= 2) {
        document.getElementById('level2Wrap').style.display = 'none';
        document.getElementById('level2').innerHTML = '<option value="">-- اختر --</option>';
    }
    if (level <= 3) {
        document.getElementById('level3Wrap').style.display = 'none';
        document.getElementById('level3').innerHTML = '<option value="">-- اختر --</option>';
    }
}
</script>
@endpush
@endsection
