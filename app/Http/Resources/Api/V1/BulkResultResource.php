<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * N-04: the answer of a bulk action — the list contract of this API
 * ({ data, meta }) and the action contract's `message`. Every id named in
 * the request has one line in `results`, in the order it was given.
 */
final class BulkResultResource extends JsonResource
{
    /**
     * @param  array{results: list<array{id: int, status: string, code?: string, message?: string}>, done: int, refused: int, amount: string}  $result
     */
    public function __construct(private readonly array $result, private readonly string $action, private readonly string $message)
    {
        parent::__construct($result);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'results' => $this->result['results'],
                'done' => $this->result['done'],
                'refused' => $this->result['refused'],
                /** the total of the records that were changed, a two-decimal string */
                'amount' => $this->result['amount'],
            ],
            'message' => $this->message,
            'level' => $this->result['refused'] === 0 ? 'success' : 'warning',
            'meta' => ['action' => $this->action, 'requested' => count($this->result['results'])],
        ];
    }
}
