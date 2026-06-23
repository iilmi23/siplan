<?php

namespace App\Http\Controllers\SPP;

use App\Http\Controllers\Controller;

use App\Http\Requests\SPP\StoreSPPRequest;
use App\Models\Customer;
use App\Models\UploadBatch;
use App\Services\SPP\SPPService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SPPController extends Controller
{
    public function __construct(private readonly SPPService $spp)
    {
    }

    public function index(Request $request)
    {
        $savedPlansQuery = \App\Models\SppBatch::query();
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (Auth::check() && !$user?->isAdmin()) {
            $savedPlansQuery->where('generated_by', Auth::id());
        }

        $savedPlans = $savedPlansQuery
            ->join('customers', 'spp_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'spp_batches.port_id', '=', 'ports.id')
            ->selectRaw('
                spp_batches.id,
                spp_batches.batch_uuid,
                spp_batches.source_file,
                spp_batches.sheet_name,
                spp_batches.created_at as saved_at,
                spp_batches.total_qty,
                customers.code as customer,
                ports.name as port,
                (SELECT COUNT(DISTINCT assy_number) FROM spp WHERE spp.spp_batch_id = spp_batches.id) as unique_assy_numbers,
                (SELECT MIN(period) FROM spp WHERE spp.spp_batch_id = spp_batches.id) as start_period,
                (SELECT MAX(period) FROM spp WHERE spp.spp_batch_id = spp_batches.id) as end_period,
                (SELECT upload_batch_id FROM spp_batch_sources WHERE spp_batch_id = spp_batches.id LIMIT 1) as upload_batch_id
            ')
            ->when($request->filled('customer'), function ($q) use ($request) {
                $q->where('customers.code', $request->customer);
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('spp_batches.source_file', 'like', '%' . $request->search . '%');
            })
            ->orderByDesc('spp_batches.created_at')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'batch_uuid' => $item->batch_uuid,
                    'source_file' => $item->source_file ?: '-',
                    'sheet_name' => $item->sheet_name ?: '-',
                    'customer' => $item->customer ?: '-',
                    'port' => $item->port ?: '-',
                    'unique_assy_numbers' => (int)$item->unique_assy_numbers,
                    'total_qty' => (int)$item->total_qty,
                    'last_updated' => $this->safeFormatDate($item->saved_at),
                    'upload_batch_id' => $item->upload_batch_id,
                    'period' => $item->start_period ?: '-',
                    'period_label' => $item->start_period && $item->end_period
                        ? $this->formatPeriodLabel($item->start_period) . ' – ' . $this->formatPeriodLabel($item->end_period)
                        : '-',
                ];
            });

        return Inertia::render('SPP/Index', [
            'srList' => $this->spp->batchSummary($request->only(['customer', 'search'])),
            'savedPlans' => $savedPlans,
            'customers' => $this->customers(),
            'filters' => $request->only(['customer', 'search']),
        ]);
    }

    public function preview(int|string $id)
    {
        $batch = UploadBatch::findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_if(!$user?->isAdmin() && $batch->uploaded_by !== Auth::id(), 403);
        return Inertia::render('SPP/Preview', $this->spp->previewData($batch));
    }


    public function show(Request $request, string $period)
    {
        $this->validatePeriod($period);
        $filters = $request->only(['customer', 'sr_batch']);
        $data = $this->spp->showData($period, $filters);

        return Inertia::render('SPP/Show', [
            'customers' => $this->customers(),
            'srBatches' => $this->spp->srBatchOptions($filters),
            'filters' => $filters,
            'period' => $period,
            'records' => $data['records'],
            'summary' => $data['summary'],
            'months' => $data['months'] ?? [],
            'rows' => $data['rows'] ?? [],
        ]);
    }

    public function export(Request $request, string $period)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $this->validatePeriod($period);
        $filters = $request->only(['customer', 'sr_batch']);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $hasOtherUsersSpp = \App\Models\SPP::where('period', $period)
            ->whereHas('sppBatch', function ($q) {
                $q->where('generated_by', '!=', Auth::id());
            })
            ->exists();
        $hasOwnSpp = \App\Models\SPP::where('period', $period)
            ->whereHas('sppBatch', function ($q) {
                $q->where('generated_by', Auth::id());
            })
            ->exists();

        if (Auth::check() && !$user?->isAdmin() && $hasOtherUsersSpp && !$hasOwnSpp) {
            abort(403);
        }

        $data = $this->spp->showData($period, $filters);

        if ($data['summary']['source'] !== 'spp') {
            return back()->with('error', 'Silakan Simpan SPP Fixed terlebih dahulu sebelum melakukan export.');
        }

        $records = $data['records'];

        if ($records->isEmpty()) {
            return back()->with('error', 'Tidak ada data SPP untuk diexport.');
        }

        $firstRecord = $records->first();
        $sppBatchId = $firstRecord->spp_batch_id;

        if (Auth::check() && !$user?->isAdmin()) {
            $unauthorized = \App\Models\SPP::where('spp_batch_id', $sppBatchId)
                ->whereHas('sppBatch', function ($q) {
                    $q->where('generated_by', '!=', Auth::id());
                })
                ->exists();
            abort_if($unauthorized, 403);
        }

        $allBatchRecords = \App\Models\SPP::with(['assy.carline', 'customer'])->where('spp_batch_id', $sppBatchId)->get();
        $uniquePeriods = $allBatchRecords->pluck('period')->unique()->sort()->values();

        // Load all production weeks for target years in a single query
        $years = $uniquePeriods->map(fn($p) => (int)substr($p, 0, 4))->unique()->all();
        $allWeeks = \App\Models\ProductionWeek::query()
            ->whereIn('year', $years)
            ->when($firstRecord->customer_id, fn($q) => $q->where('customer_id', $firstRecord->customer_id))
            ->orderBy('week_no')
            ->get();

        $months = $uniquePeriods->map(function ($p) use ($firstRecord, $allBatchRecords, $allWeeks) {
            $date = \Carbon\Carbon::createFromFormat('Y-m', $p);
            $year = $date->year;
            $monthNum = $date->month;

            $weeks = $allWeeks->where('year', $year)->where('month_number', $monthNum)->values();

            $monthLabel = strtoupper($date->format('M'));
            $rangeLabel = '';

            if ($weeks->isNotEmpty()) {
                $wFirst = $weeks->first();
                $wLast = $weeks->last();
                $start = \Carbon\Carbon::parse($wFirst->week_start);
                $end = \Carbon\Carbon::parse($wLast->end_date);
                $monthLabel = strtoupper($wFirst->month_name ?: $monthLabel);
                $rangeLabel = strtoupper($start->format('d/M') . ' ~ ' . $end->format('d/M'));
            } else {
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();
                $rangeLabel = strtoupper($start->format('d/M') . ' ~ ' . $end->format('d/M'));
            }

            $isFirm = $allBatchRecords->where('period', $p)->where('order_type', 'FIRM')->isNotEmpty();

            return [
                'period' => $p,
                'label' => $monthLabel,
                'range_label' => $rangeLabel,
                'year' => $year,
                'bucket' => $isFirm ? 'firm' : 'forecast',
            ];
        })->toArray();

        $firstBatchRecord = $allBatchRecords->first();
        $customerModel = $firstBatchRecord ? $firstBatchRecord->getRelationValue('customer') : null;
        $customerCode = $customerModel instanceof \App\Models\Customer
            ? $customerModel->code
            : (is_string($customerModel) ? $customerModel : 'ALL');
        $filename = "SPP_{$customerCode}_{$period}.xlsx";

        $exporter = new \App\Services\SPP\SPPTemplateExporter();
        $tempPath = $exporter->export($allBatchRecords, $months, $customerCode, $period);

        if ($tempPath) {
            $content = file_get_contents($tempPath);
            @unlink($tempPath);
            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'max-age=0');
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SPPExport($allBatchRecords, $months),
            $filename
        );
    }

    public function exportDraft(Request $request)
    {
        if (is_string($request->input('months'))) {
            $request->merge([
                'months' => json_decode($request->input('months'), true),
            ]);
        }
        if (is_string($request->input('rows'))) {
            $request->merge([
                'rows' => json_decode($request->input('rows'), true),
            ]);
        }

        $validated = $request->validate([
            'customer' => 'required|string',
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'months' => 'required|array',
            'rows' => 'required|array',
        ]);

        $customerCode = $validated['customer'];
        $period = $validated['period'];
        $months = $validated['months'];
        $rows = $validated['rows'];

        $allBatchRecords = collect();
        foreach ($rows as $row) {
            $monthsData = $row['months'] ?? [];
            foreach ($monthsData as $periodKey => $cell) {
                $rec = new \stdClass();
                $rec->assy_number = $row['assy_number'] ?? '';
                $rec->level = $row['level'] ?? '';
                $rec->assy_code = $row['assy_code'] ?? '';
                $rec->carline = $row['carline'] ?? '';
                $rec->std_pack = $row['std_pack'] ?? null;
                $rec->umh = $row['umh'] ?? null;
                $rec->pattern = $row['pattern'] ?? '';
                $rec->period = $periodKey;
                $rec->del_qty = $cell['del'] ?? 0;
                $rec->prod_qty = $cell['prod'] ?? 0;
                $rec->bal_qty = $cell['bal'] ?? 0;
                $rec->assy = null;

                $allBatchRecords->push($rec);
            }
        }

        $filename = "SPP_{$customerCode}_{$period}.xlsx";

        $exporter = new \App\Services\SPP\SPPTemplateExporter();
        $tempPath = $exporter->export($allBatchRecords, $months, $customerCode, $period);

        if ($tempPath) {
            $content = file_get_contents($tempPath);
            @unlink($tempPath);
            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'max-age=0');
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SPPExport($allBatchRecords, $months),
            $filename
        );
    }

    public function importDraft(Request $request)
    {
        if (is_string($request->input('months'))) {
            $request->merge([
                'months' => json_decode($request->input('months'), true),
            ]);
        }
        if (is_string($request->input('rows'))) {
            $request->merge([
                'rows' => json_decode($request->input('rows'), true),
            ]);
        }

        $validated = $request->validate([
            'customer' => 'required|string',
            'period' => 'required|string|regex:/^\d{4}-\d{2}$/',
            'months' => 'required|array',
            'rows' => 'required|array',
            'file' => 'required|file',
        ]);

        $customerCode = $validated['customer'];
        $months = $validated['months'];
        $rows = $validated['rows'];
        $file = $request->file('file');

        $exporter = new \App\Services\SPP\SPPTemplateExporter();
        $excelData = $exporter->importFromExcel($file->getRealPath(), $customerCode, $months);

        if (empty($excelData)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membaca data dari file Excel. Pastikan file sesuai dengan template customer.'
            ], 422);
        }

        // Map excel data back to current web draft rows
        $updatedRows = [];
        foreach ($rows as $row) {
            $assyNumber = $row['assy_number'] ?? '';
            if (isset($excelData[$assyNumber])) {
                $excelRow = $excelData[$assyNumber];
                
                // Update meta fields if present
                if (!empty($excelRow['pattern'])) $row['pattern'] = $excelRow['pattern'];
                if (!empty($excelRow['carline'])) $row['carline'] = $excelRow['carline'];
                if (!empty($excelRow['level'])) $row['level'] = $excelRow['level'];
                if (!empty($excelRow['assy_code'])) $row['assy_code'] = $excelRow['assy_code'];
                if ($excelRow['std_pack'] !== null) $row['std_pack'] = $excelRow['std_pack'];
                if ($excelRow['umh'] !== null) $row['umh'] = $excelRow['umh'];

                // Update months data
                $rowMonths = $row['months'] ?? [];
                $totalQty = 0;
                foreach ($excelRow['months'] as $periodKey => $cell) {
                    $rowMonths[$periodKey] = [
                        'bal' => $cell['bal'],
                        'del' => $cell['del'],
                        'prod' => $cell['prod'],
                    ];
                    $totalQty += (int)$cell['del'];
                }
                $row['months'] = $rowMonths;
                $row['total_qty'] = $totalQty;
            }
            $updatedRows[] = $row;
        }


        return response()->json([
            'success' => true,
            'rows' => $updatedRows
        ]);
    }

    public function store(StoreSPPRequest $request, int|string $id)
    {
        $batch = UploadBatch::with(['customer', 'port'])->findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_if(!$user?->isAdmin() && $batch->uploaded_by !== Auth::id(), 403);
        $storedCount = $this->spp->storeFixed($batch, $request->validated());

        return back()->with('success', 'SPP fixed berhasil disimpan: ' . $storedCount . ' rows.');
    }

    public function exportDraftDirect(int|string $id)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        $batch = UploadBatch::with(['customer', 'port'])->findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_if(!$user?->isAdmin() && $batch->uploaded_by !== Auth::id(), 403);

        $preview = $this->spp->previewData($batch);
        
        $customerCode = $batch->customer?->code ?: 'ALL';
        $months = $preview['months']->toArray();
        $rows = $preview['rows']->toArray();
        $targetPeriod = $months[1]['period'] ?? $months[0]['period'] ?? "";

        $allBatchRecords = collect();
        foreach ($rows as $row) {
            $monthsData = $row['months'] ?? [];
            foreach ($monthsData as $periodKey => $cell) {
                $rec = new \stdClass();
                $rec->assy_number = $row['assy_number'] ?? '';
                $rec->level = $row['level'] ?? '';
                $rec->assy_code = $row['assy_code'] ?? '';
                $rec->carline = $row['carline'] ?? '';
                $rec->std_pack = $row['std_pack'] ?? null;
                $rec->umh = $row['umh'] ?? null;
                $rec->pattern = $row['pattern'] ?? '';
                $rec->period = $periodKey;
                $rec->del_qty = $cell['del'] ?? 0;
                $rec->prod_qty = $cell['prod'] ?? 0;
                $rec->bal_qty = $cell['bal'] ?? 0;
                $rec->assy = null;

                $allBatchRecords->push($rec);
            }
        }

        $filename = "SPP_DRAFT_{$customerCode}_{$targetPeriod}.xlsx";

        $exporter = new \App\Services\SPP\SPPTemplateExporter();
        $tempPath = $exporter->export($allBatchRecords, $months, $customerCode, $targetPeriod);

        if ($tempPath) {
            $content = file_get_contents($tempPath);
            @unlink($tempPath);
            return response($content)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Content-Length', strlen($content))
                ->header('Cache-Control', 'max-age=0');
        }

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\SPPExport($allBatchRecords, $months),
            $filename
        );
    }

    public function storeDirect(int|string $id)
    {
        $batch = UploadBatch::with(['customer', 'port'])->findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_if(!$user?->isAdmin() && $batch->uploaded_by !== Auth::id(), 403);

        try {
            $preview = $this->spp->previewData($batch);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses data: ' . $e->getMessage());
        }

        $rows = $preview['rows']->toArray();
        $months = $preview['months']->toArray();

        $missingStdPack = 0;
        $missingUmh = 0;
        $missingMaster = 0;

        foreach ($rows as $row) {
            $std = $row['std_pack'] ?? null;
            $umh = $row['umh'] ?? null;
            $assyCode = $row['assy_code'] ?? null;
            $level = $row['level'] ?? null;

            if ($std === null || $std === '') {
                $missingStdPack++;
            }
            if ($umh === null || $umh === '') {
                $missingUmh++;
            }
            if ($assyCode === null || $assyCode === '' || $level === null || $level === '') {
                $missingMaster++;
            }
        }

        if ($missingStdPack > 0 || $missingUmh > 0 || $missingMaster > 0) {
            $errors = [];
            if ($missingMaster > 0) $errors[] = "Master Assy belum ter-mapping ({$missingMaster} item)";
            if ($missingStdPack > 0) $errors[] = "Standard Pack kosong ({$missingStdPack} item)";
            if ($missingUmh > 0) $errors[] = "UMH kosong ({$missingUmh} item)";

            return back()->with('error', 'Gagal menyimpan SPP secara langsung karena data belum lengkap: ' . implode(', ', $errors) . '. Silakan klik Preview untuk melengkapi.');
        }

        $validated = [
            'months' => $months,
            'rows' => $rows,
        ];

        $storedCount = $this->spp->storeFixed($batch, $validated);

        return back()->with('success', 'SPP fixed berhasil disimpan langsung: ' . $storedCount . ' rows.');
    }

    public function destroy(int|string $id)
    {
        $sppBatch = \App\Models\SppBatch::findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        abort_if(!$user?->isAdmin() && $sppBatch->generated_by !== Auth::id(), 403);

        \Illuminate\Support\Facades\DB::transaction(function () use ($sppBatch) {
            \App\Models\SPP::where('spp_batch_id', $sppBatch->id)->delete();
            $sppBatch->delete();
        });

        return back()->with('success', 'SPP fixed batch berhasil dihapus (unlocked).');
    }


    private function customers()
    {
        return Customer::orderBy('name')->get(['id', 'name', 'code']);
    }

    /**
     * Format tanggal secara aman, mengembalikan '-' jika gagal parse.
     */
    private function safeFormatDate(mixed $value, string $format = 'Y-m-d H:i'): string
    {
        if (!$value) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '-';
        }
    }

    /**
     * Validasi format periode YYYY-MM.
     */
    private function validatePeriod(string $period): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            abort(404, 'Format periode tidak valid.');
        }
    }

    /**
     * Format label periode secara aman.
     */
    private function formatPeriodLabel(?string $period): string
    {
        if (!$period) {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($period . '-01')->format('F Y');
        } catch (\Throwable) {
            return $period;
        }
    }
}
