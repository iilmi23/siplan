<?php

namespace App\Services\SR;

use App\Models\Assy;
use App\Models\Customer;
use App\Models\ProductionWeek;
use App\Models\SR;
use App\Models\Summary;
use App\Models\UploadBatch;
use App\Services\WeekGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SRProcessingService
{
    public function preview(Request $request): JsonResponse
    {
        $tempPath = null;

        try {
            $customer = Customer::findOrFail($request->customer);
            $sheetIndex = (int) $request->sheet;
            $tempPath = $this->storeTempFile($request->file('file'));

            [$mapped] = $this->mapUploadedSheet($tempPath, $customer, $sheetIndex);

            if (empty($mapped)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Mapping gagal: tidak ada data valid.',
                ], 400);
            }

            $unknownParts = $this->collectUnknownParts($mapped);

            $firmCount = count(array_filter($mapped, fn ($i) => ($i['order_type'] ?? '') === 'FIRM'));
            $forecastCount = count(array_filter($mapped, fn ($i) => ($i['order_type'] ?? '') === 'FORECAST'));
            $uniqueParts = count(array_unique(array_column($mapped, 'part_number')));
            $totalFirmQty = array_sum(array_column(array_filter($mapped, fn ($i) => ($i['order_type'] ?? '') === 'FIRM'), 'qty'));
            $totalForecastQty = array_sum(array_column(array_filter($mapped, fn ($i) => ($i['order_type'] ?? '') === 'FORECAST'), 'qty'));
            $monthsCovered = array_values(array_unique(array_column($mapped, 'month')));
            sort($monthsCovered);

            return response()->json([
                'success' => true,
                'data'    => [
                    'total_records'      => count($mapped),
                    'unique_parts'       => $uniqueParts,
                    'firm_count'         => $firmCount,
                    'forecast_count'     => $forecastCount,
                    'total_firm_qty'     => $totalFirmQty,
                    'total_forecast_qty' => $totalForecastQty,
                    'months_covered'     => $monthsCovered,
                    'unknown_parts'      => $unknownParts,
                    'has_unknown_parts'   => count($unknownParts) > 0,
                    'preview'             => array_slice($mapped, 0, 50),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Preview error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        } finally {
            $this->cleanupTempFile($tempPath);
        }
    }

    public function uploadTaiwan(Request $request): RedirectResponse
    {
        $tempPath = null;

        try {
            $customer = Customer::findOrFail($request->customer);
            $sheetIndex = (int) $request->sheet;
            $tempPath = $this->storeTempFile($request->file('file'));

            $originalName = $request->file('file')->getClientOriginalName();
            Log::info('SR Upload dimulai', [
                'file' => $originalName,
                'customer' => $customer->id,
                'sheet' => $sheetIndex,
            ]);

            $portName = null;
            $portId = null;

            if ($customer->ports()->exists()) {
                if (!$request->filled('port')) {
                    return redirect()->back()->with('error', '❌ Port wajib diisi untuk customer ' . $customer->name . '.');
                }
                $port = $customer->ports()->findOrFail($request->port);
                $portName = $port->name;
                $portId = $port->id;
            } elseif ($request->filled('port')) {
                $port = $customer->ports()->findOrFail($request->port);
                $portName = $port->name;
                $portId = $port->id;
            }

            [$mapped, $sheetName] = $this->mapUploadedSheet($tempPath, $customer, $sheetIndex);

            if (empty($mapped)) {
                return redirect()->back()->with('error', '❌ Mapping gagal: tidak ada data valid.');
            }

            $this->generateWeeksFromMappedData($mapped, $customer->id, $customer->code, $tempPath);

            [$mapped, $unknownParts] = $this->applyMasterMapping($mapped, $request, $customer, $portName);

            $uploadBatchUuid = (string) Str::uuid();
            $uploadedBy = Auth::id();
            $uploadBatch = UploadBatch::create([
                'batch_uuid'     => $uploadBatchUuid,
                'customer_id'    => $customer->id,
                'port_id'        => $portId,
                'uploaded_by'    => $uploadedBy,
                'source_file'    => $originalName,
                'sheet_index'    => $sheetIndex,
                'sheet_name'     => $sheetName,
                'status'         => 'processing',
                'record_count'   => 0,
                'mapped_count'   => 0,
                'unmapped_count' => 0,
                'total_qty'      => 0,
            ]);

            $now = now();

            foreach ($mapped as &$item) {
                $item['source_file'] = $originalName;
                $item['upload_batch'] = $uploadBatchUuid;
                $item['upload_batch_id'] = $uploadBatch->id;
                $item['sheet_index'] = $sheetIndex;
                $item['sheet_name'] = $sheetName;
                $item['port'] = $portName ?? ($item['port'] ?? null);
                $item['carline_id'] = $request->carline_id ?? ($item['carline_id'] ?? null);
                $item['customer'] = $customer->code;
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
            }
            unset($item);

            DB::beginTransaction();

            try {
                $insertedCount = 0;

                foreach (array_chunk($mapped, 500) as $chunk) {
                    SR::insert($chunk);
                    $insertedCount += count($chunk);
                }

                $summaryCount = $this->storeOrderSummary($mapped, $uploadBatch);

                $mappedCount = count(array_filter($mapped, fn ($i) => ($i['is_mapped'] ?? false) === true));
                $unmappedCount = count(array_filter($mapped, fn ($i) => ($i['is_mapped'] ?? false) === false));
                $totalQty = array_sum(array_column($mapped, 'qty'));

                $uploadBatch->update([
                    'status'         => 'completed',
                    'record_count'   => $insertedCount,
                    'mapped_count'   => $mappedCount,
                    'unmapped_count' => $unmappedCount,
                    'total_qty'      => $totalQty,
                    'notes'          => trim(($summaryCount > 0 ? "Summary rows: {$summaryCount}." : '') . (empty($unknownParts) ? '' : ' Unknown parts: ' . implode(', ', $unknownParts))) ?: null,
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                if (isset($uploadBatch)) {
                    $uploadBatch->update([
                        'status' => 'failed',
                        'notes'  => $e->getMessage(),
                    ]);
                }

                Log::error('DB insert gagal: ' . $e->getMessage());
                return redirect()->back()->with('error', '❌ Gagal menyimpan ke database: ' . $e->getMessage());
            }

            $mappedCount = count(array_filter($mapped, fn ($i) => ($i['is_mapped'] ?? false) === true));
            $unmappedCount = count(array_filter($mapped, fn ($i) => ($i['is_mapped'] ?? false) === false));
            $totalQty = array_sum(array_column($mapped, 'qty'));

            $message = sprintf(
                '✅ Upload berhasil! Total records: %d (Mapped: %d, Unmapped: %d, Total Qty: %s). Selanjutnya buka Summary untuk lihat batch terbaru.',
                count($mapped),
                $mappedCount,
                $unmappedCount,
                number_format($totalQty)
            );

            if ($unmappedCount > 0) {
                $message .= ' ⚠️ Ada part yang tidak dikenal. Cek master assy atau proses remap sebelum dipakai lebih lanjut.';
                return redirect()->route('summary.index')->with('warning', $message);
            }

            return redirect()->route('summary.index')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Upload gagal: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('error', '❌ Upload gagal: ' . $e->getMessage());
        } finally {
            $this->cleanupTempFile($tempPath);
        }
    }

    private function mapUploadedSheet(string $tempPath, Customer $customer, int $sheetIndex): array
    {
        $reader = $this->createReader($tempPath);
        $spreadsheet = $reader->load($tempPath);
        $worksheet = $spreadsheet->getSheet($sheetIndex);

        if ($worksheet === null) {
            throw new \Exception('Sheet tidak valid. Tersedia: ' . $spreadsheet->getSheetCount() . ' sheet.');
        }

        $sheetName = $worksheet->getTitle();
        $sheetData = $this->worksheetToArray($worksheet);

        if (empty($sheetData)) {
            throw new \Exception('Sheet yang dipilih kosong.');
        }

        $mapper = $this->resolveMapper($customer, $sheetIndex);

        if ($mapper === null) {
            throw new \Exception('Customer ' . $customer->code . ' belum punya mapper khusus atau template SR aktif. Buat template di menu SR Mapping Templates.');
        }

        $options = $this->extractSheetOptions($tempPath, $sheetIndex, $customer->code);

        if (strtoupper($customer->code) === 'YC') {
            $mapped = $this->runYCMapper($mapper, $tempPath, $options, true, $sheetIndex);
        } else {
            $mapped = $this->runMapper($mapper, $customer->code, $sheetData, $tempPath, $sheetIndex, $options, $customer->id);
        }

        $mapped = array_values(array_filter($mapped));

        return [$mapped, $sheetName];
    }

    private function generateWeeksFromMappedData(array $mapped, int $customerId, string $customerCode, string $tempPath): void
    {
        foreach ($mapped as &$item) {
            if (empty($item['etd'])) {
                continue;
            }

            if (!empty($item['week'])) {
                $weekId = WeekGenerator::resolveEtdMapping($customerId, $item['etd']);
                if ($weekId) {
                    $week = ProductionWeek::find($weekId);
                    if ($week && empty($item['month'])) {
                        $item['month'] = $week->month_name;
                        $item['year'] = $week->year;
                    }
                }
                continue;
            }

            $weekId = WeekGenerator::resolveEtdMapping($customerId, $item['etd']);

            if ($weekId) {
                $week = ProductionWeek::find($weekId);
                if ($week) {
                    $item['week'] = $week->week_no;
                    $item['month'] = $week->month_name;
                    $item['year'] = $week->year;
                }
            } else {
                $date = Carbon::parse($item['etd']);
                $item['week'] = ceil($date->day / 7);
                $item['month'] = strtoupper($date->shortMonthName);
                $item['year'] = $date->year;
                Log::warning("Week fallback untuk ETD {$item['etd']}: week={$item['week']}, month={$item['month']}");
            }
        }
        unset($item);
    }

    private function applyMasterMapping(array $mapped, Request $request, Customer $customer, ?string $portName): array
    {
        $unknownParts = [];

        foreach ($mapped as &$item) {
            $assy = Assy::with('carline')->where('part_number', $item['part_number'])->first();
            if ($assy) {
                $item['assy_id'] = $assy->id;
                $item['carline_id'] = $assy->carline_id;
                $item['model'] = $item['model'] ?? $assy->carline?->code;
                $item['family'] = $item['family'] ?? $assy->carline?->description;
                $item['is_mapped'] = true;
                $item['mapping_error'] = null;
                continue;
            }

            $item['assy_id'] = null;
            $item['is_mapped'] = false;
            $item['mapping_error'] = "Part number {$item['part_number']} tidak ditemukan di master assy.";
            $unknownParts[] = $item['part_number'];
        }
        unset($item);

        $unknownParts = array_values(array_unique(array_filter($unknownParts)));

        return [$mapped, $unknownParts];
    }

    private function storeOrderSummary(array $mapped, UploadBatch $uploadBatch): int
    {
        Summary::where('upload_batch_id', $uploadBatch->id)->delete();

        $buckets = collect($mapped)
            ->filter(fn ($item) => !empty($item['part_number']))
            ->groupBy(function ($item) {
                return implode('|', [
                    $item['part_number'] ?? '',
                    $item['order_type'] ?? '',
                    $item['month'] ?? '',
                    $item['week'] ?? '',
                    $item['etd'] ?? '',
                    $item['eta'] ?? '',
                    $item['port'] ?? '',
                ]);
            })
            ->map(function ($rows) use ($uploadBatch) {
                $first = $rows->first();

                return [
                    'upload_batch_id' => $uploadBatch->id,
                    'customer_id'     => $uploadBatch->customer_id,
                    'port_id'         => $uploadBatch->port_id,
                    'assy_id'         => $first['assy_id'] ?? null,
                    'upload_batch'    => $uploadBatch->batch_uuid,
                    'customer'        => $first['customer'] ?? $uploadBatch->customer?->code,
                    'source_file'     => $first['source_file'] ?? $uploadBatch->source_file,
                    'sheet_name'      => $first['sheet_name'] ?? $uploadBatch->sheet_name,
                    'part_number'     => $first['part_number'],
                    'model'           => $first['model'] ?? null,
                    'family'          => $first['family'] ?? null,
                    'order_type'      => $first['order_type'] ?? null,
                    'month'           => $first['month'] ?? null,
                    'week'            => $first['week'] ?? null,
                    'etd'             => $first['etd'] ?? null,
                    'eta'             => $first['eta'] ?? null,
                    'port'            => $first['port'] ?? null,
                    'line_count'      => $rows->count(),
                    'total_qty'       => $rows->sum(fn ($item) => (int) ($item['qty'] ?? 0)),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            })
            ->values();

        foreach ($buckets->chunk(500) as $chunk) {
            Summary::insert($chunk->all());
        }

        return $buckets->count();
    }

    private function collectUnknownParts(array $mapped): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($item) => $item['part_number'] ?? null,
            array_filter($mapped, fn ($item) => ($item['is_mapped'] ?? false) === false)
        ))));
    }

    private function worksheetToArray($worksheet): array
    {
        $sheetData = [];
        $highestRow = $worksheet->getHighestRow();
        $highestCol = $worksheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                $rowData[] = $cellValue;
            }
            $sheetData[$row - 1] = $rowData;
        }

        return $sheetData;
    }

    private function resolveMapper(Customer $customer, int $sheetIndex): ?SRMapperInterface
    {
        $customMapper = match (strtoupper($customer->code)) {
            'TYC'   => new TYCMapper(),
            'YNA'   => new YNAMapper(),
            'SAI'   => new SAIMapper(),
            'YC'    => new YCMapper(),
            default => null,
        };

        if ($customMapper !== null) {
            return $customMapper;
        }

        $template = $customer->activeSrMappingTemplate;

        if ($template === null) {
            return null;
        }

        if ($template->sheet_index !== null && (int) $template->sheet_index !== $sheetIndex) {
            throw new \Exception(
                'Template SR aktif untuk customer ' . $customer->code .
                ' hanya berlaku untuk sheet index ' . $template->sheet_index .
                '. Sheet yang dipilih: ' . $sheetIndex . '.'
            );
        }

        return new GenericTemplateMapper($template, $customer);
    }

    private function runMapper(
        SRMapperInterface $mapper,
        string $customerCode,
        array $sheetData,
        string $tempPath,
        int $sheetIndex,
        array $options,
        ?int $customerId = null
    ): array {
        try {
            if ($mapper instanceof GenericTemplateMapper) {
                return $mapper->map($sheetData);
            }

            if (strtoupper($customerCode) === 'YNA') {
                return $mapper->map($sheetData, null, $tempPath, $sheetIndex, $customerId);
            }

            if (strtoupper($customerCode) === 'YC') {
                return $this->runYCMapper($mapper, $tempPath, $options, true, $sheetIndex);
            }

            return $mapper->map($sheetData, null, $options);
        } catch (\Exception $e) {
            Log::error("runMapper error for {$customerCode}: " . $e->getMessage());
            throw $e;
        }
    }

    private function runYCMapper(YCMapper $mapper, string $tempPath, array $options, bool $singleSheetMode = false, ?int $sheetIndex = null): array
    {
        try {
            $reader = $this->createReader($tempPath);
            $spreadsheet = $reader->load($tempPath);

            $allSheets = [];
            $sheetNames = [];

            foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
                $sheetName = $worksheet->getTitle();
                $sheetNames[$index] = $sheetName;

                if ($singleSheetMode && $sheetIndex !== null && $index !== $sheetIndex) {
                    continue;
                }

                $allSheets[$index] = $this->worksheetToArray($worksheet);
            }

            if (empty($allSheets)) {
                throw new \Exception('Tidak ada sheet yang bisa diproses');
            }

            $sheetResults = $mapper->mapAllSheets($allSheets, $sheetNames, [], null, $options);

            if (!is_array($sheetResults)) {
                throw new \Exception('YCMapper harus return array');
            }

            $result = [];
            foreach ($sheetResults as $sheetRecords) {
                if (is_array($sheetRecords)) {
                    $result = array_merge($result, $sheetRecords);
                }
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('runYCMapper error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createReader(string $filePath): \PhpOffice\PhpSpreadsheet\Reader\IReader
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'xlsx':
            case 'xlsm':
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                break;
            case 'xls':
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                break;
            default:
                throw new \Exception("Unsupported file type: {$extension}");
        }

        $reader->setReadDataOnly(true);
        return $reader;
    }

    private function extractSheetOptions(string $filePath, int $sheetIndex, string $customerCode): array
    {
        $options = [
            'hidden_columns' => [],
            'hidden_rows'    => [],
        ];

        if (strtoupper($customerCode) === 'YNA') {
            return $options;
        }

        try {
            $reader = $this->createReader($filePath);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getSheet($sheetIndex);

            foreach ($worksheet->getColumnDimensions() as $colLetter => $colDim) {
                if (!$colDim->getVisible()) {
                    $oneBased = Coordinate::columnIndexFromString($colLetter);
                    $options['hidden_columns'][] = $oneBased - 1;
                }
            }

            foreach ($worksheet->getRowDimensions() as $rowNum => $rowDim) {
                if (!$rowDim->getVisible()) {
                    $options['hidden_rows'][] = (int) $rowNum - 1;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("extractSheetOptions gagal: " . $e->getMessage());
        }

        return $options;
    }

    private function storeTempFile(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'xlsx';
        $filename = 'sr_temp_' . uniqid('', true) . '.' . $ext;
        $relPath = 'temp/' . $filename;

        Storage::disk('local')->put($relPath, file_get_contents($file->getRealPath()));

        return Storage::disk('local')->path($relPath);
    }

    private function cleanupTempFile(?string $absolutePath): void
    {
        if ($absolutePath === null || !file_exists($absolutePath)) {
            return;
        }

        try {
            $storagePath = storage_path('app');
            if (str_starts_with($absolutePath, $storagePath)) {
                $rel = ltrim(substr($absolutePath, strlen($storagePath)), DIRECTORY_SEPARATOR);
                Storage::disk('local')->delete($rel);
            } else {
                @unlink($absolutePath);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal hapus temp file: ' . $e->getMessage());
        }
    }
}
