<?php

namespace App\Services\Summary;

use App\Models\Summary;
use App\Models\UploadBatch;
use App\Services\Utilities\PlanningCacheService;

class SummaryGeneratorService
{
    public function __construct(private readonly PlanningCacheService $cache)
    {
    }

    public function regenerateForBatch(array $mapped, UploadBatch $uploadBatch): int
    {
        Summary::where('upload_batch_id', $uploadBatch->id)->delete();

        $buckets = collect($mapped)
            ->filter(fn ($item) => ! empty($item['assy_number']))
            ->groupBy(fn ($item) => implode('|', [
                $item['assy_number'] ?? '',
                $item['order_type'] ?? '',
                $item['etd'] ?? '',
                $item['eta'] ?? '',
                $item['month'] ?? '',
                $item['week'] ?? '',
                $item['production_week_id'] ?? '',
            ]))
            ->map(function ($rows) use ($uploadBatch) {
                $first = $rows->first();

                return [
                    'upload_batch_id' => $uploadBatch->id,
                    'customer_id' => $uploadBatch->customer_id,
                    'port_id' => $uploadBatch->port_id,
                    'assy_id' => $first['assy_id'] ?? null,
                    'production_week_id' => $first['production_week_id'] ?? null,
                    'etd' => $first['etd'] ?? null,
                    'eta' => $first['eta'] ?? null,
                    'month' => $first['month'] ?? null,
                    'week' => $first['week'] ?? null,
                    'assy_number' => $first['assy_number'],
                    'order_type' => $first['order_type'] ?? null,
                    'line_count' => $rows->count(),
                    'total_qty' => $rows->sum(fn ($item) => (int) ($item['qty'] ?? 0)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values();

        foreach ($buckets->chunk(500) as $chunk) {
            Summary::insert($chunk->all());
        }

        $this->cache->invalidate('summary');
        $this->cache->invalidate('dashboard');

        return $buckets->count();
    }
}
