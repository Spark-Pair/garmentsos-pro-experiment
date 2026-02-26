<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait Filterable
{
    public function scopeApplyFilters($query, Request $request, $returnCollection = true, $noLimit = false)
    {
        $filters = $request->except(['_token', 'limit', 'page']);

        // Transform date range inputs (dual input fields) only when both values exist.
        // If one side is missing, skip date filtering instead of creating invalid column filters.
        if (array_key_exists('date_range_start', $filters) || array_key_exists('date_range_end', $filters)) {
            $start = trim((string) ($filters['date_range_start'] ?? ''));
            $end = trim((string) ($filters['date_range_end'] ?? ''));

            if ($start !== '' && $end !== '') {
                $filters['date'] = [
                    'start' => $start,
                    'end' => $end,
                ];
            }

            unset($filters['date_range_start'], $filters['date_range_end']);
        }

        $limit = $request->get('limit');

        foreach ($filters as $key => $value) {
            if (empty($value) && $value !== '0') continue;

            if (method_exists($this, 'scopeApplyModelFilters')) {
                $query->applyModelFilters($key, $value);
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
