<?php

namespace App\Services\SR\Mappers;

use App\Services\SR\SRMapperInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Mapper TYC/JAI horizontal.
 *
 * Ringkas:
 * - Mendeteksi baris FIRM/FORECAST, time chart, ETA, ETD, dan header.
 * - Memfilter kolom aktif dengan window FIRM dan FORECAST.
 * - Kolom 2W/3W/4W/5W mewarisi bulan dan type dari anchor 1W.
 * - Mengambil ETD/ETA dari baris tanggal, lalu qty dari baris assy.
 */
class TYCMapper implements SRMapperInterface
{
    private const FIRM_FORECAST_ROW = 12; // row 13 Excel = index 12
    private const TIME_CHART_ROW    = 13; // row 14 Excel = index 13
    private const ETA_TYC_ROW       = 17; // row 18 Excel = index 17
    private const HEADER_ROW        = 18; // row 19 Excel = index 18

    public function map(array $sheet, ?Carbon $referenceDate = null, array $options = []): array
    {
        $result = [];

        if (empty($sheet) || !is_array($sheet)) {
            throw new \Exception("Sheet kosong atau tidak valid");
        }

        // Safety check: limit sheet size to prevent memory issues
        if (count($sheet) > 10000) {
            throw new \Exception("Sheet terlalu besar (max 10,000 rows)");
        }

        Log::info('=== MAPPING TYC START ===');

        [$headerRow, $headerRowIndex] = $this->detectHeaderRow($sheet) ?? [$sheet[self::HEADER_ROW] ?? [], self::HEADER_ROW];
        [$firmForecastRow, $firmForecastRowIndex] = $this->detectFirmForecastRow($sheet, 0, $headerRowIndex) ?? [$sheet[self::FIRM_FORECAST_ROW] ?? [], self::FIRM_FORECAST_ROW];
        [$timeChartRow, $timeChartRowIndex] = $this->detectTimeChartRow($sheet, 0, $headerRowIndex) ?? [$sheet[self::TIME_CHART_ROW] ?? [], self::TIME_CHART_ROW];
        
        $etaRowInfo = $this->detectEtaPortKaoRow($sheet, $timeChartRowIndex + 1, $headerRowIndex)
            ?? $this->detectEtaTycRow($sheet, $timeChartRowIndex + 1, $headerRowIndex);

        if ($etaRowInfo === null && isset($sheet[$headerRowIndex + 1])) {
            $rowAfterHeader = $sheet[$headerRowIndex + 1];
            if ($this->countDateValues($rowAfterHeader) >= 5) {
                $etaRowInfo = [$rowAfterHeader, $headerRowIndex + 1];
            }
        }

        [$etaRow, $etaRowIndex] = $etaRowInfo ?? [$sheet[self::ETA_TYC_ROW] ?? [], self::ETA_TYC_ROW];

        [$etdRow, $etdRowIndex] = $this->detectEtdJaiRow($sheet, $timeChartRowIndex + 1, $headerRowIndex)
            ?? $this->detectEtdPortSurRow($sheet, $timeChartRowIndex + 1, $headerRowIndex)
            ?? $this->detectDateRowBefore($sheet, min($etaRowIndex - 1, $headerRowIndex - 1), $timeChartRowIndex + 1)
            ?? [[], -1];

        $targetYear = $this->detectPlanningYear($sheet, $headerRowIndex, $options);
        $sheetReference = $referenceDate ?? $this->guessReferenceDate($sheet, $etaRow, $targetYear);

        if ($sheetReference === null) {
            throw new \Exception('Tidak bisa menentukan reference month dari file SR TYC.');
        }

        Log::info("Detected rows: HEADER={$headerRowIndex}, FIRM_FORECAST={$firmForecastRowIndex}, TIME_CHART={$timeChartRowIndex}, ETA={$etaRowIndex}, ETD={$etdRowIndex}");
        Log::info("Reference date used for window: {$sheetReference->toDateString()}");

        $ref = $sheetReference;
        // Berdasarkan JAI production calendar range:
        // FIRM     = JAI production month range dari current month (bulan reference)
        // FORECAST = JAI production month range dari 5 bulan kedepan
        [$firmStart, $firmEnd] = $this->productionMonthRange($ref->year, $ref->month);
        $forecastStart = $firmEnd->copy()->addDay();

        $forecastTarget = $ref->copy()->addMonths(5);
        [, $forecastEnd] = $this->productionMonthRange($forecastTarget->year, $forecastTarget->month);

        Log::info("Reference   : {$ref->toDateString()}");
        Log::info("FIRM window : {$firmStart->format('Y-m')} ~ {$firmEnd->format('Y-m')}");
        Log::info("FORECAST win: {$forecastStart->format('Y-m')} ~ {$forecastEnd->format('Y-m')}");

        $headerColumns = $this->detectHeaderColumns($headerRow);

        $assyCol = $headerColumns['assy_number'] ?? null;
        if ($assyCol === null) {
            throw new \Exception("Kolom 'PRODUCT NO' tidak ditemukan");
        }

        $isStandardLayout = ($assyCol === 3);
        $modelCol = $headerColumns['model'] ?? ($isStandardLayout ? 0 : null);
        $familyCol = $headerColumns['family'] ?? ($isStandardLayout ? 1 : null);
        $noCol = $headerColumns['no'] ?? ($isStandardLayout ? 2 : null);
        $sfxCol = $headerColumns['sfx'] ?? ($isStandardLayout ? 5 : null);
        $qtyLabelCol = $headerColumns['qty_label'] ?? ($isStandardLayout ? 6 : null);

        $hiddenColumns = array_flip($options['hidden_columns'] ?? []);
        $hiddenRows = array_flip($options['hidden_rows'] ?? []);

        // Cari DATA_COL_START dari anchor tahun eksplisit
        $dataColStart = $this->findDataColStart($timeChartRow, $etaRow, $etdRow, $hiddenColumns);
        Log::info("DATA_COL_START: {$dataColStart}");

        // Bangun peta kolom (semua minggu 1W~5W)
        $dateColumns = $this->buildDateColumns(
            $firmForecastRow,
            $timeChartRow,
            $etaRow,
            $etdRow,
            $headerRow,
            $dataColStart,
            $firmStart,
            $firmEnd,
            $forecastStart,
            $forecastEnd,
            $hiddenColumns,
            $targetYear
        );

        if (empty($dateColumns)) {
            throw new \Exception(
                "Tidak ada kolom tanggal dalam window. " .
                "FIRM: {$firmStart->format('Y-m')} ~ {$firmEnd->format('Y-m')}, " .
                "FORECAST: {$forecastStart->format('Y-m')} ~ {$forecastEnd->format('Y-m')}."
            );
        }

        $firmCols     = array_filter($dateColumns, fn($d) => $d['type'] === 'FIRM');
        $forecastCols = array_filter($dateColumns, fn($d) => $d['type'] === 'FORECAST');
        Log::info("Kolom aktif: " . count($dateColumns) .
            " (FIRM=" . count($firmCols) . ", FORECAST=" . count($forecastCols) . ")");

        // Cari baris data pertama
        $dataStartRow = $this->findDataStartRow($sheet, max($headerRowIndex, $etaRowIndex), $assyCol, $qtyLabelCol, $dateColumns, $hiddenRows);
        if ($dataStartRow === null) {
            throw new \Exception("Baris data tidak ditemukan setelah header");
        }

        // Loop data rows
        $skipWords     = ['total', 'subtotal', 'grand total', 'balance'];
        $processedRows = 0;
        $lastModel     = null;
        $lastFamily    = null;

        for ($i = $dataStartRow; $i < count($sheet); $i++) {
            if (isset($hiddenRows[$i])) {
                continue;
            }

            $row = $sheet[$i];
            if (!is_array($row)) continue;

            $assyNumber = trim((string)($row[$assyCol] ?? ''));
            if (empty($assyNumber)) continue;
            if (in_array(strtolower($assyNumber), $skipWords)) continue;

            // Skip non-assy metadata rows (like Line Off dates and Packaging remarks)
            $assyLower = strtolower($assyNumber);
            if (str_contains($assyLower, 'l/o') || str_contains($assyLower, 'l.o') || str_contains($assyLower, 'pkg')) {
                continue;
            }

            $processedRows++;

            $model  = ($modelCol !== null) ? (trim((string)($row[$modelCol] ?? '')) ?: null) : null;
            $family = ($familyCol !== null) ? (trim((string)($row[$familyCol] ?? '')) ?: null) : null;
            if ($model === null) {
                $model = $lastModel;
            }
            if ($family === null) {
                $family = $lastFamily;
            }
            if ($model !== null) {
                $lastModel = $model;
            }
            if ($family !== null) {
                $lastFamily = $family;
            }

            $no     = ($noCol !== null) ? ($row[$noCol] ?? null) : null;
            $sfx    = ($sfxCol !== null) ? (trim((string)($row[$sfxCol] ?? '')) ?: null) : null;

            foreach ($dateColumns as $colIndex => $info) {
                $qty = $row[$colIndex] ?? null;
                if ($qty === null || $qty === '') continue;

                if (is_string($qty)) {
                    if (str_starts_with($qty, '=')) continue;
                    $qty = (int) preg_replace('/[^0-9-]/', '', $qty);
                } else {
                    $qty = (int) $qty;
                }

                if ($qty < 0) continue;

                $result[] = [
                    'customer'      => 'TYC',
                    'source_file'   => null,
                    'assy_number'   => $assyNumber,
                    'qty'           => $qty,
                    'delivery_date' => $info['eta']->toDateString(),
                    'eta'           => $info['eta']->toDateString(),
                    'etd'           => $info['etd']->toDateString(),
                    'week'          => trim((string)($info['label'] ?? $info['eta']->format('W'))),
                    'month'         => $info['month'],
                    'order_type'    => $info['type'],
                    'model'         => $model,
                    'family'        => $family,
                    'route'         => null,
                    'port'          => 'KAO',
                    'extra'         => json_encode([
                        'row'        => $i + 1,
                        'no'         => $no,
                        'sfx'        => $sfx,
                        'week_label' => $info['label'],
                        'col'        => $colIndex + 1,
                    ]),
                ];
            }
            // Safety check: prevent too many records
            if (count($result) > 50000) {
                throw new \Exception("Terlalu banyak records (max 50,000). Sheet mungkin tidak valid.");
            }
        }

        Log::info("Processed rows: {$processedRows} | Records: " . count($result));

        if (empty($result)) {
            throw new \Exception(
                "Tidak ada data QTY > 0 dalam window. Processed rows: {$processedRows}"
            );
        }

        return $result;
    }
    // Helpers

