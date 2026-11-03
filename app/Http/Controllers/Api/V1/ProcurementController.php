<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\Api\ApiList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementController extends Controller
{
    private function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function purchaseRequests(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            PurchaseRequest::with(['requester', 'supplier']),
            $request,
            sortable: ['date' => 'created_at', 'cost' => 'estimated_cost', 'estimated_cost' => 'estimated_cost', 'title' => 'title', 'category' => 'category', 'priority' => 'priority', 'status' => 'status', 'requested_by' => 'requested_by'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($p) => $this->purchaseRow($p));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => PurchaseRequest::where('status', 'pending')->count(),
            'approved' => PurchaseRequest::where('status', 'approved')->count(),
            'purchased' => PurchaseRequest::where('status', 'purchased')->count(),
            'total_spent' => $this->money(PurchaseRequest::where('status', 'purchased')->sum('actual_cost')),
        ]));
    }

    public function purchaseShow(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $purchaseRequest->load(['requester', 'supplier', 'treasury']);

        return response()->json([
            'data' => $this->purchaseRow($purchaseRequest) + [
                'description' => $purchaseRequest->description,
                'actual_cost' => $this->money($purchaseRequest->actual_cost ?? 0),
                'needed_by' => $purchaseRequest->needed_by?->toIso8601ZuluString(),
                'attachment' => $purchaseRequest->attachment,
                'rejection_reason' => $purchaseRequest->rejection_reason,
                'supplier_id' => $purchaseRequest->supplier_id,
                'treasury_id' => $purchaseRequest->treasury_id,
                'expense_category_id' => $purchaseRequest->expense_category_id,
                'treasury_distribution' => $purchaseRequest->treasury_distribution,
            ],
        ]);
    }

    public function maintenanceRequests(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            MaintenanceRequest::with(['reporter', 'assignee']),
            $request,
            sortable: ['date' => 'created_at', 'title' => 'title', 'location' => 'location', 'priority' => 'priority', 'status' => 'status', 'reported_by' => 'reported_by'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($m) => [
            'id' => $m->id,
            'title' => $m->title,
            'location' => $m->location,
            'priority' => $m->priority,
            'status' => $m->status,
            'reported_by' => $m->reporter?->name,
            'assigned_to' => $m->assignee?->name,
            'created_at' => $m->created_at?->toIso8601ZuluString(),
        ]);

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => MaintenanceRequest::where('status', 'pending')->count(),
            'in_progress' => MaintenanceRequest::where('status', 'in_progress')->count(),
            'resolved' => MaintenanceRequest::where('status', 'resolved')->count(),
        ]));
    }

    public function maintenanceShow(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $maintenanceRequest->load(['reporter', 'assignee']);

        return response()->json([
            'data' => [
                'id' => $maintenanceRequest->id,
                'title' => $maintenanceRequest->title,
                'description' => $maintenanceRequest->description,
                'location' => $maintenanceRequest->location,
                'priority' => $maintenanceRequest->priority,
                'status' => $maintenanceRequest->status,
                'reported_by' => $maintenanceRequest->reporter?->name,
                'assigned_to' => $maintenanceRequest->assignee?->name,
                'resolution_notes' => $maintenanceRequest->resolution_notes,
                'rejection_reason' => $maintenanceRequest->rejection_reason,
                'resolved_at' => $maintenanceRequest->resolved_at?->toIso8601ZuluString(),
                'attachment' => $maintenanceRequest->attachment,
                'created_at' => $maintenanceRequest->created_at?->toIso8601ZuluString(),
            ],
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            Supplier::withCount('purchaseRequests'),
            $request,
            sortable: ['name' => 'name', 'phone' => 'phone', 'email' => 'email'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('is_active', $v === 'active')],
        );
        $paginator->getCollection()->transform(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'notes' => $s->notes,
            'is_active' => (bool) $s->is_active,
            'purchase_requests_count' => $s->purchase_requests_count,
        ]);

        return response()->json(ApiList::envelope($paginator));
    }

    private function purchaseRow(PurchaseRequest $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'category' => $p->category,
            'priority' => $p->priority,
            'estimated_cost' => $this->money($p->estimated_cost ?? 0),
            'status' => $p->status,
            'requested_by' => $p->requester?->name,
            'supplier' => $p->supplier?->name,
            'created_at' => $p->created_at?->toIso8601ZuluString(),
        ];
    }
}
