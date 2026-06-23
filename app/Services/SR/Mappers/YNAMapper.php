<?php

namespace App\Services\SR\Mappers;

use App\Services\SR\SRMapperInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use App\Models\SRMappingTemplate;

class YNAMapper implements SRMapperInterface
{
    private const FALLBACK_SHEET_NAME = 'Final SR';

    private ?SRMappingTemplate $template = null;

    public function __construct(?SRMappingTemplate $template = null)
    {
        $this->template = $template;
    }

    private function colIndex(string $colName, int $default): int
    {
        if ($this->template && $this->template->{$colName}) {
            $colLetter = strtoupper(trim($this->template->{$colName}));
            try {
                return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($colLetter) - 1;
            } catch (\Throwable $e) {
                Log::warning("colIndex conversion failed for {$colName}: {$colLetter}, using default {$default}");
            }
        }
        return $default;
    }

    public function map(
        array $sheet,
        ?Carbon $referenceDate = null,
        ?string $filePath = null,
        int $sheetIndex = 0,
        ?int $customerId = null
    ): array {
        if (empty($sheet)) {
            throw new \Exception('Sheet kosong atau tidak valid');
        }

        if ($filePath === null || !file_exists($filePath)) {
            throw new \Exception(
                'YNAMapper membutuhkan filePath untuk membaca nilai formula Excel secara langsung.'
            );
        }

        Log::info('=== MAPPING YNA START ===');

        return $this->mapFromFile($filePath, $referenceDate, $customerId, $sheetIndex);
    }

    private function mapFromFile(string $filePath, ?Carbon $referenceDate, ?int $customerId, int $sheetIndex): array
    {
        $labelCol = $this->colIndex('label_column', 5);
        $valueCol = $this->colIndex('value_column', 6);
        $dateLabelCol = $this->colIndex('date_label_column', 8);
        $dataColStart = $this->colIndex('qty_start_column', 9);

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $this->resolveWorksheet($spreadsheet, $sheetIndex);
        $rows = $this->worksheetRows($worksheet);
        $psaRows = $this->findPsaRows($rows, $labelCol);

        Log::info('Membaca sheet: ' . $worksheet->getTitle());
        Log::info('Total rows dibaca: ' . count($rows));
        Log::info('Reference: ' . ($referenceDate?->toDateString() ?? '-') . ' | YNA mengambil semua kolom ETD/ETA');
        Log::info('Total assy blocks ditemukan: ' . count($psaRows));

        if (empty($psaRows)) {
            throw new \Exception(
                "Tidak dapat menemukan blok data di sheet '{$worksheet->getTitle()}'. " .
                "Pastikan format YNA benar dan ada baris berlabel '" . ($this->template->block_identifier ?? 'PSA#') . "'."
            );
        }

        $referenceEtaByColumn = $this->extractReferenceEtaByColumn($rows, $psaRows, $dateLabelCol, $dataColStart);
        $records = [];
        $processed = 0;
        $skipped = 0;

        foreach ($psaRows as $psaRow) {
            try {
                $blockRecords = $this->parseBlock($rows, $psaRow, $customerId, $referenceEtaByColumn, $labelCol, $valueCol, $dateLabelCol, $dataColStart);

                if ($blockRecords === []) {
                    $skipped++;
                    Log::debug('Block row ' . ($psaRow + 1) . ' tidak punya data.');
                    continue;
                }

                array_push($records, ...$blockRecords);
                $processed++;
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Error parsing block di row ' . ($psaRow + 1) . ': ' . $e->getMessage());
            }
        }

        Log::info("Processed blocks: {$processed} | Skipped: {$skipped} | Records: " . count($records));

        if (empty($records)) {
            throw new \Exception('Tidak ada data ETD/ETA yang valid di file YNA. Total blocks: ' . count($psaRows) . '.');
        }

        return $records;
    }

