<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * N-04: POST /api/v1/social-cases/bulk-active — activate or deactivate
 * several social cases at once. Each id is then judged on its own.
 */
final class BulkCaseActiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
            'ids' => ['required', 'array', 'min:1', 'max:' . \App\Services\BulkActionsService::MAX_IDS],
            'ids.*' => ['integer', 'min:1'],
        ];
    }

    protected function failedValidation(Validator $validator): JsonResponse
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'اختر حالة واحدة على الأقل (وحتى ' . \App\Services\BulkActionsService::MAX_IDS . ')',
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

    public function makeActive(): bool
    {
        return (bool) $this->validated('active');
    }
}
