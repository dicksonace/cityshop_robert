<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfiniteScroll
{
    public static function wants(Request $request): bool
    {
        return $request->header('X-Infinite-Scroll') === '1'
            || $request->boolean('infinite');
    }

    public static function json(LengthAwarePaginator $products): JsonResponse
    {
        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'has_more' => $products->hasMorePages(),
            'next_page' => $products->hasMorePages() ? $products->currentPage() + 1 : null,
        ]);
    }
}
