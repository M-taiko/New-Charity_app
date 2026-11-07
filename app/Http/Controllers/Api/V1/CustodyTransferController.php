<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustodyTransfer;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustodyTransferController extends Controller
{
    /**
     * GET /api/v1/custody-transfers?scope=sent|received|all
     * Tabs: sent, received, and (management only) all transfers (T11).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        [$query, $scope] = $this->scopedQuery($request);

        $paginator = ApiList::paginate(
            $query,
            $request,
            sortable: $this->transfersSortable(),
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('notes', 'like', "%{$term}%")
                ->orWhereHas('fromAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                ->orWhereHas('toAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            // T7-07: declared allowlist — meta.filters drives the table UI
            filterable: $this->transfersFilterable(),
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

        $custodyTransfer->load(['fromAgent', 'toAgent', 'custody.treasury', 'custody.agent', 'approver', 'canceller']);

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

    /**
     * GET /api/v1/custody-transfers/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME scope rules, query, filters, sort and search as index().
     */
    public function export(Request $request): StreamedResponse
    {
        [$query] = $this->scopedQuery($request);

        $rows = ListExport::rows(
            $request,
            $query->orderByDesc('created_at'),
            $this->transfersSortable(),
            $this->transfersFilterable(),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('notes', 'like', "%{$term}%")
                ->orWhereHas('fromAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))
                ->orWhereHas('toAgent', fn ($a) => $a->where('name', 'like', "%{$term}%"))),
            row: fn ($t) => [
                'id' => $t->id,
                'from_agent' => $t->fromAgent?->name,
                'to_agent' => $t->toAgent?->name,
                'custody_id' => '#' . $t->custody_id,
                'amount' => (float) $t->amount,
                'status' => $this->statusLabel($t->status),
                'notes' => $t->notes,
                'created_at' => optional($t->created_at)->format('Y-m-d'),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'from_agent', 'label' => 'المرسل'],
            ['key' => 'to_agent', 'label' => 'المستلم'],
            ['key' => 'custody_id', 'label' => 'العهدة'],
            ['key' => 'amount', 'label' => 'المبلغ', 'numeric' => true, 'total' => true],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'notes', 'label' => 'ملاحظات'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'custody-transfers');
    }

    /**
     * The scope-aware base query shared by the list and its export (T7-06):
     * scope=sent (default) | received | all — و«all» للإدارة فقط (T11).
     *
     * @return array{\Illuminate\Database\Eloquent\Builder, string}
     */
    private function scopedQuery(Request $request): array
    {
        $user = $request->user();
        $scope = (string) $request->query('scope', 'sent');

        $query = CustodyTransfer::with(['fromAgent', 'toAgent', 'custody.treasury', 'approver', 'canceller']);

        $isManagement = $user->can('approve_custody_transfer') || $user->can('manage_treasury');
        if ($scope === 'all') {
            abort_unless($isManagement, 403, 'عرض كل التحويلات متاح للإدارة فقط');
        } elseif ($scope === 'received') {
            $query->where('to_agent_id', $user->id);
        } else {
            $query->where('from_agent_id', $user->id);
        }

        return [$query, $scope];
    }

    /** @return array<string, string> */
    private function transfersSortable(): array
    {
        return ['date' => 'created_at', 'amount' => 'amount', 'requested_at' => 'created_at', 'sender' => 'from_agent_id', 'receiver' => 'to_agent_id', 'custody_id' => 'custody_id', 'status' => 'status', 'decided_at' => 'approved_at', 'decided_by' => 'approved_by'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable, lookup?:string, apply?:callable}> */
    private function transfersFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'pending', 'label' => 'معلق'],
                ['value' => 'approved', 'label' => 'مقبول'],
                ['value' => 'rejected', 'label' => 'مرفوض'],
                ['value' => 'cancelled', 'label' => 'ملغي'],
            ]],
            'date' => ['type' => 'date-range', 'column' => 'created_at'],
            'amount' => ['type' => 'number-range', 'column' => 'amount'],
        ];
    }

    /** Arabic label for a transfer status (export rows) */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'معلق',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            'cancelled' => 'ملغي',
            default => $status,
        };
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
            'cancelled_at' => $t->cancelled_at?->toIso8601ZuluString(),
            'cancelled_by' => $t->canceller?->name,
            // T32: مرسل/مستلم — المستقبل وحده يرى أزرار القبول والرفض
            'can_decide' => $t->status === 'pending' && $t->to_agent_id === $currentUserId,
            // T6-80: المرسل والمدير يمكنهما إلغاء التحويل المعلق
            'can_cancel' => $t->status === 'pending'
                && ((int) $t->from_agent_id === $currentUserId || $this->isManager($currentUserId)),
        ];
    }

    /** managers may cancel any pending transfer (T6-80) */
    private function isManager(int $userId): bool
    {
        $user = \App\Models\User::find($userId);

        return $user !== null && ($user->hasRole('مدير') || $user->can('approve_custody_transfer'));
    }
}
