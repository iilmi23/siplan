<?php

namespace App\Services\SR\Mappers;

use App\Services\SR\SRMapperInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Mapper YC.
 *
 * Catatan format:
 * - Week label diambil dari HEADER_ROW, bukan TIME_CHART_ROW.
 * - TIME_CHART_ROW dipakai sebagai anchor bulan.
 * - Label TIME CHART, ETA, dan ETD berada di kolom F.
 */
class YCMapper implements SRMapperInterface
{
    // Row indices (0-based) - default fallback
    private const SR_NO_ROW         = 6;
    private const JPN_FACT_ROW      = 8;
    private const OVERSEAS_ROW      = 9;
    private const PORT_ROW          = 10;
    private const CUST_GROUP_ROW    = 11;
    private const CUST_ROW          = 12;
    private const TIME_CHART_ROW    = 14;  // Row 15 Excel - anchor bulan
    private const CUST_ETA_FROM_ROW = 15;  // Row 16 Excel
    private const ETA_ROW           = 16;  // Row 17 Excel
    private const ETD_ROW           = 17;  // Row 18 Excel
    private const SR_ISSUE_DATE_ROW = 18;  // Row 19 Excel - SR ISSUE DATE
    private const HEADER_ROW        = 19;  // Row 20 Excel - kolom header + week numbers
    private const DATA_START_ROW    = 20;  // Row 21 Excel

    // Kolom indices (0-based)
    private const COL_MODEL      = 0;
    private const COL_FAMILY     = 1;
    private const COL_JIG        = 2;
    private const COL_NO         = 3;
    private const COL_PART       = 4;
    private const COL_HIST_START = 5;   // F - awal kolom historis (week 4 prev month)
    private const COL_HIST_END   = 9;   // J - akhir kolom historis (week 8 prev month)
    private const COL_LIVE_START = 10;  // K - awal kolom live (week 1 bulan TIME CHART pertama)
    private const COL_ROUTE      = 34;

    // Label row YC berada di Col F (index 5).
    private const LABEL_COL      = 5;

    private const SKIP_WORDS = ['total', 'subtotal', 'grand total'];

    public function map(array $sheet, ?Carbon $referenceDate = null, array $options = []): array
    {
        throw new \Exception(
            "YCMapper::map() tidak boleh dipanggil langsung. " .
                "Gunakan YCMapper::mapAllSheets() atau integrasikan via SRController."
        );
    }

    public function mapAllSheets(
        array   $allSheets,
        array   $sheetNames,
        array   $hiddenSheets = [],
        ?Carbon $referenceDate = null,
        array   $options = []
    ): array {
        $result = [];

        foreach ($allSheets as $sheetIndex => $sheetData) {
            if (!empty($hiddenSheets[$sheetIndex])) {
                Log::info("YCMapper: skip hidden sheet index={$sheetIndex}");
                continue;
            }

            $sheetName    = $sheetNames[$sheetIndex] ?? "Sheet{$sheetIndex}";
            $sheetOptions = $options[$sheetIndex] ?? $options;

            Log::info("YCMapper: mapping sheet [{$sheetIndex}] '{$sheetName}'");

            try {
                $sheetResult = $this->mapSingleSheet($sheetData, $sheetName, $referenceDate, $sheetOptions);

                foreach ($sheetResult as &$record) {
                    $record['sheet_index'] = $sheetIndex;
                    $record['sheet_name']  = $sheetName;
                }
                unset($record);

                $result[$sheetIndex] = $sheetResult;
                Log::info("YCMapper: sheet '{$sheetName}' → " . count($sheetResult) . " records");
            } catch (\Exception $e) {
                Log::warning("YCMapper: sheet '{$sheetName}' gagal — " . $e->getMessage());
                $result[$sheetIndex] = [];
            }
        }

        Log::info("YCMapper: total sheets processed = " . count($result));
        return $result;
    }

