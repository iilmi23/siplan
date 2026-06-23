<?php

namespace App\Services\SPP;

use App\Models\Assy;
use App\Models\Customer;
use App\Models\HistoryLog;
use App\Models\ProductionWeek;
use App\Models\SPP;
use App\Models\SppBatch;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SPPService
{
    /**
     * Get summary of all upload batches with filters.
     */
    public function batchSummary(array $filters): Collection
    {
        $query = $this->applyFilters(Summary::query(), $filters)
            ->join('upload_batches', 'summaries.upload_batch_id', '=', 'upload_batches.id')
            ->join('customers', 'upload_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'upload_batches.port_id', '=', 'ports.id');

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            $query->where('upload_batches.uploaded_by', Auth::id());
        }

        return $query->selectRaw('
                summaries.upload_batch_id as id,
                summaries.upload_batch_id,
                customers.code as customer,
                ports.name as port,
                upload_batches.source_file,
                upload_batches.batch_uuid as upload_batch,
                upload_batches.sheet_name,
                upload_batches.created_at as upload_date,
                SUM(summaries.line_count) as total_items,
                SUM(summaries.total_qty) as total_qty,
                SUM(CASE WHEN summaries.order_type = \'FIRM\' THEN summaries.total_qty ELSE 0 END) as firm_qty,
                SUM(CASE WHEN summaries.order_type = \'FORECAST\' THEN summaries.total_qty ELSE 0 END) as forecast_qty,
                COUNT(DISTINCT summaries.assy_number) as unique_assy_numbers,
                (SELECT MIN(eta) FROM srs WHERE srs.upload_batch_id = summaries.upload_batch_id) as earliest_eta,
                (SELECT MAX(eta) FROM srs WHERE srs.upload_batch_id = summaries.upload_batch_id) as latest_eta
            ')
            ->groupBy(
                'summaries.upload_batch_id',
                'customers.code',
                'ports.name',
                'upload_batches.source_file',
                'upload_batches.batch_uuid',
                'upload_batches.sheet_name',
                'upload_batches.created_at'
            )
            ->orderByDesc('upload_batches.created_at')
            ->get();
    }

    /**
     * Preview SPP data before saving.
     */
    public function previewData(UploadBatch $batch): array
    {
        $records = $this->summaryRowsForBatch($batch)
            ->with(['assy.carline', 'productionWeek'])
            ->get();

        abort_if($records->isEmpty(), 422, 'Summary belum tersedia untuk upload ini. Review Summary dulu sebelum membuat SPP.');

        return $this->previewPayload($this->batchPayload($batch), $records, $batch->customer_id, 'total_qty');
    }

    /**
     * Construct preview payload.
     */
    private function previewPayload(array $srPayload, Collection $records, ?int $customerId, string $qtyColumn): array
    {
        $firstEta = $records->pluck('eta')->filter()->sort()->first();
        if (!$firstEta) {
            throw new \InvalidArgumentException('ETA belum lengkap untuk membuat periode SPP.');
        }

        $start = Carbon::parse($firstEta)->startOfMonth();
        $firmCutoff = $this->firmCutoffMonth($records);
        $months = $this->sppMonths($start, $firmCutoff, $customerId);
        $rows = $this->sppRows($records, $months, $qtyColumn);

        return [
            'sr' => $srPayload,
            'summary' => [
                'total_records' => $records->count(),
                'total_qty' => $records->sum($qtyColumn),
                'unique_assy_numbers' => $records->pluck('assy_number')->filter()->unique()->count(),
                'period_range' => sprintf(
                    '%s - %s',
                    $months->first()['period_label'] ?? '-',
                    $months->last()['period_label'] ?? '-'
                ),
            ],
            'months' => $months->values(),
            'rows' => $rows->values(),
            'monthTotals' => $this->monthTotals($rows, $months),
        ];
    }

    /**
     * Show SPP page details.
     */
    public function showData(string $period, array $filters): array
    {
        $query = $this->resolvedSourceQuery($filters);
        $source = $query['source'];
        $date = Carbon::createFromFormat('Y-m', $period);
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $records = $this->fetchRecordsForSource($source, $query['query'], $date, $start, $end, $period);
        $monthsList = null;
        $rowsList = null;

        if ($source === 'spp') {
            $firstRec = (clone $query['query'])->where('period', $period)->first();
            if ($firstRec) {
                $allRecords = SPP::with(['assy.carline', 'customer'])
                    ->where('spp_batch_id', $firstRec->spp_batch_id)
                    ->get();

                $monthsList = $this->buildSppMonthsList($allRecords, $firstRec->customer_id);
                $rowsList = $this->buildSppRowsList($allRecords, $monthsList);
            }
        }

        $qtyColumn = $source === 'sr' ? 'qty' : 'total_qty';

        $payload = [
            'records' => $records,
            'summary' => [
                'period' => $date->format('F Y'),
                'total_records' => $records->count(),
                'total_qty' => $records->sum($qtyColumn),
                'unique_assy_numbers' => $records->pluck('assy_number')->unique()->count(),
                'source' => $source,
                'selected_sr' => $this->selectedBatch($filters),
            ],
        ];

        if ($monthsList !== null && $rowsList !== null) {
            $payload['months'] = $monthsList;
            $payload['rows'] = $rowsList;
        }

        return $payload;
    }

    /**
     * Fetch records based on data source (spp, summary, sr).
     */
    private function fetchRecordsForSource(string $source, Builder $query, Carbon $date, Carbon $start, Carbon $end, string $period): Collection
    {
        if ($source === 'spp') {
            return $query->with(['assy.carline'])->where('period', $period)->get();
        }

        if ($source === 'summary') {
            return $query->with(['assy.carline'])->whereHas('productionWeek', function ($q) use ($date) {
                $q->where('year', $date->year)
                  ->where('month_number', $date->month);
            })->get();
        }

        return $query->whereBetween('eta', [$start->toDateString(), $end->toDateString()])->get();
    }

    /**
     * Build month metadata lists for showData spp.
     */
    private function buildSppMonthsList(Collection $allRecords, ?int $customerId): Collection
    {
        $uniquePeriods = $allRecords->pluck('period')->unique()->sort()->values();
        $years = $uniquePeriods->map(fn($p) => (int)substr($p, 0, 4))->unique()->all();

        $allWeeks = ProductionWeek::query()
            ->whereIn('year', $years)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->orderBy('week_no')
            ->get();

        return $uniquePeriods->map(function ($p) use ($allRecords, $allWeeks) {
            $date = Carbon::createFromFormat('Y-m', $p);
            $weeks = $allWeeks->where('year', $date->year)->where('month_number', $date->month)->values();
            $monthLabel = strtoupper($date->format('M'));
            $rangeLabel = '';

            if ($weeks->isNotEmpty()) {
                $wFirst = $weeks->first();
                $wLast = $weeks->last();
                $start = Carbon::parse($wFirst->week_start);
                $end = Carbon::parse($wLast->end_date);
                $monthLabel = strtoupper($wFirst->month_name ?: $monthLabel);
                $rangeLabel = strtoupper($start->format('d/M') . ' ~ ' . $end->format('d/M'));
            } else {
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();
                $rangeLabel = strtoupper($start->format('d/M') . ' ~ ' . $end->format('d/M'));
            }

            $isFirm = $allRecords->where('period', $p)->where('order_type', 'FIRM')->isNotEmpty();

            return [
                'period' => $p,
                'label' => $monthLabel,
                'range_label' => $rangeLabel,
                'year' => $date->year,
                'bucket' => $isFirm ? 'firm' : 'forecast',
            ];
        })->values();
    }

    /**
     * Build rows list for showData spp.
     */
    private function buildSppRowsList(Collection $allRecords, Collection $monthsList): Collection
    {
        return $allRecords->groupBy('assy_number')->map(function ($assyRows, $assyNumber) use ($monthsList) {
            $first = $assyRows->first();
            $assy = $first->assy;
            $monthly = [];

            foreach ($monthsList as $month) {
                $p = $month['period'];
                $rec = $assyRows->where('period', $p)->first();
                $monthly[$p] = [
                    'bal' => $rec ? (int)$rec->bal_qty : 0,
                    'del' => $rec ? (int)$rec->del_qty : 0,
                    'prod' => $rec ? (int)$rec->prod_qty : 0,
                    'order_type' => $rec ? $rec->order_type : ($month['bucket'] === 'firm' ? 'FIRM' : 'FORECAST'),
                ];
            }

            $customerModel = $first->getRelationValue('customer');
            $customerCode = $customerModel instanceof Customer
                ? $customerModel->code
                : (is_string($customerModel) ? $customerModel : '');

            return [
                'customer' => $customerCode,
                'type' => $assy?->pattern ?: '',
                'pattern' => $assy?->pattern ?: '',
                'carline' => $assy?->carline?->code ?? $first->carline,
                'assy_number' => $assyNumber,
                'level' => $assy?->level ?? $first->level,
                'assy_code' => $assy?->assy_code ?? $first->assy_code,
                'std_pack' => $first->std_pack !== null ? $first->std_pack : ($assy?->standard_sea_quantity ?? ''),
                'umh' => $first->umh !== null ? $first->umh : ($assy?->umh ?? ''),
                'months' => $monthly,
                'total_qty' => $assyRows->sum('del_qty'),
            ];
        })->values();
    }

    /**
     * Retrieve options for SR batch select filter.
     */
    public function srBatchOptions(array $filters): Collection
    {
        $query = UploadBatch::query();
        /** @var User|null $user */
        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            $query->where('uploaded_by', Auth::id());
        }

        return $query
            ->with(['customer:id,code,name'])
            ->when(!empty($filters['customer']), function ($query) use ($filters) {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('code', $filters['customer']));
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

    /**
     * Store fixed SPP plan.
     */
    public function storeFixed(UploadBatch $batch, array $validated): int
    {
        $plan = $this->singlePlan($batch);
        $records = $this->buildFixedRecordsForPlan($plan, $validated);

        return $this->persistSppPlan($plan, $records);
    }

    /**
     * Resolve the source query data provider.
     */
    private function resolvedSourceQuery(array $filters): array
    {
        $sppQuery = $this->filteredQuery(SPP::query(), $filters);

        if ((clone $sppQuery)->exists()) {
            return ['source' => 'spp', 'query' => $sppQuery];
        }

        $summaryQuery = $this->filteredQuery(Summary::query(), $filters);

        if ((clone $summaryQuery)->exists()) {
            return ['source' => 'summary', 'query' => $summaryQuery];
        }

        return ['source' => 'sr', 'query' => $this->filteredQuery(SR::query(), $filters)];
    }

    /**
     * Filter queries with generic conditions.
     */
    private function filteredQuery(Builder $query, array $filters): Builder
    {
        $query = $query
            ->when(!empty($filters['customer']), function ($query) use ($filters) {
                $query->whereHas('customer', fn ($q) => $q->where('code', $filters['customer']));
            })
            ->when(!empty($filters['sr_batch']), fn ($query) => $query->where('upload_batch_id', (int) $filters['sr_batch']));

        /** @var User|null $user */
        $user = Auth::user();
        if ($user && !$user->isAdmin()) {
            $modelClass = get_class($query->getModel());
            if ($modelClass === SPP::class) {
                $query->whereHas('sppBatch', fn($q) => $q->where('generated_by', Auth::id()));
            } elseif ($modelClass === Summary::class || $modelClass === SR::class) {
                $query->whereHas('uploadBatch', fn($q) => $q->where('uploaded_by', Auth::id()));
            }
        }

        return $query;
    }

    private function summaryRowsForBatch(UploadBatch $batch): Builder
    {
        return Summary::query()
            ->where('upload_batch_id', $batch->id)
            ->orderBy('production_week_id')
            ->orderBy('assy_number');
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(!empty($filters['customer']), fn ($query) => $query->where('customers.code', $filters['customer']))
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $query->where('upload_batches.source_file', 'like', '%' . $filters['search'] . '%');
            });
    }

    /**
     * Map request row lists to database record arrays.
     */
    private function buildFixedRecordsForPlan(array $plan, array $validated): array
    {
        $monthMap = collect($validated['months'])->keyBy('period');
        $assyMap = Assy::query()
            ->with('carline')
            ->whereIn('assy_number', collect($validated['rows'])->pluck('assy_number')->filter()->unique())
            ->get()
            ->keyBy('assy_number');
        $records = [];
        $now = now();

        foreach ($validated['rows'] as $row) {
            $assyNumber = $row['assy_number'];
            $assy = $assyMap->get($assyNumber);

            foreach ($monthMap as $period => $month) {
                $cell = $row['months'][$period] ?? [];
                $delQty = $this->integerValue($cell['del'] ?? 0);
                $records[] = [
                    'spp_batch_id' => null,
                    'upload_batch_id' => $plan['upload_batch_id'],
                    'customer_id' => $plan['customer_id'],
                    'port_id' => $plan['port_id'],
                    'assy_id' => $assy?->id,
                    'type' => $row['type'] ?? null,
                    'assy_number' => $assyNumber,
                    'level' => $row['level'] ?? $assy?->level,
                    'assy_code' => $row['assy_code'] ?? $assy?->assy_code,
                    'std_pack' => $this->nullableInteger($row['std_pack'] ?? $assy?->standard_sea_quantity),
                    'umh' => $this->nullableDecimal($row['umh'] ?? $assy?->umh),
                    'period' => $period,
                    'order_type' => strtoupper($month['bucket'] ?? '') === 'FIRM' ? 'FIRM' : 'FORECAST',
                    'bal_qty' => $this->integerValue($cell['bal'] ?? 0),
                    'del_qty' => $delQty,
                    'prod_qty' => $this->integerValue($cell['prod'] ?? $delQty),
                    'total_qty' => $delQty,
                    'extra' => json_encode([
                        'source_sr_id' => $plan['source_sr_id'],
                        'source_batch_ids' => $plan['source_batch_ids'],
                        'is_mapped' => $assy !== null,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return $records;
    }

    /**
     * Persist SPP plan within a transaction.
     */
    private function persistSppPlan(array $plan, array $records): int
    {
        return DB::transaction(function () use ($plan, $records) {
            $sppBatch = SppBatch::updateOrCreate(
                ['batch_uuid' => $plan['upload_batch']],
                [
                    'customer_id' => $plan['customer_id'],
                    'port_id' => $plan['port_id'],
                    'generated_by' => Auth::id(),
                    'source_file' => $plan['source_file'],
                    'sheet_name' => $plan['sheet_name'],
                    'status' => 'generated',
                    'source_batch_count' => count($plan['source_batch_ids']),
                    'record_count' => count($records),
                    'total_qty' => collect($records)->sum('total_qty'),
                    'metadata' => [
                        'source_sr_id' => $plan['source_sr_id'],
                        'source_batch_ids' => $plan['source_batch_ids'],
                        'combined' => count($plan['source_batch_ids']) > 1,
                    ],
                ]
            );

            $sppBatch->sourceBatches()->sync($plan['source_batch_ids']);

            $this->cleanExistingSppData($sppBatch, $plan, $records);

            foreach (array_chunk($records, 500) as $chunk) {
                SPP::insert($chunk);
            }

            HistoryLog::log(
                'spp_generated',
                'SPP Generated',
                'generated',
                $plan['customer'],
                $plan['port'],
                $plan['source_file'],
                $sppBatch->batch_uuid,
                $plan['sheet_name'],
                count($records),
                collect($records)->sum('total_qty'),
                0,
                0,
                count($plan['source_batch_ids']),
                $sppBatch->id,
                $sppBatch->notes
            );

            return count($records);
        });
    }

    /**
     * Bersihkan rekaman database lama yang merujuk ke periode dan customer yang sama.
     */
    private function cleanExistingSppData(SppBatch $sppBatch, array $plan, array &$records): void
    {
        // Hapus data SPP lama yang terikat dengan batch SPP ini
        SPP::query()
            ->where('spp_batch_id', $sppBatch->id)
            ->delete();

        // Jika ada upload_batch_id terkait, hapus juga data SPP lama yang terkait dengan upload batch tersebut
        if ($plan['upload_batch_id']) {
            SPP::query()
                ->where('upload_batch_id', $plan['upload_batch_id'])
                ->delete();
        }

        // Dapatkan seluruh periode SPP unik dari data record yang akan disimpan
        $periods = collect($records)->pluck('period')->unique()->all();

        // Cari ID batch SPP lama milik customer yang sama dan berada pada periode yang sama
        $oldSppBatchIds = SPP::query()
            ->where('customer_id', $plan['customer_id'])
            ->whereIn('period', $periods)
            ->where('spp_batch_id', '!=', $sppBatch->id)
            ->pluck('spp_batch_id')
            ->unique()
            ->all();

        // Hapus data SPP lama di database agar tidak terjadi duplikasi data per periode
        SPP::query()
            ->where('customer_id', $plan['customer_id'])
            ->whereIn('period', $periods)
            ->where('spp_batch_id', '!=', $sppBatch->id)
            ->delete();

        if (!empty($oldSppBatchIds)) {
            foreach ($oldSppBatchIds as $oldBatchId) {
                $hasRecords = SPP::where('spp_batch_id', $oldBatchId)->exists();
                if (!$hasRecords) {
                    SppBatch::destroy($oldBatchId);
                }
            }
        }

        // Masukkan ID batch SPP yang baru dibuat ke dalam setiap baris data record (menggunakan reference agar termutasi di luar fungsi)
        foreach ($records as &$record) {
            $record['spp_batch_id'] = $sppBatch->id;
        }
    }

    private function batchPayload(UploadBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'customer' => $batch->customer?->code,
            'port' => $batch->port?->name,
            'source_file' => $batch->source_file,
            'sheet_name' => $batch->sheet_name,
            'upload_batch' => $batch->batch_uuid,
            'upload_date' => optional($batch->created_at)->format('Y-m-d H:i:s'),
        ];
    }

    private function singlePlan(UploadBatch $batch): array
    {
        return [
            'upload_batch_id' => $batch->id,
            'customer_id' => $batch->customer_id,
            'port_id' => $batch->port_id,
            'customer' => $batch->customer?->code,
            'source_file' => $batch->source_file,
            'sheet_name' => $batch->sheet_name,
            'upload_batch' => $batch->batch_uuid,
            'port' => $batch->port?->name,
            'source_sr_id' => null,
            'source_batch_ids' => [$batch->id],
        ];
    }

    private function selectedBatch(array $filters): ?array
    {
        if (empty($filters['sr_batch'])) {
            return null;
        }

        $batch = UploadBatch::query()
            ->with(['customer:id,code,name'])
            ->find((int) $filters['sr_batch']);

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

    /**
     * Generate metadata for months in the SPP target offset timeline.
     */
    private function sppMonths(Carbon $start, ?Carbon $firmCutoff, ?int $customerId): Collection
    {
        $firmCutoff = $firmCutoff?->copy()->startOfMonth();
        $dates = collect(range(-1, 5))->map(fn($offset) => $start->copy()->addMonths($offset));

        $years = $dates->map(fn($d) => $d->year)->unique()->values()->all();
        $monthNumbers = $dates->map(fn($d) => $d->month)->unique()->values()->all();

        $allWeeks = ProductionWeek::query()
            ->whereIn('year', $years)
            ->whereIn('month_number', $monthNumbers)
            ->where(function ($q) use ($customerId) {
                if ($customerId) {
                    $q->where('customer_id', $customerId)->orWhereNull('customer_id');
                } else {
                    $q->whereNull('customer_id');
                }
            })
            ->orderBy('week_no')
            ->get()
            ->groupBy(fn($w) => $w->year . '-' . str_pad($w->month_number, 2, '0', STR_PAD_LEFT));

        return $dates->map(function (Carbon $date) use ($firmCutoff, $customerId, $allWeeks) {
            $key = $date->format('Y-m');
            $monthLabel = strtoupper($date->format('M'));

            $weeksForMonth = $allWeeks->get($key, collect());

            if ($customerId) {
                $customerWeeks = $weeksForMonth->where('customer_id', $customerId)->values();
                $weeks = $customerWeeks->isNotEmpty() ? $customerWeeks : $weeksForMonth->whereNull('customer_id')->values();
            } else {
                $weeks = $weeksForMonth->whereNull('customer_id')->values();
            }

            if ($weeks->isNotEmpty()) {
                $first = $weeks->sortBy('week_start')->first();
                $last  = $weeks->sortBy('end_date')->last();
                $startDate = Carbon::parse($first->week_start);
                $endDate   = Carbon::parse($last->end_date);
                $monthLabel = strtoupper($first->month_name ?: $monthLabel);
                $rangeLabel = strtoupper($startDate->format('d/M') . ' ~ ' . $endDate->format('d/M'));
            } else {
                $startDate = $date->copy()->startOfMonth();
                $endDate   = $date->copy()->endOfMonth();
                $rangeLabel = $this->formatSppDateRange($startDate, $endDate);
            }

            return [
                'period'       => $key,
                'label'        => $monthLabel,
                'label_full'   => $date->format('F Y'),
                'period_label' => $date->format('M Y'),
                'range_label'  => $rangeLabel,
                'year'         => $date->format('Y'),
                'period_start' => $startDate->toDateString(),
                'period_end'   => $endDate->toDateString(),
                'bucket'       => $firmCutoff !== null && $date->lessThanOrEqualTo($firmCutoff) ? 'firm' : 'forecast',
            ];
        });
    }

    private function firmCutoffMonth(Collection $records): ?Carbon
    {
        $firmPeriods = $records
            ->filter(fn ($item) => strtoupper((string) ($item->order_type ?? '')) === 'FIRM')
            ->map(fn ($item) => $this->itemPeriod($item))
            ->filter()
            ->sort()
            ->values();

        if ($firmPeriods->isEmpty()) {
            return null;
        }

        return Carbon::createFromFormat('Y-m', $firmPeriods->last())->startOfMonth();
    }

    /**
     * Map target lines into month-by-month SPP rows structure.
     */
    private function sppRows(Collection $items, Collection $months, string $qtyColumn): Collection
    {
        $periods = $months->pluck('period')->all();
        $firstItem = $items->first();
        $customerId = $firstItem?->customer_id;
        $assyNumbers = $items->pluck('assy_number')->filter()->unique()->all();

        $existingSpp = SPP::query()
            ->whereIn('assy_number', $assyNumbers)
            ->whereIn('period', $periods)
            ->where('customer_id', $customerId)
            ->latest('created_at')
            ->get()
            ->groupBy('assy_number')
            ->map(fn($group) => $group->groupBy('period')->map(fn($pGroup) => $pGroup->first()));

        return $items
            ->groupBy(fn ($item) => $item->assy_number ?: '-')
            ->map(function ($assyRows, $assyNumber) use ($months, $periods, $qtyColumn, $existingSpp) {
                $first = $assyRows->first();
                $assy = $first->assy;
                $monthly = [];
                $assyExisting = $existingSpp->get($assyNumber);

                foreach ($months as $idx => $month) {
                    $period = $month['period'];
                    $prevSpp = $assyExisting ? $assyExisting->get($period) : null;

                    if ($idx === 0 && $prevSpp) {
                        $bal = (int) $prevSpp->bal_qty;
                        $del = (int) $prevSpp->del_qty;
                        $prod = (int) $prevSpp->prod_qty;
                    } else {
                        $bal = 0;
                        $del = (int) $assyRows
                            ->filter(fn ($item) => $this->itemPeriod($item) === $period)
                            ->sum($qtyColumn);
                        $prod = $del;
                    }

                    $monthly[$period] = [
                        'bal' => $bal,
                        'del' => $del,
                        'prod' => $prod,
                        'order_type' => $month['bucket'] === 'firm' ? 'FIRM' : 'FORECAST',
                    ];
                }

                return [
                    'customer' => $first->customer,
                    'type' => $first->model ?: $first->family ?: $assy?->pattern ?: '',
                    'pattern' => $assy?->pattern ?: '',
                    'carline' => $assy?->carline?->code,
                    'assy_number' => $assyNumber,
                    'level' => $assy?->level ?: '',
                    'assy_code' => $assy?->assy_code ?: '',
                    'std_pack' => $assy?->standard_sea_quantity ?: '',
                    'umh' => $assy?->umh ?: '',
                    'months' => $monthly,
                    'total_qty' => collect($periods)->sum(fn ($period) => $monthly[$period]['del'] ?? 0),
                ];
            })
            ->sortBy([
                ['type', 'asc'],
                ['assy_number', 'asc'],
            ])
            ->values();
    }

    private function itemPeriod(mixed $item): ?string
    {
        $month = $item->month;
        if ($month) {
            if (preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
                return $month;
            }
            $monthMap = [
                'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
                'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
                'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
            ];
            $upperMonth = strtoupper(trim((string)$month));
            if (isset($monthMap[$upperMonth])) {
                $year = $item->year ?? ($item->etd ? Carbon::parse($item->etd)->year : ($item->eta ? Carbon::parse($item->eta)->year : null));
                if ($year) {
                    return sprintf('%04d-%s', $year, $monthMap[$upperMonth]);
                }
            }
        }

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

    private function formatSppDateRange(Carbon $start, Carbon $end): string
    {
        return strtoupper($start->format('d/M') . ' ~ ' . $end->format('d/M'));
    }

    private function integerValue(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round((float) $value);
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round((float) $value);
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
