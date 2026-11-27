<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * N-04: POST /api/v1/expenses/bulk-review — review or unreview several
 * expenses at once. Each id is then judged on its own (see BulkActionsService):
 * one refused id never stops the others.
 */
final class BulkExpenseReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:review,unreview'],
            'ids' => ['required', 'array', 'min:1', 'max:' . \App\Services\BulkActionsService::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): JsonResponse
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'اختر مصروفًا واحدًا على الأقل (وحتى ' . \App\Services\BulkActionsService::MAX_IDS . ')',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /** the ids as integers, each once, in the order they were given */
    public function idList(): array
    {
        $ids = [];
        foreach ($this->validated('ids') as $id) {
            $ids[(int) $id] = (int) $id;
        }

        return array_values($ids);
    }

    public function unreview(): bool
    {
        return $this->validated('action') === 'unreview';
    }
}
