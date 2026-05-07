<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class SPPController extends Controller
{
    public function index(Request $request)
    {
        $srList = $this->batchSummaryQuery($request)
            ->orderByRaw('MIN(created_at) desc')
            ->get();

        return Inertia::render('SPP/Index', [
            'srList' => $srList,
            'customers' => Customer::orderBy('name')->get(['name', 'code']),
            'filters' => $request->only(['customer', 'search']),
        ]);
    }

    public function preview($id)
    {
        $sr = SR::findOrFail($id);
        $records = $this->batchRows($sr)
            ->with(['assy.carline'])
            ->whereNotNull('eta')
            ->get();

        $firstEta = $records->pluck('eta')->filter()->sort()->first();
        $start = $firstEta
            ? Carbon::parse($firstEta)->startOfMonth()
            : Carbon::now()->subMonth()->startOfMonth();
        $currentMonth = Carbon::now()->startOfMonth();
        $months = $this->sppMonths($start, $currentMonth);
        $rows = $this->sppRows($records, $months, 'qty');
        $monthTotals = $this->monthTotals($rows, $months);

        return Inertia::render('SPP/Preview', [
            'sr' => [
                'id' => $sr->id,
                'customer' => $sr->customer,
                'port' => $sr->port,
                'source_file' => $sr->source_file,
                'sheet_name' => $sr->sheet_name,
                'upload_batch' => $sr->upload_batch,
                'upload_date' => optional($sr->created_at)->format('Y-m-d H:i:s'),
            ],
            'summary' => [
                'total_records' => $records->count(),
                'total_qty' => $records->sum('qty'),
                'unique_parts' => $records->pluck('part_number')->filter()->unique()->count(),
                'period_range' => sprintf(
                    '%s - %s',
                    $months->first()['range_label'] ?? '-',
                    $months->last()['range_label'] ?? '-'
                ),
            ],
            'months' => $months->values(),
            'rows' => $rows->values(),
            'monthTotals' => $monthTotals,
        ]);
    }

    public function show(Request $request, $period)
    {
        $customers = Customer::orderBy('name')->get(['name', 'code']);

        $query = Summary::query();
        if ($request->filled('customer')) {
            $query->where('customer', $request->customer);
        }
        if ($request->filled('sr_batch')) {
            $query->where('upload_batch_id', $request->integer('sr_batch'));
        }

        $usesSummaryTable = (clone $query)->exists();
        if (!$usesSummaryTable) {
            $query = SR::query();
            if ($request->filled('customer')) {
                $query->where('customer', $request->customer);
            }
            if ($request->filled('sr_batch')) {
                $query->where('upload_batch_id', $request->integer('sr_batch'));
            }
        }

        $date = Carbon::createFromFormat('Y-m', $period);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $records = $query->whereBetween('eta', [$start->toDateString(), $end->toDateString()])->get();
        $qtyColumn = $usesSummaryTable ? 'total_qty' : 'qty';

        $summary = [
            'period' => $date->format('F Y'),
            'total_records' => $records->count(),
            'total_qty' => $records->sum($qtyColumn),
            'unique_parts' => $records->pluck('part_number')->unique()->count(),
            'source' => $usesSummaryTable ? 'summary' : 'sr',
            'selected_sr' => $this->selectedBatch($request),
        ];

        return Inertia::render('SPP/Show', [
            'customers' => $customers,
            'srBatches' => $this->srBatchOptions($request),
            'filters' => $request->only('customer', 'sr_batch'),
            'period' => $period,
            'records' => $records,
            'summary' => $summary,
        ]);
    }

    private function baseQuery(Request $request): array
    {
        $summaryQuery = Summary::query();
        if ($request->filled('customer')) {
            $summaryQuery->where('customer', $request->customer);
        }
        if ($request->filled('sr_batch')) {
            $summaryQuery->where('upload_batch_id', $request->integer('sr_batch'));
        }

        if ((clone $summaryQuery)->exists()) {
            return [
                'source' => 'summary',
                'query' => $summaryQuery,
            ];
        }

        $srQuery = SR::query();
        if ($request->filled('customer')) {
            $srQuery->where('customer', $request->customer);
        }
        if ($request->filled('sr_batch')) {
            $srQuery->where('upload_batch_id', $request->integer('sr_batch'));
        }

        return [
            'source' => 'sr',
            'query' => $srQuery,
        ];
    }

    private function batchRows(SR $sr)
    {
        return SR::query()
            ->when(
                $sr->upload_batch,
                fn ($query) => $query->where('upload_batch', $sr->upload_batch),
                fn ($query) => $query->whereKey($sr->id)
            )
            ->orderBy('eta')
            ->orderBy('part_number');
    }

    private function batchSummaryQuery(Request $request)
    {
        return $this->applyFilters(SR::query(), $request)
            ->selectRaw('
                MIN(id) as id,
                customer,
                port,
                source_file,
                upload_batch,
                MIN(sheet_name) as sheet_name,
                MIN(created_at) as upload_date,
                COUNT(*) as total_items,
                SUM(qty) as total_qty,
                SUM(CASE WHEN order_type = \'FIRM\' THEN qty ELSE 0 END) as firm_qty,
                SUM(CASE WHEN order_type = \'FORECAST\' THEN qty ELSE 0 END) as forecast_qty,
                COUNT(DISTINCT part_number) as unique_parts,
                MIN(eta) as earliest_eta,
                MAX(eta) as latest_eta
            ')
            ->groupBy('customer', 'port', 'source_file', 'upload_batch');
    }

    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('customer'), fn ($query) => $query->where('customer', $request->customer))
            ->when($request->filled('search'), fn ($query) => $query->where('source_file', 'like', '%' . $request->search . '%'));
    }

    private function srBatchOptions(Request $request): Collection
    {
        return UploadBatch::query()
            ->with(['customer:id,code,name'])
            ->when($request->filled('customer'), function ($query) use ($request) {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('code', $request->customer));
            })
            ->where(function ($query) {
                $query->whereHas('orderSummaries')
                    ->orWhereHas('srs');
            })
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (UploadBatch $batch) => [
                'id' => $batch->id,
                'customer' => $batch->customer?->code,
                'customer_name' => $batch->customer?->name,
                'source_file' => $batch->source_file,
                'sheet_name' => $batch->sheet_name,
                'record_count' => $batch->record_count,
                'total_qty' => $batch->total_qty,
                'uploaded_at' => $batch->created_at?->format('Y-m-d H:i'),
                'label' => trim(sprintf(
                    '%s - %s%s',
                    $batch->customer?->code ?: 'SR',
                    $batch->source_file,
                    $batch->sheet_name ? ' / ' . $batch->sheet_name : ''
                )),
            ]);
    }

    private function selectedBatch(Request $request): ?array
    {
        if (!$request->filled('sr_batch')) {
            return null;
        }

        $batch = UploadBatch::query()
            ->with(['customer:id,code,name'])
            ->find($request->integer('sr_batch'));

        if (!$batch) {
            return null;
        }

        return [
            'id' => $batch->id,
            'customer' => $batch->customer?->code,
            'source_file' => $batch->source_file,
            'sheet_name' => $batch->sheet_name,
            'record_count' => $batch->record_count,
            'total_qty' => $batch->total_qty,
            'uploaded_at' => $batch->created_at?->format('Y-m-d H:i'),
        ];
    }

    private function sppMonths(Carbon $start, Carbon $currentMonth): Collection
    {
        return collect(range(0, 5))->map(function ($offset) use ($start, $currentMonth) {
            $date = $start->copy()->addMonths($offset);
            $period = $date->format('Y-m');

            return [
                'period' => $period,
                'label' => strtoupper($date->format('M')),
                'label_full' => $date->format('F Y'),
                'range_label' => $date->format('M Y'),
                'year' => $date->format('Y'),
                'bucket' => $date->lessThanOrEqualTo($currentMonth) ? 'firm' : 'forecast',
            ];
        });
    }

    private function sppRows(Collection $items, Collection $months, string $qtyColumn): Collection
    {
        $periods = $months->pluck('period')->all();

        return $items
            ->groupBy(fn ($item) => $item->part_number ?: '-')
            ->map(function ($partRows, $partNumber) use ($months, $periods, $qtyColumn) {
                $first = $partRows->first();
                $assy = $first->assy;
                $monthly = [];

                foreach ($months as $month) {
                    $period = $month['period'];
                    $monthRows = $partRows->filter(fn ($item) => $this->itemPeriod($item) === $period);
                    $qty = (int) $monthRows->sum($qtyColumn);

                    $monthly[$period] = [
                        'bal' => 0,
                        'del' => $qty,
                        'prod' => $qty,
                        'order_type' => $month['bucket'] === 'firm' ? 'FIRM' : 'FORECAST',
                    ];
                }

                return [
                    'customer' => $first->customer,
                    'type' => $first->model ?: $first->family ?: $assy?->type ?: '',
                    'carline' => $assy?->carline?->code,
                    'part_number' => $partNumber,
                    'level' => $assy?->level ?: '',
                    'assy_code' => $assy?->assy_code ?: '',
                    'cct' => $assy?->cct ?: '',
                    'std_pack' => $assy?->std_pack ?: '',
                    'umh' => $assy?->umh ?: '',
                    'months' => $monthly,
                    'total_qty' => collect($periods)->sum(fn ($period) => $monthly[$period]['del'] ?? 0),
                ];
            })
            ->sortBy([
                ['type', 'asc'],
                ['part_number', 'asc'],
            ])
            ->values();
    }

    private function itemPeriod($item): ?string
    {
        if ($item->eta) {
            return Carbon::parse($item->eta)->format('Y-m');
        }

        if ($item->month && preg_match('/^\d{4}-\d{2}$/', (string) $item->month)) {
            return $item->month;
        }

        return null;
    }

    private function monthTotals(Collection $rows, Collection $months): array
    {
        return $months
            ->mapWithKeys(function ($month) use ($rows) {
                $period = $month['period'];

                return [
                    $period => [
                        'bal' => $rows->sum(fn ($row) => $row['months'][$period]['bal'] ?? 0),
                        'del' => $rows->sum(fn ($row) => $row['months'][$period]['del'] ?? 0),
                        'prod' => $rows->sum(fn ($row) => $row['months'][$period]['prod'] ?? 0),
                    ],
                ];
            })
            ->all();
    }
}