    private function parseBlock(
        array $rows,
        int $psaRow,
        ?int $customerId,
        array $referenceEtaByColumn,
        int $labelCol,
        int $valueCol,
        int $dateLabelCol,
        int $dataColStart
    ): array {
        $assyOffset = $this->template ? $this->template->assy_number_row_offset ?? 2 : 2;
        $etdOffset = $this->template ? $this->template->etd_row_offset ?? 3 : 3;
        $etaOffset = $this->template ? $this->template->eta_row_offset ?? 4 : 4;
        $qtyOffset = $this->template ? $this->template->qty_row_offset ?? 5 : 5;
        $familyOffset = $this->template ? $this->template->family_row_offset ?? 6 : 6;

        if (!isset($rows[$psaRow + $qtyOffset])) {
            Log::debug('Block di row ' . ($psaRow + 1) . ' tidak lengkap, skip.');
            return [];
        }

        $assyRow = $rows[$psaRow + $assyOffset - 1] ?? [];
        $customerPartRow = $rows[$psaRow + $assyOffset] ?? [];
        $etdRow = $rows[$psaRow + $etdOffset] ?? [];
        $etaRow = $rows[$psaRow + $etaOffset] ?? [];
        $netRow = $rows[$psaRow + $qtyOffset] ?? [];
        $familyRow = $rows[$psaRow + $familyOffset] ?? [];

        $assyNumber = $this->readAssyNumber($assyRow, $customerPartRow, $psaRow, $labelCol, $valueCol);
        if ($assyNumber === null || !$this->isValidBlock($psaRow, $assyNumber, $etdRow, $netRow, $dateLabelCol)) {
            return [];
        }

        $model = $this->cleanString($netRow[$valueCol] ?? null) ?: null;
        $family = $this->readFamily($familyRow, $labelCol, $valueCol);
        $hasEtaRow = $this->template ? ($etaOffset !== null) : ($this->cleanString($etaRow[$dateLabelCol] ?? null) === 'ETA Date');
        $maxCols = max(count($etdRow), count($etaRow), count($netRow));
        $records = [];

        for ($col = $dataColStart; $col < $maxCols; $col++) {
            $record = $this->recordFromColumn(
                $psaRow,
                $col,
                $assyNumber,
                $etdRow,
                $etaRow,
                $netRow,
                $hasEtaRow,
                $referenceEtaByColumn,
                $customerId,
                $model,
                $family,
                $dataColStart
            );

            if ($record !== null) {
                $records[] = $record;
            }
        }

        Log::debug("Block row " . ($psaRow + 1) . " assy '{$assyNumber}': " . count($records) . ' kolom parsed.');

        return $records;
    }

    private function recordFromColumn(
        int $psaRow,
        int $col,
        string $assyNumber,
        array $etdRow,
        array $etaRow,
        array $netRow,
        bool $hasEtaRow,
        array $referenceEtaByColumn,
        ?int $customerId,
        ?string $model,
        ?string $family,
        int $dataColStart
    ): ?array {
        $etd = $this->parseDateValue($etdRow[$col] ?? null);
        if ($etd === null) {
            return null;
        }

        [$eta, $etaSource] = $this->resolveEta($etaRow[$col] ?? null, $hasEtaRow, $referenceEtaByColumn[$col] ?? null);
        $qty = $this->parseQty($netRow[$col] ?? null, $psaRow, $assyNumber, $col);

        if ($qty < 0) {
            Log::debug('Block row ' . ($psaRow + 1) . ' col ' . ($col + 1) . ": qty negatif ({$qty}), skip.");
            return null;
        }

        $weekInfo = $this->resolveWeekFromEtd($customerId, $etd);

        return [
            'customer' => $this->template ? ($this->template->customer->code ?? 'YNA') : 'YNA',
            'source_file' => null,
            'assy_number' => $assyNumber,
            'qty' => $qty,
            'delivery_date' => $eta?->toDateString(),
            'eta' => $eta?->toDateString(),
            'etd' => $etd->toDateString(),
            'week' => $weekInfo['week'],
            'month' => $weekInfo['month'],
            'year' => $weekInfo['year'],
            'order_type' => 'FIRM',
            'model' => $model,
            'family' => $family,
            'route' => null,
            'port' => null,
            'extra' => json_encode([
                'row' => $psaRow + 1,
                'col' => $col + 1,
                'etd_raw' => $etd->toDateString(),
                'eta_source' => $eta ? $etaSource : null,
                'eta_fallback' => false,
                'week_source' => $weekInfo['source'],
                'model_source' => $model ? 'sr_car_line' : null,
                'family_source' => $family ? 'sr_family' : null,
            ]),
        ];
    }

