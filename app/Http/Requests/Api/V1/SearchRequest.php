<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

/**
 * N-01: the global record search's one parameter. Two characters at least —
 * one character of Arabic matches half of the database, and the palette
 * waits for a pause before asking.
 */
final class SearchRequest extends FormRequest
{
    public const MIN_LENGTH = 2;
    public const MAX_LENGTH = 100;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:' . self::MIN_LENGTH, 'max:' . self::MAX_LENGTH],
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
                'message' => 'أدخل حرفين على الأقل للبحث',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /** the trimmed search text */
    public function term(): string
    {
        return trim((string) $this->validated('q'));
    }
}
