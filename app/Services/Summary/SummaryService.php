<?php

namespace App\Services\Summary;

use App\Models\Customer;
use App\Models\Assy;
use App\Models\Port;
use App\Models\ProductionWeek;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use App\Services\Utilities\PlanningCacheService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class SummaryService
{
    public function __construct(private readonly PlanningCacheService $cache)
    {
    }

    public function filterKeys(): array
    {
        return [
            'customer',
            'month',
            'search',
            'assy_number',
            'order_type',
            'etd_start',
            'etd_end',
            'eta_start',
            'eta_end',
        ];
    }

    public function batchSummaries(array $filters, ?int $perPage = 25)
    {
        $page = request()->integer('page') ?: 1;

        return $this->cache->remember('summary', [
            'filters' => $filters,
            'per_page' => $perPage,
            'page' => $perPage ? $page : null,
        ], function () use ($filters, $perPage) {
            if ($this->hasCompleteMaterializedSummaries()) {
                return $this->materializedBatchSummaries($filters, $perPage);
            }

            return $this->rawBatchSummaries($filters, $perPage);
        }, 5);
    }

    public function rawBatchSummaries(array $filters, ?int $perPage = 25)
    {
        $orderTypeSummary = $this->srHasColumn('order_type')
            ? '
                SUM(CASE WHEN srs.order_type = \'FIRM\' THEN srs.qty ELSE 0 END) as firm_qty,
                SUM(CASE WHEN srs.order_type = \'FORECAST\' THEN srs.qty ELSE 0 END) as forecast_qty,
                COUNT(CASE WHEN srs.order_type = \'FIRM\' THEN 1 END) as firm_count,
                COUNT(CASE WHEN srs.order_type = \'FORECAST\' THEN 1 END) as forecast_count,
            '
            : '
                0 as firm_qty,
                0 as forecast_qty,
                0 as firm_count,
                0 as forecast_count,
            ';

        $query = $this->applyFilters(SR::query(), $filters)
            ->leftJoin('upload_batches', 'srs.upload_batch_id', '=', 'upload_batches.id')
            ->leftJoin('customers', 'upload_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'upload_batches.port_id', '=', 'ports.id');

        if (Auth::check() && !Auth::user()?->isAdmin()) {
            $query->where('upload_batches.uploaded_by', Auth::id());
        }

        $query->selectRaw("
                MIN(srs.id) as id,
                srs.upload_batch_id,
                customers.code as customer,
                ports.name as port,
                upload_batches.source_file as source_file,
                upload_batches.batch_uuid as upload_batch,
                upload_batches.sheet_name,
                COALESCE(MIN(upload_batches.created_at), MIN(srs.created_at)) as upload_date,
                COUNT(*) as total_items,
                SUM(srs.qty) as total_qty,
                {$orderTypeSummary}
                COUNT(DISTINCT srs.assy_number) as unique_assy_numbers,
                MIN(srs.etd) as earliest_etd,
                MAX(srs.etd) as latest_etd
            ")
            ->groupBy(
                'srs.upload_batch_id',
                'customers.code',
                'ports.name',
                'upload_batches.source_file',
                'upload_batches.batch_uuid',
                'upload_batches.sheet_name'
            )
            ->orderByRaw('COALESCE(MIN(upload_batches.created_at), MIN(srs.created_at)) desc');

        return $perPage ? $query->paginate($perPage)->withQueryString() : $query->get();
    }

    private function materializedBatchSummaries(array $filters, ?int $perPage = 25)
    {
        $representativeSr = SR::query()
            ->selectRaw('MIN(id) as id, upload_batch_id')
            ->whereNotNull('upload_batch_id')
            ->groupBy('upload_batch_id');

        $query = $this->applySummaryFilters(Summary::query(), $filters)
            ->join('upload_batches', 'summaries.upload_batch_id', '=', 'upload_batches.id')
            ->leftJoin('customers', 'upload_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'upload_batches.port_id', '=', 'ports.id')
            ->leftJoinSub($representativeSr, 'representative_srs', function ($join) {
                $join->on('summaries.upload_batch_id', '=', 'representative_srs.upload_batch_id');
            });

        if (Auth::check() && !Auth::user()?->isAdmin()) {
            $query->where('upload_batches.uploaded_by', Auth::id());
        }

        $query->selectRaw("
                representative_srs.id as id,
                summaries.upload_batch_id,
                customers.code as customer,
                ports.name as port,
                upload_batches.source_file,
                upload_batches.batch_uuid as upload_batch,
                upload_batches.sheet_name,
                upload_batches.created_at as upload_date,
                SUM(summaries.line_count) as total_items,
                SUM(summaries.total_qty) as total_qty,
                SUM(CASE WHEN summaries.order_type = 'FIRM' THEN summaries.total_qty ELSE 0 END) as firm_qty,
                SUM(CASE WHEN summaries.order_type = 'FORECAST' THEN summaries.total_qty ELSE 0 END) as forecast_qty,
                SUM(CASE WHEN summaries.order_type = 'FIRM' THEN summaries.line_count ELSE 0 END) as firm_count,
                SUM(CASE WHEN summaries.order_type = 'FORECAST' THEN summaries.line_count ELSE 0 END) as forecast_count,
                COUNT(DISTINCT summaries.assy_number) as unique_assy_numbers,
                (SELECT MIN(etd) FROM srs WHERE srs.upload_batch_id = summaries.upload_batch_id) as earliest_etd,
                (SELECT MAX(etd) FROM srs WHERE srs.upload_batch_id = summaries.upload_batch_id) as latest_etd
            ")
            ->groupBy(
                'representative_srs.id',
                'summaries.upload_batch_id',
                'customers.code',
                'ports.name',
                'upload_batches.source_file',
                'upload_batches.batch_uuid',
                'upload_batches.sheet_name',
                'upload_batches.created_at'
            )
            ->orderByDesc('upload_batches.created_at');

        return $perPage ? $query->paginate($perPage)->withQueryString() : $query->get();
    }

    private function hasCompleteMaterializedSummaries(): bool
    {
        $summaryBatchCount = Summary::query()
            ->whereNotNull('upload_batch_id')
            ->distinct('upload_batch_id')
            ->count('upload_batch_id');

        if ($summaryBatchCount === 0) {
            return false;
        }

        $srBatchCount = SR::query()
            ->whereNotNull('upload_batch_id')
            ->distinct('upload_batch_id')
            ->count('upload_batch_id');

        return $srBatchCount === 0 || $summaryBatchCount >= $srBatchCount;
    }

    public function detail(SR $sr)
    {
        if ($sr->upload_batch_id) {
            $summaries = Summary::query()
                ->with(['assy.carline', 'productionWeek'])
                ->where('upload_batch_id', $sr->upload_batch_id)
                ->orderBy('etd')
                ->orderBy('production_week_id')
                ->orderBy('assy_number')
                ->get();

            if ($summaries->isNotEmpty()) {
                return $this->summaryRowsForReview($summaries);
            }
        }

        return $this->enrichRows($this->batchRows($sr)->get());
    }

    public function srPayload(SR $sr, bool $includeMonth = false): array
    {
        $payload = [
            'id' => $sr->id,
            'source_file' => $sr->source_file,
            'customer' => $sr->customer,
            'port' => $sr->port,
            'sheet_name' => $sr->sheet_name,
            'upload_date' => $sr->created_at?->toIso8601String(),
        ];

        if ($includeMonth) {
            $payload['month'] = $sr->month;
        }

        return $payload;
    }

    public function apiPayload(SR $sr): array
    {
        $summaryData = $this->detail($sr);
        $firmQty = $summaryData->where('order_type', 'FIRM')->sum('qty');
        $forecastQty = $summaryData->where('order_type', 'FORECAST')->sum('qty');

        return [
            'success' => true,
            'sr' => $this->srPayload($sr),
            'summary' => [
                'total_records' => $summaryData->count(),
                'unique_assy_numbers' => $summaryData->pluck('assy_number')->unique()->count(),
                'firm_qty' => $firmQty,
                'forecast_qty' => $forecastQty,
                'total_qty' => $firmQty + $forecastQty,
                'months_covered' => $summaryData->pluck('month')->filter()->unique()->sort()->values(),
            ],
            'data' => $summaryData,
        ];
    }

    public function deleteUpload(SR $sr): array
    {
        $deleted = DB::transaction(function () use ($sr) {
            $sourceFile = $sr->source_file;

            if ($sr->upload_batch_id) {
                $deletedCount = SR::where('upload_batch_id', $sr->upload_batch_id)->delete();
                UploadBatch::whereKey($sr->upload_batch_id)->delete();
            } else {
                $deletedCount = $sr->delete() ? 1 : 0;
            }

            return [
                'source_file' => $sourceFile,
                'deleted_count' => $deletedCount,
            ];
        });

        $this->cache->invalidate();

        return $deleted;
    }

    public function updateReviewedRow(Summary $summary, array $validated): Summary
    {
        $assyNumber = trim((string) ($validated['assy_number'] ?? $summary->assy_number));
        $assy = Assy::query()
            ->with('carline')
            ->where('assy_number', $assyNumber)
            ->first();

        $etd = $validated['etd'] ?? null;
        $week = $etd && $summary->customer_id
            ? ProductionWeek::findByDate($summary->customer_id, Carbon::parse($etd))
            : null;
        $portName = $validated['port'] ?? null;
        $portId = $summary->port_id;

        if ($portName !== null && $summary->customer_id) {
            $portId = Port::query()
                ->where('customer_id', $summary->customer_id)
                ->whereRaw('LOWER(name) = ?', [strtolower($portName)])
                ->value('id') ?: $portId;
        }

        $summary->fill([
            'assy_number' => $assyNumber,
            'assy_id' => $assy?->id,
            'port_id' => $portId,
            'production_week_id' => $week?->id,
            'etd' => $this->nullableDate($validated['etd'] ?? null),
            'eta' => $this->nullableDate($validated['eta'] ?? null),
            'month' => $this->nullableString($validated['month'] ?? null) ?: $week?->month_name,
            'week' => $this->nullableString($validated['week'] ?? null) ?: $week?->week_no,
            'order_type' => $this->nullableUpper($validated['order_type'] ?? null),
        ]);

        $changes = [];
        foreach ($summary->getDirty() as $key => $newValue) {
            $originalValue = $summary->getOriginal($key);
            if (in_array($key, ['created_at', 'updated_at'])) {
                continue;
            }
            if ($originalValue instanceof \Carbon\Carbon || $originalValue instanceof \DateTimeInterface) {
                $originalValue = $originalValue->toDateString();
            }
            if ($newValue instanceof \Carbon\Carbon || $newValue instanceof \DateTimeInterface) {
                $newValue = $newValue->toDateString();
            }
            if ($originalValue != $newValue) {
                if ($key === 'port_id') {
                    $oldPort = Port::find($originalValue)?->name;
                    $newPort = Port::find($newValue)?->name;
                    $changes['port'] = [
                        'old' => $oldPort,
                        'new' => $newPort,
                    ];
                } elseif ($key === 'production_week_id') {
                    $oldWeek = ProductionWeek::find($originalValue)?->week_no;
                    $newWeek = ProductionWeek::find($newValue)?->week_no;
                    $changes['production_week'] = [
                        'old' => $oldWeek,
                        'new' => $newWeek,
                    ];
                } else {
                    $changes[$key] = [
                        'old' => $originalValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        if (!empty($changes)) {
            $batch = $summary->uploadBatch()->first();
            $repSrId = SR::where('upload_batch_id', $summary->upload_batch_id)->min('id');
            \App\Models\HistoryLog::log(
                'summary_row_updated',
                'Summary Row Updated',
                'updated',
                $summary->customer,
                $summary->port,
                $batch?->source_file,
                $batch?->batch_uuid,
                $batch?->sheet_name,
                1,
                $summary->total_qty,
                0, 0, 0,
                $repSrId ?: $summary->id,
                "Updated summary row for Assy {$summary->assy_number}",
                ['changes' => $changes]
            );
        }

        $summary->save();

        $this->cache->invalidate('summary');
        $this->cache->invalidate('dashboard');

        return $summary->refresh();
    }

    public function updateReviewedPeriod(array $summaryIds, array $validated): int
    {
        $rows = Summary::query()
            ->whereIn('id', $summaryIds)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($rows, $validated, $summaryIds) {
            $updated = 0;
            $appliedChanges = [];
            foreach ($validated as $key => $value) {
                if ($key === 'summary_ids') {
                    continue;
                }
                if ($value !== null && $value !== '') {
                    $appliedChanges[$key] = $value;
                }
            }

            foreach ($rows as $summary) {
                $etd = $validated['etd'] ?? null;
                $week = $etd && $summary->customer_id
                    ? ProductionWeek::findByDate($summary->customer_id, Carbon::parse($etd))
                    : null;
                $portName = $validated['port'] ?? null;
                $portId = $summary->port_id;

                if ($portName !== null && $summary->customer_id) {
                    $portId = Port::query()
                        ->where('customer_id', $summary->customer_id)
                        ->whereRaw('LOWER(name) = ?', [strtolower($portName)])
                        ->value('id') ?: $portId;
                }

                $summary->fill([
                    'order_type' => $this->nullableUpper($validated['order_type'] ?? null),
                    'port_id' => $portId,
                    'production_week_id' => $week?->id,
                    'etd' => $this->nullableDate($validated['etd'] ?? null),
                    'eta' => $this->nullableDate($validated['eta'] ?? null),
                    'month' => $this->nullableString($validated['month'] ?? null) ?: $week?->month_name,
                    'week' => $this->nullableString($validated['week'] ?? null) ?: $week?->week_no,
                ]);
                $summary->save();
                $updated += 1;
            }

            if ($updated > 0) {
                $firstSummary = $rows->first();
                $batch = $firstSummary->uploadBatch()->first();
                $repSrId = SR::where('upload_batch_id', $firstSummary->upload_batch_id)->min('id');

                \App\Models\HistoryLog::log(
                    'summary_period_updated',
                    'Summary Period Updated',
                    'updated',
                    $firstSummary->customer,
                    $firstSummary->port,
                    $batch?->source_file,
                    $batch?->batch_uuid,
                    $batch?->sheet_name,
                    $updated,
                    $rows->sum('total_qty'),
                    0, 0, 0,
                    $repSrId ?: $firstSummary->id,
                    "Bulk updated {$updated} summary rows",
                    ['changes' => $appliedChanges, 'updated_ids' => $summaryIds]
                );
            }

            $this->cache->invalidate('summary');
            $this->cache->invalidate('dashboard');

            return $updated;
        });
    }

    private function batchRows(SR $sr)
    {
        return SR::query()
            ->with('assy.carline')
            ->when(
                $sr->upload_batch_id,
                fn ($query) => $query->where('upload_batch_id', $sr->upload_batch_id),
                fn ($query) => $query->whereKey($sr->id)
            )
            ->orderBy('etd')
            ->orderBy('assy_number');
    }

    private function enrichRows(Collection $rows)
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

            $row->assy_spp_number = $row->assy?->assy_number ?: $this->formatAssySppNumber($row->assy_number);

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

    private function summaryRowsForReview(Collection $rows)
    {
        return $rows->map(function (Summary $row) {
            $carline = $row->assy?->carline;

            return (object) [
                'id' => $row->id,
                'summary_id' => $row->id,
                'source' => 'summary',
                'is_editable' => true,
                'customer' => $row->customer,
                'assy_number' => $row->assy_number,
                'assy_spp_number' => $row->assy?->assy_number ?: $this->formatAssySppNumber($row->assy_number),
                'model' => $row->model ?: $carline?->code,
                'family' => $row->family ?: $carline?->description,
                'order_type' => $row->order_type,
                'month' => $row->month,
                'week' => $row->week,
                'etd' => $row->etd?->toDateString(),
                'eta' => $row->eta?->toDateString(),
                'port' => $row->port,
                'route' => null,
                'qty' => $row->total_qty,
                'total_qty' => $row->total_qty,
                'line_count' => $row->line_count,
                'assy_id' => $row->assy_id,
                'production_week_id' => $row->production_week_id,
                'extra' => [
                    'summary_row' => true,
                    'mapped' => $row->assy_id !== null,
                ],
            ];
        });
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['customer']), fn ($query) => $query->whereHas('customer', fn ($q) => $q->where('code', $filters['customer'])))
            ->when(!empty($filters['search']), fn ($query) => $query->whereHas('uploadBatch', fn ($q) => $q->where('source_file', 'like', '%' . $filters['search'] . '%')))
            ->when(!empty($filters['assy_number']), fn ($query) => $query->where('srs.assy_number', 'like', '%' . $filters['assy_number'] . '%'))
            ->when(!empty($filters['order_type']) && $this->srHasColumn('order_type'), fn ($query) => $query->where('srs.order_type', $filters['order_type']))
            ->when(!empty($filters['etd_start']), fn ($query) => $query->where('srs.etd', '>=', $filters['etd_start']))
            ->when(!empty($filters['etd_end']), fn ($query) => $query->where('srs.etd', '<=', $filters['etd_end']))
            ->when(!empty($filters['eta_start']), fn ($query) => $query->where('srs.eta', '>=', $filters['eta_start']))
            ->when(!empty($filters['eta_end']), fn ($query) => $query->where('srs.eta', '<=', $filters['eta_end']))
            ->when(!empty($filters['month']), fn ($query) => $query->whereHas('productionWeek', fn ($q) => $q->where('month_name', $filters['month'])));
    }

    private function applySummaryFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['customer']), fn ($query) => $query->whereHas('customer', fn ($q) => $q->where('code', $filters['customer'])))
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $query->whereHas('uploadBatch', function ($q) use ($filters) {
                    $q->where('source_file', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when(!empty($filters['assy_number']), fn ($query) => $query->where('summaries.assy_number', 'like', '%' . $filters['assy_number'] . '%'))
            ->when(!empty($filters['order_type']), fn ($query) => $query->where('summaries.order_type', $filters['order_type']))
            ->when(!empty($filters['etd_start']), fn ($query) => $query->where('summaries.etd', '>=', $filters['etd_start']))
            ->when(!empty($filters['etd_end']), fn ($query) => $query->where('summaries.etd', '<=', $filters['etd_end']))
            ->when(!empty($filters['eta_start']), fn ($query) => $query->where('summaries.eta', '>=', $filters['eta_start']))
            ->when(!empty($filters['eta_end']), fn ($query) => $query->where('summaries.eta', '<=', $filters['eta_end']))
            ->when(!empty($filters['month']), fn ($query) => $query->where('summaries.month', $filters['month']));
    }

    private function srHasColumn(string $column): bool
    {
        static $columns = null;

        $columns ??= array_flip(Schema::getColumnListing('srs'));

        return isset($columns[$column]);
    }

    private function nullableUpper(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : strtoupper($value);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    private function formatAssySppNumber(?string $value): ?string
    {
        $assyNumber = trim((string) $value);

        if ($assyNumber === '') {
            return null;
        }

        if (str_contains($assyNumber, '-') || strlen($assyNumber) <= 5) {
            return $assyNumber;
        }

        return substr($assyNumber, 0, -5) . '-' . substr($assyNumber, -5);
    }
}
