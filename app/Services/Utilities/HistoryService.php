<?php

namespace App\Services\Utilities;

use App\Models\SppBatch;
use App\Models\UploadBatch;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class HistoryService
{
    public function filterKeys(): array
    {
        return [
            'type',
            'status',
            'customer',
            'search',
            'date_start',
            'date_end',
        ];
    }

    public function defaultFilters(array $filters): array
    {
        $end = Carbon::today()->toDateString();
        $start = Carbon::today()->subDays(30)->toDateString();

        return [
            'type' => $filters['type'] ?? '',
            'status' => $filters['status'] ?? '',
            'customer' => $filters['customer'] ?? '',
            'search' => $filters['search'] ?? '',
            'date_start' => $filters['date_start'] ?? $start,
            'date_end' => $filters['date_end'] ?? $end,
        ];
    }

    public function timeline(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $filters = $this->defaultFilters($filters);
        $perPage = min(max($perPage, 10), 100);

        $query = DB::table('history_logs')
            ->leftJoin('users', 'history_logs.user_id', '=', 'users.id')
            ->leftJoin('customers', 'history_logs.customer_code', '=', 'customers.code')
            ->selectRaw("
                history_logs.id as id,
                history_logs.activity_type as type,
                history_logs.title as title,
                history_logs.status as status,
                history_logs.created_at as occurred_at,
                history_logs.batch_uuid as batch_uuid,
                history_logs.source_file as source_file,
                history_logs.sheet_name as sheet_name,
                history_logs.record_count as record_count,
                history_logs.total_qty as total_qty,
                history_logs.mapped_count as mapped_count,
                history_logs.unmapped_count as unmapped_count,
                history_logs.source_batch_count as source_batch_count,
                history_logs.customer_code as customer,
                customers.name as customer_name,
                history_logs.port_name as port,
                users.name as actor,
                history_logs.related_id as related_id,
                history_logs.notes as notes,
                history_logs.details as details
            ")
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $query = $this->applyHistoryFilters($query, $filters);

        $paginator = $query->paginate($perPage)->withQueryString();
        $items = $paginator->getCollection()->map(fn ($row) => $this->rowPayload((array) $row));

        $this->attachSppSources($items);

        $paginator->setCollection($items);

        return $paginator;
    }

    private function applyHistoryFilters(Builder $query, array $filters): Builder
    {
        $start = $filters['date_start'] ?? null;
        $end = $filters['date_end'] ?? null;

        if (Auth::check() && !Auth::user()?->isAdmin()) {
            $query->where('history_logs.user_id', Auth::id());
        }

        return $query
            ->when($start, fn ($query) => $query->where("history_logs.created_at", '>=', Carbon::parse($start)->startOfDay()))
            ->when($end, fn ($query) => $query->where("history_logs.created_at", '<=', Carbon::parse($end)->endOfDay()))
            ->when(!empty($filters['type']), fn ($query) => $query->where("history_logs.activity_type", $filters['type']))
            ->when(!empty($filters['status']), fn ($query) => $query->where("history_logs.status", $filters['status']))
            ->when(!empty($filters['customer']), fn ($query) => $query->where("history_logs.customer_code", $filters['customer']))
            ->when(!empty($filters['search']), function ($query) use ($filters) {
                $keyword = '%' . $filters['search'] . '%';

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery
                        ->where("history_logs.source_file", 'like', $keyword)
                        ->orWhere("history_logs.batch_uuid", 'like', $keyword)
                        ->orWhere("history_logs.notes", 'like', $keyword)
                        ->orWhere("users.name", 'like', $keyword);
                });
            });
    }

    private function rowPayload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'status' => $row['status'],
            'occurred_at' => $row['occurred_at'] ? \Carbon\Carbon::parse($row['occurred_at'], 'UTC')->toIso8601String() : null,
            'batch_uuid' => $row['batch_uuid'],
            'source_file' => $row['source_file'],
            'sheet_name' => $row['sheet_name'],
            'record_count' => (int) $row['record_count'],
            'total_qty' => (int) $row['total_qty'],
            'mapped_count' => (int) $row['mapped_count'],
            'unmapped_count' => (int) $row['unmapped_count'],
            'source_batch_count' => (int) $row['source_batch_count'],
            'customer' => $row['customer'],
            'customer_name' => $row['customer_name'],
            'port' => $row['port'],
            'actor' => $row['actor'],
            'related_id' => $row['related_id'] ? (int) $row['related_id'] : null,
            'notes' => $row['notes'],
            'details' => isset($row['details']) ? (is_string($row['details']) ? json_decode($row['details'], true) : $row['details']) : null,
            'source_batches' => [],
        ];
    }

    private function attachSppSources(Collection $items): void
    {
        $sppIds = $items
            ->where('type', 'spp_generated')
            ->pluck('id')
            ->all();

        if (empty($sppIds)) {
            return;
        }

        $representativeSr = DB::table('srs')
            ->selectRaw('MIN(id) as sr_id, upload_batch_id')
            ->whereNotNull('upload_batch_id')
            ->groupBy('upload_batch_id');

        $sources = DB::table('spp_batch_sources')
            ->join('upload_batches', 'spp_batch_sources.upload_batch_id', '=', 'upload_batches.id')
            ->leftJoinSub($representativeSr, 'representative_srs', function ($join) {
                $join->on('upload_batches.id', '=', 'representative_srs.upload_batch_id');
            })
            ->whereIn('spp_batch_sources.spp_batch_id', $sppIds)
            ->orderBy('upload_batches.created_at')
            ->get([
                'spp_batch_sources.spp_batch_id',
                'upload_batches.id',
                'upload_batches.batch_uuid',
                'upload_batches.source_file',
                'upload_batches.sheet_name',
                'upload_batches.record_count',
                'upload_batches.total_qty',
                'representative_srs.sr_id',
            ])
            ->groupBy('spp_batch_id');

        $items->transform(function (array $item) use ($sources) {
            if ($item['type'] !== 'spp_generated') {
                return $item;
            }

            $item['source_batches'] = ($sources[$item['id']] ?? collect())
                ->map(fn ($source) => [
                    'id' => (int) $source->id,
                    'batch_uuid' => $source->batch_uuid,
                    'source_file' => $source->source_file,
                    'sheet_name' => $source->sheet_name,
                    'record_count' => (int) $source->record_count,
                    'total_qty' => (int) $source->total_qty,
                    'sr_id' => $source->sr_id ? (int) $source->sr_id : null,
                ])
                ->values()
                ->all();

            return $item;
        });
    }
}
