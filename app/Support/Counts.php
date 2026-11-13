<?php

namespace App\Support;

class Counts
{
    public static function ints(object $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = (int) round((float) ($row->{$key} ?? 0));
        }

        return $out;
    }
}
