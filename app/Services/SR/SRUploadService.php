<?php

namespace App\Services\SR;

use App\Models\Customer;
use App\Models\UploadBatch;
use App\Services\Variance\VarianceTriggerService;
use App\Models\SR;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\Utilities\WeekResolverService;
use App\Services\Master\MasterAssyResolverService;
use App\Services\Summary\UploadBatchService;
use App\Services\Summary\SummaryGeneratorService;
use App\Services\Utilities\PlanningCacheService;

class SRUploadService
{
    public function __construct(
        private readonly SRMapperService $mapper,
        private readonly WeekResolverService $weeks,
        private readonly MasterAssyResolverService $assyResolver,
        private readonly UploadBatchService $batches,
        private readonly SummaryGeneratorService $summaries,
        private readonly VarianceTriggerService $variance,
        private readonly PlanningCacheService $cache
    ) {
    }

    public function upload(Request $request): RedirectResponse
    {
        $tempPath = null;

        try {
            $customer = Customer::with('ports')->findOrFail($request->customer);
            $sheetIndex = (int) $request->sheet;
            $file = $request->file('file');
            $tempPath = $this->storeTempFile($file);
            $originalName = $file->getClientOriginalName();

            Log::info('SR upload started', [
                'file' => $originalName,
                'customer_id' => $customer->id,
                'customer' => $customer->code,
                'sheet_index' => $sheetIndex,
                'uploaded_by' => Auth::id(),
            ]);

            [$portId, $portName] = $this->resolvePort($request, $customer);
            
            $mappingResult = $this->mapper->mapUploadedSheet($tempPath, $customer, $sheetIndex, $originalName);
            $mapped = $mappingResult[0];
            $sheetName = $mappingResult[1];
            $warning = $mappingResult[2] ?? null;

            if (empty($mapped)) {
                return redirect()->back()->with('error', 'Mapping gagal: tidak ada data valid.');
            }

            $mapped = $this->weeks->applyProductionWeeks($mapped, $customer->id);
            [$mapped, $unknownAssyNumbers] = $this->assyResolver->apply($mapped);
            $uploadBatch = $this->batches->createForSrUpload($customer, $portId, Auth::id(), $originalName, $sheetIndex, $sheetName);
            $mapped = $this->decorateRows($mapped, $customer->code, $uploadBatch, $originalName, $sheetIndex, $sheetName, $portName);

            DB::beginTransaction();

            try {
                $allowedColumns = array_merge(
                    (new SR())->getFillable(),
                    ['created_at', 'updated_at']
                );

                $insertedCount = 0;

                foreach (array_chunk($mapped, 500) as $chunk) {
                    $filteredChunk = array_map(function ($item) use ($allowedColumns) {
                        return array_intersect_key($item, array_flip($allowedColumns));
                    }, $chunk);

                    SR::insert($filteredChunk);
                    $insertedCount += count($chunk);
                }

                $summaryCount = $this->summaries->regenerateForBatch($mapped, $uploadBatch);
                $this->batches->markCompleted($uploadBatch, $mapped, $insertedCount, $summaryCount, $unknownAssyNumbers, $warning);

                $repSrId = SR::where('upload_batch_id', $uploadBatch->id)->min('id');
                $mappedCount = count(array_filter($mapped, fn ($item) => ($item['is_mapped'] ?? false) === true));
                $unmappedCount = count(array_filter($mapped, fn ($item) => ($item['is_mapped'] ?? false) === false));

                \App\Models\HistoryLog::log(
                    'sr_upload',
                    'SR Uploaded',
                    'completed',
                    $customer->code,
                    $portName,
                    $originalName,
                    $uploadBatch->batch_uuid,
                    $uploadBatch->sheet_name,
                    $insertedCount,
                    array_sum(array_column($mapped, 'qty')),
                    $mappedCount,
                    $unmappedCount,
                    0,
                    $repSrId ?: $uploadBatch->id,
                    trim(
                        ($summaryCount > 0 ? "Summary rows: {$summaryCount}." : '').
                        (empty($unknownAssyNumbers) ? '' : ' Unknown assy numbers: '.implode(', ', $unknownAssyNumbers)) .
                        ($warning ? ' ' . $warning : '')
                    ) ?: null
                );

                DB::commit();
            } catch (\Throwable $exception) {
                DB::rollBack();
                $this->batches->markFailed($uploadBatch, $exception);

                \App\Models\HistoryLog::log(
                    'sr_upload',
                    'SR Uploaded',
                    'failed',
                    $customer->code,
                    $portName,
                    $originalName,
                    $uploadBatch->batch_uuid,
                    $uploadBatch->sheet_name,
                    0,
                    0,
                    0,
                    0,
                    0,
                    $uploadBatch->id,
                    $exception->getMessage()
                );

                Log::error('SR upload database write failed', [
                    'upload_batch_id' => $uploadBatch->id,
                    'error' => $exception->getMessage(),
                ]);

                return redirect()->back()->with('error', 'Gagal menyimpan ke database: '.$exception->getMessage());
            }

            $this->variance->refreshForBatch($uploadBatch);
            $this->cache->invalidate();

            return redirect()
                ->route('summary.index')
                ->with($this->messageType($unknownAssyNumbers, $warning), $this->successMessage($mapped, $unknownAssyNumbers, $warning));
        } catch (\Throwable $exception) {
            Log::error('SR upload failed', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'Upload gagal: '.$exception->getMessage());
        } finally {
            $this->cleanupTempFile($tempPath);
        }
    }

