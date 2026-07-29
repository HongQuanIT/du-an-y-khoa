<?php

declare(strict_types=1);

namespace App\Support\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Builds the standard success/error envelope defined in
 * srs/00-nen-tang/05-api-conventions.md §3.
 */
final class ApiResponse
{
    /**
     * Single resource: { "data": {...}, "meta": {...} }.
     *
     * @param  JsonResource|array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function item(JsonResource|array $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
            'meta' => array_merge(['request_id' => self::requestId()], $meta),
        ], $status);
    }

    /**
     * Paginated list with pagination meta + links.
     *
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function paginated(LengthAwarePaginator $paginator, ?ResourceCollection $collection = null): JsonResponse
    {
        $items = $collection?->resolve() ?? $paginator->items();

        return response()->json([
            'data' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                ],
                'request_id' => self::requestId(),
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'prev' => $paginator->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Standardised error envelope.
     *
     * @param  array<int, array<string, mixed>>  $details
     */
    public static function error(string $code, string $message, int $status = 400, array $details = []): JsonResponse
    {
        $error = [
            'code' => $code,
            'message' => $message,
            'request_id' => self::requestId(),
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return response()->json(['error' => $error], $status);
    }

    private static function requestId(): string
    {
        return (string) (request()->attributes->get('request_id') ?? request()->header('X-Request-Id', ''));
    }
}
