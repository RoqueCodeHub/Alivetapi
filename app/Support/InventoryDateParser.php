<?php

namespace App\Support;

use Carbon\Carbon;

class InventoryDateParser
{
    private const MONTHS = [
        'enero' => 1,
        'febrero' => 2,
        'marzo' => 3,
        'abril' => 4,
        'mayo' => 5,
        'junio' => 6,
        'julio' => 7,
        'agosto' => 8,
        'septiembre' => 9,
        'setiembre' => 9,
        'octubre' => 10,
        'noviembre' => 11,
        'diciembre' => 12,
        'ene' => 1,
        'feb' => 2,
        'mar' => 3,
        'abr' => 4,
        'may' => 5,
        'jun' => 6,
        'jul' => 7,
        'ago' => 8,
        'set' => 9,
        'sep' => 9,
        'oct' => 10,
        'nov' => 11,
        'dic' => 12,
    ];

    public static function lastDayOfMonth(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (float) $value;
            if ($number > 20000 && $number < 80000) {
                $timestamp = (int) round(($number - 25569) * 86400);
                $date = Carbon::createFromTimestampUTC($timestamp);

                return $date->endOfMonth()->toDateString();
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = mb_strtolower(str_replace(['/', '.', '_'], '-', $raw));
        $normalized = preg_replace('/\s+/', '-', $normalized) ?? $normalized;

        foreach (self::MONTHS as $name => $month) {
            if (preg_match('/^'.$name.'-(\d{4})$/u', $normalized, $m)) {
                return Carbon::create((int) $m[1], $month, 1)->endOfMonth()->toDateString();
            }
        }

        if (preg_match('/^(\d{1,2})-(\d{4})$/', $normalized, $m)) {
            $month = (int) $m[1];
            $year = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
            }
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', $normalized, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
            }
        }

        try {
            $date = Carbon::parse($raw);

            return $date->endOfMonth()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