    public function mapSingleSheet(
        array   $sheet,
        string  $sheetName,
        ?Carbon $referenceDate = null,
        array   $options = []
    ): array {
        $result = [];

        if (empty($sheet) || !is_array($sheet)) {
            throw new \Exception("Sheet '{$sheetName}' kosong");
        }

        // ── 1. Metadata ───────────────────────────────────────────────────────
        $srNo    = trim((string) ($sheet[self::SR_NO_ROW][1]      ?? ''));
        $jpnFact = trim((string) ($sheet[self::JPN_FACT_ROW][1]   ?? ''));
        $overseas = trim((string) ($sheet[self::OVERSEAS_ROW][1]   ?? ''));
        $port    = trim((string) ($sheet[self::PORT_ROW][1]       ?? ''));
        $custGrp = trim((string) ($sheet[self::CUST_GROUP_ROW][1] ?? ''));
        $custRaw = trim((string) ($sheet[self::CUST_ROW][1]       ?? ''));

        Log::info("YCMapper [{$sheetName}]: SR={$srNo}, PORT={$port}, CUST={$custRaw}");

        // ── 2. Deteksi baris kunci ────────────────────────────────────────────
        // FIX BUG C: gunakan LABEL_COL = 5, bukan default 0
        $timeChartRowIdx = $this->detectRowIndexByLabel(
            $sheet,
            'TIME CHART MONTH',
            self::TIME_CHART_ROW,
            self::LABEL_COL,
            25
        ) ?? self::TIME_CHART_ROW;

        $etaRowIdx = $this->detectRowIndexByLabel(
            $sheet,
            'ETA',
            self::ETA_ROW,
            self::LABEL_COL,
            25,
            true
        ) ?? self::ETA_ROW;

        $etdRowIdx = $this->detectRowIndexByLabel(
            $sheet,
            'ETD',
            self::ETD_ROW,
            self::LABEL_COL,
            25,
            true
        ) ?? self::ETD_ROW;

        $headerRowIdx = $this->detectHeaderRowIndex($sheet) ?? self::HEADER_ROW;
        $dataStartRow = $headerRowIdx + 1;

        $timeChartRow = $sheet[$timeChartRowIdx] ?? [];
        $etdRow       = $sheet[$etdRowIdx]       ?? [];
        $etaRow       = $sheet[$etaRowIdx]       ?? [];
        $headerRow    = $sheet[$headerRowIdx]    ?? [];
        $srIssueDateRowIdx = $this->detectRowIndexByLabel(
            $sheet,
            'SR ISSUE DATE',
            self::SR_ISSUE_DATE_ROW,
            self::LABEL_COL,
            25
        ) ?? self::SR_ISSUE_DATE_ROW;
        $srIssueDateRow = $sheet[$srIssueDateRowIdx] ?? [];

        Log::info("YCMapper [{$sheetName}]: timeChartRowIdx={$timeChartRowIdx}, etdRowIdx={$etdRowIdx}, etaRowIdx={$etaRowIdx}, headerRowIdx={$headerRowIdx}, dataStartRow={$dataStartRow}");

        $targetYear = $this->detectPlanningYear($sheet, $headerRowIdx, $options);
        $sheetReference = $referenceDate
            ?? $this->guessReferenceDate($sheet, $srIssueDateRow, $etdRow, $etaRow, $targetYear, $options);

        if ($sheetReference === null) {
            throw new \Exception("Sheet '{$sheetName}': tidak bisa menentukan reference month dari file SR.");
        }

        $ref = $sheetReference;
        // Berdasarkan JAI production calendar range:
        // FIRM     = JAI production month range dari current month (bulan reference)
        // FORECAST = JAI production month range dari 5 bulan kedepan
        [$firmStart, $firmEnd] = $this->productionMonthRange($ref->year, $ref->month);
        $forecastStart = $firmEnd->copy()->addDay();

        $forecastTarget = $ref->copy()->addMonths(5);
        [, $forecastEnd] = $this->productionMonthRange($forecastTarget->year, $forecastTarget->month);

        $options['target_year'] = $targetYear;

        Log::info("YCMapper [{$sheetName}]: ref={$ref->toDateString()}, FIRM={$firmStart->format('Y-m')}~{$firmEnd->format('Y-m')}, FORECAST={$forecastStart->format('Y-m')}~{$forecastEnd->format('Y-m')}");

        // ── 4. Hidden options ─────────────────────────────────────────────────
        $hiddenColumns = array_flip($options['hidden_columns'] ?? []);
        $hiddenRows    = array_flip($options['hidden_rows']    ?? []);

        // ── 5. Bangun peta kolom ──────────────────────────────────────────────
        $dateColumns = $this->buildDateColumns(
            $etdRow,
            $etaRow,
            $headerRow,
            $srIssueDateRow,
            $firmStart,
            $firmEnd,
            $forecastStart,
            $forecastEnd,
            $hiddenColumns,
            $options
        );

        if (empty($dateColumns)) {
            Log::warning("YCMapper [{$sheetName}]: window kosong, mencoba fallback tanpa filter window");
            $dateColumns = $this->buildDateColumnsNoFilter(
                $etdRow,
                $etaRow,
                $headerRow,
                $srIssueDateRow,
                $firmEnd,
                $hiddenColumns,
                $options
            );
        }

        if (empty($dateColumns)) {
            throw new \Exception("Sheet '{$sheetName}': tidak ada kolom tanggal ditemukan.");
        }

        Log::info("YCMapper [{$sheetName}]: " . count($dateColumns) . " kolom aktif");

        // ── 6. Loop baris data ────────────────────────────────────────────────
        $lastModel  = null;
        $lastFamily = null;
        $processedRows = 0;

        for ($i = $dataStartRow; $i < count($sheet); $i++) {
            if (isset($hiddenRows[$i])) {
                continue;
            }

            $row = $sheet[$i];
            if (!is_array($row) || empty($row)) {
                continue;
            }

            $colA    = trim((string) ($row[self::COL_MODEL]  ?? ''));
            $colB    = trim((string) ($row[self::COL_FAMILY] ?? ''));
            $colC    = trim((string) ($row[self::COL_JIG]    ?? ''));
            $colD    = $row[self::COL_NO]   ?? null;
            $colE    = trim((string) ($row[self::COL_PART]   ?? ''));
            $colDStr = trim((string) ($colD ?? ''));

            // Stop di baris TOTAL level sheet
            if (strtolower($colA) === 'total') {
                break;
            }

            // Skip baris TOTAL subgroup
            if (strtolower($colB) === 'total') {
                continue;
            }

            // Skip CUM row: colE kosong dan colD kosong
            // (CUM row = baris genap tanpa PRODUCT NO — berisi running cumulative qty)
            if ($colE === '' && $colDStr === '') {
                continue;
            }

            // Update lastFamily / lastModel dari baris header subgroup
            if ($colE === '') {
                if ($colB !== '' && !is_numeric($colB) && strtolower($colB) !== 'total') {
                    $lastFamily = $colB;
                }
                if ($colA !== '') {
                    $lastModel = $colA;
                }
                continue;
            }

            if (in_array(strtolower($colE), self::SKIP_WORDS, true)) {
                continue;
            }

            if ($colA !== '') {
                $lastModel = $colA;
            }
            if ($colB !== '' && !is_numeric($colB) && strtolower($colB) !== 'total') {
                $lastFamily = $colB;
            }

            $processedRows++;
            $jigType = $colC ?: null;
            $no      = $colDStr !== '' ? $colD : null;
            $route   = isset($row[self::COL_ROUTE])
                ? (trim((string) ($row[self::COL_ROUTE])) ?: null)
                : null;

            foreach ($dateColumns as $colIndex => $info) {
                $qtyRaw = $row[$colIndex] ?? null;

                if ($qtyRaw === null || $qtyRaw === '') {
                    continue;
                }
                if (is_string($qtyRaw) && str_starts_with(trim($qtyRaw), '=')) {
                    continue;
                }

                $qty = is_string($qtyRaw)
                    ? (int) preg_replace('/[^0-9\-]/', '', $qtyRaw)
                    : (int) $qtyRaw;

                if ($qty < 0) {
                    continue;
                }

                $result[] = [
                    'customer'      => 'YC',
                    'source_file'   => null,
                    'assy_number'   => $colE,
                    'qty'           => $qty,
                    'delivery_date' => $info['eta']->toDateString(),
                    'eta'           => $info['eta']->toDateString(),
                    'etd'           => $info['etd']->toDateString(),
                    'week'          => $info['week_label'],
                    'month'         => $info['month'],
                    'order_type'    => $info['type'],
                    'model'         => $lastModel,
                    'family'        => $lastFamily,
                    'route'         => $route,
                    'port'          => $port ?: 'HAKATA BA',
                    'extra'         => json_encode([
                        'row'        => $i + 1,
                        'sheet'      => $sheetName,
                        'sr_no'      => $srNo,
                        'no'         => $no,
                        'jig_type'   => $jigType,
                        'week_label' => $info['week_label'],
                        'col'        => $colIndex + 1,
                        'jpn_fact'   => $jpnFact,
                        'overseas'   => $overseas,
                        'cust_group' => $custGrp,
                        'cust'       => $custRaw,
                    ]),
                ];
            }
        }

        Log::info("YCMapper [{$sheetName}]: processed_rows={$processedRows}, records=" . count($result));
        return $result;
    }

