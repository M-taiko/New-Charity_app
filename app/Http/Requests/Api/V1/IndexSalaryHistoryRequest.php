<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/hr/salary-history — the salary record of every employee.
 *
 * The table sends its filters as filter[key]=… and its sort as sort=…; which
 * keys are accepted is the service's declaration (the list contract rejects
 * the rest). What is validated here is the shape of what is accepted.
 */
final class IndexSalaryHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // the same gate the HR dashboard and the "حسابات المرتبات" tab answer to
        return $this->user() !== null && $this->user()->can('manage_treasury');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'array'],
            'filter.employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'filter.month' => ['nullable', 'integer', 'between:1,12'],
            'filter.year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'filter.status' => ['nullable', 'string', 'in:draft,calculated,approved,paid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * Always JSON on this API — the default of a Form Request is a redirect
     * back to the previous page, which is for the browser, not for the SPA.
     */
    protected function failedValidation(Validator $validator): JsonResponse
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'بيانات التصفية غير صالحة',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
