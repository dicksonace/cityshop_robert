<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class AdminJson
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $page
     * @return array<string, int>
     */
    public static function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }
}
