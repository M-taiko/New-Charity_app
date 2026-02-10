<div class="btn-group" role="group">
    <a href="{{ route('custodies.show', $row->id) }}" class="btn btn-sm btn-info">عرض</a>
    @if($row->status == 'pending')
        <form action="{{ route('custodies.accept', $row->id) }}" method="POST" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('هل تريد قبول هذه العهدة؟')">قبول</button>
        </form>
        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $row->id }}">رفض</button>
    @elseif($row->status == 'accepted')
        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#returnModal{{ $row->id }}">إرجاع</button>
    @endif
</div>

@if($row->status == 'pending')
<!-- Reject Modal -->
<div class="modal fade" id="rejectModal{{ $row->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">رفض العهدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.reject', $row->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">السبب</label>
                        <textarea name="reason" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-danger">رفض</button>
                </div>
            </form>
        </div>
    </div>
</div>
@elseif($row->status == 'accepted')
<!-- Return Modal -->
<div class="modal fade" id="returnModal{{ $row->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">إرجاع العهدة</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('custodies.return', $row->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">المبلغ المرجع (ج.م)</label>
                        <input type="number" step="0.01" name="returned_amount" class="form-control" max="{{ $row->getRemainingBalance() }}" required>
                        <small class="text-muted">الحد الأقصى: {{ number_format($row->getRemainingBalance(), 2) }} ج.م</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إرجاع</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