    // =========================================================================
    // PRIVATE — Column Map Builder
    // =========================================================================

    /**
     * Bangun peta kolom aktif dengan filter window FIRM/FORECAST.
     *
     * FIX BUG A+B: Tambah parameter $headerRow.
     * Week label dibaca dari $headerRow[$col] yang berisi "1,2,3,4,5"
     * per bulan — BUKAN dari $timeChartRow yang berisi nomor bulan (3,4,5,6,7,8).
     */
    private function buildDateColumns(
        array  $etdRow,
        array  $etaRow,
        array  $headerRow,
        array  $srIssueDateRow,
        Carbon $firmStart,
        Carbon $firmEnd,
        Carbon $forecastStart,
        Carbon $forecastEnd,
        array  $hiddenColumns = [],
        array  $options = []
    ): array {
        $columns = [];
        $skipped = [];

        $targetYear = $options['target_year'] ?? null;
        $maxCol = max(count($etdRow), count($etaRow));

        // First live month is February 2026 (based on Column 10 starting February block)
        $firstLiveMonth = '2026-02';
        $resolvedFirstLiveMonth = Carbon::parse($firstLiveMonth . '-01')->startOfMonth();
        if ($targetYear !== null) {
            $resolvedFirstLiveMonth->year($targetYear);
        }
        $histMonth = $resolvedFirstLiveMonth->copy()->subMonth()->format('Y-m');

        for ($col = self::COL_HIST_START; $col < $maxCol; $col++) {
            if (isset($hiddenColumns[$col])) {
                continue;
            }

            $etdRaw = $etdRow[$col] ?? null;
            $etaRaw = $etaRow[$col] ?? null;
            if (empty($etdRaw) && empty($etaRaw)) {
                continue;
            }

            $etdBase = $this->parseDateValue($etdRaw);
            $etaBase = $this->parseDateValue($etaRaw);

            if ($etdBase === null && $etaBase === null) {
                continue;
            }

            $yearOffset = 0;
            if ($targetYear !== null) {
                if ($etdBase !== null) {
                    $yearOffset = $targetYear - $etdBase->year;
                } elseif ($etaBase !== null) {
                    $yearOffset = $targetYear - $etaBase->year;
                }
            }

            if ($etdBase !== null) {
                $etdBase = $etdBase->copy()->addYears($yearOffset);
            }
            if ($etaBase !== null) {
                $etaBase = $etaBase->copy()->addYears($yearOffset);
            }

            if ($etdBase === null) {
                $etdBase = $etaBase->copy()->subDays(14);
            }
            if ($etaBase === null) {
                $etaBase = $etdBase->copy()->addDays(14);
            }

            if ($col < self::COL_LIVE_START) {
                $colMonthStr = $histMonth;
                $rawWeekVal = $headerRow[$col] ?? null;
                $weekLabel = ($rawWeekVal !== null && is_numeric($rawWeekVal))
                    ? ((int) $rawWeekVal . 'W')
                    : ('W' . ($col - self::COL_HIST_START + 4));
            } else {
                $info = $this->getJaiMonthAndWeekForCol($col);
                if ($info === null) {
                    continue;
                }

                $resolvedMonth = Carbon::parse($info['month'] . '-01')->startOfMonth();
                if ($targetYear !== null) {
                    $resolvedMonth->year($targetYear);
                }
                $colMonthStr = $resolvedMonth->format('Y-m');
                $weekLabel = $info['week_label'];
            }

            $resolvedMonthObj = Carbon::parse($colMonthStr . '-01')->startOfMonth();
            $resolvedProdStart = $this->productionMonthStart($resolvedMonthObj->year, $resolvedMonthObj->month);

            if ($col < self::COL_LIVE_START) {
                $type = 'FIRM';
                $inWindow = $resolvedProdStart->betweenIncluded($firmStart->copy()->subMonth(), $firmEnd);
            } else {
                if ($resolvedProdStart->betweenIncluded($firmStart, $firmEnd)) {
                    $type = 'FIRM';
                    $inWindow = true;
                } elseif ($resolvedProdStart->betweenIncluded($forecastStart, $forecastEnd)) {
                    $type = 'FORECAST';
                    $inWindow = true;
                } else {
                    $type = 'OUTSIDE';
                    $inWindow = false;
                }
            }

            $entry = [
                'etd'        => $etdBase,
                'eta'        => $etaBase,
                'type'       => $type,
                'week_label' => $weekLabel,
                'month'      => $colMonthStr,
            ];

            if ($inWindow) {
                $columns[$col] = $entry;
            } else {
                $skipped[$col] = $entry;
            }
        }

        if (empty($columns) && !empty($skipped)) {
            Log::warning('YCMapper: window kosong, fallback ke semua kolom.');
            return $skipped;
        }

        return $columns;
    }

