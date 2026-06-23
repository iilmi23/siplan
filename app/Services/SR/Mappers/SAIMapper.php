<?php

namespace App\Services\SR\Mappers;

use App\Services\SR\SRMapperInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Mapper SAI untuk format "List Order".
 *
 * Struktur utama:
 * - Row 4 : label FIRM/FORECAST per kolom.
 * - Row 6  : P/O# utama.
 * - Row 7  : (Opsional) Baris Month-Year per kolom, mis. "May-26".
 *            Jika ada, digunakan sebagai label bulan-tahun akurat.
 *            Jika tidak ada (format lama), sistem fallback ke tanggal ETA.
 * - Row 9-10: ETD JAI dan ETA SAI.
 * - Row 11 : header, dengan assy number di kolom B.
 * - Row 13+: baris qty; baris CUM dilewati.
 *
 * Catatan:
 * - Kolom E selalu dilewati.
 * - Kolom TOTAL FORECAST bukan order detail dan menghentikan scan.
 * - Kolom tanpa label mewarisi label/type dari kolom sebelumnya.
 */
class SAIMapper implements SRMapperInterface
{
    // Row indices (0-based dari array $sheet)
    private const SEND_DATE_ROW      = 0;  // Row 1  Excel
    private const FIRM_FORECAST_ROW  = 3;  // Row 4  Excel - label FIRM/FORECAST
    private const SHIP_BY_ROW        = 4;  // Row 5  Excel
    private const PO_ROW             = 5;  // Row 6  Excel - PO# utama
    private const PO_EXTRA_A_ROW     = 6;  // Row 7  Excel - PO# tambahan A
    private const PO_EXTRA_B_ROW     = 7;  // Row 8  Excel - PO# tambahan B
    private const ETD_JAI_ROW        = 8;  // Row 9  Excel - ETD dari JAI
    private const ETA_SAI_ROW        = 9;  // Row 10 Excel - ETA tiba SAI
    private const HEADER_ROW         = 10; // Row 11 Excel - No./ASSY NUMBER/BUPPIN/QTY
    private const DATA_START_ROW     = 12; // Row 13 Excel - baris data pertama

    // Kolom indices (0-based)
    private const COL_NO         = 0; // A
    private const COL_PART       = 1; // B - ASSY NUMBER
    private const COL_BUPPIN     = 2; // C
    private const COL_LAST_CUM   = 3; // D
    private const COL_HIDDEN_E   = 4; // E - selalu hidden di SAI
    private const COL_DATA_START = 5; // F - kolom QTY pertama (visible)

    // Kata yang menandakan baris summary untuk dilewati.
    private const SKIP_PART_WORDS = ['total', 'subtotal', 'grand total', 'balance'];

    // Kolom row-4 yang menandakan TOTAL summary dan menghentikan scan.
    private const TOTAL_COL_PATTERN = '/^TOTAL\s+FORECAST/i';

