<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

use App\Http\Requests\Assy\ImportAssySheetRequest;
use App\Http\Requests\Assy\PreviewAssySheetRequest;
use App\Http\Requests\Assy\SheetNamesRequest;
use App\Http\Requests\Assy\StoreAssyRequest;
use App\Http\Requests\Assy\UpdateAssyRequest;
use App\Http\Requests\Assy\UploadAssyRequest;
use App\Models\Assy;
use App\Models\Carline;
use App\Models\HistoryLog;
use App\Models\SR;
use App\Services\Master\AssyService;
use App\Services\Integration\SirepMasterSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

class AssyController extends Controller
{
    public function __construct(private readonly AssyService $assies)
    {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 20);
        if ($perPage < 1 || $perPage > 500) {
            $perPage = 20;
        }

        return Inertia::render('Master/Assy/Index', [
            'assy' => $this->assies
                ->query($request->only(['search', 'carline_id', 'is_active']))
                ->orderBy('assy_number')
                ->paginate($perPage),
            'carlines' => $this->carlines(),
            'filters' => $request->only(['search', 'carline_id', 'is_active', 'per_page']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Master/Assy/Create', [
            'carlines' => $this->carlines(),
        ]);
    }

    public function store(StoreAssyRequest $request)
    {
        Assy::create($request->validated());

        return redirect()->route('assy.index')
            ->with('success', 'Assy berhasil ditambahkan');
    }

    public function edit(Assy $assy)
    {
        return Inertia::render('Master/Assy/Edit', [
            'assy' => $assy,
            'carlines' => $this->carlines(),
        ]);
    }

    public function update(UpdateAssyRequest $request, Assy $assy)
    {
        $assy->update($request->validated());

        return redirect()->route('assy.index')
            ->with('success', 'Assy berhasil diubah');
    }

    public function destroy(Assy $assy)
    {
        if (SR::where('assy_id', $assy->id)->exists() || $assy->spp()->exists()) {
            return back()->with('error', 'Assy tidak bisa dihapus karena sudah digunakan di SR atau SPP');
        }

        $assy->delete();

        return redirect()->route('assy.index')
            ->with('success', 'Assy berhasil dihapus');
    }

    public function show(Assy $assy)
    {
        return Inertia::render('Master/Assy/Show', [
            'assy' => $assy->load('carline'),
        ]);
    }

    public function toggleStatus(Assy $assy)
    {
        $assy->update(['is_active' => !$assy->is_active]);

        return back()->with('success', 'Status assy berhasil diubah');
    }

    public function syncSirep(SirepMasterSyncService $service)
    {
        try {
            $result = $service->syncAssy();
            $assy   = $result['assy'];
            $carline = $result['carline'];

            $summary = sprintf(
                'Assy: %d created, %d updated, %d skipped. Carline dari Assy: %d created, %d updated.',
                $assy['created'], $assy['updated'], $assy['skipped'],
                $carline['created'], $carline['updated']
            );

            HistoryLog::log(
                activityType: 'sirep_sync',
                title: 'Sync SIREP Assy',
                status: 'completed',
                notes: $summary,
                details: [
                    'scope'   => 'assy',
                    'assy'    => $assy,
                    'carline' => $carline,
                ]
            );

            return back()->with('success', 'Sync SIREP Assy selesai. ' . $summary);
        } catch (Throwable $exception) {
            HistoryLog::log(
                activityType: 'sirep_sync',
                title: 'Sync SIREP Assy',
                status: 'failed',
                notes: $exception->getMessage(),
            );

            return back()->with('error', 'Sync SIREP Assy gagal: ' . $exception->getMessage());
        }
    }

    public function search(Request $request)
    {
        return response()->json(
            Assy::active()
                ->where(function ($query) use ($request) {
                    $query->where('assy_number', 'like', "%{$request->get('q')}%")
                        ->orWhere('assy_code', 'like', "%{$request->get('q')}%");
                })
                ->limit(20)
                ->get(['id', 'assy_number', 'assy_code', 'level', 'umh'])
        );
    }

    public function upload(UploadAssyRequest $request)
    {
        try {
            $result = $this->assies->uploadMaster(
                $request->file('excel_file'),
                (int) $request->input('carline_id')
            );

            if (!empty($result['errors'])) {
                return redirect()->back()->with('warning', $this->uploadWarningMessage($result['errors']));
            }

            return redirect()->back()
                ->with('success', "Berhasil upload {$result['count']} data Assy untuk Car Line yang dipilih!");
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Gagal upload data: ' . $exception->getMessage());
        }
    }

