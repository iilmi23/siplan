<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

use App\Http\Requests\Carline\CarlineSheetRequest;
use App\Http\Requests\Carline\StoreCarlineRequest;
use App\Http\Requests\Carline\UpdateCarlineRequest;
use App\Models\Carline;
use App\Models\HistoryLog;
use App\Services\Master\CarlineService;
use App\Services\Integration\SirepMasterSyncService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class CarlineController extends Controller
{
    public function __construct(private readonly CarlineService $carlines)
    {
    }

    public function index(Request $request)
    {
        return Inertia::render('Master/Carline/Index', [
            'carlines' => $this->carlines->query($request->only(['search']))->orderBy('code')->get(),
            'filters' => $request->only(['search']),
            'isLocal' => app()->environment('local', 'testing'),
        ]);
    }


    public function store(StoreCarlineRequest $request)
    {
        if (!app()->environment('local', 'testing')) {
            return redirect()->back()->with('error', 'Tambah manual dinonaktifkan di environment ini.');
        }

        Carline::create($request->validated());

        return redirect()->route('carline.index')
            ->with('success', 'Carline berhasil ditambahkan');
    }


    public function update(UpdateCarlineRequest $request, Carline $carline)
    {
        if (!app()->environment('local', 'testing')) {
            return redirect()->back()->with('error', 'Edit manual dinonaktifkan di environment ini.');
        }

        $carline->update($request->validated());

        return redirect()->route('carline.index')
            ->with('success', 'Carline berhasil diubah');
    }

    public function destroy(Carline $carline)
    {
        if (!app()->environment('local', 'testing')) {
            return redirect()->back()->with('error', 'Hapus manual dinonaktifkan di environment ini.');
        }

        if ($carline->assy()->count() > 0 || \App\Models\SR::where('carline_id', $carline->id)->exists()) {
            return redirect()->back()
                ->with('error', 'Carline cannot be deleted because it is in use by Assy or SR records.');
        }

        $carline->delete();

        return redirect()->route('carline.index')
            ->with('success', 'Carline berhasil dihapus');
    }

    public function syncSirep(SirepMasterSyncService $service)
    {
        try {
            $result = $service->syncCarlines();

            $summary = sprintf(
                '%d created, %d updated, %d skipped.',
                $result['created'], $result['updated'], $result['skipped']
            );

            HistoryLog::log(
                activityType: 'sirep_sync',
                title: 'Sync SIREP Carline',
                status: 'completed',
                notes: $summary,
                details: [
                    'scope'   => 'carline',
                    'carline' => $result,
                ]
            );

            return back()->with('success', 'Sync SIREP Carline selesai. ' . $summary);
        } catch (Throwable $exception) {
            HistoryLog::log(
                activityType: 'sirep_sync',
                title: 'Sync SIREP Carline',
                status: 'failed',
                notes: $exception->getMessage(),
            );

            return back()->with('error', 'Sync SIREP Carline gagal: ' . $exception->getMessage());
        }
    }

    public function getSheets(CarlineSheetRequest $request)
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Aksi dinonaktifkan di environment ini.',
            ], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'sheets' => $this->carlines->sheetNames($request->file('file')),
            ]);
        } catch (Throwable $exception) {
            return $this->excelError('Gagal membaca file Excel: ' . $exception->getMessage());
        }
    }

    public function previewSheet(CarlineSheetRequest $request)
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Aksi dinonaktifkan di environment ini.',
            ], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->carlines->previewSheet($request->file('file'), $request->input('sheet')),
            ]);
        } catch (Throwable $exception) {
            return $this->excelError('Gagal preview sheet: ' . $exception->getMessage());
        }
    }

    public function import(CarlineSheetRequest $request)
    {
        if (!app()->environment('local', 'testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Import Excel manual dinonaktifkan di environment ini.',
            ], 403);
        }

        try {
            return response()->json(
                $this->carlines->importSheet($request->file('file'), $request->input('sheet'))
            );
        } catch (Throwable $exception) {
            return $this->excelError('Gagal mengimport data: ' . $exception->getMessage());
        }
    }


    public function downloadTemplate()
    {
        try {
            $template = $this->carlines->createTemplate();

            return response()->streamDownload(function () use ($template) {
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($template['spreadsheet']))->save('php://output');
            }, $template['filename'], [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (Throwable $exception) {
            return response()->json(['error' => 'Gagal generate template: ' . $exception->getMessage()], 500);
        }
    }

    public function apiIndex(Request $request)
    {
        $carlines = $this->carlines->query($request->only(['search']))->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $carlines,
            'count' => $carlines->count(),
        ]);
    }

    private function excelError(string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], 400);
    }
}
