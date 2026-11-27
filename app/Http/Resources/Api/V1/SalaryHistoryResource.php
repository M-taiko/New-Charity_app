<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\SalaryCalculation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the salary record: the same shape the history of a single
 * employee returns, with the employee's name — the amounts as two-decimal
 * strings, never as floats.
 *
 * @property SalaryCalculation $resource
 */
final class SalaryHistoryResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        $record = $this->resource;

        return [
            'id' => $record->id,
            'employee' => $record->employee?->user?->name,
            'employee_id' => $record->employee_id,
            'year' => (int) $record->year,
            'month' => (int) $record->month,
            'base_salary' => $this->money($record->base_salary),
            'attendance_days' => (int) $record->attendance_days,
            'absence_days' => (int) $record->absence_days,
            'calculated_salary' => $this->money($record->calculated_salary),
            'deductions' => $this->money($record->deductions),
            'bonuses' => $this->money($record->bonuses),
            'final_salary' => $this->money($record->final_salary),
            'status' => $record->status,
        ];
    }

    /** a decimal as the two-decimal text the API sends; nothing as "0.00" */
    private function money(mixed $value): string
    {
        return $value === null ? '0.00' : number_format((float) $value, 2, '.', '');
    }
}
