<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            sortable: $this->purchaseSortable(),
            filterable: $this->purchaseFilterable(),
            search: $this->purchaseSearch(),
            filters: $this->purchaseFilters(),
        );
        $paginator->getCollection()->transform(fn ($p) => $this->purchaseRow($p));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => PurchaseRequest::where('status', 'pending')->count(),
            'approved' => PurchaseRequest::where('status', 'approved')->count(),
            'purchased' => PurchaseRequest::where('status', 'purchased')->count(),
            'total_spent' => $this->money(PurchaseRequest::where('status', 'purchased')->sum('actual_cost')),
        ]));
    }

    // ── purchase requests declarations — shared by the list and its export (T7-06) ──

    /** @return array<string, string> */
    private function purchaseSortable(): array
    {
        return ['date' => 'created_at', 'cost' => 'estimated_cost', 'estimated_cost' => 'estimated_cost', 'title' => 'title', 'category' => 'category', 'priority' => 'priority', 'status' => 'status', 'requested_by' => 'requested_by'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function purchaseFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'pending', 'label' => 'معلق'],
                ['value' => 'approved', 'label' => 'معتمد'],
                ['value' => 'purchased', 'label' => 'تم الشراء'],
                ['value' => 'rejected', 'label' => 'مرفوض'],
            ]],
            'requested_by' => ['type' => 'lookup', 'column' => 'requested_by'],
        ];
    }

    /** @return callable */
    private function purchaseSearch(): callable
    {
        return fn ($q, $term) => $q->where(fn ($w) => $w
            ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
    }

    /** @return array<string, callable> */
    private function purchaseFilters(): array
    {
        return ['status' => fn ($q, $v) => $q->where('status', $v)];
    }

    /**
     * GET /api/v1/purchase-requests/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as purchaseRequests().
     */
    public function purchaseExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            PurchaseRequest::with(['requester', 'supplier']),
            $this->purchaseSortable(),
            $this->purchaseFilterable(),
            $this->purchaseFilters(),
            $this->purchaseSearch(),
            row: fn ($p) => $this->purchaseRow($p) + ['actual_cost' => $p->actual_cost],
        )->map(fn (array $p) => [
            'id' => $p['id'],
            'title' => $p['title'],
            'category' => $this->categoryLabel((string) $p['category']),
            'priority' => $this->priorityLabel((string) $p['priority']),
            'estimated_cost' => $this->moneyFloat($p['estimated_cost']),
            'actual_cost' => $p['actual_cost'] !== null ? $this->moneyFloat($p['actual_cost']) : null,
            'status' => $this->purchaseStatusLabel((string) $p['status']),
            'requested_by' => $p['requested_by'],
            'created_at' => ($p['created_at'] ?? '') !== '' ? substr((string) $p['created_at'], 0, 10) : '',
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'العنوان'],
            ['key' => 'category', 'label' => 'الفئة'],
            ['key' => 'priority', 'label' => 'الأولوية'],
            ['key' => 'estimated_cost', 'label' => 'التكلفة التقديرية', 'numeric' => true, 'total' => true],
            ['key' => 'actual_cost', 'label' => 'التكلفة الفعلية', 'numeric' => true, 'total' => true],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'requested_by', 'label' => 'الطالب'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'purchase-requests');
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
            sortable: $this->maintenanceSortable(),
            filterable: $this->maintenanceFilterable(),
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
        );
        $paginator->getCollection()->transform(fn ($m) => $this->maintenanceRow($m));

        return response()->json(ApiList::envelope($paginator, fn () => [
            'pending' => MaintenanceRequest::where('status', 'pending')->count(),
            'in_progress' => MaintenanceRequest::where('status', 'in_progress')->count(),
            'resolved' => MaintenanceRequest::where('status', 'resolved')->count(),
        ]));
    }

    /** @return array<string, string> */
    private function maintenanceSortable(): array
    {
        return ['date' => 'created_at', 'title' => 'title', 'location' => 'location', 'priority' => 'priority', 'status' => 'status', 'reported_by' => 'reported_by'];
    }

    /** @return array<string, array{type:string, column?:string}> */
    private function maintenanceFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status'],
            'priority' => ['type' => 'select', 'column' => 'priority'],
        ];
    }

    private function maintenanceRow(MaintenanceRequest $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'location' => $m->location,
            'priority' => $m->priority,
            'status' => $m->status,
            'reported_by' => $m->reporter?->name,
            'assigned_to' => $m->assignee?->name,
            'created_at' => $m->created_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * GET /api/v1/maintenance-requests/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as maintenanceRequests().
     */
    public function maintenanceExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            MaintenanceRequest::with(['reporter', 'assignee']),
            $this->maintenanceSortable(),
            $this->maintenanceFilterable(),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")),
            row: fn ($m) => $this->maintenanceRow($m),
        )->map(fn (array $m) => [
            'id' => $m['id'],
            'title' => $m['title'],
            'location' => $m['location'],
            'priority' => $this->priorityLabel((string) $m['priority']),
            'status' => $this->maintenanceStatusLabel((string) $m['status']),
            'reported_by' => $m['reported_by'],
            'assigned_to' => $m['assigned_to'],
            'created_at' => ($m['created_at'] ?? '') !== '' ? substr((string) $m['created_at'], 0, 10) : '',
        ]);

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'title', 'label' => 'المشكلة'],
            ['key' => 'location', 'label' => 'الموقع'],
            ['key' => 'priority', 'label' => 'الأولوية'],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'reported_by', 'label' => 'المُبلِّغ'],
            ['key' => 'assigned_to', 'label' => 'المكلف'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'maintenance-requests');
    }

    public function maintenanceShow(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $maintenanceRequest->load(['reporter', 'assignee', 'reviewer']);

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
                'rejected_by' => $maintenanceRequest->status === 'rejected' ? $maintenanceRequest->reviewer?->name : null,
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
            sortable: $this->suppliersSortable(),
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('is_active', $v === 'active')],
            // T7-07: declared allowlist — meta.filters drives the table UI
            filterable: [
                'active' => ['type' => 'boolean', 'column' => 'is_active'],
            ],
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

    /** @return array<string, string> */
    private function suppliersSortable(): array
    {
        return ['name' => 'name', 'phone' => 'phone', 'email' => 'email', 'purchase_requests_count' => 'raw:purchase_requests_count'];
    }

    /**
     * GET /api/v1/suppliers/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as suppliers().
     */
    public function suppliersExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            Supplier::withCount('purchaseRequests'),
            $this->suppliersSortable(),
            ['active' => ['type' => 'boolean', 'column' => 'is_active']],
            filters: ['status' => fn ($q, $v) => $q->where('is_active', $v === 'active')],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")),
            row: fn ($s) => [
                'name' => $s->name,
                'phone' => $s->phone,
                'email' => $s->email,
                'address' => $s->address,
                'is_active' => $s->is_active ? 'نشط' : 'غير نشط',
                'purchase_requests_count' => (int) $s->purchase_requests_count,
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'email', 'label' => 'البريد الإلكتروني'],
            ['key' => 'address', 'label' => 'العنوان'],
            ['key' => 'is_active', 'label' => 'الحالة'],
            ['key' => 'purchase_requests_count', 'label' => 'عدد طلبات الشراء', 'numeric' => true],
        ], 'suppliers');
    }

    /** Arabic label for a purchase request status (export rows) */
    private function purchaseStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'في الانتظار',
            'approved' => 'موافق عليها',
            'purchased' => 'تم الشراء',
            'rejected' => 'مرفوضة',
            default => $status,
        };
    }

    /** Arabic label for a maintenance request status (export rows) */
    private function maintenanceStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'في الانتظار',
            'in_progress' => 'جاري المعالجة',
            'resolved' => 'تم الحل',
            'rejected' => 'مرفوض',
            default => $status,
        };
    }

    /** Arabic label for a priority (export rows) */
    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'low' => 'منخفضة',
            'medium' => 'متوسطة',
            'high' => 'عالية',
            'urgent' => 'عاجلة',
            default => $priority,
        };
    }

    /** Arabic label for a purchase category (export rows) */
    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'office_supplies' => 'مستلزمات مكتبية',
            'equipment' => 'معدات وأجهزة',
            'services' => 'خدمات',
            'maintenance' => 'صيانة',
            'other' => 'أخرى',
            default => $category,
        };
    }

    /** export-safe float from a money-formatted row value (commas stripped) */
    private function moneyFloat(mixed $v): float
    {
        return (float) str_replace(',', '', (string) $v);
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
