<?php

namespace App\Services\SR;

use App\Models\Assy;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use Illuminate\Support\Facades\DB;

class UnmappedAssyService
{
    /**
     * Get unmapped items list, summary counts, and distinct customers.
     */
    public function getUnmappedData(array $filters, ?int $userId, bool $isAdmin, int $perPage = 25): array
    {
        $itemsQuery = SR::query()
            ->join('customers', 'srs.customer_id', '=', 'customers.id')
            ->leftJoin('upload_batches', 'srs.upload_batch_id', '=', 'upload_batches.id')
            ->where('srs.is_mapped', false)
            ->whereNotNull('srs.assy_number')
            ->when(!$isAdmin, fn($q) => $q->where('upload_batches.uploaded_by', $userId))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('srs.assy_number', 'like', '%'.$search.'%');
            })
            ->when($filters['customer'] ?? null, function ($query, $customer) {
                $query->where('customers.code', $customer);
            });

        $items = $itemsQuery
            ->selectRaw('
                srs.assy_number,
                customers.code as customer,
                COUNT(*) as record_count,
                SUM(srs.qty) as total_qty,
                MAX(srs.created_at) as latest_upload,
                MAX(upload_batches.source_file) as latest_source_file,
                MAX(upload_batches.batch_uuid) as latest_batch
            ')
            ->groupBy('srs.assy_number', 'customers.code')
            ->orderByDesc('latest_upload')
            ->paginate($perPage)
            ->withQueryString();

        $summary = [
            'unique_assy' => SR::query()
                ->where('is_mapped', false)
                ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                ->distinct('assy_number')
                ->count('assy_number'),
            'records' => SR::query()
                ->where('is_mapped', false)
                ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                ->count(),
            'total_qty' => (int) SR::query()
                ->where('is_mapped', false)
                ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                ->sum('qty'),
        ];

        $customers = SR::query()
            ->join('customers', 'srs.customer_id', '=', 'customers.id')
            ->leftJoin('upload_batches', 'srs.upload_batch_id', '=', 'upload_batches.id')
            ->where('srs.is_mapped', false)
            ->whereNotNull('customers.code')
            ->when(!$isAdmin, fn($q) => $q->where('upload_batches.uploaded_by', $userId))
            ->distinct()
            ->orderBy('customers.code')
            ->pluck('customers.code')
            ->values();

        return [
            'items' => $items,
            'summary' => $summary,
            'customers' => $customers,
        ];
    }

    /**
     * Remap the chosen unmapped assy numbers to newly active/available master assy records.
     */
    public function remap(array $rawAssyNumbers, ?int $userId, bool $isAdmin): array
    {
        $assyNumbers = collect($rawAssyNumbers)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($assyNumbers->isEmpty()) {
            throw new \InvalidArgumentException('Pilih minimal satu assy untuk diremap.');
        }

        $masters = Assy::with('carline')
            ->whereIn('assy_number', $assyNumbers)
            ->get()
            ->keyBy('assy_number');

        $remapped = 0;
        $touchedBatchIds = collect();

        DB::transaction(function () use ($assyNumbers, $masters, &$remapped, $touchedBatchIds, $isAdmin, $userId) {
            foreach ($assyNumbers as $assyNumber) {
                $assy = $masters->get($assyNumber);

                if (! $assy) {
                    continue;
                }

                $batchQuery = SR::query()
                    ->where('is_mapped', false)
                    ->where('assy_number', $assyNumber)
                    ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)));

                $batchIds = (clone $batchQuery)->pluck('upload_batch_id');

                $affected = $batchQuery->update([
                    'assy_id' => $assy->id,
                    'carline_id' => $assy->carline_id,
                    'model' => $assy->carline?->code,
                    'family' => $assy->carline?->description,
                    'is_mapped' => true,
                    'mapping_error' => null,
                    'updated_at' => now(),
                ]);

                Summary::query()
                    ->whereNull('assy_id')
                    ->where('assy_number', $assyNumber)
                    ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                    ->update([
                        'assy_id' => $assy->id,
                        'model' => $assy->carline?->code,
                        'family' => $assy->carline?->description,
                        'updated_at' => now(),
                    ]);

                $remapped += $affected;
                $touchedBatchIds->push(...$batchIds->filter()->all());
            }

            $touchedBatchIds
                ->unique()
                ->each(function ($batchId) {
                    $batch = UploadBatch::find($batchId);

                    if (! $batch) {
                        return;
                    }

                    $batch->update([
                        'mapped_count' => $batch->srs()->where('is_mapped', true)->count(),
                        'unmapped_count' => $batch->srs()->where('is_mapped', false)->count(),
                    ]);
                });
        });

        $notFoundCount = $assyNumbers->count() - $masters->count();

        return [
            'remapped' => $remapped,
            'notFoundCount' => $notFoundCount,
        ];
    }
}