    /**
     * Cari kolom start: kolom pertama di TIME CHART row dengan format 'YYYY/M'.
     * Kolom sebelumnya = data historis lama → di-skip.
     * Fallback = 7 jika tidak ada anchor.
     */
    private function findDataColStart(array $timeChartRow, array $etaRow, array $etdRow, array $hiddenColumns = []): int
    {
        foreach ($timeChartRow as $i => $val) {
            if ($i < 7 || isset($hiddenColumns[$i])) continue;
            if (preg_match('/^\d{4}[\/\-]\d{1,2}$/', trim((string)$val))) {
                return $i;
            }
        }

        foreach ($etaRow as $i => $val) {
            if ($i < 7 || isset($hiddenColumns[$i])) continue;
            if ($this->parseDateValue($val) !== null) {
                return $i;
            }
        }

        foreach ($etdRow as $i => $val) {
            if ($i < 7 || isset($hiddenColumns[$i])) continue;
            if ($this->parseDateValue($val) !== null) {
                return $i;
            }
        }

        return 7;
    }

    private function findDataStartRow(array $sheet, int $headerRowIndex, int $assyCol, ?int $qtyLabelCol, array $dateColumns, array $hiddenRows = []): ?int
    {
        for ($i = $headerRowIndex + 1; $i < count($sheet); $i++) {
            if (isset($hiddenRows[$i])) {
                continue;
            }

            $row = $sheet[$i];
            if (!is_array($row)) {
                continue;
            }

            $assyNumber = trim((string)($row[$assyCol] ?? ''));
            if ($assyNumber === '') {
                continue;
            }

            $qtyLabel = '';
            if ($qtyLabelCol !== null) {
                $qtyLabel = strtoupper(trim((string)($row[$qtyLabelCol] ?? '')));
            }
            if (str_starts_with($qtyLabel, 'QTY')) {
                if ($this->rowHasQtyValues($row, $dateColumns)) {
                    return $i;
                }
                return $i + 1;
            }

            if ($this->rowHasQtyValues($row, $dateColumns)) {
                return $i;
            }
        }

        return null;
    }

