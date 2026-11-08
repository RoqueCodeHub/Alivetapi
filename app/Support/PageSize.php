<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class PageSize
{
    public const ALLOWED = [10, 20, 50];

    public const DEFAULT = 10;

    public static function resolve(?Request $request = null): int
    {
        $request ??= request();
        $value = (int) $request->query('per_page', self::DEFAULT);

        return in_array($value, self::ALLOWED, true) ? $value : self::DEFAULT;
    }

    public static function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'last_page' => max(1, $page->lastPage()),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
        ];
    }
}
