<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Hands out document numbers such as NCR-2026-0001, one counter per prefix and year.
 */
class Sequence
{
    public static function next(string $prefix): string
    {
        $year = now()->year;

        return DB::transaction(function () use ($prefix, $year) {
            DB::table('sequences')->insertOrIgnore(['prefix' => $prefix, 'year' => $year, 'last_number' => 0]);

            $row = DB::table('sequences')->where(['prefix' => $prefix, 'year' => $year])->lockForUpdate()->first();
            $number = (int) $row->last_number + 1;

            DB::table('sequences')->where('id', $row->id)->update(['last_number' => $number]);

            return sprintf('%s-%d-%04d', $prefix, $year, $number);
        });
    }
}
