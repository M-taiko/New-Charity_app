<div class="d-flex justify-content-between align-items-center py-1 px-2 mt-1 rounded"
     style="background:white; border:1px solid #e0e7ff;">
    <div>
        <i class="fas fa-tag" style="color:#6366f1; font-size:.8rem;"></i>
        <span class="ms-1">{{ $item->name }}</span>
        <code class="ms-1 text-muted" style="font-size:.72rem;">{{ $item->code }}</code>
        @if($item->default_amount)
        <span class="badge bg-light text-dark ms-1" style="font-size:.7rem; border:1px solid #ddd;">
            {{ number_format($item->default_amount, 2) }} ج.م
        </span>
        @endif
        @if(!$item->is_active)
        <span class="badge bg-secondary ms-1" style="font-size:.7rem;">غير نشط</span>
        @endif
    </div>
    <div class="d-flex gap-1">
        <a href="{{ route('expense-items.edit', $item->id) }}"
           class="btn btn-outline-primary py-0 px-1" style="font-size:.75rem;">
            <i class="fas fa-edit"></i>
        </a>
        <form action="{{ route('expense-items.destroy', $item->id) }}" method="POST" class="d-inline"
              onsubmit="return confirm('حذف هذا البند؟')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger py-0 px-1" style="font-size:.75rem;">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    </div>
</div>