    private function readAssyNumber(array $assyRow, array $customerPartRow, int $psaRow, int $labelCol, int $valueCol): ?string
    {
        $assyNumber = $this->cleanString($customerPartRow[$valueCol] ?? null);

        if (!$this->template) {
            $ynaPartLabel = $this->cleanString($assyRow[5] ?? null);
            $customerPartLabel = $this->cleanString($customerPartRow[5] ?? null);

            if ($ynaPartLabel !== 'YNA Part#') {
                Log::debug("Block row " . ($psaRow + 1) . ": label +1 bukan 'YNA Part#', got '{$ynaPartLabel}', skip.");
                return null;
            }

            if ($customerPartLabel !== 'Customer Part#') {
                Log::debug("Block row " . ($psaRow + 1) . ": label +2 bukan 'Customer Part#', got '{$customerPartLabel}', skip.");
                return null;
            }
        }

        if ($assyNumber === '') {
            Log::debug('Block row ' . ($psaRow + 1) . ': customer part kosong di value col, skip.');
            return null;
        }

        return $assyNumber;
    }

    private function isValidBlock(int $psaRow, string $assyNumber, array $etdRow, array $netRow, int $dateLabelCol): bool
    {
        if (!$this->template) {
            $etdLabel = $this->cleanString($etdRow[$dateLabelCol] ?? null);
            $netLabel = $this->cleanString($netRow[$dateLabelCol] ?? null);

            if ($etdLabel !== 'ETD Date') {
                Log::warning("Block row " . ($psaRow + 1) . " assy '{$assyNumber}': ETD label tidak cocok, got '{$etdLabel}'");
                return false;
            }

            if ($netLabel !== 'Net') {
                Log::warning("Block row " . ($psaRow + 1) . " assy '{$assyNumber}': Net label tidak cocok, got '{$netLabel}'");
                return false;
            }
        }

        return true;
    }

    private function readFamily(array $familyRow, int $labelCol, int $valueCol): ?string
    {
        if (!$this->template) {
            if ($this->cleanString($familyRow[$labelCol] ?? null) !== 'Family') {
                return null;
            }
        }

        return $this->cleanString($familyRow[$valueCol] ?? null) ?: null;
    }

    private function resolveEta($etaRaw, bool $hasEtaRow, ?Carbon $referenceEta): array
    {
        $eta = $hasEtaRow ? $this->parseDateValue($etaRaw) : null;

        if ($eta !== null) {
            return [$eta, 'sr_eta_row'];
        }

        if ($referenceEta !== null) {
            return [$referenceEta->copy(), 'yna_reference_eta_row'];
        }

        return [null, null];
    }

