<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Port;
use App\Models\SR;
use App\Models\SPP;
use App\Models\Summary;
use App\Models\UploadBatch;
use App\Models\CarLine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Hitung total data
        $totalCustomers = Customer::count();
        $totalPorts = Port::count();
        $totalSR = SR::count();
        $totalSPP = SPP::count();
        $totalCarlines = CarLine::count();
        
        // Optional: Ambil data untuk grafik atau chart
        $recentCustomers = Customer::latest()->take(5)->get();
        $recentSR = SR::latest()->take(5)->get();
        
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_customers' => $totalCustomers,
                'total_ports' => $totalPorts,
                'total_sr' => $totalSR,
                'total_spp' => $totalSPP,
                'total_carlines' => $totalCarlines,
            ],
            'recent_customers' => $recentCustomers,
            'recent_sr' => $recentSR,
            'varianceChart' => $this->varianceChart(),
        ]);
    }

    private function varianceChart(): array
    {
        $comparisons = UploadBatch::query()
            ->with('customer')
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->get()
            ->unique('customer_id')
            ->map(fn ($batch) => $this->compareBatch($batch))
            ->filter()
            ->values();

        $rows = $comparisons
            ->flatMap(fn ($comparison) => collect($comparison['rows'])->map(function ($row) use ($comparison) {
                return array_merge($row, ['customer' => $comparison['customer']]);
            }))
            ->filter(fn ($row) => (int) ($row['variance_qty'] ?? 0) !== 0)
            ->values();

        $byCustomer = $comparisons
            ->map(function ($comparison) {
                return [
                    'customer' => $comparison['customer'],
                    'current_qty' => $comparison['totals']['current_qty'] ?? 0,
                    'previous_qty' => $comparison['totals']['previous_qty'] ?? 0,
                    'variance_qty' => $comparison['totals']['variance_qty'] ?? 0,
                    'current_file' => $comparison['current_batch']['source_file'] ?? null,
                    'previous_file' => $comparison['previous_batch']['source_file'] ?? null,
                ];
            })
            ->sortByDesc(fn ($row) => abs((int) $row['variance_qty']))
            ->take(8)
            ->values();

        return [
            'summary' => [
                'current_qty' => $comparisons->sum(fn ($item) => $item['totals']['current_qty'] ?? 0),
                'previous_qty' => $comparisons->sum(fn ($item) => $item['totals']['previous_qty'] ?? 0),
                'variance_qty' => $comparisons->sum(fn ($item) => $item['totals']['variance_qty'] ?? 0),
                'increase_count' => $rows->where('variance_qty', '>', 0)->count(),
                'decrease_count' => $rows->where('variance_qty', '<', 0)->count(),
                'changed_parts' => $rows->pluck('part_number')->unique()->count(),
            ],
            'by_customer' => $byCustomer,
            'top_parts' => $rows
                ->sortByDesc(fn ($row) => abs((int) ($row['variance_qty'] ?? 0)))
                ->take(5)
                ->values(),
        ];
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
                'totals' => $this->emptyVarianceTotals(),
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

            return [
                'part_number' => $base['part_number'] ?? '-',
                'order_type' => $base['order_type'] ?? null,
                'month' => $base['month'] ?? null,
                'week' => $base['week'] ?? null,
                'current_qty' => $currentQty,
                'previous_qty' => $previousQty,
                'variance_qty' => $currentQty - $previousQty,
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
                    'week' => $this->normalizeWeek($row->week),
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
                'week' => $this->normalizeWeek($row->week),
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
                    'week' => $this->normalizeWeek($first->week),
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

    private function normalizeWeek($week)
    {
        if ($week === null || $week === '') {
            return '';
        }

        if (is_numeric($week)) {
            return (int) $week;
        }

        if (preg_match('/\d+/', (string) $week, $matches)) {
            return (int) $matches[0];
        }

        return $week;
    }

    private function batchMeta(UploadBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'source_file' => $batch->source_file,
            'sheet_name' => $batch->sheet_name,
            'uploaded_at' => optional($batch->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function emptyVarianceTotals(): array
    {
        return [
            'current_qty' => 0,
            'previous_qty' => 0,
            'variance_qty' => 0,
        ];
    }
}
