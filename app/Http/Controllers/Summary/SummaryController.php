<?php

namespace App\Http\Controllers\Summary;

use App\Http\Controllers\Controller;

use App\Exports\SAIExport;
use App\Exports\SummaryExport;
use App\Exports\SummaryListExport;
use App\Exports\TYCExport;
use App\Exports\YCExport;
use App\Exports\YNAExport;
use App\Models\Customer;
use App\Models\SR;
use App\Models\Summary;
use App\Services\Summary\SummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SummaryController extends Controller
{
    public function __construct(private readonly SummaryService $summaries)
    {
    }

    public function index(Request $request)
    {
        $filters = $request->only($this->summaries->filterKeys());
        $srList = $this->summaries->batchSummaries(
            $filters,
            min(max((int) $request->integer('per_page', 25), 10), 100)
        );

        Log::info('Summary index', [
            'total_results' => $srList->count(),
            'filters' => $filters,
        ]);

        return Inertia::render('Summary/Index', [
            'srList' => $srList,
            'customers' => Customer::orderBy('name')->get(['name', 'code']),
            'filters' => $filters,
            'flash' => $this->flashMessages(),
        ]);
    }

    public function show(int $id)
    {
        $sr = SR::with(['customer', 'port', 'uploadBatch.customer', 'uploadBatch.port'])->findOrFail($id);
        abort_if(!Auth::user()?->isAdmin() && $sr->uploadBatch()->first()?->uploaded_by !== Auth::id(), 403);

        $currentWeek = \App\Models\ProductionWeek::findByDate($sr->customer_id, \Carbon\Carbon::today())
            ?? \App\Models\ProductionWeek::findByDate($sr->customer_id, $sr->created_at);

        return Inertia::render('Summary/Show', [
            'sr' => $this->summaries->srPayload($sr, includeMonth: true),
            'data' => $this->summaries->detail($sr),
            'running_week' => $currentWeek ? [
                'id' => $currentWeek->id,
                'week_no' => $currentWeek->week_no,
                'month_name' => $currentWeek->month_name,
                'year' => $currentWeek->year,
                'week_start' => $currentWeek->week_start->toDateString(),
                'end_date' => $currentWeek->end_date?->toDateString(),
            ] : null,
        ]);
    }

    public function data(int $id)
    {
        try {
            $sr = SR::with(['customer', 'port', 'uploadBatch.customer', 'uploadBatch.port'])->findOrFail($id);
            abort_if(!Auth::user()?->isAdmin() && $sr->uploadBatch()->first()?->uploaded_by !== Auth::id(), 403);
            return response()->json($this->summaries->apiPayload($sr));
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Summary data error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Data tidak ditemukan',
            ], 404);
        }
    }

    public function updateRow(Request $request, Summary $summary)
    {
        abort_if(!Auth::user()?->isAdmin() && $summary->uploadBatch()->first()?->uploaded_by !== Auth::id(), 403);

        $validated = $request->validate([
            'assy_number' => ['required', 'string', 'max:255'],
            'order_type' => ['nullable', 'string', 'max:255'],
            'etd' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'month' => ['nullable', 'string', 'max:255'],
            'week' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'family' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $updated = $this->summaries->updateReviewedRow($summary, $validated);
        } catch (\Throwable $exception) {
            Log::error('Summary row update error: ' . $exception->getMessage());

            return back()->with('error', 'Gagal update Summary: ' . $exception->getMessage());
        }

        return back()->with('success', "Summary {$updated->assy_number} berhasil diupdate.");
    }

    public function updatePeriod(Request $request)
    {
        $validated = $request->validate([
            'summary_ids' => ['required', 'array', 'min:1'],
            'summary_ids.*' => ['integer', 'exists:summaries,id'],
            'order_type' => ['nullable', 'string', 'max:255'],
            'etd' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
            'month' => ['nullable', 'string', 'max:255'],
            'week' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:255'],
        ]);

        if (!Auth::user()?->isAdmin()) {
            $hasUnauthorized = Summary::whereIn('id', $validated['summary_ids'])
                ->whereHas('uploadBatch', function ($q) {
                    $q->where('uploaded_by', '!=', Auth::id());
                })
                ->exists();
            abort_if($hasUnauthorized, 403);
        }

        try {
            $updatedCount = $this->summaries->updateReviewedPeriod($validated['summary_ids'], $validated);
        } catch (\Throwable $exception) {
            Log::error('Summary period update error: ' . $exception->getMessage());

            return back()->with('error', 'Gagal update periode Summary: ' . $exception->getMessage());
        }

        return back()->with('success', "{$updatedCount} row Summary berhasil diupdate.");
    }

    public function destroy(int $id)
    {
        $sr = SR::findOrFail($id);
        abort_if(!Auth::user()?->isAdmin() && $sr->uploadBatch()->first()?->uploaded_by !== Auth::id(), 403);

        try {
            $deleted = $this->summaries->deleteUpload($sr);

            \App\Models\HistoryLog::log(
                'summary_deleted',
                'Summary Deleted',
                'deleted',
                $sr->customer,
                $sr->port,
                $deleted['source_file'],
                $sr->upload_batch,
                null,
                $deleted['deleted_count'],
                0,
                0,
                0,
                0,
                null,
                "Deleted summary batch upload: {$deleted['source_file']} ({$deleted['deleted_count']} records)"
            );

            return redirect()
                ->route('summary.index')
                ->with('success', "Upload \"{$deleted['source_file']}\" deleted! ({$deleted['deleted_count']} records)");
        } catch (\Throwable $exception) {
            Log::error('Summary delete error: ' . $exception->getMessage());

            return redirect()
                ->route('summary.index')
                ->with('error', 'Gagal hapus: ' . $exception->getMessage());
        }
    }

    public function export(int $id)
    {
        $sr = SR::with(['customer', 'port', 'uploadBatch.customer', 'uploadBatch.port'])->findOrFail($id);
        abort_if(!Auth::user()?->isAdmin() && $sr->uploadBatch()->first()?->uploaded_by !== Auth::id(), 403);

        try {
            $summaryData = $this->summaries->detail($sr);
            $filename = Str::slug(pathinfo((string) $sr->source_file, PATHINFO_FILENAME)) ?: $sr->id;
            $exportClass = $this->exportClassFor($sr->customer);

            $currentWeek = \App\Models\ProductionWeek::findByDate($sr->customer_id, \Carbon\Carbon::today())
                ?? \App\Models\ProductionWeek::findByDate($sr->customer_id, $sr->created_at);

            $periodsPerMonth = request()->integer('periods_per_month') ?: null;

            return Excel::download(
                new $exportClass($summaryData, $currentWeek, $periodsPerMonth),
                "Summary_{$filename}_Detail.xlsx"
            );
        } catch (\Throwable $exception) {
            Log::error('Summary export error: ' . $exception->getMessage());

            return redirect()
                ->route('summary.index')
                ->with('error', 'Gagal export: ' . $exception->getMessage());
        }
    }

    public function exportAll(Request $request)
    {
        $srList = $this->summaries->batchSummaries($request->only($this->summaries->filterKeys()), null);

        return Excel::download(new SummaryListExport($srList), 'Summary_List.xlsx');
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

    private function flashMessages(): array
    {
        return session('flash') ?: [
            'success' => session('success'),
            'warning' => session('warning'),
            'error' => session('error'),
        ];
    }
}
