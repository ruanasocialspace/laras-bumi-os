<?php

declare(strict_types=1);

namespace App\Modules\SharedInfrastructure;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;

final class ApiPage
{
    public static function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public static function response(Builder $query, array $filters, ?callable $map = null): JsonResponse
    {
        $page = $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return response()->json([
            'data' => $map === null ? $page->items() : $page->getCollection()->map($map)->all(),
            'links' => ['first' => $page->url(1), 'last' => $page->url($page->lastPage()), 'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }
}
