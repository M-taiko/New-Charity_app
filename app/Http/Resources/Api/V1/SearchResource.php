<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * N-01: the answer of GET /api/v1/search — the list contract of this API
 * ({ data, meta }). The SPA builds the routes from the group and the id: it
 * knows its own pages; the server does not repeat them here.
 *
 * @property \App\Models\User $resource the signed-in user
 */
final class SearchResource extends JsonResource
{
    /**
     * @param  array<string, array<int, array{id: int, title: string, subtitle: string|null}>>  $groups
     * @param  list<string>  $searched  the groups the service searched for this user
     */
    public function __construct($resource, private readonly array $groups, private readonly array $searched, private readonly string $term)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->groups,
            'meta' => [
                'q' => $this->term,
                'per_group' => \App\Services\GlobalSearchService::PER_GROUP,
                // which groups were searched for this user (the others are empty by rule, not by luck)
                'groups' => $this->searched,
            ],
        ];
    }
}