    private function buildDateColumnsNoFilter(
        array $etdRow,
        array $etaRow,
        array $headerRow,
        array $srIssueDateRow,
        Carbon $firmEnd,
        array $hiddenColumns = [],
        array $options = []
    ): array {
        $columns = [];
        $targetYear = $options['target_year'] ?? null;
        $maxCol = max(count($etdRow), count($etaRow));

        // First live month is February 2026 (based on Column 10 starting February block)
        $firstLiveMonth = '2026-02';
        $resolvedFirstLiveMonth = Carbon::parse($firstLiveMonth . '-01')->startOfMonth();
        if ($targetYear !== null) {
            $resolvedFirstLiveMonth->year($targetYear);
        }
        $histMonth = $resolvedFirstLiveMonth->copy()->subMonth()->format('Y-m');

        for ($col = self::COL_HIST_START; $col < $maxCol; $col++) {
            if (isset($hiddenColumns[$col])) {
                continue;
            }

            $etdRaw = $etdRow[$col] ?? null;
            $etaRaw = $etaRow[$col] ?? null;
            if (empty($etdRaw) && empty($etaRaw)) {
                continue;
            }

            $etdBase = $this->parseDateValue($etdRaw);
            $etaBase = $this->parseDateValue($etaRaw);
            if ($etdBase === null && $etaBase === null) {
                continue;
            }

            $yearOffset = 0;
            if ($targetYear !== null) {
                if ($etdBase !== null) {
                    $yearOffset = $targetYear - $etdBase->year;
                } elseif ($etaBase !== null) {
                    $yearOffset = $targetYear - $etaBase->year;
                }
            }

            if ($etdBase !== null) {
                $etdBase = $etdBase->copy()->addYears($yearOffset);
            }
            if ($etaBase !== null) {
                $etaBase = $etaBase->copy()->addYears($yearOffset);
            }

            if ($etdBase === null) {
                $etdBase = $etaBase->copy()->subDays(14);
            }
            if ($etaBase === null) {
                $etaBase = $etdBase->copy()->addDays(14);
            }

            if ($col < self::COL_LIVE_START) {
                $colMonthStr = $histMonth;
                $rawWeekVal = $headerRow[$col] ?? null;
                $weekLabel = ($rawWeekVal !== null && is_numeric($rawWeekVal))
                    ? ((int) $rawWeekVal . 'W')
                    : ('W' . ($col - self::COL_HIST_START + 4));
            } else {
                $info = $this->getJaiMonthAndWeekForCol($col);
                if ($info === null) {
                    continue;
                }

                $resolvedMonth = Carbon::parse($info['month'] . '-01')->startOfMonth();
                if ($targetYear !== null) {
                    $resolvedMonth->year($targetYear);
                }
                $colMonthStr = $resolvedMonth->format('Y-m');
                $weekLabel = $info['week_label'];
            }

            $resolvedMonthObj = Carbon::parse($colMonthStr . '-01')->startOfMonth();
            $resolvedProdStart = $this->productionMonthStart($resolvedMonthObj->year, $resolvedMonthObj->month);
            $type = $resolvedProdStart->lte($firmEnd) ? 'FIRM' : 'FORECAST';

            $columns[$col] = [
                'etd'        => $etdBase,
                'eta'        => $etaBase,
                'type'       => $type,
                'week_label' => $weekLabel,
                'month'      => $colMonthStr,
            ];
        }

        return $columns;
    }


