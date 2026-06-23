<?php

namespace App\Services\Master;

use App\Models\Assy;
use Illuminate\Support\Facades\DB;

class MasterAssyResolverService
{
    public function apply(array $mapped): array
    {
        // Load all assys with carline into memory since the master assy table is very small (~283 rows).
        // This avoids running slow REPLACE SQL queries that bypass database indexes.
        $assyMap = Assy::with('carline')
            ->get()
            ->keyBy(function ($assy) {
                return str_replace(['-', ' '], '', $assy->assy_number);
            });

        $unknownAssyNumbers = [];

        foreach ($mapped as &$item) {
            $cleanKey = str_replace(['-', ' '], '', $item['assy_number'] ?? '');
            $assy = $assyMap->get($cleanKey);

            if ($assy) {
                $item['assy_id'] = $assy->id;
                $item['assy_number'] = $assy->assy_number; // Standardize to Master format
                $item['carline_id'] = $assy->carline_id;
                $item['model'] = $item['model'] ?? $assy->carline?->code;
                $item['family'] = $item['family'] ?? $assy->carline?->description;
                $item['is_mapped'] = true;
                $item['mapping_error'] = null;

                continue;
            }

            $item['assy_id'] = null;
            $item['is_mapped'] = false;
            $item['mapping_error'] = 'Assy number '.($item['assy_number'] ?? '-').' tidak ditemukan di master assy.';
            $unknownAssyNumbers[] = $item['assy_number'] ?? null;
        }
        unset($item);

        return [
            $mapped,
            array_values(array_unique(array_filter($unknownAssyNumbers))),
        ];
    }
}
