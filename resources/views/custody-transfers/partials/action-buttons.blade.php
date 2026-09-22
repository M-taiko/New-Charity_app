<div class="btn-group btn-group-sm" role="group">
    <a href="{{ route('custody-transfers.show', $transfer) }}" class="btn btn-info" title="عرض التفاصيل">
        <i class="fas fa-eye"></i> عرض
    </a>

    @if($transfer->status === 'pending' && auth()->id() === $transfer->to_agent_id)
        <form action="{{ route('custody-transfers.approve', $transfer) }}" method="POST" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-success btn-sm" title="قبول التحويل" onclick="return confirm('هل تريد قبول هذا التحويل؟')">
                <i class="fas fa-check"></i> قبول
            </button>
        </form>
    @endif
</div>
