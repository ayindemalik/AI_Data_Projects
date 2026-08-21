<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Case-insensitive matching against a translated JSON column.
 *
 * MySQL returns JSON_EXTRACT results collated utf8mb4_bin, so a plain LIKE is
 * CASE-SENSITIVE: searching "lavabo" finds nothing while "Lavabo" finds 378
 * rows. SQLite has no JSON_UNQUOTE at all, and its LIKE is case-insensitive
 * only for ASCII — which is why this divergence hides from the test suite.
 *
 * Lowercasing happens in SQL on both sides of the comparison rather than in
 * PHP, so one engine's idea of case is used throughout. That matters in
 * Turkish, where PHP's mb_strtolower('İ') yields "i̇" (i + combining dot)
 * while MySQL's LOWER() yields plain "i".
 */
class JsonText
{
    /**
     * SQL that lowercases one translation of a JSON column.
     * e.g. lower('name', 'tr') for products.name->>'$.tr'
     */
    public static function lower(string $column, string $path): string
    {
        $expression = DB::getDriverName() === 'sqlite'
            ? "json_extract({$column}, '$.{$path}')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}'))";

        return "LOWER({$expression})";
    }
}
