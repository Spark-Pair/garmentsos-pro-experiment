<?php

namespace App\Traits;

use App\Support\DateRange;
use Illuminate\Http\Request;

trait Filterable
{
    public function scopeApplyFilters($query, Request $request, $returnCollection = true, $noLimit = false)
    {
        $filters = $request->except(['_token', 'limit', 'page']);

        // Transform date range inputs (dual input fields) only when both values exist.
        // If one side is missing, skip date filtering instead of creating invalid column filters.
        $rangeMap = [
            'date' => ['date_range_start', 'date_range_end'],
            'created_at' => ['created_range_start', 'created_range_end'],
        ];

        foreach ($rangeMap as $field => [$startKey, $endKey]) {
            if (array_key_exists($startKey, $filters) || array_key_exists($endKey, $filters)) {
                $start = trim((string) ($filters[$startKey] ?? ''));
                $end = trim((string) ($filters[$endKey] ?? ''));

                if ($start !== '' && $end !== '') {
                    $filters[$field] = [
                        'start' => $start,
                        'end' => $end,
                    ];
                }

                unset($filters[$startKey], $filters[$endKey]);
            }
        }

        $limit = $request->get('limit');

        foreach ($filters as $key => $value) {
            if (empty($value) && $value !== '0') continue;

            if (method_exists($this, 'scopeApplyModelFilters')) {
                $query->applyModelFilters($key, $value);
            } elseif (is_array($value) && isset($value['start'], $value['end'])) {
                DateRange::apply($query, $key, $value['start'], $value['end']);
            } else {
                $query->where($key, 'like', "%{$value}%");
            }
        }

        if ($limit && !$noLimit) {
            $query->limit($limit);
        }

        if ($returnCollection) {
            return $query->get()->map->toFormattedArray();
        }

        return $query;
    }
}
