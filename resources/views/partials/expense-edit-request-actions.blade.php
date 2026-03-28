<div class="btn-group btn-group-sm" role="group">
    <a href="{{ route('expense-edit-requests.show', $row) }}" class="btn btn-info btn-sm">
        <i class="fas fa-eye"></i> عرض
    </a>
    @if($row->status === 'pending')
        <form action="{{ route('expense-edit-requests.approve', $row) }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('هل تريد الموافقة على التعديل؟');">
                <i class="fas fa-check"></i> موافقة
            </button>
        </form>
    @endif
</div>
