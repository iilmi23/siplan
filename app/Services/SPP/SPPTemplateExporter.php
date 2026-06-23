<?php

namespace App\Services\SPP;

use App\Models\Customer;
use App\Models\ProductionWeek;
use App\Models\SR;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\SPP\DTO\ExportParams;
use App\Services\SPP\Strategies\ExportStrategyInterface;

class SPPTemplateExporter
{
    private readonly ExportStrategyFactory $strategyFactory;
    private readonly ExcelXmlHelper $xmlHelper;

    public function __construct()
    {
        $this->strategyFactory = new ExportStrategyFactory();
        $this->xmlHelper = new ExcelXmlHelper();
    }

    /**
     * Ekspor data SPP ke template Excel.
     */
    public function export(Collection|iterable $allBatchRecords, array $months, string $customerCode, string $period): ?string
    {
        // Bungkus parameter ekspor ke dalam DTO ExportParams untuk mempermudah pemindahan data
        $params = new ExportParams($allBatchRecords, $months, $customerCode, $period);
        return $this->exportWithParams($params);
    }

    /**
     * Ekspor data SPP menggunakan parameter yang dibungkus dalam DTO ExportParams.
     */
    public function exportWithParams(ExportParams $params): ?string
    {
        // 1. Tentukan strategi ekspor berdasarkan customer (misal: SAI, TYC, YC, YNA)
        $strategy = $this->strategyFactory->make($params->getCustomerCode());
        if (!$strategy) {
            return null;
        }

        // 2. Cari file template Excel asli berdasarkan konfigurasi strategi customer
        $templatePath = base_path($strategy->getTemplatePath());
        if (!file_exists($templatePath)) {
            return null;
        }

        // 3. Buat file salinan sementara (temp file) agar template asli tidak termodifikasi
        $tempFile = $this->initializeTempFile($templatePath);
        if (!$tempFile) {
            return null;
        }

        // 4. Buka file sementara tersebut sebagai file ZIP (karena file .xlsx sebenarnya adalah arsip ZIP berisi file XML)
        $zip = new \ZipArchive();
        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);
            return null;
        }

        // 5. Baca teks bersama (shared strings) dan temukan rute/path file XML untuk masing-masing Sheet
        $sharedStrings = $this->xmlHelper->getSharedStrings($zip);
        $sheetPathMap = $this->xmlHelper->getSheetPaths($zip);

        // 6. Isi data minggu-minggu produksi pada sheet 'WEEK'
        $this->populateWeekSheet($zip, $strategy, $sheetPathMap, $params);
        
        // 7. Isi data mentah relasi kuantitas di sheet 'Sheet1'
        $this->populateSheet1($zip, $strategy, $sheetPathMap, $params);

        // 8. Dapatkan path XML untuk sheet utama (misal: 'QTY ' atau 'GUM')
        $mainSheetPath = $sheetPathMap[$strategy->getMainSheet()] ?? null;
        if (!$mainSheetPath) {
            $zip->close();
            @unlink($tempFile);
            return null;
        }

        // 9. Muat konten XML (DOMDocument) dari sheet utama untuk dimanipulasi
        $mainDom = $this->xmlHelper->getSheetDOM($zip, $mainSheetPath);
        $mainSheetData = $mainDom->getElementsByTagName('sheetData')->item(0);

        // 10. Tulis ulang nama bulan dan rentang tanggal pada header kolom sheet utama
        $this->writeMainSheetHeaders($mainDom, $mainSheetData, $strategy, $params->getMonths());
        
        // 11. Tulis header tambahan khusus jika ada (misal: penyesuaian sheet pendukung di SAI)
        $strategy->writeAdditionalHeaders(
            $zip,
            $sheetPathMap,
            $params->getMonths(),
            fn($z, $path) => $this->xmlHelper->getSheetDOM($z, $path),
            fn($z, $path, $dom) => $this->xmlHelper->saveSheetDOM($z, $path, $dom),
            fn($dom, $sheetData, $r) => $this->xmlHelper->getOrCreateRow($dom, $sheetData, $r),
            fn($dom, $row, $ref) => $this->xmlHelper->getOrCreateCell($dom, $row, $ref),
            fn($dom, $cell, $val) => $this->xmlHelper->setCellValue($dom, $cell, $val)
        );

        // 12. Isi baris-baris data utama (Assy Number, UMH, Qty Mingguan, beserta formula saldo BAL/PROD)
        $this->populateMainSheetData($mainDom, $mainSheetData, $strategy, $params, $sharedStrings);
        
        // 13. Simpan kembali DOMDocument sheet utama yang telah diperbarui ke dalam file ZIP
        $this->xmlHelper->saveSheetDOM($zip, $mainSheetPath, $mainDom);

        // 14. Hitung jumlah baris data riil dan replikasikan rumus ke sheet sekunder lainnya (misal: AS400, dll.)
        $numActualRows = $params->getAllBatchRecords()->groupBy('assy_number')->count();
        $this->populateSecondarySheets($zip, $strategy, $sheetPathMap, $numActualRows, $strategy->getStartRow(), $sharedStrings);

        // 15. Tutup file ZIP (menyimpan semua perubahan ke file .xlsx sementara) dan kembalikan path file
        $zip->close();
        return $tempFile;
    }

    /**
     * Buat file sementara dari file template.
     */
    private function initializeTempFile(string $templatePath): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'spp_export_');
        if (!copy($templatePath, $tempFile)) {
            return null;
        }
        return $tempFile;
    }

    /**
     * Isi data pada sheet WEEK di dalam template.
     */
    private function populateWeekSheet(\ZipArchive $zip, ExportStrategyInterface $strategy, array $sheetPathMap, ExportParams $params): void
    {
        if (!isset($sheetPathMap['WEEK'])) {
            return;
        }

        $year = Carbon::parse($params->getPeriod() . '-01')->year;
        $customer = Customer::where('code', $params->getCustomerCode())->first();
        $customerId = $customer ? $customer->id : null;
        $weeksData = $this->getWeeksData($year, $customerId);

        $weekDom = $this->xmlHelper->getSheetDOM($zip, $sheetPathMap['WEEK']);
        $this->populateWeekSheetXML($weekDom, $params->getCustomerCode(), $year, $weeksData);
        $this->xmlHelper->saveSheetDOM($zip, $sheetPathMap['WEEK'], $weekDom);
    }

    /**
     * Isi data pada Sheet1 di dalam template.
     */
    private function populateSheet1(\ZipArchive $zip, ExportStrategyInterface $strategy, array $sheetPathMap, ExportParams $params): void
    {
        if (isset($sheetPathMap['Sheet1'])) {
            $this->populateSheet1XML($zip, $sheetPathMap['Sheet1'], $params->getMonths(), $params->getCustomerCode(), $params->getAllBatchRecords(), $strategy);
        }
    }

    /**
     * Tulis ulang header Bulan/Tanggal pada sheet utama.
     */
    private function writeMainSheetHeaders(\DOMDocument $mainDom, \DOMElement $mainSheetData, ExportStrategyInterface $strategy, array $months): void
    {
        $monthStartCol = $strategy->getMonthStartCol();
        $monthRow = $strategy->getMonthRow();
        $dateRow = $strategy->getDateRow();

        foreach ($months as $idx => $month) {
            $colIdx = $monthStartCol + $idx * 3;
            $colLetter = Coordinate::stringFromColumnIndex($colIdx);

            $rowElem = $this->xmlHelper->getOrCreateRow($mainDom, $mainSheetData, $monthRow);
            $cellElem = $this->xmlHelper->getOrCreateCell($mainDom, $rowElem, $colLetter . $monthRow);
            $this->xmlHelper->setCellValue($mainDom, $cellElem, $month['label']);

            $dateRowElem = $this->xmlHelper->getOrCreateRow($mainDom, $mainSheetData, $dateRow);
            $dateCellElem = $this->xmlHelper->getOrCreateCell($mainDom, $dateRowElem, $colLetter . $dateRow);
            $this->xmlHelper->setCellValue($mainDom, $dateCellElem, $month['range_label']);
        }
    }

    /**
     * Isi baris data pada sheet utama.
     */
    private function populateMainSheetData(
        \DOMDocument $mainDom,
        \DOMElement $mainSheetData,
        ExportStrategyInterface $strategy,
        ExportParams $params,
        array $sharedStrings
    ): void {
        // Kelompokkan data SPP berdasarkan nomor Assy
        $groupedRecords = $params->getAllBatchRecords()->groupBy('assy_number');
        $numActualRows = $groupedRecords->count();

        // Dapatkan baris awal data dan cari baris akhir template di file XML
        $startRow = $strategy->getStartRow();
        $mainEndRow = $this->xmlHelper->findEndRowDOM($mainDom, $startRow, $sharedStrings);
        $numTemplateRows = $mainEndRow - $startRow + 1;

        // Geser baris-baris rumus total di bawahnya jika jumlah baris data riil melebihi baris template yang disediakan
        $shiftOffset = $numActualRows - $numTemplateRows;
        if ($shiftOffset > 0) {
            $this->xmlHelper->shiftRowsDown($mainDom, $mainSheetData, $mainEndRow, $shiftOffset, $startRow, $mainEndRow);
        }

        // Ambil dan simpan style (border, warna, font) dari baris pertama template untuk diduplikasi ke baris baru
        $styleCache = $this->xmlHelper->getRowStyleCache($mainSheetData, $startRow);

        // Tulis data baris demi baris
        for ($i = 0; $i < $numActualRows; $i++) {
            $r = $startRow + $i;
            $recordGroup = $groupedRecords->values()->get($i);
            $firstRec = $recordGroup->first();
            $keyedRecordGroup = $recordGroup->keyBy('period');

            $rowElem = $this->xmlHelper->getOrCreateRow($mainDom, $mainSheetData, $r);

            // Jika baris baru berada di luar batas baris template asli, salin style dari baris template pertama
            if ($r > $mainEndRow) {
                $templateRow = $this->xmlHelper->getOrCreateRow($mainDom, $mainSheetData, $startRow);
                $this->xmlHelper->copyRowAttributes($templateRow, $rowElem);
            }

            // Tulis metadata (nomor assy, carline, std pack, dll.) dan kuantitas mingguan beserta rumusnya
            $this->writeMetadataColumns($mainDom, $rowElem, $strategy, $firstRec, $r, $styleCache);
            $this->writeWeeklyQuantities($mainDom, $rowElem, $strategy, $keyedRecordGroup, $params->getMonths(), $r, $styleCache);
        }

        // Bersihkan data baris sisa di template jika jumlah baris data riil lebih sedikit dari baris template bawaan
        $this->clearExcessTemplateRows($mainDom, $mainSheetData, $startRow, $numActualRows, $mainEndRow);

        // Hapus referensi mergeCells untuk baris data yang dinamis dan bersihkan formula yang rusak
        $this->xmlHelper->removeMergeCellsDOM($mainDom, $startRow);
        $this->xmlHelper->cleanSheetFormulas($mainDom);
    }

    /**
     * Tulis kolom metadata baris berdasarkan konfigurasi.
     */
    private function writeMetadataColumns(
        \DOMDocument $dom,
        \DOMElement $rowElem,
        ExportStrategyInterface $strategy,
        mixed $firstRec,
        int $r,
        array $styleCache
    ): void {
        foreach ($strategy->getMapping() as $colIdx => $field) {
            $val = match ($field) {
                'pattern' => $firstRec->assy?->pattern ?? ($firstRec->pattern ?? ''),
                'assy_number' => $firstRec->assy_number,
                'level' => $firstRec->level,
                'assy_code' => $firstRec->assy_code,
                'carline' => $firstRec->carline,
                'std_pack' => $firstRec->std_pack,
                default => '',
            };

            $colLetter = Coordinate::stringFromColumnIndex($colIdx);
            $cell = $this->xmlHelper->getOrCreateCell($dom, $rowElem, $colLetter . $r);
            $this->xmlHelper->applyCellStyle($cell, $colLetter, $styleCache);
            $this->xmlHelper->setCellValue($dom, $cell, $val);
        }

        if ($strategy->hasSpecialIndexCol()) {
            $strategy->writeSpecialIndexCol(
                $dom,
                $rowElem,
                $r,
                $styleCache,
                function($row, $col, $cache) use ($dom, $r) {
                    $cell = $this->xmlHelper->getOrCreateCell($dom, $row, $col . $r);
                    $this->xmlHelper->applyCellStyle($cell, $col, $cache);
                    return $cell;
                },
                fn($d, $c, $v) => $this->xmlHelper->setCellValue($d, $c, $v)
            );
        }

        $umhColLetter = Coordinate::stringFromColumnIndex($strategy->getUmhCol());
        $cell = $this->xmlHelper->getOrCreateCell($dom, $rowElem, $umhColLetter . $r);
        $this->xmlHelper->applyCellStyle($cell, $umhColLetter, $styleCache);
        $this->xmlHelper->setCellValue($dom, $cell, $firstRec->umh);
    }

    /**
     * Tulis kolom bulanan/mingguan untuk DEL, PROD, dan BAL.
     */
    private function writeWeeklyQuantities(
        \DOMDocument $dom,
        \DOMElement $rowElem,
        ExportStrategyInterface $strategy,
        Collection $keyedRecordGroup,
        array $months,
        int $r,
        array $styleCache
    ): void {
        // Tentukan kolom mulai untuk kuantitas bulanan/mingguan
        $monthStartCol = $strategy->getMonthStartCol();
        $firstMonthPeriod = $months[0]['period'] ?? null;
        $firstMonthRec = $firstMonthPeriod ? $keyedRecordGroup->get($firstMonthPeriod) : null;
        $firstMonthBal = $firstMonthRec ? (int)$firstMonthRec->bal_qty : 0;
        $firstMonthBalColLetter = Coordinate::stringFromColumnIndex($monthStartCol);

        // Loop untuk menulis kolom kuantitas setiap bulan (BAL, DEL, PROD)
        foreach ($months as $idx => $month) {
            $periodKey = $month['period'];
            $rec = $keyedRecordGroup->get($periodKey);
            $delVal = $rec ? (int)$rec->del_qty : 0;
            $prodVal = $rec ? (int)$rec->prod_qty : 0;
            $balVal = $rec ? (int)$rec->bal_qty : 0;

            // Tentukan huruf kolom untuk BAL, DEL, dan PROD
            $balColLetter = Coordinate::stringFromColumnIndex($monthStartCol + $idx * 3);
            $delColLetter = Coordinate::stringFromColumnIndex($monthStartCol + $idx * 3 + 1);
            $prodColLetter = Coordinate::stringFromColumnIndex($monthStartCol + $idx * 3 + 2);

            // Tulis nilai DEL (Delivery)
            $delCell = $this->xmlHelper->getOrCreateCell($dom, $rowElem, $delColLetter . $r);
            $this->xmlHelper->applyCellStyle($delCell, $delColLetter, $styleCache);
            $this->xmlHelper->setCellValue($dom, $delCell, $delVal);

            // Tulis nilai PROD (Production)
            $prodCell = $this->xmlHelper->getOrCreateCell($dom, $rowElem, $prodColLetter . $r);
            $this->xmlHelper->applyCellStyle($prodCell, $prodColLetter, $styleCache);
            if ($idx === 0) {
                // Untuk bulan pertama, nilai PROD ditulis langsung sebagai nilai statis
                $this->xmlHelper->setCellValue($dom, $prodCell, $prodVal);
            } else {
                // Untuk bulan berikutnya, gunakan rumus formula kalkulasi produksi dari strategi customer
                $formula = $strategy->getProdFormula($delColLetter, $balColLetter, $firstMonthBalColLetter, $r, $prodVal, $delVal, $balVal, $firstMonthBal);
                $this->xmlHelper->setCellValue($dom, $prodCell, $formula);
            }

            // Tulis nilai BAL (Balance/Saldo awal)
            $balCell = $this->xmlHelper->getOrCreateCell($dom, $rowElem, $balColLetter . $r);
            $this->xmlHelper->applyCellStyle($balCell, $balColLetter, $styleCache);
            if ($idx === 0) {
                $this->xmlHelper->setCellValue($dom, $balCell, $balVal);
            } else {
                $prevBalCol = Coordinate::stringFromColumnIndex($monthStartCol + ($idx - 1) * 3);
                $prevProdCol = Coordinate::stringFromColumnIndex($monthStartCol + ($idx - 1) * 3 + 2);
                $prevDelCol = Coordinate::stringFromColumnIndex($monthStartCol + ($idx - 1) * 3 + 1);
                $this->xmlHelper->setCellValue($dom, $balCell, "={$prevBalCol}{$r}+{$prevProdCol}{$r}-{$prevDelCol}{$r}");
            }
        }
    }

    /**
     * Bersihkan baris template data yang berlebih.
     */
    private function clearExcessTemplateRows(\DOMDocument $dom, \DOMElement $sheetData, int $startRow, int $numActualRows, int $endRow): void
    {
        if ($numActualRows < ($endRow - $startRow + 1)) {
            for ($r = $startRow + $numActualRows; $r <= $endRow; $r++) {
                $rowElem = $this->xmlHelper->getOrCreateRow($dom, $sheetData, $r);
                foreach ($rowElem->getElementsByTagName('c') as $cell) {
                    $this->xmlHelper->setCellValue($dom, $cell, null);
                }
            }
        }
    }

    private function getWeeksData(int $year, ?int $customerId): array
    {
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        $weeksData = [];

        $allYearWeeks = ProductionWeek::query()
            ->where('year', $year)
            ->when($customerId, fn($q) => $q->where('customer_id', $customerId))
            ->orderBy('week_no')
            ->get();

        foreach ($months as $idx => $mLabel) {
            $monthNum = $idx + 1;
            $dbWeeks = $allYearWeeks->where('month_number', $monthNum)->values();

            $weeksCount = 4;
            $rangeLabel = '';

            if ($dbWeeks->isNotEmpty()) {
                $weeksCount = $dbWeeks->count();
                $wFirst = $dbWeeks->first();
                $wLast = $dbWeeks->last();
                $start = Carbon::parse($wFirst->week_start);
                $end = Carbon::parse($wLast->end_date);
                $rangeLabel = $start->format('d/M') . ' ~ ' . $end->format('d/M');
            } else {
                $date = Carbon::create($year, $monthNum, 1);
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();
                $rangeLabel = $start->format('d/M') . ' ~ ' . $end->format('d/M');

                $curr = $start->copy();
                $weeksCount = 0;
                while ($curr->lte($end)) {
                    if ($curr->dayOfWeek === Carbon::MONDAY) {
                        $weeksCount++;
                    }
                    $curr->addDay();
                }
                $weeksCount = max($weeksCount, 1);
            }

            $weeksData[$mLabel] = [
                'range' => strtoupper($rangeLabel),
                'weeks' => $weeksCount
            ];
        }

        return $weeksData;
    }

    private function populateWeekSheetXML(\DOMDocument $dom, string $customerCode, int $year, array $weeksData)
    {
        $monthsList = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
        $ranges = [];
        if ($customerCode === 'SAI') {
            $ranges[] = [2, 13];
            $ranges[] = [16, 27];
        } elseif ($customerCode === 'TYC') {
            $ranges[] = [3, 14];
        } elseif ($customerCode === 'YC') {
            $ranges[] = [55, 66];
        } elseif ($customerCode === 'YNA') {
            $ranges[] = [2, 13];
        }

        $sheetData = $dom->getElementsByTagName('sheetData')->item(0);
        if (!$sheetData) return;

        foreach ($ranges as $range) {
            list($startR, $endR) = $range;
            for ($r = $startR; $r <= $endR; $r++) {
                $monthIdx = $r - $startR;
                if ($monthIdx < 0 || $monthIdx >= 12) continue;
                $mLabel = $monthsList[$monthIdx];
                $data = $weeksData[$mLabel];

                $row = $this->xmlHelper->getOrCreateRow($dom, $sheetData, $r);

                if ($customerCode === 'SAI') {
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'A' . $r), $mLabel);
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'B' . $r), $data['weeks']);
                } else {
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'A' . $r), $mLabel);
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'B' . $r), $data['range']);
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'C' . $r), $year);
                    $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'D' . $r), $data['weeks']);
                    if ($customerCode === 'YNA') {
                        $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'E' . $r), $data['weeks']);
                        $this->xmlHelper->setCellValue($dom, $this->xmlHelper->getOrCreateCell($dom, $row, 'F' . $r), $data['weeks']);
                    }
                }
            }
        }
    }

    private function populateSheet1XML(\ZipArchive $zip, string $sheet1Path, array $months, string $customerCode, Collection|iterable $allBatchRecords, ExportStrategyInterface $strategy)
    {
        $years = collect($months)->pluck('year')->unique()->all();
        $customer = Customer::where('code', $customerCode)->first();
        $customerId = $customer ? $customer->id : null;

        $weeks = ProductionWeek::query()
            ->whereIn('year', $years)
            ->where(function ($q) use ($customerId) {
                if ($customerId) {
                    $q->where('customer_id', $customerId)->orWhereNull('customer_id');
                } else {
                    $q->whereNull('customer_id');
                }
            })
            ->orderBy('year')
            ->orderBy('month_number')
            ->orderBy('week_no')
            ->get();

        $uploadBatchIds = collect($allBatchRecords)->pluck('upload_batch_id')->filter()->unique()->all();

        $srQtyMap = [];
        if (!empty($uploadBatchIds)) {
            $srs = SR::query()
                ->whereIn('upload_batch_id', $uploadBatchIds)
                ->select('assy_number', 'production_week_id', DB::raw('SUM(qty) as total_qty'))
                ->groupBy('assy_number', 'production_week_id')
                ->get();

            foreach ($srs as $sr) {
                $srQtyMap[$sr->assy_number][$sr->production_week_id] = (int)$sr->total_qty;
            }
        }

        $uniqueAssyNumbers = collect($allBatchRecords)->pluck('assy_number')->filter()->unique()->sort()->values()->all();

        $dom = $this->xmlHelper->getSheetDOM($zip, $sheet1Path);
        $sheetData = $dom->getElementsByTagName('sheetData')->item(0);
        if (!$sheetData) return;

        while ($sheetData->hasChildNodes()) {
            $sheetData->removeChild($sheetData->firstChild);
        }

        $headerRow = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
        $headerRow->setAttribute('r', 1);
        $sheetData->appendChild($headerRow);

        $cellB1 = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
        $cellB1->setAttribute('r', 'B1');
        $this->xmlHelper->setCellValue($dom, $cellB1, 'Harness No');
        $headerRow->appendChild($cellB1);

        $weekColumnIndices = [];
        foreach ($months as $monthIdx => $monthInfo) {
            $p = $monthInfo['period'];
            $date = Carbon::parse($p . '-01');

            $monthWeeks = $weeks->where('year', $date->year)->where('month_number', $date->month)->sortBy('week_no')->values();
            foreach ($monthWeeks as $weekIdx => $pw) {
                $weekNo = $pw->week_no;
                $colIdx = $strategy->getSRColumnIndex($monthIdx, $weekNo);
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);

                $weekColumnIndices[$pw->id] = $colIdx;

                $cell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
                $cell->setAttribute('r', $colLetter . '1');
                $this->xmlHelper->setCellValue($dom, $cell, "M" . ($monthIdx + 1) . " W" . $weekNo);
                $headerRow->appendChild($cell);
            }
        }

        $rowNum = 2;
        foreach ($uniqueAssyNumbers as $assyNumber) {
            $rowElem = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
            $rowElem->setAttribute('r', $rowNum);
            $sheetData->appendChild($rowElem);

            $cellB = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
            $cellB->setAttribute('r', 'B' . $rowNum);
            $this->xmlHelper->setCellValue($dom, $cellB, $assyNumber);
            $rowElem->appendChild($cellB);

            $assyQtyMap = $srQtyMap[$assyNumber] ?? [];
            foreach ($weekColumnIndices as $weekId => $colIdx) {
                $qty = $assyQtyMap[$weekId] ?? 0;
                $colLetter = Coordinate::stringFromColumnIndex($colIdx);

                $cell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
                $cell->setAttribute('r', $colLetter . $rowNum);
                $this->xmlHelper->setCellValue($dom, $cell, $qty);
                $rowElem->appendChild($cell);
            }

            $rowNum++;
        }

        $this->xmlHelper->saveSheetDOM($zip, $sheet1Path, $dom);
    }

    /**
     * Isi sheet sekunder dengan mereplikasi baris rumus.
     */
    private function populateSecondarySheets(\ZipArchive $zip, ExportStrategyInterface $strategy, array $sheetPathMap, int $numActualRows, int $startRow, array $sharedStrings): void
    {
        // Loop untuk setiap sheet sekunder (AS400, down rate, dll.) yang dikonfigurasi dalam strategi
        foreach ($strategy->getSheets() as $sheetName => $secStartRow) {
            // Lewati jika sheet ini adalah sheet utama (sudah diproses sebelumnya)
            if ($sheetName === $strategy->getMainSheet()) continue;

            $secPath = $sheetPathMap[$sheetName] ?? null;
            if (!$secPath) continue;

            // Muat DOM XML untuk sheet sekunder ini
            $secDom = $this->xmlHelper->getSheetDOM($zip, $secPath);
            $secSheetData = $secDom->getElementsByTagName('sheetData')->item(0);
            if (!$secSheetData) continue;

            // Cari baris terakhir di template sheet sekunder ini
            $secEndRow = $this->xmlHelper->findEndRowDOM($secDom, $secStartRow, $sharedStrings);
            $secNumTemplateRows = $secEndRow - $secStartRow + 1;

            // Geser baris ke bawah jika baris data baru lebih banyak dari baris template bawaan
            $secShiftOffset = $numActualRows - $secNumTemplateRows;
            if ($secShiftOffset > 0) {
                $this->xmlHelper->shiftRowsDown($secDom, $secSheetData, $secEndRow, $secShiftOffset, $startRow, $secEndRow);
            }

            $offset = $startRow - $secStartRow;

            // Ambil cache cell dan formula dari baris data pertama (secStartRow) untuk direplikasi ke bawah
            $secSrcRow = $this->xmlHelper->getOrCreateRow($secDom, $secSheetData, $secStartRow);
            $secRowCells = [];
            foreach ($secSrcRow->getElementsByTagName('c') as $c) {
                $ref = $c->getAttribute('r');
                $col = preg_replace('/\d+/', '', $ref);
                $val = $this->xmlHelper->getCellRawValueOrFormula($c, $sharedStrings);

                // Bersihkan rumus-rumus agar referensi kolom/baris awal aman
                if (is_string($val) && strpos($val, '=') === 0) {
                    $val = $this->xmlHelper->cleanFormulaReferences($val);
                    $this->xmlHelper->setCellValue($secDom, $c, $val);
                }

                $secRowCells[$col] = [
                    's' => $c->getAttribute('s'),
                    't' => $c->getAttribute('t'),
                    'val' => $val,
                ];
            }

            // Tulis dan duplikasi baris rumus untuk baris-baris berikutnya
            for ($i = 1; $i < $numActualRows; $i++) {
                $r = $secStartRow + $i;
                $rowElem = $this->xmlHelper->getOrCreateRow($secDom, $secSheetData, $r);

                // Salin atribut tinggi/style dari baris sumber
                $this->xmlHelper->copyRowAttributes($secSrcRow, $rowElem);

                // Replikasi sel demi sel beserta perubahan referensi nomor baris di dalam rumusnya
                foreach ($secRowCells as $col => $cellInfo) {
                    $cell = $this->xmlHelper->getOrCreateCell($secDom, $rowElem, $col . $r);
                    if ($cellInfo['s'] !== '') {
                        $cell->setAttribute('s', $cellInfo['s']);
                    }

                    $val = $cellInfo['val'];
                    // Sesuaikan referensi baris dalam formula jika sel berisi formula Excel (misal: =A9 menjadi =A10, =A11 dst.)
                    if (is_string($val) && strpos($val, '=') === 0) {
                        $val = preg_replace('/([A-Z]+)' . $startRow . '\b/i', '${1}' . ($r + $offset), $val);
                        if ($startRow !== $secStartRow) {
                            $val = preg_replace('/([A-Z]+)' . $secStartRow . '\b/i', '${1}' . $r, $val);
                        }
                    }
                    $this->xmlHelper->setCellValue($secDom, $cell, $val);
                }
            }

            // Bersihkan baris template yang berlebih di bawah jika baris data aktual lebih sedikit
            $this->clearExcessTemplateRows($secDom, $secSheetData, $secStartRow, $numActualRows, $secEndRow);

            // Bersihkan reference mergeCells dan hapus rumus kosong yang rusak
            $this->xmlHelper->removeMergeCellsDOM($secDom, $secStartRow);
            $this->xmlHelper->cleanSheetFormulas($secDom);
            $this->xmlHelper->saveSheetDOM($zip, $secPath, $secDom);
        }
    }

    /**
     * Impor jumlah dan detail SPP dari file Excel.
     */
    public function importFromExcel(string $filePath, string $customerCode, array $months): array
    {
        // 1. Tentukan strategi ekspor/impor berdasarkan kode customer
        $strategy = $this->strategyFactory->make($customerCode);
        if (!$strategy || !file_exists($filePath)) {
            return [];
        }

        // 2. Gunakan PhpSpreadsheet Reader untuk membuka file Excel (mode read-data-only agar cepat & hemat memori)
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheetByName($strategy->getMainSheet());
        if (!$sheet) {
            return [];
        }

        // 3. Petakan indeks kolom berdasarkan nama kolom konfigurasi strategi
        $colIndices = $this->resolveImportColumnIndices($strategy);
        if (!isset($colIndices['assy_number'])) {
            return [];
        }

        $startRow = $strategy->getStartRow();
        $highestRow = $sheet->getHighestRow();
        $importedData = [];

        // 4. Lakukan loop baris demi baris dari baris awal data hingga baris terakhir
        $emptyRowCount = 0;
        for ($r = $startRow; $r <= $highestRow; $r++) {
            $assyNumberVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['assy_number'], $r]));
            $assyNumber = trim((string)$assyNumberVal);
            
            // OPTIMASI: Jika menemukan baris kosong berturut-turut hingga 10 baris, hentikan proses parsing (break)
            // Ini mencegah PhpSpreadsheet terus melakukan loop pada baris kosong ber-style di Excel
            if ($assyNumber === '') {
                $emptyRowCount++;
                if ($emptyRowCount >= 10) {
                    break;
                }
                continue;
            }
            $emptyRowCount = 0;

            // Lewati baris jika terdapat kata 'total' (biasanya baris ringkasan di bawah Excel)
            if (strpos(strtolower($assyNumber), 'total') !== false) {
                continue;
            }

            // 5. Uraikan data baris tersebut (metadata dan kuantitas bulanan)
            $importedData[$assyNumber] = $this->importRowData($sheet, $strategy, $colIndices, $months, $r, $assyNumber);
        }

        return $importedData;
    }

    /**
     * Selesaikan pemetaan kolom menjadi indeks numerik aktual.
     */
    private function resolveImportColumnIndices(ExportStrategyInterface $strategy): array
    {
        $colIndices = [];
        foreach ($strategy->getMapping() as $colIdx => $field) {
            if ($field === 'assy_number') $colIndices['assy_number'] = $colIdx;
            if ($field === 'pattern') $colIndices['pattern'] = $colIdx;
            if ($field === 'carline') $colIndices['carline'] = $colIdx;
            if ($field === 'level') $colIndices['level'] = $colIdx;
            if ($field === 'assy_code') $colIndices['assy_code'] = $colIdx;
            if ($field === 'std_pack') $colIndices['std_pack'] = $colIdx;
        }
        return $colIndices;
    }

    /**
     * Uraikan nilai baris untuk metadata dan kuantitas.
     */
    private function importRowData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, ExportStrategyInterface $strategy, array $colIndices, array $months, int $r, string $assyNumber): array
    {
        $pattern = isset($colIndices['pattern']) ? trim((string)$this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['pattern'], $r]))) : '';
        $carline = isset($colIndices['carline']) ? trim((string)$this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['carline'], $r]))) : '';
        $level = isset($colIndices['level']) ? trim((string)$this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['level'], $r]))) : '';
        $assyCode = isset($colIndices['assy_code']) ? trim((string)$this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['assy_code'], $r]))) : '';

        $stdPack = null;
        if (isset($colIndices['std_pack'])) {
            $stdPackVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$colIndices['std_pack'], $r]));
            if ($stdPackVal !== null && $stdPackVal !== '') {
                $stdPack = (int)$stdPackVal;
            }
        }

        $umh = null;
        $umhCol = $strategy->getUmhCol();
        if ($umhCol > 0) {
            $umhVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$umhCol, $r]));
            if ($umhVal !== null && $umhVal !== '') {
                $umhValNormalized = str_replace(',', '.', (string)$umhVal);
                $umh = (float)$umhValNormalized;
            }
        }

        $monthsData = $this->importRowMonthsData($sheet, $strategy, $months, $r);

        return [
            'pattern' => $pattern,
            'carline' => $carline,
            'level' => $level,
            'assy_code' => $assyCode,
            'std_pack' => $stdPack,
            'umh' => $umh,
            'months' => $monthsData,
        ];
    }

    /**
     * Impor kolom bulanan BAL, DEL, PROD untuk setiap baris.
     */
    private function importRowMonthsData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, ExportStrategyInterface $strategy, array $months, int $r): array
    {
        $monthStartCol = $strategy->getMonthStartCol();
        $monthsData = [];

        foreach ($months as $idx => $m) {
            $periodKey = $m['period'];

            $balCol = $monthStartCol + $idx * 3;
            $delCol = $monthStartCol + $idx * 3 + 1;
            $prodCol = $monthStartCol + $idx * 3 + 2;

            $balVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$balCol, $r]));
            $delVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$delCol, $r]));
            $prodVal = $this->getCellValueSafePhpSpreadsheet($sheet->getCell([$prodCol, $r]));

            $monthsData[$periodKey] = [
                'bal' => ($balVal !== null && $balVal !== '') ? (int)$balVal : 0,
                'del' => ($delVal !== null && $delVal !== '') ? (int)$delVal : 0,
                'prod' => ($prodVal !== null && $prodVal !== '') ? (int)$prodVal : 0,
            ];
        }

        return $monthsData;
    }

    private function getCellValueSafePhpSpreadsheet(?\PhpOffice\PhpSpreadsheet\Cell\Cell $cell)
    {
        // Jika sel bernilai null, kembalikan string kosong
        if (!$cell) {
            return '';
        }
        
        // Jika isi sel berupa formula/rumus Excel, ambil nilai hasil perhitungannya
        if ($cell->isFormula()) {
            try {
                // Coba ambil nilai kalkulasi lama yang tersimpan terlebih dahulu (lebih cepat)
                $val = $cell->getOldCalculatedValue();
                if ($val === null || $val === '') {
                    $val = $cell->getCalculatedValue();
                }
                return $val;
            } catch (\Throwable $e) {
                try {
                    // Jika gagal, hitung ulang nilai formula secara dinamis
                    return $cell->getCalculatedValue();
                } catch (\Throwable $ex) {
                    return null;
                }
            }
        }
        
        // Jika sel biasa, kembalikan nilai mentah (raw value) sel tersebut
        return $cell->getValue();
    }
}