    private function parseQty($value, int $psaRow, string $assyNumber, int $col): int
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            return 0;
        }

        if (is_string($value) && str_starts_with(trim($value), '=')) {
            $qty = $this->parseInteger($value);
            if ($qty !== null) {
                Log::debug("Block row " . ($psaRow + 1) . " assy '{$assyNumber}' col " . ($col + 1) . ": formula qty terbaca: {$qty}");
                return $qty;
            }

            Log::warning("Block row " . ($psaRow + 1) . " assy '{$assyNumber}' col " . ($col + 1) . ": formula qty tidak terbaca, default 0. Raw: {$value}");
            return 0;
        }

        return $this->parseInteger($value) ?? 0;
    }

    private function extractReferenceEtaByColumn(array $rows, array $psaRows, int $dateLabelCol, int $dataColStart): array
    {
        $etaOffset = $this->template ? $this->template->eta_row_offset ?? 4 : 4;

        foreach ($psaRows as $psaRow) {
            $etaRow = $rows[$psaRow + $etaOffset] ?? [];
            if (!$this->template && $this->cleanString($etaRow[$dateLabelCol] ?? null) !== 'ETA Date') {
                continue;
            }

            $etaByColumn = $this->dateMapFromRow($etaRow, $dataColStart);
            if ($etaByColumn !== []) {
                Log::info('YNA reference ETA row memakai block row ' . ($psaRow + 1) . ' dengan ' . count($etaByColumn) . ' kolom ETA.');
                return $etaByColumn;
            }
        }

        Log::warning('YNA reference ETA row tidak ditemukan; ETA kosong akan tetap null.');
        return [];
    }

    public function extractEtdRangeFromFile(string $filePath, int $sheetIndex = 0): array
    {
        $labelCol = $this->colIndex('label_column', 5);
        $dataColStart = $this->colIndex('qty_start_column', 9);
        $etdOffset = $this->template ? $this->template->etd_row_offset ?? 3 : 3;

        $rows = $this->worksheetRows($this->resolveWorksheet(IOFactory::load($filePath), $sheetIndex));
        $dates = [];

        foreach ($this->findPsaRows($rows, $labelCol) as $psaRow) {
            foreach ($this->dateMapFromRow($rows[$psaRow + $etdOffset] ?? [], $dataColStart) as $date) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates === [] ? [null, null] : [min($dates), max($dates)];
    }

    public function extractWeekNumbersFromFile(string $filePath, int $sheetIndex = 0): array
    {
        $dataColStart = $this->colIndex('qty_start_column', 9);
        $rows = $this->worksheetRows($this->resolveWorksheet(IOFactory::load($filePath), $sheetIndex));

        for ($row = 0; $row < min(5, count($rows)); $row++) {
            $weekMap = $this->weekMapFromRow($rows[$row], $dataColStart);
            if (count($weekMap) >= 3) {
                Log::info('Extract week numbers dari row ' . ($row + 1) . ': ' . count($weekMap) . ' weeks found');
                return $weekMap;
            }
        }

        Log::debug('No explicit week labels found in file, will use ETD-based resolution');
        return [];
    }

    private function resolveWorksheet(Spreadsheet $spreadsheet, int $sheetIndex): Worksheet
    {
        if ($sheetIndex >= 0 && $sheetIndex < $spreadsheet->getSheetCount()) {
            $worksheet = $spreadsheet->getSheet($sheetIndex);
            Log::info("YNA memakai worksheet pilihan user index {$sheetIndex}: " . $worksheet->getTitle());
            return $worksheet;
        }

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if (strtolower(trim($worksheet->getTitle())) === strtolower(self::FALLBACK_SHEET_NAME)) {
                Log::warning("Sheet index {$sheetIndex} tidak valid, fallback ke sheet '" . self::FALLBACK_SHEET_NAME . "'.");
                return $worksheet;
            }
        }

        $worksheet = $spreadsheet->getActiveSheet();
        Log::warning("Sheet index {$sheetIndex} tidak valid, menggunakan sheet aktif: " . $worksheet->getTitle());

        return $worksheet;
    }

    private function worksheetRows(Worksheet $worksheet): array
    {
        $rows = [];

        foreach ($worksheet->getRowIterator() as $row) {
            $rowData = [];
            foreach ($row->getCellIterator() as $cell) {
                $val = $cell->getValue();
                if (is_string($val) && str_starts_with(trim($val), '=')) {
                    try {
                        $val = $cell->getOldCalculatedValue();
                        if ($val === null || $val === '') {
                            $val = $cell->getCalculatedValue();
                        }
                    } catch (\Throwable $e) {
                        try {
                            $val = $cell->getCalculatedValue();
                        } catch (\Throwable $ex) {
                            $val = null;
                        }
                    }
                }
                $rowData[] = $val;
            }
            $rows[] = $rowData;
        }

        return $rows;
    }

    private function findPsaRows(array $rows, int $labelCol): array
    {
        $psaRows = [];
        $identifier = $this->template ? $this->template->block_identifier : 'PSA#';
        if (empty($identifier)) {
            $identifier = 'PSA#';
        }

        foreach ($rows as $index => $row) {
            if ($this->cleanString($row[$labelCol] ?? null) === $identifier) {
                $psaRows[] = $index;
            }
        }

        return $psaRows;
    }

    private function dateMapFromRow(array $row, int $dataColStart): array
    {
        $dates = [];

        for ($col = $dataColStart; $col < count($row); $col++) {
            $date = $this->parseDateValue($row[$col] ?? null);
            if ($date !== null) {
                $dates[$col] = $date;
            }
        }

        return $dates;
    }

    private function weekMapFromRow(array $row, int $dataColStart): array
    {
        $weekMap = [];

        for ($col = $dataColStart; $col < count($row); $col++) {
            $value = $this->cleanString($row[$col] ?? null);

            if (preg_match('/^w(?:eek)?\s*(\d+)$/i', $value, $match)) {
                $weekMap[$col] = (int) $match[1];
            } elseif (is_numeric($value) && (int) $value > 0 && (int) $value < 53) {
                $weekMap[$col] = (int) $value;
            }
        }

        return $weekMap;
    }

    private function resolveWeekFromEtd(?int $customerId, Carbon $etd): array
    {
        $weekInfo = $this->getYNAWeekInfo($etd);

        return [
            'week' => $weekInfo['week'],
            'month' => $weekInfo['month_year'],
            'year' => $etd->year,
            'source' => 'yna_etd',
        ];
    }

    private function getYNAWeekInfo(Carbon $date): array
    {
        $weekMonday = $date->copy()->startOfWeek(Carbon::MONDAY);
        $monthDate = $weekMonday->copy();

        if (($weekMonday->daysInMonth - $weekMonday->day + 1) <= 1) {
            $monthDate->addMonthNoOverflow();
        }

        $firstMonday = Carbon::create($monthDate->year, $monthDate->month, 1)->startOfWeek(Carbon::MONDAY);

        if ($firstMonday->month !== $monthDate->month) {
            $remainingDaysInPreviousMonth = $firstMonday->daysInMonth - $firstMonday->day + 1;
            if ($remainingDaysInPreviousMonth > 1) {
                $firstMonday->addWeek();
            }
        }

        $weekNumber = intdiv($firstMonday->diffInDays($weekMonday, false), 7) + 1;

        return [
            'week' => min($weekNumber, 5),
            'month_year' => $monthDate->format('Y-m'),
        ];
    }

    private function parseDateValue($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_float($value) || (is_int($value) && $value > 40000)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->startOfDay();
            } catch (\Throwable) {
                Log::debug('ExcelDate conversion failed for value: ' . var_export($value, true));
            }
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || str_starts_with($value, '=')) {
            return null;
        }

        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'n/j/Y', 'n/j/y'] as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false && $date->format($format) === $value) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                // Try the next format.
            }
        }

        try {
            $date = Carbon::parse($value);
            return $date->year >= 2000 && $date->year <= 2100 ? $date->startOfDay() : null;
        } catch (\Throwable) {
            Log::debug('Date parsing failed for string: ' . $value);
            return null;
        }
    }

    private function parseInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '=')) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9\-]/', '', $value);
        if ($cleaned === '') {
            return null;
        }

        $number = (int) $cleaned;
        return abs($number) > 1000000 ? null : $number;
    }

    private function cleanString($value): string
    {
        return trim((string) ($value ?? ''));
    }
}
