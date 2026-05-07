<?php

namespace App\Http\Controllers;

use App\Exports\SAIExport;
use App\Exports\SummaryExport;
use App\Exports\SummaryListExport;
use App\Exports\TYCExport;
use App\Exports\YCExport;
use App\Exports\YNAExport;
use App\Models\Customer;
use App\Models\ProductionWeek;
use App\Models\SR;
use App\Models\UploadBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SummaryController extends Controller
{
    public function index(Request $request)
    {
        $srList = $this->batchSummaryQuery($request)
            ->orderByRaw('MIN(created_at) desc')
            ->get();

        Log::info('Summary index', [
            'total_results' => $srList->count(),
            'filters' => $request->only($this->filterKeys()),
        ]);

        return Inertia::render('Summary/Index', [
            'srList' => $srList,
            'customers' => Customer::orderBy('name')->get(['name', 'code']),
            'filters' => $request->only($this->filterKeys()),
            'flash' => $this->flashMessages(),
        ]);
    }

    public function show($id)
    {
        $sr = SR::findOrFail($id);
        $summaryData = $this->enrichRows($this->batchRows($sr)->get());

        return Inertia::render('Summary/Show', [
            'sr' => [
                'id' => $sr->id,
                'source_file' => $sr->source_file,
                'customer' => $sr->customer,
                'port' => $sr->port,
                'month' => $sr->month,
                'sheet_name' => $sr->sheet_name,
                'upload_date' => optional($sr->created_at)->format('Y-m-d H:i:s'),
            ],
            'data' => $summaryData,
        ]);
    }

    public function data($id)
    {
        try {
            $sr = SR::findOrFail($id);
            $summaryData = $this->enrichRows($this->batchRows($sr)->get());

            $firmQty = $summaryData->where('order_type', 'FIRM')->sum('qty');
            $forecastQty = $summaryData->where('order_type', 'FORECAST')->sum('qty');
            $months = $summaryData->pluck('month')->filter()->unique()->sort()->values();

            return response()->json([
                'success' => true,
                'sr' => [
                    'id' => $sr->id,
                    'source_file' => $sr->source_file,
                    'customer' => $sr->customer,
                    'port' => $sr->port,
                    'sheet_name' => $sr->sheet_name,
                    'upload_date' => optional($sr->created_at)->format('Y-m-d H:i:s'),
                ],
                'summary' => [
                    'total_records' => $summaryData->count(),
                    'unique_parts' => $summaryData->pluck('part_number')->unique()->count(),
                    'firm_qty' => $firmQty,
                    'forecast_qty' => $forecastQty,
                    'total_qty' => $firmQty + $forecastQty,
                    'months_covered' => $months,
                ],
                'data' => $summaryData,
            ]);
        } catch (\Exception $e) {
            Log::error('Summary data error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Data tidak ditemukan',
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $sr = SR::findOrFail($id);
            $sourceFile = $sr->source_file;

            if ($sr->upload_batch) {
                $deletedCount = SR::where('upload_batch', $sr->upload_batch)->delete();
                UploadBatch::where('batch_uuid', $sr->upload_batch)->delete();
            } else {
                $deletedCount = $sr->delete() ? 1 : 0;
            }

            DB::commit();

            return redirect()
                ->route('summary.index')
                ->with('success', "Upload \"{$sourceFile}\" deleted! ({$deletedCount} records)");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Summary delete error: ' . $e->getMessage());

            return redirect()
                ->route('summary.index')
                ->with('error', 'Gagal hapus: ' . $e->getMessage());
        }
    }

    public function export($id)
    {
        try {
            $sr = SR::findOrFail($id);
            $summaryData = $this->enrichRows($this->batchRows($sr)->get());
            $filename = Str::slug(pathinfo((string) $sr->source_file, PATHINFO_FILENAME)) ?: $sr->id;
            $exportClass = $this->exportClassFor($sr->customer);

            return Excel::download(
                new $exportClass($summaryData),
                "Summary_{$filename}_Detail.xlsx"
            );
        } catch (\Exception $e) {
            Log::error('Summary export error: ' . $e->getMessage());

            return redirect()
                ->route('summary.index')
                ->with('error', 'Gagal export: ' . $e->getMessage());
        }
    }

    public function exportAll(Request $request)
    {
        $srList = $this->batchSummaryQuery($request)
            ->orderByRaw('MIN(created_at) desc')
            ->get();

        return Excel::download(new SummaryListExport($srList), 'Summary_List.xlsx');
    }

    private function batchRows(SR $sr)
    {
        return SR::query()
            ->with('assy.carline')
            ->when(
                $sr->upload_batch,
                fn ($query) => $query->where('upload_batch', $sr->upload_batch),
                fn ($query) => $query->whereKey($sr->id)
            )
            ->orderBy('etd')
            ->orderBy('part_number');
    }

    private function enrichRows($rows)
    {
        $customerIds = Customer::whereIn('code', $rows->pluck('customer')->filter()->unique())
            ->pluck('id', 'code');
        $weekCache = [];

        return $rows->map(function ($row) use ($customerIds, &$weekCache) {
            $carline = $row->assy?->carline;

            if (empty($row->model) && $carline) {
                $row->model = $carline->code;
            }

            if (empty($row->family) && $carline) {
                $row->family = $carline->description;
            }

            if (empty($row->week) && !empty($row->etd)) {
                $customerId = $customerIds[$row->customer] ?? null;
                $cacheKey = ($customerId ?: 'global') . '|' . $row->etd;

                if (!array_key_exists($cacheKey, $weekCache)) {
                    $weekCache[$cacheKey] = ProductionWeek::findByDate($customerId, Carbon::parse($row->etd));
                }

                $week = $weekCache[$cacheKey];
                if ($week) {
                    $row->week = $week->week_no;
                    $row->month = $row->month ?: $week->month_name;
                    $row->year = $row->year ?: $week->year;
                }
            }

            return $row;
        });
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
                COUNT(CASE WHEN order_type = \'FIRM\' THEN 1 END) as firm_count,
                COUNT(CASE WHEN order_type = \'FORECAST\' THEN 1 END) as forecast_count,
                COUNT(DISTINCT part_number) as unique_parts,
                MIN(etd) as earliest_etd,
                MAX(etd) as latest_etd
            ')
            ->groupBy('customer', 'port', 'source_file', 'upload_batch', 'sheet_name');
    }

    private function applyFilters($query, Request $request)
    {
        return $query
            ->when($request->filled('customer'), fn ($query) => $query->where('customer', $request->customer))
            ->when($request->filled('search'), fn ($query) => $query->where('source_file', 'like', '%' . $request->search . '%'))
            ->when($request->filled('part_number'), fn ($query) => $query->where('part_number', 'like', '%' . $request->part_number . '%'))
            ->when($request->filled('order_type'), fn ($query) => $query->where('order_type', $request->order_type))
            ->when($request->filled('etd_start'), fn ($query) => $query->where('etd', '>=', $request->etd_start))
            ->when($request->filled('etd_end'), fn ($query) => $query->where('etd', '<=', $request->etd_end))
            ->when($request->filled('eta_start'), fn ($query) => $query->where('eta', '>=', $request->eta_start))
            ->when($request->filled('eta_end'), fn ($query) => $query->where('eta', '<=', $request->eta_end))
            ->when($request->filled('month'), fn ($query) => $query->where('month', $request->month));
    }

    private function exportClassFor(?string $customer): string
    {
        return match (strtoupper($customer ?? '')) {
            'YNA' => YNAExport::class,
            'YC' => YCExport::class,
            'TYC' => TYCExport::class,
            'SAI' => SAIExport::class,
            default => SummaryExport::class,
        };
    }

    private function filterKeys(): array
    {
        return [
            'customer',
            'month',
            'search',
            'part_number',
            'order_type',
            'etd_start',
            'etd_end',
            'eta_start',
            'eta_end',
        ];
    }

    private function flashMessages(): array
    {
        return session('flash') ?: [
            'success' => session('success'),
            'warning' => session('warning'),
            'error' => session('error'),
        ];
    }
}
