<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DateRange
{
    public static function dateString($date): ?string
    {
        return filled($date) ? Carbon::parse($date)->toDateString() : null;
    }

    public static function apply($query, string $column, $fromDate = null, $toDate = null, bool $includeGivenDate = true): void
    {
        $from = self::dateString($fromDate);
        $to = self::dateString($toDate);
        $columnExpr = DB::raw("DATE($column)");

        if ($from && $to) {
            if ($includeGivenDate) {
                $query->whereBetween($columnExpr, [$from, $to]);
                return;
            }

            $query->where($columnExpr, '>', $from)->where($columnExpr, '<', $to);
            return;
        }

        if ($from) {
            $query->where($columnExpr, $includeGivenDate ? '>=' : '>', $from);
            return;
        }

        if ($to) {
            $query->where($columnExpr, $includeGivenDate ? '<=' : '<', $to);
        }
    }
}
