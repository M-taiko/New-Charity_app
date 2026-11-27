<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\Api\V1\IndexSalaryHistoryRequest;
use App\Http\Resources\Api\V1\SalaryHistoryResource;
use App\Models\Employee;
use App\Models\SalaryCalculation;
use App\Support\Api\ApiList;
use App\Support\Api\ListExport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The salary record of every employee — what "سجل مرتبات الموظف" shows. The
 * history of ONE employee (hr/employees/{employee}/salary-history) stays where
 * it is; this is the same records across all of them, filtered by employee,
 * month, year and status.
 */
final class SalaryHistoryService
{
    /** the statuses the salaries table knows */
    public const STATUSES = ['draft', 'calculated', 'approved', 'paid'];

    /** @var array<int, string> */
    public const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    /** @return array<string, string> the sort keys the table may send => the column they order */
    public function sortable(): array
    {
        return [
            'date' => 'created_at',
            'year' => 'year',
            'month' => 'month',
            'final_salary' => 'final_salary',
        ];
    }

    /**
     * The filter allowlist — the list contract's declaration, which also
     * drives the table's filter panel.
     *
     * @return array<string, array{type: string, column: string, options: callable}>
     */
    public function filterable(): array
    {
        return [
            'employee_id' => ['type' => 'select', 'column' => 'employee_id', 'options' => fn () => $this->employeeOptions()],
            'month' => ['type' => 'select', 'column' => 'month', 'options' => fn () => $this->monthOptions()],
            'year' => ['type' => 'select', 'column' => 'year', 'options' => fn () => $this->yearOptions()],
            'status' => ['type' => 'select', 'column' => 'status', 'options' => fn () => $this->statusOptions()],
        ];
    }

    public function paginate(IndexSalaryHistoryRequest $request): LengthAwarePaginator
    {
        $query = SalaryCalculation::with('employee.user');
        if (! $request->filled('sort')) {
            // newest first until the table asks for another order
            $query->orderByDesc('created_at');
        }

        $paginator = ApiList::paginate($query, $request, sortable: $this->sortable(), search: $this->search(), filterable: $this->filterable());
        $paginator->setCollection(SalaryHistoryResource::collection($paginator->getCollection())->collection);

        return $paginator;
    }

    /** the search box: an employee's name (the wildcards in what was typed are literal) */
    private function search(): callable
    {
        return function ($query, string $term): void {
            $like = '%' . addcslashes($term, '%_\\') . '%';
            $query->whereHas('employee.user', fn ($user) => $user->where('name', 'like', $like));
        };
    }

    /**
     * The export: the SAME query and filters as the list, one array per row.
     * Amounts are numbers here — a cell in a sheet, as every export does it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function exportRows(IndexSalaryHistoryRequest $request): Collection
    {
        return ListExport::rows(
            $request,
            SalaryCalculation::with('employee.user'),
            $this->sortable(),
            $this->filterable(),
            search: $this->search(),
            row: fn (SalaryCalculation $record) => $this->exportRow($record),
        );
    }

    /** @return array<string, mixed> */
    private function exportRow(SalaryCalculation $record): array
    {
        return [
            'employee' => $record->employee?->user?->name,
            'year' => (int) $record->year,
            'month' => self::MONTHS[max(((int) $record->month) - 1, 0)] ?? (string) $record->month,
            'base_salary' => (float) $record->base_salary,
            'calculated_salary' => (float) $record->calculated_salary,
            'deductions' => (float) $record->deductions,
            'bonuses' => (float) $record->bonuses,
            'final_salary' => (float) $record->final_salary,
            'status' => self::statusLabel((string) $record->status),
        ];
    }

    /** @return array<int, array{key: string, label: string, numeric?: bool, total?: bool}> */
    public function exportColumns(): array
    {
        return [
            ['key' => 'employee', 'label' => 'اسم الموظف'],
            ['key' => 'year', 'label' => 'السنة'],
            ['key' => 'month', 'label' => 'الشهر'],
            ['key' => 'base_salary', 'label' => 'المرتب الأساسي', 'numeric' => true, 'total' => true],
            ['key' => 'calculated_salary', 'label' => 'المرتب المحسوب', 'numeric' => true, 'total' => true],
            ['key' => 'deductions', 'label' => 'الاستقطاعات', 'numeric' => true, 'total' => true],
            ['key' => 'bonuses', 'label' => 'المكافآت', 'numeric' => true, 'total' => true],
            ['key' => 'final_salary', 'label' => 'المرتب النهائي', 'numeric' => true, 'total' => true],
            ['key' => 'status', 'label' => 'الحالة'],
        ];
    }

    /** @return list<array{value: int, label: string}> */
    private function employeeOptions(): array
    {
        return Employee::with('user')
            ->orderBy('id')
            ->get()
            ->map(fn (Employee $employee) => [
                'value' => $employee->id,
                'label' => $employee->user?->name ?? ('موظف #' . $employee->id),
            ])
            ->values()
            ->all();
    }

    /** @return list<array{value: int, label: string}> */
    private function monthOptions(): array
    {
        return array_map(fn (int $index) => ['value' => $index, 'label' => self::MONTHS[$index - 1]], range(1, 12));
    }

    /** @return list<array{value: int, label: string}> */
    private function yearOptions(): array
    {
        return SalaryCalculation::query()
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => ['value' => (int) $year, 'label' => (string) $year])
            ->values()
            ->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'مسودة'],
            ['value' => 'calculated', 'label' => 'محسوب'],
            ['value' => 'approved', 'label' => 'معتمد'],
            ['value' => 'paid', 'label' => 'مدفوع'],
        ];
    }

    /** the Arabic label of a status, for the export */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'مسودة',
            'calculated' => 'محسوب',
            'approved' => 'معتمد',
            'paid' => 'مدفوع',
            default => $status,
        };
    }
}