    public function downloadTemplate(Request $request)
    {
        try {
            $carlineId = $request->route('carline_id') ?? $request->query('carline_id');
            $carline = $carlineId && $carlineId !== 'all' ? Carline::findOrFail($carlineId) : null;

            $template = $this->assies->createTemplate($carline);

            return response()->streamDownload(function () use ($template) {
                (new Xlsx($template['spreadsheet']))->save('php://output');
            }, $template['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (Throwable $exception) {
            return response()->json(['error' => 'Gagal generate template: ' . $exception->getMessage()], 500);
        }
    }

    public function importPage()
    {
        return Inertia::render('Master/Assy/Import', [
            'carlines' => $this->carlines(),
        ]);
    }

    public function getSheets(SheetNamesRequest $request)
    {
        try {
            return response()->json([
                'success' => true,
                'sheets' => $this->assies->sheetNames($request->file('file')),
            ]);
        } catch (Throwable $exception) {
            return $this->excelError('Gagal membaca file Excel: ' . $exception->getMessage());
        }
    }

    public function previewSheet(PreviewAssySheetRequest $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->assies->previewSheet($request->file('file'), $request->input('sheet')),
            ]);
        } catch (Throwable $exception) {
            return $this->excelError('Gagal preview sheet: ' . $exception->getMessage());
        }
    }

    public function import(ImportAssySheetRequest $request)
    {
        try {
            $carlineId = $request->input('carline_id');
            $carlineId = $carlineId && $carlineId !== 'all' ? (int) $carlineId : null;

            return response()->json($this->assies->importSheet(
                $request->file('file'),
                $request->input('sheet'),
                $carlineId
            ));
        } catch (Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Assy Import Error: ' . $exception->getMessage(), [
                'exception' => $exception,
                'file' => $request->file('file')?->getClientOriginalName(),
                'sheet' => $request->input('sheet'),
            ]);
            return $this->excelError('Gagal mengimport data: ' . $exception->getMessage());
        }
    }

    public function apiIndex(Request $request)
    {
        $limit = min((int) $request->get('limit', 100), 500);

        $assy = $this->assies
            ->query($request->only(['search', 'carline_id', 'is_active']))
            ->orderBy('assy_number')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assy,
            'count' => $assy->count(),
        ]);
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'carline_id' => 'required|exists:carline,id',
            'assy_number' => 'required|string|max:50|unique:assy,assy_number',
            'assy_code' => 'required|string|max:20|unique:assy,assy_code',
            'level' => 'required|string|max:20',
            'umh' => 'required|numeric|min:0|max:9999.999999',
        ]);

        $assy = Assy::create([
            'carline_id' => $validated['carline_id'],
            'assy_number' => $validated['assy_number'],
            'assy_code' => $validated['assy_code'],
            'level' => $validated['level'],
            'umh' => $validated['umh'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Assy berhasil didaftarkan.',
            'data' => $assy,
        ]);
    }

    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.carline_id' => 'required|exists:carline,id',
            'items.*.assy_number' => 'required|string|max:50|unique:assy,assy_number',
            'items.*.assy_code' => 'required|string|max:20|unique:assy,assy_code',
            'items.*.level' => 'required|string|max:20',
            'items.*.pattern' => 'nullable|string|max:255',
            'items.*.standard_sea_quantity' => 'nullable|integer|min:0',
            'items.*.standard_air_quantity' => 'nullable|integer|min:0',
            'items.*.max_quantity_sea' => 'nullable|integer|min:0',
            'items.*.max_quantity_air' => 'nullable|integer|min:0',
            'items.*.umh' => 'required|numeric|min:0|max:9999.999999',
        ]);

        $created = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, &$created) {
            foreach ($validated['items'] as $item) {
                $created[] = Assy::create([
                    'carline_id' => $item['carline_id'],
                    'assy_number' => $item['assy_number'],
                    'assy_code' => $item['assy_code'],
                    'level' => $item['level'],
                    'pattern' => $item['pattern'] ?? null,
                    'standard_sea_quantity' => isset($item['standard_sea_quantity']) && $item['standard_sea_quantity'] !== '' ? (int)$item['standard_sea_quantity'] : null,
                    'standard_air_quantity' => isset($item['standard_air_quantity']) && $item['standard_air_quantity'] !== '' ? (int)$item['standard_air_quantity'] : null,
                    'max_quantity_sea' => isset($item['max_quantity_sea']) && $item['max_quantity_sea'] !== '' ? (int)$item['max_quantity_sea'] : null,
                    'max_quantity_air' => isset($item['max_quantity_air']) && $item['max_quantity_air'] !== '' ? (int)$item['max_quantity_air'] : null,
                    'umh' => $item['umh'],
                    'is_active' => true,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($created) . ' Assy berhasil didaftarkan.',
            'data' => $created,
        ]);
    }

    private function carlines()
    {
        return Carline::orderBy('code')->get();
    }

    private function uploadWarningMessage(array $errors): string
    {
        $message = 'Upload selesai dengan ' . count($errors) . " error:\n" . implode("\n", array_slice($errors, 0, 5));

        if (count($errors) > 5) {
            $message .= "...\nDan " . (count($errors) - 5) . ' error lainnya';
        }

        return $message;
    }

    private function excelError(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 400);
    }
}
