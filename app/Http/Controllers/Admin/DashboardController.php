<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Assy;
use App\Models\CarLine;
use App\Models\Customer;
use App\Models\ProductionWeek;
use App\Models\SPP;
use App\Models\SR;
use App\Models\SppBatch;
use App\Models\Summary;
use App\Models\UploadBatch;
use App\Services\Utilities\PlanningCacheService;
use App\Services\Variance\VarianceAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly VarianceAnalyticsService $varianceAnalytics,
        private readonly PlanningCacheService $cache
    ) {
    }

    public function index()
    {
        $isAdmin = Auth::check() && Auth::user()?->isAdmin();
        $userId = Auth::id();

        return Inertia::render('Admin/Dashboard', $this->cache->remember('dashboard', ['variance' => 'operational_v1'], fn () => [
            'stats' => [
                'total_customers' => Customer::count(),
                'total_assy' => Assy::count(),
                'total_sr' => UploadBatch::query()
                    ->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))
                    ->count(),
                'total_spp' => SppBatch::query()
                    ->when(!$isAdmin, fn($q) => $q->where('generated_by', $userId))
                    ->count(),
                'total_carlines' => CarLine::count(),
                'unmapped_assy' => SR::query()
                    ->where('is_mapped', false)
                    ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                    ->distinct('assy_number')
                    ->count('assy_number'),
                'total_qty' => (int) SR::query()
                    ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                    ->sum('qty'),
            ],
            'operationalKpis' => $this->operationalKpis(),
            'actionItems' => $this->actionItems(),
            'recent_customers' => Customer::latest()->take(5)->get(),
            'recent_sr' => SR::query()
                ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
                ->latest()
                ->take(5)
                ->get(),
            'varianceDashboard' => $this->varianceAnalytics->operationalDashboard(),
        ], 5));
    }

    private function operationalKpis(): array
    {
        $isAdmin = Auth::check() && Auth::user()?->isAdmin();
        $userId = Auth::id();

        $totalSrRows = SR::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->count();
        $mappedSrRows = SR::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->where('is_mapped', true)
            ->count();
        $unmappedSrRows = SR::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->where('is_mapped', false)
            ->count();
        $latestUpload = UploadBatch::query()
            ->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))
            ->latest()
            ->first();
        $summarizedBatches = Summary::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->whereNotNull('upload_batch_id')
            ->distinct('upload_batch_id')
            ->count('upload_batch_id');
        $completedBatches = UploadBatch::query()
            ->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))
            ->where('status', 'completed')
            ->count();
        $generatedSppBatches = SPP::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->whereNotNull('upload_batch_id')
            ->distinct('upload_batch_id')
            ->count('upload_batch_id');
        $today = Carbon::today();
        $activeWeeks = ProductionWeek::whereDate('week_start', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->count();

        return [
            'total_sr_rows' => $totalSrRows,
            'total_sr_batches' => UploadBatch::query()->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))->count(),
            'total_order_qty' => (int) SR::query()->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))->sum('qty'),
            'mapping_success_rate' => $this->percentage($mappedSrRows, $totalSrRows),
            'mapped_sr_rows' => $mappedSrRows,
            'unmapped_sr_rows' => $unmappedSrRows,
            'summary_ready_rate' => $this->percentage($summarizedBatches, max($completedBatches, 1)),
            'summarized_batches' => $summarizedBatches,
            'completed_batches' => $completedBatches,
            'spp_generated_batches' => $generatedSppBatches,
            'spp_total_qty' => (int) SPP::query()->when(!$isAdmin, fn($q) => $q->whereHas('sppBatch', fn($sub) => $sub->where('generated_by', $userId)))->sum('total_qty'),
            'active_production_weeks' => $activeWeeks,
            'latest_upload' => $latestUpload ? [
                'id' => $latestUpload->id,
                'file' => $latestUpload->source_file,
                'status' => $latestUpload->status,
                'uploaded_at' => optional($latestUpload->created_at)->format('d M Y H:i'),
                'total_qty' => (int) $latestUpload->total_qty,
            ] : null,
        ];
    }

    private function actionItems(): array
    {
        $isAdmin = Auth::check() && Auth::user()?->isAdmin();
        $userId = Auth::id();

        $unmappedSrRows = SR::query()
            ->when(!$isAdmin, fn($q) => $q->whereHas('uploadBatch', fn($sub) => $sub->where('uploaded_by', $userId)))
            ->where('is_mapped', false)
            ->count();
        $failedUploads = UploadBatch::query()
            ->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))
            ->where('status', 'failed')
            ->count();
        $processingUploads = UploadBatch::query()
            ->when(!$isAdmin, fn($q) => $q->where('uploaded_by', $userId))
            ->where('status', 'processing')
            ->count();
        $inactiveAssy = Assy::where('is_active', false)->count();
        $missingCurrentWeeks = Customer::whereDoesntHave('productionWeeks', function ($query) {
            $query->where('year', Carbon::today()->year);
        })->count();

        return collect([
            [
                'label' => 'Unmapped Assy',
                'description' => 'Baris SR belum cocok dengan master Assy.',
                'count' => $unmappedSrRows,
                'href' => route('unmapped-assy.index'),
                'tone' => $unmappedSrRows > 0 ? 'critical' : 'normal',
            ],
            [
                'label' => 'Upload Failed',
                'description' => 'Batch SR gagal diproses dan perlu dicek ulang.',
                'count' => $failedUploads,
                'href' => route('history'),
                'tone' => $failedUploads > 0 ? 'critical' : 'normal',
            ],
            [
                'label' => 'Upload Processing',
                'description' => 'Batch masih dalam proses.',
                'count' => $processingUploads,
                'href' => route('history'),
                'tone' => $processingUploads > 0 ? 'warning' : 'normal',
            ],
            [
                'label' => 'Inactive Assy',
                'description' => 'Master Assy nonaktif yang perlu ditinjau.',
                'count' => $inactiveAssy,
                'href' => route('assy.index'),
                'tone' => $inactiveAssy > 0 ? 'warning' : 'normal',
            ],
            [
                'label' => 'Production Week Setup',
                'description' => 'Customer belum punya setup production week tahun ini.',
                'count' => $missingCurrentWeeks,
                'href' => route('production-week.index'),
                'tone' => $missingCurrentWeeks > 0 ? 'warning' : 'normal',
            ],
        ])->values()->all();
    }

    private function percentage(int $value, int $total): float
    {
        if ($total <= 0) {
            return 0;
        }

        return round(($value / $total) * 100, 1);
    }
}