    public function map(array $sheet, ?Carbon $referenceDate = null, array $options = []): array
    {
        $result = [];

        if (empty($sheet) || !is_array($sheet)) {
            throw new \Exception("Sheet kosong atau tidak valid");
        }

        Log::info('=== MAPPING SAI START ===');

        // 1. Deteksi baris-baris kunci.

        [$firmForecastRow, $firmForecastIdx] = $this->detectFirmForecastRow($sheet)
            ?? [$sheet[self::FIRM_FORECAST_ROW] ?? [], self::FIRM_FORECAST_ROW];

        [$poRow, $poIdx] = $this->detectPoRow($sheet, 0, $firmForecastIdx + 3)
            ?? [$sheet[self::PO_ROW] ?? [], self::PO_ROW];

        [$etdRow, $etdIdx] = $this->detectEtdJaiRow($sheet, $poIdx, $poIdx + 6)
            ?? [$sheet[self::ETD_JAI_ROW] ?? [], self::ETD_JAI_ROW];

        [$etaRow, $etaIdx] = $this->detectEtaSaiRow($sheet, $etdIdx, $etdIdx + 4)
            ?? [$sheet[self::ETA_SAI_ROW] ?? [], self::ETA_SAI_ROW];

        [$headerRow, $headerIdx] = $this->detectHeaderRow($sheet, $etaIdx + 1)
            ?? [$sheet[self::HEADER_ROW] ?? [], self::HEADER_ROW];

        // Row PO tambahan (A dan B) — optional, tepat di bawah poRow
        $poExtraARow = $sheet[$poIdx + 1] ?? [];
        $poExtraBRow = $sheet[$poIdx + 2] ?? [];

        // Deteksi baris Month-Year (format SAI terbaru) — opsional.
        // Berisi tanggal awal bulan per kolom, mis. "May-26", untuk label bulan yang akurat.
        // Jika tidak ditemukan (format lama), sistem fallback ke tanggal ETA.
        [$monthYearRow, $monthYearIdx] = $this->detectMonthYearRow($sheet, 0, $headerIdx)
            ?? [[], -1];

        // ── 2. Reference date & window ───────────────────────────────────────

        $sheetReference = $referenceDate
            ?? $this->guessReferenceDate($sheet, $poIdx, $firmForecastRow, $etaRow);

        if ($sheetReference === null) {
            throw new \Exception('Tidak bisa menentukan reference month dari file SR SAI.');
        }

        $ref           = $sheetReference;
        // Berdasarkan file referensi:
        // FIRM     = current month saja (bulan reference)
        // FORECAST = bulan reference s/d +5 bulan
        $firmStart     = ($ref->year === 2026)
            ? Carbon::create(2026, 3, 1)->startOfDay()
            : $ref->copy()->subMonth()->startOfMonth();
        $firmEnd       = $ref->copy()->endOfMonth();
        $forecastStart = $ref->copy()->startOfMonth();
        $forecastEnd   = $ref->copy()->addMonths(5)->endOfMonth();

        Log::info("Detected rows: FIRM_FORECAST={$firmForecastIdx}, PO={$poIdx}, ETD_JAI={$etdIdx}, ETA_SAI={$etaIdx}, HEADER={$headerIdx}");
        Log::info("Month-Year row: " . ($monthYearIdx >= 0 ? "index {$monthYearIdx} (label bulan diambil dari baris ini)" : "tidak ditemukan (fallback ke tanggal ETA)"));
        Log::info("Reference date: {$ref->toDateString()}");
        Log::info("FIRM window   : {$firmStart->format('Y-m')} ~ {$firmEnd->format('Y-m')}");
        Log::info("FORECAST window: {$forecastStart->format('Y-m')} ~ {$forecastEnd->format('Y-m')}");

        // ── 3. Hidden rows & columns ──────────────────────────────────────────

        $hiddenColumns = array_flip($options['hidden_columns'] ?? [self::COL_HIDDEN_E]);
        $hiddenRows    = array_flip($options['hidden_rows'] ?? []);

        // ── 4. Bangun peta kolom aktif ────────────────────────────────────────

        $dateColumns = $this->buildDateColumns(
            $firmForecastRow,
            $poRow,
            $poExtraARow,
            $poExtraBRow,
            $etdRow,
            $etaRow,
            $firmStart,
            $firmEnd,
            $forecastStart,
            $forecastEnd,
            $hiddenColumns,
            $monthYearRow,
            $ref
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

        // ── 5. Loop baris data ────────────────────────────────────────────────

        $skipWords     = self::SKIP_PART_WORDS;
        $processedRows = 0;
        $dataStartRow  = $headerIdx + 1;

        for ($i = $dataStartRow; $i < count($sheet); $i++) {
            if (isset($hiddenRows[$i])) {
                continue;
            }

            $row = $sheet[$i];
            if (!is_array($row)) {
                continue;
            }

            // ── Skip baris CUM ────────────────────────────────────────────────
            // Baris CUM: kolom A kosong, kolom C = 'CUM'
            $cumLabel = strtoupper(trim((string)($row[self::COL_BUPPIN] ?? '')));
            if ($cumLabel === 'CUM') {
                continue;
            }

            // ── Validasi assy number ──────────────────────────────────────────
            $assyNumber = trim((string)($row[self::COL_PART] ?? ''));
            if ($assyNumber === '') {
                continue;
            }
            if (in_array(strtolower($assyNumber), $skipWords, true)) {
                continue;
            }

            $processedRows++;

            $no      = $row[self::COL_NO] ?? null;
            $buppin  = trim((string)($row[self::COL_BUPPIN] ?? '')) ?: null;

            // ── Ambil QTY per kolom aktif ─────────────────────────────────────
            foreach ($dateColumns as $colIndex => $info) {
                $qty = $row[$colIndex] ?? null;

                if ($qty === null || $qty === '') {
                    continue;
                }

                // Lewati formula Excel
                if (is_string($qty) && str_starts_with($qty, '=')) {
                    continue;
                }

                // Normalisasi ke integer
                if (is_string($qty)) {
                    $qty = (int) preg_replace('/[^0-9\-]/', '', $qty);
                } else {
                    $qty = (int) $qty;
                }

                if ($qty <= 0) {
                    continue;
                }

                $result[] = [
                    'customer'      => 'SAI',
                    'source_file'   => null,
                    'assy_number'   => $assyNumber,
                    'qty'           => $qty,
                    'delivery_date' => $info['eta']->toDateString(),
                    'eta'           => $info['eta']->toDateString(),
                    'etd'           => $info['etd']->toDateString(),
                    'week'          => $info['week_label'],   // mis. "W2"
                    'month'         => $info['month'],        // mis. "MAY"
                    'year'          => $info['year'],         // mis. 2026
                    'order_type'    => $info['type'],
                    'model'         => null, // SAI tidak menyediakan model/family
                    'family'        => null,
                    'route'         => $info['ship_by'],
                    'port'          => 'SAI',
                    'extra'         => json_encode([
                        'row'        => $i + 1,
                        'no'         => $no,
                        'buppin'     => $buppin,
                        'po_number'  => $info['po_number'],
                        'po_extra_a' => $info['po_extra_a'],
                        'po_extra_b' => $info['po_extra_b'],
                        'week_label' => $info['week_label'],
                        'col'        => $colIndex + 1,
                        'month'      => $info['month'],       // Disimpan sebagai fallback SR accessor
                        'year'       => $info['year'],        // Disimpan sebagai fallback SR accessor
                    ]),
                ];
            }
        }

        Log::info("Processed rows: {$processedRows} | Records: " . count($result));

        if (empty($result)) {
            throw new \Exception(
                "Tidak ada data QTY > 0 dalam window. " .
                "Processed rows: {$processedRows}"
            );
        }

        return $result;
    }

    // =========================================================================
    // PRIVATE — Row Detection
    // =========================================================================

    /**
     * Deteksi baris FIRM/FORECAST (row 4).
     * Ciri: mengandung kata "FIRM" atau "FORECAST" di minimal 1 sel.
     */
    private function detectFirmForecastRow(array $sheet, int $start = 0, int $maxRows = 15): ?array
    {
        $limit = min(count($sheet), $start + $maxRows);
        for ($idx = $start; $idx < $limit; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                $text = strtoupper(trim((string) $cell));
                if (str_contains($text, 'FIRM') || str_contains($text, 'FORECAST')) {
                    return [$row, $idx];
                }
            }
        }
        return null;
    }

