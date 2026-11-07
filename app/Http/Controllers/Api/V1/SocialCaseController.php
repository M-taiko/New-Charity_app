<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\SocialCase;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SocialCaseController extends Controller
{
    private function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            SocialCase::with(['researcher']),
            $request,
            sortable: $this->casesSortable(),
            filterable: $this->casesFilterable(),
            search: $this->casesSearch(),
            filters: $this->casesFilters(),
        );

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total' => SocialCase::count(),
            'pending' => SocialCase::where('status', 'pending')->count(),
            'approved' => SocialCase::where('status', 'approved')->count(),
            'rejected' => SocialCase::where('status', 'rejected')->count(),
        ]));
    }

    // ── social cases declarations — shared by the list and its export (T7-06) ──

    /** @return array<string, string> */
    private function casesSortable(): array
    {
        return ['date' => 'created_at', 'created_at' => 'created_at', 'name' => 'name', 'amount' => 'assistance_amount', 'phone' => 'phone', 'governorate' => 'city', 'researcher' => 'researcher_id', 'status' => 'status'];
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function casesFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'pending', 'label' => 'قيد الدراسة'],
                ['value' => 'approved', 'label' => 'مقبولة'],
                ['value' => 'rejected', 'label' => 'مرفوضة'],
            ]],
            'governorate' => ['type' => 'select', 'column' => 'city', 'options' => fn () => \App\Models\SocialCase::distinct()->whereNotNull('city')->pluck('city')->map(fn ($c) => ['value' => $c, 'label' => $c])->values()->all()],
            'researcher' => ['type' => 'lookup', 'column' => 'researcher_id'],
        ];
    }

    /** @return callable */
    private function casesSearch(): callable
    {
        return fn ($q, $term) => $q->where(fn ($w) => $w
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('national_id', 'like', "%{$term}%"));
    }

    /** @return array<string, callable> */
    private function casesFilters(): array
    {
        return [
            'status' => fn ($q, $v) => $q->where('status', $v),
            'researcher_id' => fn ($q, $v) => $q->where('researcher_id', (int) $v),
            // T34: فلترة بالمحافظة (تسوية الإملاء عبر المساعد)
            'governorate' => function ($q, $v) {
                if ($v === 'unknown') {
                    $q->where(fn ($w) => $w->whereNull('city')->orWhere('city', ''));

                    return;
                }
                $canonical = \App\Support\EgyptianGovernorates::match((string) $v) ?? (string) $v;
                $variants = SocialCase::whereNotNull('city')->where('city', '!=', '')->distinct()
                    ->pluck('city')
                    ->filter(fn ($city) => \App\Support\EgyptianGovernorates::match($city) === $canonical)
                    ->push($canonical)
                    ->unique()->values();
                $q->whereIn('city', $variants);
            },
        ];
    }

    /**
     * GET /api/v1/social-cases/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as index().
     */
    public function export(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            SocialCase::with(['researcher']),
            $this->casesSortable(),
            $this->casesFilterable(),
            $this->casesFilters(),
            $this->casesSearch(),
            row: fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'governorate' => $c->city,
                'researcher' => $c->researcher?->name,
                'assistance_type' => $c->assistance_type,
                'status' => $this->caseStatusLabel((string) $c->status),
                'created_at' => $c->created_at?->format('Y-m-d'),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'governorate', 'label' => 'المحافظة'],
            ['key' => 'researcher', 'label' => 'الباحث'],
            ['key' => 'assistance_type', 'label' => 'نوع المساعدة'],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'social-cases');
    }

    /** Arabic label for a social case status (export rows) */
    private function caseStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'جديدة',
            'pending' => 'قيد الانتظار',
            'approved' => 'موافق عليها',
            'rejected' => 'مرفوضة',
            default => $status,
        };
    }

    public function mine(Request $request): JsonResponse
    {
        $paginator = ApiList::paginate(
            SocialCase::where('researcher_id', $request->user()->id),
            $request,
            sortable: ['date' => 'created_at', 'created_at' => 'created_at', 'name' => 'name', 'phone' => 'phone', 'assistance_type' => 'assistance_type', 'status' => 'status'],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            // T7-07: declared allowlist — meta.filters drives the table UI
            filterable: $this->mineFilterable(),
        );

        $base = SocialCase::where('researcher_id', $request->user()->id);

        return response()->json(ApiList::envelope($paginator, fn () => [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ]));
    }

    /** @return array<string, array{type:string, column?:string, options?:callable}> */
    private function mineFilterable(): array
    {
        return [
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => [
                ['value' => 'new', 'label' => 'جديدة'],
                ['value' => 'pending', 'label' => 'قيد الانتظار'],
                ['value' => 'approved', 'label' => 'موافق عليها'],
                ['value' => 'rejected', 'label' => 'مرفوضة'],
            ]],
            'date' => ['type' => 'date-range', 'column' => 'created_at'],
        ];
    }

    /**
     * GET /api/v1/my-cases/export?format=xlsx|pdf|csv (T7-06) —
     * the SAME query/filters/sort as mine(), own cases only.
     */
    public function mineExport(Request $request): StreamedResponse
    {
        $rows = ListExport::rows(
            $request,
            SocialCase::where('researcher_id', $request->user()->id),
            ['date' => 'created_at', 'created_at' => 'created_at', 'name' => 'name', 'phone' => 'phone', 'assistance_type' => 'assistance_type', 'status' => 'status'],
            $this->mineFilterable(),
            filters: ['status' => fn ($q, $v) => $q->where('status', $v)],
            search: fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")),
            row: fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'governorate' => $c->city,
                'assistance_type' => $c->assistance_type,
                'status' => $this->caseStatusLabel((string) $c->status),
                'created_at' => $c->created_at?->format('Y-m-d'),
            ],
        );

        return ListExport::stream($request, $rows, [
            ['key' => 'id', 'label' => '#'],
            ['key' => 'name', 'label' => 'الاسم'],
            ['key' => 'phone', 'label' => 'الهاتف'],
            ['key' => 'governorate', 'label' => 'المحافظة'],
            ['key' => 'assistance_type', 'label' => 'نوع المساعدة'],
            ['key' => 'status', 'label' => 'الحالة'],
            ['key' => 'created_at', 'label' => 'التاريخ'],
        ], 'my-cases');
    }

    public function show(Request $request, SocialCase $socialCase): JsonResponse
    {
        $socialCase->load(['researcher', 'reviewer']);

        return response()->json([
            'data' => [
                'id' => $socialCase->id,
                'name' => $socialCase->name,
                'national_id' => $socialCase->national_id,
                'phone' => $socialCase->phone,
                'researcher' => $socialCase->researcher?->name,
                'researcher_id' => $socialCase->researcher_id,
                'case_intake_status' => $socialCase->case_intake_status,
                'family_composition' => $socialCase->family_composition,
                'children_count' => (int) ($socialCase->children_count ?? 0),
                'disabled_count' => (int) ($socialCase->disabled_count ?? 0),
                'disability_type' => $socialCase->disability_type,
                'health_conditions' => $socialCase->health_conditions,
                'assistance_reason' => $socialCase->assistance_reason,
                'other_assistance' => $socialCase->other_assistance,
                'assistance_type' => $socialCase->assistance_type,
                'assistance_other' => $socialCase->assistance_other,
                'status' => $socialCase->status,
                'is_active' => (bool) $socialCase->is_active,
                'description' => $socialCase->description,
                'internal_notes' => $socialCase->internal_notes,
                'address' => $socialCase->address,
                'governorate' => $socialCase->city,
                'district' => $socialCase->district,
                'nationality' => $socialCase->nationality,
                'marital_status' => $socialCase->marital_status,
                'monthly_income' => $this->money($socialCase->monthly_income ?? 0),
                'monthly_expenses' => $this->money($socialCase->monthly_expenses ?? 0),
                'income_source' => $socialCase->income_source,
                'housing_type' => $socialCase->housing_type,
                'housing_status' => $socialCase->housing_status,
                'family_members_count' => (int) ($socialCase->family_members_count ?? 0),
                'affiliated_to' => $socialCase->affiliated_to,
                'gender' => $socialCase->gender,
                'reviewed_by' => $socialCase->reviewer?->name,
                'reviewed_at' => $socialCase->reviewed_at?->toIso8601ZuluString(),
                'created_at' => $socialCase->created_at?->toIso8601ZuluString(),
                'family_members' => $socialCase->familyMembers->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'relation' => $m->relationship,
                    'gender' => $m->gender,
                    'phone' => $m->phone,
                    'status' => $m->status,
                ]),
                'documents' => $socialCase->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'path' => $d->file_path,
                    'type' => $d->file_type,
                    'created_at' => $d->created_at?->toIso8601ZuluString(),
                ]),
                'expenses' => $socialCase->expenses()->with('user')->latest()->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'user' => $e->user?->name,
                        'amount' => $this->money($e->amount),
                        'description' => $e->description,
                        'date' => $e->expense_date?->toIso8601ZuluString(),
                    ]),
                'total_spent' => $this->money(Expense::where('social_case_id', $socialCase->id)->sum('amount')),
            ],
        ]);
    }
}
