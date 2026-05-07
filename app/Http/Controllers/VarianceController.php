<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VarianceController extends Controller
{
    public function index(Request $request)
    {
        $customerCode = $request->string('customer')->toString();
        $selectedBatchId = $request->integer('batch_id') ?: null;

        $customers = Customer::orderBy('name')->get(['id', 'name', 'code']);
        $selectedCustomer = $customerCode
            ? Customer::where('code', $customerCode)->first()
            : null;

        $batchOptions = $selectedCustomer
            ? UploadBatch::query()
                ->where('customer_id', $selectedCustomer->id)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->get(['id', 'batch_uuid', 'source_file', 'sheet_name', 'created_at'])
                ->map(fn ($batch) => $this->batchMeta($batch))
                ->values()
            : collect();

        $currentBatches = $this->currentBatches($selectedCustomer, $selectedBatchId);

        $comparisons = $currentBatches
            ->map(fn ($batch) => $this->compareBatch($batch))
            ->filter()
            ->values();

        $allRows = $comparisons
            ->flatMap(fn ($comparison) => collect($comparison['rows'])->map(function ($row) use ($comparison) {
                return array_merge($row, [
                    'customer' => $comparison['customer'],
                    'current_file' => $comparison['current_batch']['source_file'] ?? null,
                    'previous_file' => $comparison['previous_batch']['source_file'] ?? null,
                ]);
            }))
            ->filter(fn ($row) => (int) ($row['variance_qty'] ?? 0) !== 0)
            ->sortByDesc(fn ($row) => abs((int) ($row['variance_qty'] ?? 0)))
            ->values();

        $totals = [
            'current_qty' => $comparisons->sum(fn ($item) => $item['totals']['current_qty'] ?? 0),
            'previous_qty' => $comparisons->sum(fn ($item) => $item['totals']['previous_qty'] ?? 0),
            'variance_qty' => $comparisons->sum(fn ($item) => $item['totals']['variance_qty'] ?? 0),
            'increase_count' => $allRows->where('variance_qty', '>', 0)->count(),
            'decrease_count' => $allRows->where('variance_qty', '<', 0)->count(),
            'changed_parts' => $allRows->pluck('part_number')->unique()->count(),
        ];

        return Inertia::render('Variance/Index', [
            'customers' => $customers,
            'batchOptions' => $batchOptions,
            'filters' => [
                'customer' => $customerCode,
                'batch_id' => $selectedBatchId,
            ],
            'summary' => $totals,
            'comparisons' => $comparisons,
            'rows' => $allRows->take(500)->values(),
        ]);
    }

    private function currentBatches(?Customer $customer, ?int $selectedBatchId)
    {
        if ($selectedBatchId) {
            return UploadBatch::query()
                ->whereKey($selectedBatchId)
                ->where('status', 'completed')
                ->get();
        }

        if ($customer) {
            return UploadBatch::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->orderByDesc('created_at')
                ->limit(1)
                ->get();
        }

        return UploadBatch::query()
            ->with('customer')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get()
            ->unique('customer_id')
            ->values();
    }

    private function compareBatch(UploadBatch $currentBatch): ?array
    {
        $previousBatch = UploadBatch::query()
            ->where('customer_id', $currentBatch->customer_id)
            ->where('id', '<>', $currentBatch->id)
            ->where('created_at', '<', $currentBatch->created_at)
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->first();

        if (!$previousBatch) {
            return [
                'customer' => $currentBatch->customer?->code ?? '-',
                'current_batch' => $this->batchMeta($currentBatch),
                'previous_batch' => null,
                'totals' => $this->emptyTotals(),
                'rows' => [],
            ];
        }

        $currentRows = $this->rowsForBatch($currentBatch);
        $previousRows = $this->rowsForBatch($previousBatch);
        $keys = $currentRows->keys()->merge($previousRows->keys())->unique()->values();

        $rows = $keys->map(function ($key) use ($currentRows, $previousRows) {
            $current = $currentRows->get($key);
            $previous = $previousRows->get($key);
            $base = $current ?: $previous;
            $currentQty = (int) ($current['qty'] ?? 0);
            $previousQty = (int) ($previous['qty'] ?? 0);
            $delta = $currentQty - $previousQty;

            return [
                'part_number' => $base['part_number'] ?? '-',
                'order_type' => $base['order_type'] ?? null,
                'month' => $base['month'] ?? null,
                'week' => $base['week'] ?? null,
                'etd' => $base['etd'] ?? null,
                'eta' => $base['eta'] ?? null,
                'port' => $base['port'] ?? null,
                'previous_qty' => $previousQty,
                'current_qty' => $currentQty,
                'variance_qty' => $delta,
                'variance_percent' => $previousQty === 0 ? null : round(($delta / $previousQty) * 100, 2),
            ];
        })->values();

        return [
            'customer' => $currentBatch->customer?->code ?? '-',
            'current_batch' => $this->batchMeta($currentBatch),
            'previous_batch' => $this->batchMeta($previousBatch),
            'totals' => [
                'current_qty' => $rows->sum('current_qty'),
                'previous_qty' => $rows->sum('previous_qty'),
                'variance_qty' => $rows->sum('variance_qty'),
            ],
            'rows' => $rows,
        ];
    }

    private function rowsForBatch(UploadBatch $batch)
    {
        $summaryRows = Summary::query()
            ->where('upload_batch_id', $batch->id)
            ->whereRaw('UPPER(order_type) = ?', ['FIRM'])
            ->get();

        if ($summaryRows->isNotEmpty()) {
            return $summaryRows
                ->map(fn ($row) => [
                    'part_number' => $row->part_number,
                    'order_type' => $row->order_type,
                    'month' => $row->month,
                    'week' => $row->week,
                    'etd' => optional($row->etd)->toDateString(),
                    'eta' => optional($row->eta)->toDateString(),
                    'port' => $row->port,
                    'qty' => $row->total_qty,
                ])
                ->keyBy(fn ($row) => $this->varianceKey($row));
        }

        return SR::query()
            ->where(function ($query) use ($batch) {
                $query->where('upload_batch_id', $batch->id)
                    ->orWhere('upload_batch', $batch->batch_uuid);
            })
            ->whereRaw('UPPER(order_type) = ?', ['FIRM'])
            ->get()
            ->groupBy(fn ($row) => $this->varianceKey([
                'part_number' => $row->part_number,
                'order_type' => $row->order_type,
                'month' => $row->month,
                'week' => $row->week,
                'etd' => $row->etd,
                'eta' => $row->eta,
                'port' => $row->port,
            ]))
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'part_number' => $first->part_number,
                    'order_type' => $first->order_type,
                    'month' => $first->month,
                    'week' => $first->week,
                    'etd' => $first->etd,
                    'eta' => $first->eta,
                    'port' => $first->port,
                    'qty' => $rows->sum('qty'),
                ];
            });
    }

    private function varianceKey(array $row): string
    {
        return implode('|', [
            $row['part_number'] ?? '',
            $row['order_type'] ?? '',
            $row['month'] ?? '',
            $row['week'] ?? '',
            $row['etd'] ?? '',
            $row['eta'] ?? '',
            $row['port'] ?? '',
        ]);
    }

    private function batchMeta(UploadBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'batch_uuid' => $batch->batch_uuid,
            'source_file' => $batch->source_file,
            'sheet_name' => $batch->sheet_name,
            'uploaded_at' => optional($batch->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'current_qty' => 0,
            'previous_qty' => 0,
            'variance_qty' => 0,
        ];
    }
}
