<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustodyTransfer;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustodyTransferController extends Controller
{
    /**
     * GET /api/v1/custody-transfers?scope=sent|received|all
     * Tabs: sent, received, and (management only) all transfers (T11).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = (string) $request->query('scope', 'sent');

        $query = CustodyTransfer::with(['fromAgent', 'toAgent', 'custody.treasury', 'approver']);

        $isManagement = $user->can('approve_custody_transfer') || $user->can('manage_treasury');
        if ($scope === 'all') {
            abort_unless($isManagement, 403, 'عرض كل التحويلات متاح للإدارة فقط');
        } elseif ($scope === 'received') {
            $query->where('to_agent_id', $user->id);
        } else {
            $query->where('from_agent_id', $user->id);
        }

        $paginator = ApiList::paginate(
            $query,
            $request,
            sortable: ['date' => 'created_at', 'amount' => 'amount', 'requested_at' => 'created_at', 'sender' => 'from_agent_id', 'receiver' => 'to_agent_id', 'custody_id' => 'custody_id', 'status' => 'status'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('notes', 'like', "%{$term}%")
                ->orWhereHas('fromAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                ->orWhereHas('toAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($t) => $this->row($t, $user->id));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'scope' => $scope,
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'approved_count' => (clone $query)->where('status', 'approved')->count(),
            'rejected_count' => (clone $query)->where('status', 'rejected')->count(),
        ]));
    }

    public function show(Request $request, CustodyTransfer $custodyTransfer): JsonResponse
    {
        $user = $request->user();
        $isParty = $custodyTransfer->from_agent_id === $user->id || $custodyTransfer->to_agent_id === $user->id;
        $isManagement = $user->can('approve_custody_transfer') || $user->can('manage_treasury');
        abort_unless($isParty || $isManagement, 403, 'غير مصرح لك بعرض هذا التحويل');

        $custodyTransfer->load(['fromAgent', 'toAgent', 'custody.treasury', 'custody.agent', 'approver']);

        $data = $this->row($custodyTransfer, $user->id);
        $custody = $custodyTransfer->custody;
        if ($custody) {
            // T26: المتاح بعد التجميد والحد الأقصى للتحويل
            $data['custody'] = [
                'id' => $custody->id,
                'agent' => $custody->agent?->name,
                'treasury' => $custody->treasury?->name,
                'amount' => number_format((float) $custody->amount, 2, '.', ''),
                'remaining' => number_format((float) $custody->getRemainingBalance(), 2, '.', ''),
                'frozen' => number_format((float) $custody->pending_transfer_out, 2, '.', ''),
            ];
        }

        return response()->json(['data' => $data]);
    }

    private function row(CustodyTransfer $t, int $currentUserId): array
    {
        return [
            'id' => $t->id,
            'sender' => $t->fromAgent?->name,
            'receiver' => $t->toAgent?->name,
            'custody_id' => $t->custody_id,
            'amount' => number_format((float) $t->amount, 2, '.', ''),
            'status' => $t->status,
            'notes' => $t->notes,
            'rejection_reason' => $t->rejection_reason,
            'requested_at' => $t->created_at?->toIso8601ZuluString(),
            'decided_at' => $t->approved_at?->toIso8601ZuluString(),
            'decided_by' => $t->approver?->name,
            // T32: مرسل/مستلم — المستقبل وحده يرى أزرار القبول والرفض
            'can_decide' => $t->status === 'pending' && $t->to_agent_id === $currentUserId,
        ];
    }
}
