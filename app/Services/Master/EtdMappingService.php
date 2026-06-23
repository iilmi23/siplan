<?php

namespace App\Services\Master;

use App\Models\Customer;
use App\Models\EtdMapping;
use App\Models\ProductionWeek;
use Illuminate\Support\Facades\DB;

class EtdMappingService
{
    /**
     * Get mappings and available weeks for a given customer.
     */
    public function getCustomerMappings(int|string $customerId): array
    {
        $customer = Customer::findOrFail($customerId);

        $mappings = EtdMapping::with('productionWeek')
            ->where('customer_id', $customerId)
            ->orderBy('etd_date', 'asc')
            ->get()
            ->map(function ($mapping) use ($customerId) {
                // Ambil semua weeks yang tersedia untuk customer ini
                $availableWeeks = ProductionWeek::where('customer_id', $customerId)
                    ->orderBy('year', 'asc')
                    ->orderBy('month_number', 'asc')
                    ->orderBy('week_no', 'asc')
                    ->get()
                    ->map(function ($week) {
                        return [
                            'id' => $week->id,
                            'label' => "{$week->month_name} {$week->year} - Week {$week->week_no} (" . $week->week_start->format('d/m/Y') . ")",
                        ];
                    });

                return [
                    'id' => $mapping->id,
                    'etd_date' => $mapping->etd_date->format('Y-m-d'),
                    'production_week_id' => $mapping->production_week_id,
                    'week_label' => $mapping->productionWeek ?
                        "{$mapping->productionWeek->month_name} {$mapping->productionWeek->year} W{$mapping->productionWeek->week_no}" : '-',
                    'is_edited' => $mapping->is_edited,
                    'available_weeks' => $availableWeeks,
                ];
            });

        return [
            'customer' => $customer,
            'mappings' => $mappings,
        ];
    }

    /**
     * Sync ETDs from SR to EtdMapping
     */
    public function syncEtd(int|string $customerId): array
    {
        // Ambil semua ETD unik dari tabel srs
        $etdDates = DB::table('srs')
            ->where('customer_id', $customerId)
            ->whereNotNull('etd')
            ->distinct()
            ->pluck('etd')
            ->toArray();

        $synced = 0;
        $errors = [];

        foreach ($etdDates as $etdDate) {
            try {
                $week = ProductionWeek::findByDate($customerId, $etdDate);

                if ($week) {
                    EtdMapping::updateOrCreate(
                        [
                            'customer_id' => $customerId,
                            'etd_date' => $etdDate,
                        ],
                        [
                            'production_week_id' => $week->id,
                            'is_edited' => false,
                        ]
                    );
                    $synced++;
                }
            } catch (\Exception $e) {
                $errors[] = $etdDate . ': ' . $e->getMessage();
            }
        }

        return [
            'synced' => $synced,
            'errors' => $errors,
        ];
    }
}