    // =========================================================================
    // PRIVATE — Row Detection
    // =========================================================================

    /**
     * FIX BUG C: Parameter $labelCol sekarang dipakai dengan benar.
     * Caller harus pass LABEL_COL = 5 untuk label YC (Col F).
     */
    private function detectRowIndexByLabel(
        array  $sheet,
        string $label,
        int    $defaultIdx,
        int    $labelCol = 0,
        int    $maxRows  = 25,
        bool   $exact    = false
    ): ?int {
        $limit = min(count($sheet), $maxRows);
        for ($i = 0; $i < $limit; $i++) {
            $row = $sheet[$i];
            if (!is_array($row)) {
                continue;
            }
            $cellVal = strtoupper(trim((string) ($row[$labelCol] ?? '')));
            $target = strtoupper($label);
            if ($exact ? $cellVal === $target : str_contains($cellVal, $target)) {
                return $i;
            }
        }
        return null;
    }

    private function detectHeaderRowIndex(array $sheet, int $maxRows = 25): ?int
    {
        $limit = min(count($sheet), $maxRows);
        for ($i = 0; $i < $limit; $i++) {
            $row = $sheet[$i];
            if (!is_array($row)) {
                continue;
            }
            $e = strtoupper(trim((string) ($row[self::COL_PART] ?? '')));
            if ($e === 'PRODUCT NO' || $e === 'PRODUCT NO.') {
                return $i;
            }
        }
        return null;
    }