    private function resolvePort(Request $request, Customer $customer): array
    {
        if ($customer->ports->isNotEmpty()) {
            if (! $request->filled('port')) {
                throw new \DomainException('Port wajib diisi untuk customer '.$customer->name.'.');
            }

            $port = $customer->ports()->findOrFail($request->port);

            return [$port->id, $port->name];
        }

        if ($request->filled('port')) {
            $port = $customer->ports()->findOrFail($request->port);

            return [$port->id, $port->name];
        }

        return [null, null];
    }

    private function decorateRows(array $mapped, string $customerCode, UploadBatch $uploadBatch, string $sourceFile, int $sheetIndex, ?string $sheetName, ?string $portName): array
    {
        $now = now();

        foreach ($mapped as &$item) {
            $item['source_file'] = $sourceFile;
            $item['upload_batch'] = $uploadBatch->batch_uuid;
            $item['upload_batch_id'] = $uploadBatch->id;
            $item['sheet_index'] = $sheetIndex;
            $item['sheet_name'] = $sheetName;
            $item['port'] = $portName ?? ($item['port'] ?? null);
            $item['customer'] = $customerCode;
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }
        unset($item);

        return $mapped;
    }

    private function successMessage(array $mapped, array $unknownAssyNumbers, ?string $warning = null): string
    {
        $mappedCount = count(array_filter($mapped, fn ($item) => ($item['is_mapped'] ?? false) === true));
        $unmappedCount = count(array_filter($mapped, fn ($item) => ($item['is_mapped'] ?? false) === false));
        $message = sprintf(
            'Upload berhasil! Total records: %d (Mapped: %d, Unmapped: %d, Total Qty: %s). Selanjutnya buka Summary untuk lihat batch terbaru.',
            count($mapped),
            $mappedCount,
            $unmappedCount,
            number_format(array_sum(array_column($mapped, 'qty')))
        );

        if (! empty($unknownAssyNumbers)) {
            $message .= ' Ada assy number yang tidak dikenal. Cek master assy atau proses remap sebelum dipakai lebih lanjut.';
        }

        if ($warning) {
            $message .= ' ' . $warning;
        }

        return $message;
    }

    private function messageType(array $unknownAssyNumbers, ?string $warning = null): string
    {
        return (empty($unknownAssyNumbers) && $warning === null) ? 'success' : 'warning';
    }

    private function storeTempFile(UploadedFile $file): string
    {
        $filename = 'sr_temp_'.uniqid('', true).'.'.($file->getClientOriginalExtension() ?: 'xlsx');
        $relPath = 'temp/'.$filename;

        Storage::disk('local')->put($relPath, file_get_contents($file->getRealPath()));

        return Storage::disk('local')->path($relPath);
    }

    private function cleanupTempFile(?string $absolutePath): void
    {
        if ($absolutePath === null || ! file_exists($absolutePath)) {
            return;
        }

        $storagePath = storage_path('app');

        if (str_starts_with($absolutePath, $storagePath)) {
            Storage::disk('local')->delete(ltrim(substr($absolutePath, strlen($storagePath)), DIRECTORY_SEPARATOR));
            return;
        }

        @unlink($absolutePath);
    }
}