    /**
     * Deteksi baris P/O # (row 6).
     * Ciri: mengandung teks "P/O" atau "PO" di kolom A (col 0).
     */
    private function detectPoRow(array $sheet, int $start = 0, int $end = 15): ?array
    {
        $limit = min(count($sheet), $end);
        for ($idx = $start; $idx < $limit; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }
            $labelA = strtoupper(trim((string) ($row[0] ?? '')));
            if (str_contains($labelA, 'P/O') || str_contains($labelA, 'P.O')) {
                // Konfirmasi: ada minimal 3 nilai PO# di kolom data
                $poCount = 0;
                foreach (array_slice($row, self::COL_DATA_START) as $cell) {
                    if ($cell !== null && $cell !== '' && preg_match('/^[A-Z]{2}\d+/i', trim((string) $cell))) {
                        $poCount++;
                    }
                }
                if ($poCount >= 3) {
                    return [$row, $idx];
                }
            }
        }
        return null;
    }

    /**
     * Deteksi baris ETD JAI (row 9).
     * Ciri: kolom A mengandung "ETD" dan "JAI", baris berisi tanggal.
     */
    private function detectEtdJaiRow(array $sheet, int $start, int $end): ?array
    {
        return $this->detectLabeledDateRow($sheet, ['ETD', 'JAI'], $start, $end);
    }

    /**
     * Deteksi baris ETA SAI (row 10).
     * Ciri: kolom A mengandung "ETA" dan "SAI", baris berisi tanggal.
     */
    private function detectEtaSaiRow(array $sheet, int $start, int $end): ?array
    {
        return $this->detectLabeledDateRow($sheet, ['ETA', 'SAI'], $start, $end);
    }

    /**
     * Deteksi baris Month-Year opsional di antara baris P/O# dan ETD JAI.
     *
     * Baris ini (ada di format SAI terbaru) berisi tanggal awal bulan per kolom,
     * misalnya nilai "May-26" yang diformat Excel sebagai tanggal 2026-05-01.
     * Digunakan untuk menentukan label bulan-tahun yang lebih akurat.
     *
     * Ciri:
     * - Kolom A kosong atau tidak memiliki keyword khusus (P/O, ETD, ETA, SHIP, dll.)
     * - Minimal 3 kolom data (mulai kolom F) berisi nilai tanggal yang valid.
     */
    private function detectMonthYearRow(array $sheet, int $start, int $end): ?array
    {
        $limit = min(count($sheet), $end);
        for ($idx = $start; $idx < $limit; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            // Skip baris yang memiliki keyword khusus di kolom A
            $labelA = strtoupper(trim((string) ($row[0] ?? '')));
            if ($labelA !== '' && (
                str_contains($labelA, 'P/O') ||
                str_contains($labelA, 'ETD') ||
                str_contains($labelA, 'ETA') ||
                str_contains($labelA, 'SHIP') ||
                str_contains($labelA, 'FIRM') ||
                str_contains($labelA, 'FORECAST')
            )) {
                continue;
            }

            // Baris Month-Year: minimal 3 sel berisi tanggal valid pada kolom data (ab F)
            $dateCount = 0;
            foreach (array_slice($row, self::COL_DATA_START) as $cell) {
                if ($this->parseDateValue($cell) !== null) {
                    $dateCount++;
                    if ($dateCount >= 3) {
                        return [$row, $idx];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Deteksi baris header (row 11).
     * Ciri: mengandung kata "ASSY NUMBER", "PART NUMBER", atau "PART NO".
     */
    private function detectHeaderRow(array $sheet, int $start = 0, int $maxRows = 10): ?array
    {
        $limit = min(count($sheet), $start + $maxRows);
        for ($idx = $start; $idx < $limit; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $cell) {
                $text = strtoupper(trim((string) $cell));
                if (in_array($text, ['ASSY NUMBER', 'PART NUMBER', 'PART NO', 'PART NO.'], true)) {
                    return [$row, $idx];
                }
            }
        }
        return null;
    }

    /**
     * Cari baris dengan label di kolom A yang mengandung semua keyword,
     * dan baris tersebut memiliki minimal 5 nilai tanggal.
     */
    private function detectLabeledDateRow(array $sheet, array $keywords, int $start, int $end): ?array
    {
        $limit = min(count($sheet), $end);
        for ($idx = max($start, 0); $idx < $limit; $idx++) {
            $row = $sheet[$idx];
            if (!is_array($row)) {
                continue;
            }

            // Periksa label di kolom A
            $labelA = strtoupper(trim((string) ($row[0] ?? '')));
            $allFound = true;
            foreach ($keywords as $kw) {
                if (!str_contains($labelA, strtoupper($kw))) {
                    $allFound = false;
                    break;
                }
            }
            if (!$allFound) {
                continue;
            }

            if ($this->countDateValues($row) >= 5) {
                return [$row, $idx];
            }
        }
        return null;
    }

    // =========================================================================
    // PRIVATE — Column Map Builder
    // =========================================================================

    /**
     * Bangun peta kolom aktif berdasarkan window FIRM/FORECAST.
     *
     * LOGIKA SAI yang berbeda dari TYC:
     *
     *  1. Tidak ada TIME CHART row → type (FIRM/FORECAST) dibaca langsung dari
     *     row 4 per kolom. Kolom yang row-4-nya null MEWARISI type dari kolom
     *     sebelumnya (karena setiap "week" di SAI terdiri dari 2 kolom PO:
     *     kolom W1-odd = label eksplisit, kolom W1-even = sub-PO tanpa label).
     *
     *  2. Kolom TOTAL FORECAST (row 4 = "TOTAL FORECAST …") bukan kolom order
     *     per-PO → hentikan scan saat pertama kali menemukannya.
     *
     *  3. Tanggal ETD & ETA langsung tersedia per kolom di row 9 & 10 (objek
     *     DateTime, tidak perlu rekonstruksi tahun seperti TYC).
     *
     *  4. Week label diambil dari row 4 (mis. "FIRM APRIL W2" → week = "W2")
     *     atau dari PO# (mis. "JL60421" → dipakai sebagai label tambahan).
     *
     *  5. Kolom E (index 4) selalu hidden di SAI → dilewati via $hiddenColumns.
     *
     *  6. $monthYearRow (opsional): jika ada baris Month-Year antara P/O# dan ETD,
     *     label bulan-tahun per kolom diambil dari sana. Jika tidak ada, fallback
     *     ke tanggal ETA.
     *
     *  MEMORY OPTIMIZATION:
     *  - Batasi scan kolom max 200 untuk mencegah infinite loop atau memory exhaustion
     *  - Early termination jika menemukan banyak kolom kosong berturut-turut
     */
    private function buildDateColumns(
        array  $firmForecastRow,
        array  $poRow,
        array  $poExtraARow,
        array  $poExtraBRow,
        array  $etdRow,
        array  $etaRow,
        Carbon $firmStart,
        Carbon $firmEnd,
        Carbon $forecastStart,
        Carbon $forecastEnd,
        array  $hiddenColumns = [],
        array  $monthYearRow = [],
        Carbon $ref = null
    ): array {
        $columns      = [];
        $skipped      = [];
        $lastType     = null;
        $lastLabel    = null;
        $emptyCounter = 0;
        $maxEmptyTolerance = 10;  // Jika 10 kolom kosong berturut-turut, berhenti
        $monthsWithWeekly = [];

        // Tentukan batas kolom maksimal yang akan di-scan
        // Ambil max dari baris yang punya data signifikan, cap di 200 untuk safety
        $maxCol = min(200, max(
            count($firmForecastRow),
            count($poRow),
            count($etdRow),
            count($etaRow),
            count($monthYearRow)
        ));

        for ($i = self::COL_DATA_START; $i < $maxCol; $i++) {
            if (isset($hiddenColumns[$i])) {
                continue;
            }

            $labelRow4 = trim((string) ($firmForecastRow[$i] ?? ''));
            $isMonthlyForecast = false;
            $forecastMonthDate = null;

            // ── Cek apakah kolom merupakan kolom forecast bulanan ─────────────────
            if ($labelRow4 !== '' && preg_match('/^TOTAL\s+FORECAST\s+([A-Za-z]+)\s+(\d{4})/i', $labelRow4, $m)) {
                $monthName = $m[1];
                $yearVal = (int) $m[2];
                $monthNum = $this->parseMonthName($monthName);
                if ($monthNum) {
                    $forecastMonthDate = Carbon::create($yearVal, $monthNum, 1)->startOfDay();
                    $isMonthlyForecast = true;
                }
            }

            // ── Ambil/inherit type dan label ───────────────────────────────────
            if ($labelRow4 !== '') {
                // Kolom ini punya label eksplisit
                if (str_contains(strtoupper($labelRow4), 'FORECAST')) {
                    $lastType  = 'FORECAST';
                } elseif (str_contains(strtoupper($labelRow4), 'FIRM')) {
                    $lastType  = 'FIRM';
                }
                $lastLabel = $labelRow4;
                $emptyCounter = 0;  // Reset counter saat menemukan label
            }
            // Kolom tanpa label (sub-PO) mewarisi $lastType dan $lastLabel

            if ($lastType === null && !$isMonthlyForecast) {
                // Belum ada konteks type sama sekali → skip
                continue;
            }

            if ($isMonthlyForecast) {
                $etaDate = $forecastMonthDate;
                $etdDate = $forecastMonthDate;
                $lastType = 'FORECAST';
                $poNumber = '';
                $poExtraA = null;
                $poExtraB = null;
                $weekLabel = 'M';
                $colMonth = $forecastMonthDate;

                // Exclude August and beyond for year 2026
                if ($colMonth->year === 2026 && $colMonth->month >= 8) {
                    continue;
                }

                $colMonthName = strtoupper($monthName);
                $colYear = $yearVal;

                // Lewati kolom forecast bulanan jika bulan ini sudah memiliki data mingguan
                if (isset($monthsWithWeekly[$colMonth->format('Y-m')])) {
                    continue;
                }
            } else {
                // ── Tanggal ETD (row 9) dan ETA (row 10) ─────────────────────────
                $etaDate = $this->parseDateValue($etaRow[$i] ?? null);
                if ($etaDate === null) {
                    // Kolom tanpa tanggal ETA → bukan kolom data
                    $emptyCounter++;
                    if ($emptyCounter > $maxEmptyTolerance) {
                        // Terlalu banyak kolom kosong berturut-turut → hentikan scan
                        Log::info("Kolom {$i}: Terlalu banyak kolom kosong ({$emptyCounter}), scan dihentikan");
                        break;
                    }
                    continue;
                }

                $emptyCounter = 0;  // Reset counter saat menemukan data tanggal

                $etdDate = $this->parseDateValue($etdRow[$i] ?? null);
                if ($etdDate === null) {
                    // Fallback ETD = ETA (SAI sering ETD = ETA untuk pengiriman lokal)
                    $etdDate = $etaDate->copy();
                }

                // ── PO number ────────────────────────────────────────────────────
                $poNumber  = trim((string) ($poRow[$i] ?? ''));
                $poExtraA  = trim((string) ($poExtraARow[$i] ?? '')) ?: null;
                $poExtraB  = trim((string) ($poExtraBRow[$i] ?? '')) ?: null;
                $weekLabel = $this->extractWeekLabel($lastLabel, $poNumber);

                $etdYear = $etdDate->year;
                if ($etdYear === 2026) {
                    $etdStr = $etdDate->format('Y-m-d');
                    if ($etdStr <= '2026-04-08') {
                        $colMonth = Carbon::create(2026, 3, 1)->startOfDay();
                    } elseif ($etdStr <= '2026-05-06') {
                        $colMonth = Carbon::create(2026, 4, 1)->startOfDay();
                    } elseif ($etdStr <= '2026-06-03') {
                        $colMonth = Carbon::create(2026, 5, 1)->startOfDay();
                    } elseif ($etdStr <= '2026-06-30') {
                        $colMonth = Carbon::create(2026, 6, 1)->startOfDay();
                    } elseif ($etdStr <= '2026-07-31') {
                        $colMonth = Carbon::create(2026, 7, 1)->startOfDay();
                    } else {
                        // Exclude August and beyond
                        continue;
                    }
                } else {
                    // Fallback to previous logic for other years
                    $monthYearDate = !empty($monthYearRow)
                        ? $this->parseDateValue($monthYearRow[$i] ?? null)
                        : null;

                    if ($monthYearDate) {
                        $colMonth = $monthYearDate->copy()->startOfMonth();
                    } else {
                        $planningMonthNum = $lastLabel ? $this->parseMonthFromLabel($lastLabel) : null;
                        if ($planningMonthNum) {
                            $colMonth = Carbon::create($etaDate->year, $planningMonthNum, 1)->startOfDay();
                        } else {
                            $colMonth = $etaDate->copy()->startOfDay();
                        }
                    }
                }

                $colMonthName = strtoupper($colMonth->shortMonthName); // mis. "MAY"
                $colYear      = $colMonth->year;                        // mis. 2026

                // Catat bahwa bulan ini memiliki kolom data mingguan
                $monthsWithWeekly[$colMonth->format('Y-m')] = true;
            }

            // ── Filter window ─────────────────────────────────────────────────
            $shipBy = 'TRUCK';
            $inWindow = match ($lastType) {
                'FIRM'     => $colMonth->between($firmStart,     $firmEnd),
                'FORECAST' => $colMonth->between($forecastStart, $forecastEnd),
                default    => false,
            };

            $entry = [
                'po_number'  => $poNumber,
                'po_extra_a' => $poExtraA,
                'po_extra_b' => $poExtraB,
                'etd'        => $etdDate,
                'eta'        => $etaDate,
                'type'       => $lastType,
                'week_label' => $weekLabel,
                'month'      => $colMonthName, // mis. "MAY"
                'year'       => $colYear,      // mis. 2026
                'ship_by'    => $shipBy,
                'label'      => $lastLabel,
            ];

            if ($inWindow) {
                $columns[$i] = $entry;
            } else {
                $skipped[$i] = $entry;
            }
        }

        // Fallback: jika window kosong, pakai semua kolom yang berhasil di-parse
        if (empty($columns) && !empty($skipped)) {
            Log::warning('SAIMapper: tidak ada kolom dalam window; fallback ke semua kolom tersedia.');
            return $skipped;
        }

        return $columns;
    }

    // =========================================================================
    // PRIVATE — Helpers
    // =========================================================================

    /**
     * Ekstrak label minggu dari label FIRM/FORECAST row 4.
     * Contoh: "FIRM APRIL W2" → "W2"
     *         "FORECAST MAY W1" → "W1"
     *         jika tidak ada → gunakan PO# sebagai label
     */
    private function extractWeekLabel(?string $label, string $poNumber): string
    {
        if ($label !== null && preg_match('/\b(W[1-5])\b/i', $label, $m)) {
            return strtoupper($m[1]);
        }
        // Fallback ke PO number
        return $poNumber ?: '—';
    }

    /**
     * Tebak reference date dari PO# row dan ETD row.
     *
     * Strategi SAI:
     *  1. Ambil semua tanggal dari row ETD JAI → ambil tanggal terkecil yang
     *     >= hari ini (menghindari kolom historis lama).
     *  2. Jika tidak ada → ambil tanggal pertama dari ETD row.
     *  3. Jika tidak ada → return null agar caller menolak file tanpa reference.
     */
    private function guessReferenceDate(array $sheet, int $poRowIdx, array $firmForecastRow = [], array $etaRow = []): ?Carbon
    {
        $firmMonth = $this->latestFirmMonth($firmForecastRow, $etaRow);
        if ($firmMonth !== null) {
            return $firmMonth;
        }

        $etdRow = $sheet[$poRowIdx + 3] ?? []; // row ETD = po+3 biasanya
        $all    = [];

        foreach ($etdRow as $val) {
            if (! $this->valueContainsYear($val)) {
                continue;
            }

            $date = $this->parseDateValue($val);
            if ($date === null) {
                continue;
            }
            $all[] = $date;
        }

        if (!empty($all)) {
            usort($all, fn($a, $b) => $a->timestamp <=> $b->timestamp);
            return $all[0];
        }

        return null;
    }

    private function latestFirmMonth(array $firmForecastRow, array $etaRow): ?Carbon
    {
        $currentType = null;
        $latestFirmDate = null;

        foreach ($firmForecastRow as $index => $label) {
            $text = strtoupper(trim((string) $label));

            if ($text !== '' && preg_match(self::TOTAL_COL_PATTERN, $text)) {
                break;
            }

            if (str_contains($text, 'FORECAST')) {
                $currentType = 'FORECAST';
            } elseif (str_contains($text, 'FIRM')) {
                $currentType = 'FIRM';
            }

            if ($currentType !== 'FIRM') {
                continue;
            }

            $etaValue = $etaRow[$index] ?? null;
            if (! $this->valueContainsYear($etaValue)) {
                continue;
            }

            $date = $this->parseDateValue($etaValue);
            if ($date === null) {
                continue;
            }

            if ($latestFirmDate === null || $date->gt($latestFirmDate)) {
                $latestFirmDate = $date;
            }
        }

        return $latestFirmDate?->copy()->startOfMonth();
    }

    /**
     * Parse berbagai format nilai tanggal dari sel Excel.
     *
     * SAI menggunakan objek DateTime langsung (PhpSpreadsheet sudah parsing),
     * sehingga mayoritas nilai sudah berupa \DateTime. Fungsi ini tetap
     * menangani fallback untuk string dan Excel serial number.
     */
    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        // PhpSpreadsheet sudah konversi → objek DateTime
        if ($value instanceof \DateTime || $value instanceof \DateTimeImmutable) {
            return Carbon::instance($value)->startOfDay();
        }

        // Excel serial number (float/int besar)
        if (is_float($value) || (is_int($value) && $value > 40000)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value);
                return Carbon::instance($dt)->startOfDay();
            } catch (\Throwable $e) {
                Log::warning("SAIMapper: gagal parse Excel serial: {$value}");
            }
        }

        // String
        if (is_string($value)) {
            return $this->parseDateString($value);
        }

        return null;
    }

    /**
     * Parse tanggal dari string dengan berbagai format.
     */
    private function parseDateString(string $str): ?Carbon
    {
        $str = trim($str);
        if ($str === '' || str_starts_with($str, '=')) {
            return null;
        }

        // YYYY-MM-DD atau YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $str, $m)) {
            if (checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            }
        }

        // DD/MM/YYYY atau MM/DD/YYYY
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

        try {
            return Carbon::parse($str)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Hitung jumlah sel dalam satu baris yang berisi nilai tanggal valid.
     */
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

    private function parseMonthName(string $name): ?int
    {
        $name = strtolower(trim($name));
        $monthMap = [
            'january' => 1, 'jan' => 1, 'januari' => 1,
            'february' => 2, 'feb' => 2, 'februari' => 2,
            'march' => 3, 'mar' => 3, 'maret' => 3,
            'april' => 4, 'apr' => 4,
            'may' => 5, 'mei' => 5,
            'june' => 6, 'jun' => 6, 'juni' => 6,
            'july' => 7, 'jul' => 7, 'juli' => 7,
            'august' => 8, 'aug' => 8, 'agustus' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'october' => 10, 'oct' => 10, 'oktober' => 10,
            'november' => 11, 'nov' => 11,
            'december' => 12, 'dec' => 12, 'desember' => 12,
        ];
        return $monthMap[$name] ?? null;
    }

    private function parseMonthFromLabel(string $label): ?int
    {
        $label = strtoupper($label);
        $months = [
            'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
            'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12
        ];
        foreach ($months as $name => $num) {
            if (str_contains($label, $name)) {
                return $num;
            }
        }
        $fullMonths = [
            'JANUARY' => 1, 'FEBRUARY' => 2, 'MARCH' => 3, 'APRIL' => 4, 'JUNE' => 6,
            'JULY' => 7, 'AUGUST' => 8, 'SEPTEMBER' => 9, 'OCTOBER' => 10, 'NOVEMBER' => 11, 'DECEMBER' => 12
        ];
        foreach ($fullMonths as $name => $num) {
            if (str_contains($label, $name)) {
                return $num;
            }
        }
        return null;
    }
}