    // =========================================================================
    // PRIVATE — Date & Year Helpers (tidak berubah)
    // =========================================================================

    private function guessReferenceDate(array $sheet, array $srIssueDateRow, array $etdRow, array $etaRow, ?int $targetYear = null, array $options = []): ?Carbon
    {
        $activeCol = $options['active_column'] ?? null;
        if ($activeCol !== null && isset($srIssueDateRow[$activeCol])) {
            $issueDate = $this->parseDateValue($srIssueDateRow[$activeCol]);
            if ($issueDate !== null) {
                Log::info("YCMapper: reference date resolved from active column SR ISSUE DATE: " . $issueDate->toDateString());
                return $issueDate->copy()->startOfMonth();
            }
        }

        if (!empty($options['source_file'])) {
            if (preg_match('/\b(202\d|203\d)[-\/_]?(\d{2})[-\/_]?(\d{2})\b/', $options['source_file'], $m)) {
                $result = Carbon::create((int)$m[1], (int)$m[2], 1)->startOfMonth();
                Log::info("YCMapper: reference date resolved from source file name: " . $result->toDateString());
                return $result;
            }
        }

        for ($r = 0; $r < 15; $r++) {
            if (!isset($sheet[$r]) || !is_array($sheet[$r])) {
                continue;
            }
            foreach ($sheet[$r] as $cellVal) {
                if ($cellVal !== null && preg_match('/\b(202\d|203\d)[-\/](\d{1,2})[-\/](\d{1,2})\b/', (string)$cellVal, $m)) {
                    $result = Carbon::create((int)$m[1], (int)$m[2], 1)->startOfMonth();
                    Log::info("YCMapper: reference date resolved from metadata: " . $result->toDateString());
                    return $result;
                }
            }
        }

        $srNo = trim((string) ($sheet[self::SR_NO_ROW][1] ?? ''));
        if ($srNo !== '') {
            if (preg_match('/\b(202\d|203\d)[-\/_]?(\d{2})[-\/_]?(\d{2})\b/', $srNo, $m)) {
                $result = Carbon::create((int)$m[1], (int)$m[2], 1)->startOfMonth();
                Log::info("YCMapper: reference date resolved from SR No: " . $result->toDateString());
                return $result;
            }
            if (preg_match('/(?<!\d)(\d{2})(\d{2})(\d{2})(?!\d)/', $srNo, $matches)) {
                $year = 2000 + (int) $matches[1];
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                if (checkdate($month, $day, $year)) {
                    $result = Carbon::create($year, $month, 1)->startOfMonth();
                    Log::info("YCMapper: reference date resolved from SR No pattern: " . $result->toDateString());
                    return $result;
                }
            }
        }

        for ($col = self::COL_LIVE_START; $col < count($etdRow); $col++) {
            $etdDate = $this->parseDateValue($etdRow[$col] ?? null);
            if ($etdDate !== null) {
                Log::info("YCMapper: reference date fallback to first live ETD: " . $etdDate->toDateString());
                return $etdDate->copy()->startOfMonth();
            }
        }

        return now()->startOfMonth();
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

    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
            return Carbon::instance($value)->startOfDay();
        }