    private function rowHasQtyValues(array $row, array $dateColumns): bool
    {
        foreach ($dateColumns as $colIndex => $_info) {
            if (!array_key_exists($colIndex, $row)) {
                continue;
            }

            $qty = $row[$colIndex];
            if ($qty === null || $qty === '') {
                continue;
            }

            if (is_string($qty) && str_starts_with($qty, '=')) {
                continue;
            }

            $qtyValue = is_string($qty)
                ? preg_replace('/[^0-9-]/', '', $qty)
                : $qty;

            if ((int) $qtyValue > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bangun peta kolom tanggal yang aktif dalam window.
     *
     * Untuk setiap kolom >= $dataColStart yang punya ETA PORT KAO:
     * 1. Jika kolom punya label bulan di TIME CHART row → update anchor state
     * 2. Kolom 2W/3W/4W/5W mewarisi state (bulan + type) dari anchor sebelumnya
     * 3. Rekonstruksi tahun dari urutan increment bulan
     * 4. Koreksi tahun ETA PORT KAO / ETD PORT SUR
     * 5. Filter window per type
     */
    private function buildDateColumns(
        array  $firmForecastRow,
        array  $timeChartRow,
        array  $etaRow,
        array  $etdRow,
        array  $headerRow,
        int    $dataColStart,
        Carbon $firmStart,
        Carbon $firmEnd,
        Carbon $forecastStart,
        Carbon $forecastEnd,
        array  $hiddenColumns = [],
        ?int   $targetYear = null
    ): array {
        $columns = [];
        $maxCol = max(count($firmForecastRow), count($timeChartRow), count($etaRow), count($etdRow));

        // NEW LOGIC: Extract month/year directly from ETA dates, not from TIME CHART row
        // Include all months found in ETA dates
        // Exclude only old FIRM (previous months), keep current month + future
        // DEDUPLICATE: Only keep first occurrence of each (month, type) combination
        // SKIP TOT COLUMNS: Skip columns with "TOT" in header or eta row

        $currentType = null;

        for ($i = $dataColStart; $i < $maxCol; $i++) {
            if (isset($hiddenColumns[$i])) {
                continue;
            }

            // SKIP TOT COLUMNS: Check if this is a TOT column
            $headerVal = strtoupper(trim((string)($headerRow[$i] ?? '')));
            if (str_contains($headerVal, 'TOT')) {
                Log::debug("TYC col {$i}: SKIPPED - TOT column");
                continue;
            }

            $etaRaw = $etaRow[$i] ?? null;
            if (empty($etaRaw)) {
                continue;
            }

            $etaBase = $this->parseDateValue($etaRaw);
            if (!$etaBase) {
                continue;
            }

            // Adjust year to planning target year if provided
            $yearOffset = 0;
            if ($targetYear !== null) {
                $yearOffset = $targetYear - $etaBase->year;
                $etaBase = $etaBase->copy()->addYears($yearOffset);
            }

            $etaYear = $etaBase->year;

            // Parse ETD date early so we can use it to determine the calendar month
            $etdRaw = $etdRow[$i] ?? null;
            $etdBase = $this->parseDateValue($etdRaw);
            if ($etdBase !== null && $targetYear !== null) {
                $etdBase = $etdBase->copy()->addYears($yearOffset);
            }

            // Gunakan ETD date (atau fallback ke ETA date jika ETD kosong) untuk menentukan bulan kalender JAI
            $dateForMonth = $etdBase ?? $etaBase;
            $colMonthStart = Carbon::create($dateForMonth->year, $dateForMonth->month, 1)->startOfMonth();
            $colMonthStr = $colMonthStart->format('Y-m');

            // CATATAN: File TYC SR melabeli SEMUA kolom sebagai "FIRM" di row 13,
            // termasuk Apr-Jul yang harusnya FORECAST. Oleh karena itu kita IGNORE
            // marker row dan gunakan window date untuk menentukan type yang benar.
            // FIRM = Bulan saat ini (reference month) dan bulan sebelumnya yang tersisa di berkas (misal Feb).
            // FORECAST = Bulan mendatang (reference month + 1 dst).
            if ($dateForMonth->betweenIncluded($firmStart, $firmEnd)) {
                $colType = 'FIRM';
                $shouldInclude = true;
            } elseif ($dateForMonth->betweenIncluded($forecastStart, $forecastEnd)) {
                $colType = 'FORECAST';
                $shouldInclude = true;
            } else {
                $colType = 'OUTSIDE';
                $shouldInclude = false;
            }

            if (!$shouldInclude) {
                Log::debug("TYC col {$i}: SKIPPED - {$colMonthStr} ({$colType} outside active window)");
                continue;
            }


            $info = [
                'eta'   => $etaBase,
                'etd'   => null,
                'type'  => $colType,
                'label' => trim((string)($headerRow[$i] ?? '')),
                'month' => null,
            ];

            if ($etdBase !== null) {
                $info['etd'] = $this->normalizeDateValueWithYear($etdBase, $etdRaw, $etaYear);
            } else {
                $info['etd'] = $etaBase->copy()->subDays(5);
            }

            $info['month'] = $colMonthStart->format('Y-m');
            $columns[$i] = $info;
        }

        if (empty($columns)) {
            Log::warning('TYC: No date columns detected; returning empty array');
        }

        return $columns;
    }

    // ── Parse helpers ──────────────────────────────────────────────────

    /** 
     * Parse month label dengan berbagai format
     * Return [year_hint|null, month] atau null
     * 
     * Format yang didukung:
     * - "2026-05" atau "2026/05" (YYYY/M)
     * - "2026-5" atau "2026/5"
     * - "05-22" atau "5-22" (M-YY, tahun 2-digit)
     * - "May-22" atau "May-2026" (Text-YY atau Text-YYYY)
     * - "5/22" atau "5-22" (M/YY)
     * - "5" atau "05" (M only, no year)
     */
    private function parseMonthLabel($value): ?array
    {
        if ($value === null || $value === '') return null;
        $s = trim((string) $value);

        // 1. Format: YYYY-MM atau YYYY/MM (4-digit year + 1-2 digit month)
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $s, $m)) {
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                return [(int) $m[1], $month];
            }
        }

        // 2. Format: Text month + 4-digit year (e.g., "May-2026", "Jun-2022")
        if (preg_match('/^([A-Za-z]+)[\/\-](\d{4})$/', $s, $m)) {
            $month = $this->parseMonthName($m[1]);
            if ($month !== null) {
                return [(int) $m[2], $month];
            }
        }

        // 3. Format: Text month + 2-digit year (e.g., "May-22", "Jun-26")
        if (preg_match('/^([A-Za-z]+)[\/\-](\d{2})$/', $s, $m)) {
            $month = $this->parseMonthName($m[1]);
            if ($month !== null) {
                $year2digit = (int) $m[2];
                $year4digit = $this->convertYearTwoDigit($year2digit);
                return [$year4digit, $month];
            }
        }

        // 4. Format: M-YY atau M/YY (1-2 digit month + 2-digit year)
        if (preg_match('/^(\d{1,2})[\/\-](\d{2})$/', $s, $m)) {
            $month = (int) $m[1];
            if ($month >= 1 && $month <= 12) {
                $year2digit = (int) $m[2];
                $year4digit = $this->convertYearTwoDigit($year2digit);
                return [$year4digit, $month];
            }
        }

        // 5. Format: M-M atau M/M (ambiguous, assume first is month)
        // Pattern for "5/22" already handled above, this is for "5/6" case
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})$/', $s, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            
            // Heuristic: jika first adalah month (1-12) dan second bukan, assume first is month
            if ($first >= 1 && $first <= 12 && ($second > 12 || $second < 1)) {
                return [null, $first];
            }
        }

        // 6. Format: M only (1-2 digits)
        if (preg_match('/^\d{1,2}$/', $s)) {
            $month = (int) $s;
            if ($month >= 1 && $month <= 12) {
                return [null, $month];
            }
        }

        return null;
    }

    /**
     * Parse month name (English or Indonesian)
     * Return 1-12 atau null
     */
    private function parseMonthName(string $name): ?int
    {
        $name = strtolower(trim($name));
        
        $monthMap = [
            // English
            'january' => 1, 'jan' => 1,
            'february' => 2, 'feb' => 2,
            'march' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5,
            'june' => 6, 'jun' => 6,
            'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'october' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12,
            // Indonesian
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];
        
        return $monthMap[$name] ?? null;
    }

    /**
     * Convert 2-digit year to 4-digit year
     * 00-30 → 2000-2030
     * 31-99 → 1931-1999
     * 
     * But untuk SR yang lebih modern (2020+), logic lebih baik:
     * Jika tahun-digit < current 2-digit year, assume current century
     * Jika >= current 2-digit year, assume previous century
     */
    private function convertYearTwoDigit(int $year2digit): int
    {
        if ($year2digit < 0 || $year2digit > 99) {
            return (int) now()->year; // Invalid, return current year
        }

        $currentYear = now()->year; // e.g., 2026
        $currentYear2digit = $currentYear % 100; // e.g., 26

        // 22 < 26 (current) → 2022
        // 30 > 26 (current) → 1930
        if ($year2digit <= $currentYear2digit) {
            return 2000 + $year2digit;
        } else {
            return 1900 + $year2digit;
        }
    }

    private function parseDateValue($value): ?Carbon
    {
        if ($value === null || $value === '') return null;

        if ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
            return Carbon::instance($value);
        }

        if (is_float($value) || (is_int($value) && $value > 40000)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                $dt->setTime(0, 0, 0);
                return Carbon::instance($dt);
            } catch (\Throwable $e) {
                Log::warning("Gagal parse Excel serial: {$value}");
            }
        }

        return $this->parseDateString((string) $value);
    }

    private function guessReferenceDate(array $sheet, array $etaTycRow, ?int $targetYear = null): ?Carbon
    {
        $yearToUse = $targetYear ?? now()->year;

        // 1. Prioritas utama: Deteksi tanggal perencanaan dari baris atas (misal mencari string date "YYYY-MM-DD" seperti "JAI 2026-03-01")
        for ($r = 0; $r < 15; $r++) {
            if (!isset($sheet[$r]) || !is_array($sheet[$r])) {
                continue;
            }
            foreach ($sheet[$r] as $cellVal) {
                if ($cellVal !== null && preg_match('/\b(202\d|203\d)[-\/](\d{1,2})[-\/](\d{1,2})\b/', (string)$cellVal, $m)) {
                    $result = Carbon::create((int)$m[1], (int)$m[2], 1)->startOfMonth();
                    Log::info("TYCMapper: Reference date detected from JAI metadata: {$result->toDateString()}");
                    return $result;
                }
            }
        }

        $datesWithYear = [];
        $datesWithoutYear = [];

        // 2. Kumpulkan tanggal dari ETA row
        foreach ($etaTycRow as $value) {
            if (empty($value)) {
                continue;
            }

            $date = $this->parseDateValue($value);
            if ($date === null) {
                continue;
            }

            if ($targetYear !== null) {
                $date = $date->copy()->year($targetYear);
            }

            // Kategorisasi: dengan tahun vs tanpa tahun
            if ($this->valueContainsYear($value)) {
                $datesWithYear[] = $date;
            } else {
                $datesWithoutYear[] = $date;
            }
        }

        // 3. Prioritas: gunakan tanggal dengan tahun eksplisit (lebih reliable)
        if (!empty($datesWithYear)) {
            usort($datesWithYear, fn($a, $b) => $a->timestamp <=> $b->timestamp);
            $result = $datesWithYear[0];
            Log::info("TYCMapper: Reference date detected from ETA row (with year): {$result->toDateString()}");
            return $result;
        }

        // 4. Fallback: gunakan tanggal tanpa tahun (assume targetYear or current year)
        if (!empty($datesWithoutYear)) {
            usort($datesWithoutYear, fn($a, $b) => $a->timestamp <=> $b->timestamp);
            $firstDate = $datesWithoutYear[0];
            $firstDate = $firstDate->setYear($yearToUse);
            Log::info("TYCMapper: Reference date detected from ETA row (without year): {$firstDate->toDateString()}");
            return $firstDate;
        }

        Log::warning('TYCMapper: Tidak bisa mendeteksi reference date dari ETA row atau TIME CHART row');
        return null;
    }

    private function parseDateString(string $str): ?Carbon
    {
        $str = trim($str);
        if (empty($str) || str_starts_with($str, '=')) return null;

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            if (checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                return Carbon::create((int)$m[1], (int)$m[2], (int)$m[3]);
            }
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})$/', $str, $m)) {
            if (checkdate((int)$m[2], 1, (int)$m[1])) {
                return Carbon::create((int)$m[1], (int)$m[2], 1);
            }
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $str, $m)) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            $y = (int)$m[3];

            if ($a > 12 && checkdate($b, $a, $y)) {
                return Carbon::create($y, $b, $a);
            }
            if ($b > 12 && checkdate($a, $b, $y)) {
                return Carbon::create($y, $a, $b);
            }
            if (checkdate($b, $a, $y)) {
                return Carbon::create($y, $b, $a);
            }
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            $a = (int)$m[1];
            $b = (int)$m[2];
            $year = 2000;

            if ($a > 12 && checkdate($b, $a, $year)) {
                return Carbon::create($year, $b, $a);
            }
            if ($b > 12 && checkdate($a, $b, $year)) {
                return Carbon::create($year, $a, $b);
            }
            if (checkdate($b, $a, $year)) {
                return Carbon::create($year, $b, $a);
            }
        }

        try {
            return Carbon::parse($str);
        } catch (\Throwable $e) {
            Log::warning("Unrecognized date format: {$str}");
            return null;
        }
    }

    private function detectHeaderRow(array $sheet): ?array
    {
        foreach ($sheet as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                $cellStr = strtolower(trim((string)$cell));
                if ($cellStr === 'product no' || $cellStr === 'assy no' || $cellStr === 'assy no (spp)') {
                    return [$row, $idx];
                }
            }
        }

        return null;
    }

    private function detectHeaderColumns(array $headerRow): array
    {
        $columns = [
            'assy_number' => null,
            'model' => null,
            'family' => null,
            'no' => null,
            'sfx' => null,
            'qty_label' => null,
        ];

        foreach ($headerRow as $idx => $value) {
            $text = strtoupper(trim((string)$value));
            if ($text === '') {
                continue;
            }

            if (str_contains($text, 'PRODUCT')) {
                $columns['assy_number'] = $idx;
            } elseif (str_contains($text, 'ASSY')) {
                if ($columns['assy_number'] === null || str_contains($text, 'SPP')) {
                    $columns['assy_number'] = $idx;
                }
            }
            if (str_contains($text, 'MODEL')) {
                $columns['model'] = $idx;
            }
            if (str_contains($text, 'FAMILY')) {
                $columns['family'] = $idx;
            }
            if ($text === 'NO' || $text === 'NO.' || $text === 'N0' || $text === 'N0.') {
                $columns['no'] = $idx;
            }
            if ($text === 'SFX') {
                $columns['sfx'] = $idx;
            }
            if (stripos($text, 'QTY') !== false) {
                $columns['qty_label'] = $idx;
            }
        }

        return $columns;
    }

    private function findQtyLabelColumn(array $headerRow): ?int
    {
        foreach ($headerRow as $idx => $val) {
            $text = trim((string)$val);
            if ($text === '') {
                continue;
            }
            if (stripos($text, 'QTY') !== false) {
                return $idx;
            }
        }

        return null;
    }

    private function detectFirmForecastRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        $max = min(count($sheet), $end);
        for ($idx = $start; $idx < $max; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            $found = 0;
            foreach ($row as $cell) {
                $text = strtoupper(trim((string)$cell));
                if (str_contains($text, 'FIRM') || str_contains($text, 'FORECAST')) {
                    $found++;
                }
            }

            if ($found >= 1) {
                return [$row, $idx];
            }
        }

        return null;
    }

    private function detectTimeChartRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        $pattern = '/^(?:\d{4}[\/\-]\d{1,2}|\d{1,2}W|\d{1,2})$/i';
        $max = min(count($sheet), $end);

        for ($idx = $start; $idx < $max; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            $valid = 0;
            foreach ($row as $cell) {
                if (preg_match($pattern, trim((string)$cell))) {
                    $valid++;
                }
            }

            if ($valid >= 3) {
                return [$row, $idx];
            }
        }

        return null;
    }

    private function detectEtaPortKaoRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        return $this->detectLabeledDateRow($sheet, ['ETA', 'KAO'], $start, $end);
    }

    private function detectEtdJaiRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        return $this->detectLabeledDateRow($sheet, ['ETD', 'JAI'], $start, $end);
    }

    private function detectEtdPortSurRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        return $this->detectLabeledDateRow($sheet, ['ETD', 'SUR'], $start, $end);
    }

    private function detectDateRowBefore(array $sheet, int $index, int $start = 0): ?array
    {
        for ($idx = $index; $idx >= max($start, 0); $idx--) {
            $row = $sheet[$idx] ?? null;
            if (!is_array($row)) {
                continue;
            }

            if ($this->countDateValues($row) >= 5) {
                return [$row, $idx];
            }
        }

        return null;
    }

    private function detectLabeledDateRow(array $sheet, array $mustContain, int $start = 0, int $end = 30): ?array
    {
        $max = min(count($sheet), $end);
        $mustContain = array_map(fn($word) => strtoupper($word), $mustContain);

        for ($idx = max($start, 0); $idx < $max; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            $text = strtoupper(implode(' ', array_map(fn($cell) => trim((string)$cell), $row)));
            $found = true;
            foreach ($mustContain as $word) {
                if ($word === '' || str_contains($word, ' ')) {
                    if (stripos($text, $word) === false) {
                        $found = false;
                        break;
                    }
                } else {
                    if (strpos($text, $word) === false) {
                        $found = false;
                        break;
                    }
                }
            }

            if (!$found) {
                continue;
            }

            if ($this->countDateValues($row) >= 5) {
                return [$row, $idx];
            }
        }

        return null;
    }

    private function countDateValues(array $row): int
    {
        $count = 0;
        foreach ($row as $cell) {
            if ($this->parseDateValue($cell) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function normalizeDateValueWithYear(Carbon $date, $rawValue, ?int $year): Carbon
    {
        if ($year === null || $this->valueContainsYear($rawValue)) {
            return $date->copy();
        }

        try {
            return $date->copy()->year($year);
        } catch (\Throwable $e) {
            return $date->copy();
        }
    }

    private function valueContainsYear($value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_numeric($value) && (float) $value > 30000) {
            return true;
        }

        $text = trim((string)$value);
        if (preg_match('/^(\d{4})[\/\-]/', $text)) {
            return true;
        }
        if (preg_match('/\d{4}$/', $text)) {
            return true;
        }

        return false;
    }

    private function detectEtaTycRow(array $sheet, int $start = 0, int $end = 30): ?array
    {
        $max = min(count($sheet), $end);

        for ($idx = max($start, 0); $idx < $max; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            $valid = 0;
            foreach ($row as $cell) {
                if ($this->parseDateValue($cell) !== null) {
                    $valid++;
                }
            }

            if ($valid >= 5) {
                return [$row, $idx];
            }
        }

        return null;
    }

    private function detectPlanningYear(array $sheet, int $headerRowIndex, array $options = []): int
    {
        if (!empty($options['source_file'])) {
            if (preg_match('/\b(202\d|203\d)\b/', $options['source_file'], $m)) {
                return (int)$m[1];
            }
        }
        for ($r = 0; $r < $headerRowIndex; $r++) {
            if (!isset($sheet[$r]) || !is_array($sheet[$r])) {
                continue;
            }
            foreach ($sheet[$r] as $cellVal) {
                if ($cellVal !== null && preg_match('/\b(202\d|203\d)\b/', (string)$cellVal, $m)) {
                    return (int)$m[1];
                }
            }
        }
        return (int)now()->year;
    }

    private function productionMonthStart(int $year, int $monthNumber): Carbon
    {
        $firstOfMonth = Carbon::createFromDate($year, $monthNumber, 1)->startOfDay();
        $startDate = $firstOfMonth->copy()->startOfWeek(Carbon::MONDAY);

        if ($startDate->month !== $monthNumber) {
            $previousMonthRemainingDays = $startDate->daysInMonth - $startDate->day + 1;

            if ($previousMonthRemainingDays > 1) {
                $startDate->addWeek();
            }
        }

        return $startDate;
    }

    private function productionMonthRange(int $year, int $monthNumber): array
    {
        $startDate = $this->productionMonthStart($year, $monthNumber);
        $nextMonth = $monthNumber === 12 ? 1 : $monthNumber + 1;
        $nextYear = $monthNumber === 12 ? $year + 1 : $year;
        $nextMonthStart = $this->productionMonthStart($nextYear, $nextMonth);
        $endDate = $nextMonthStart->copy()->subDay();

        return [$startDate, $endDate];
    }
}