        if (is_float($value) || (is_int($value) && $value > 40000)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                Log::warning("YCMapper: gagal parse serial: {$value}");
            }
        }

        if (is_string($value)) {
            return $this->parseDateString($value);
        }

        return null;
    }

    private function parseDateString(string $str): ?Carbon
    {
        $str = trim($str);
        if ($str === '' || str_starts_with($str, '=')) {
            return null;
        }

        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            }
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $str, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $y = (int) $m[3];
            if ($a > 12 && checkdate($b, $a, $y)) {
                return Carbon::create($y, $b, $a)->startOfDay();
            }
            if ($b > 12 && checkdate($a, $b, $y)) {
                return Carbon::create($y, $a, $b)->startOfDay();
            }
            if (checkdate($b, $a, $y)) {
                return Carbon::create($y, $b, $a)->startOfDay();
            }
        }

        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $placeholderYear = 2000;

            if ($a > 12 && checkdate($b, $a, $placeholderYear)) {
                return Carbon::create($placeholderYear, $b, $a)->startOfDay();
            }
            if ($b > 12 && checkdate($a, $b, $placeholderYear)) {
                return Carbon::create($placeholderYear, $a, $b)->startOfDay();
            }
            if (checkdate($a, $b, $placeholderYear)) {
                return Carbon::create($placeholderYear, $a, $b)->startOfDay();
            }
        }

        if (! $this->valueContainsYear($str)) {
            return null;
        }

        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function valueContainsYear(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (is_numeric($value) && (float) $value > 30000) {
            return true;
        }

        return is_string($value) && preg_match('/\b\d{4}\b/', $value) === 1;
    }

    private function getJaiMonthAndWeekForCol(int $col): ?array
    {
        // Define JAI month blocks starting from index 10 (Column K)
        // Format: [start_col, end_col, 'YYYY-MM']
        $blocks = [
            [10, 13, '2026-03'], // Mar: 4 cols (K, L, M, N)
            [14, 17, '2026-04'], // Apr: 4 cols (O, P, Q, R)
            [18, 20, '2026-05'], // May: 3 cols (S, T, U)
            [21, 25, '2026-06'], // Jun: 5 cols (V, W, X, Y, Z)
            [26, 29, '2026-07'], // Jul: 4 cols (AA, AB, AC, AD)
            [30, 33, '2026-08'], // Aug: 4 cols (AE, AF, AG, AH)
            [34, 38, '2026-09'], // Sep: 5 cols
            [39, 42, '2026-10'], // Oct: 4 cols
            [43, 46, '2026-11'], // Nov: 4 cols
            [47, 51, '2026-12'], // Dec: 5 cols
        ];

        foreach ($blocks as $block) {
            if ($col >= $block[0] && $col <= $block[1]) {
                $weekNum = $col - $block[0] + 1;
                return [
                    'month' => $block[2],
                    'week_label' => $weekNum . 'W',
                ];
            }
        }

        return null;
    }
}
