<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SummaryExport implements FromArray, WithStyles, WithColumnWidths, WithCustomStartCell
{
    protected Collection $data;

    // ── Fixed Left Columns ────────────────────────────────────────────────
    const COLOR_HEADER_FIXED    = 'FF1D4D2A'; // dark forest       → col A-C header rows 1-4
    const COLOR_LEFT_BG         = 'FF2D5A3D'; // slate green       → col A-C data rows
    const COLOR_LEFT_QTY_BG     = 'FF3A6B4E'; // muted green       → "QTY" label col

    // ── Period Header Sub-rows ────────────────────────────────────────────
    const COLOR_HEADER_ETD      = 'FF1D6F42'; // forest green      → ETD row
    const COLOR_HEADER_ETA      = 'FF2E9E5E'; // medium green      → ETA row
    const COLOR_HEADER_WEEK     = 'FF237A50'; // between ETD/ETA   → week row

    // ── Data Rows ─────────────────────────────────────────────────────────
    const COLOR_ROW_ODD         = 'FFEAF5EF'; // soft mint
    const COLOR_ROW_EVEN        = 'FFC6E8D4'; // powder green

    // ── Text ──────────────────────────────────────────────────────────────
    const COLOR_TEXT_WHITE      = 'FFFFFFFF';
    const COLOR_TEXT_MUTED      = 'FFAABFB0';
    const COLOR_TEXT_VALUE      = 'FF0D4F2C';
    const COLOR_TEXT_QTY_LABEL  = 'FFD4EDE0';

    // ── Borders ───────────────────────────────────────────────────────────
    const COLOR_BORDER_LIGHT    = 'FF8FC9A8';
    const COLOR_BORDER_HEADER   = 'FF145233';

    // ── FIRM / FORECAST group header colors ───────────────────────────────
    const COLOR_FIRM_HEADER     = 'FFFFFF00'; // yellow
    const COLOR_FORE_HEADER     = 'FFF4B183'; // orange

    // ── FIRM Column Custom Colors (Yellow/Gold/Amber Theme) ────────────────
    const COLOR_FIRM_MONTH_BG   = 'FFFFF9C4'; // soft yellow
    const COLOR_FIRM_ETD_BG     = 'FFFFEE58'; // medium yellow
    const COLOR_FIRM_WEEK_BG    = 'FFFBC02D'; // dark yellow
    const COLOR_FIRM_TOT_BG     = 'FFF57F17'; // orange/amber for TOT column
    const COLOR_FIRM_DATA_ODD   = 'FFFFFDE7'; // extremely soft yellow
    const COLOR_FIRM_DATA_EVEN  = 'FFFFF9C4'; // soft yellow
    const COLOR_FIRM_TEXT       = 'FF5D4037'; // brown text

    // ── TOT Column ────────────────────────────────────────────────────────
    const COLOR_TOT_HEADER_BG   = 'FF5A8F75'; // muted green for TOT sub-header rows 2,3
    const COLOR_TOT_WEEK_BG     = 'FF2D5A3D'; // darker forest green for TOT week row
    const COLOR_TOT_DATA_ODD    = 'FFC6E8D4'; // powder green to match data odd
    const COLOR_TOT_DATA_EVEN   = 'FFB0D9C4'; // deeper powder green for harmony
    const COLOR_TOT_TEXT        = 'FF0D4F2C'; // dark green text matching VALUE color

    // ── GRAND TOTAL Row ───────────────────────────────────────────────────
    const COLOR_TOTAL_BG        = 'FF0F3320';
    const COLOR_TOTAL_BORDER    = 'FF2E7D52';
    const COLOR_TOTAL_TOP       = 'FF4CAF78';

    /** @var array|null Cached periods with month totals */
    private ?array $_cachedPeriods = null;

    protected mixed $runningWeek;
    protected ?int $periodsPerMonth;

    public function __construct(Collection $data, mixed $runningWeek = null, ?int $periodsPerMonth = null)
    {
        $this->data = $data;
        $this->runningWeek = $runningWeek;
        $this->periodsPerMonth = $periodsPerMonth;
    }

    private function isPeriodRunningWeek(array $period): bool
    {
        if (!$this->runningWeek || empty($period['etd_raw'])) {
            return false;
        }

        $etd = \Carbon\Carbon::parse($period['etd_raw'])->startOfDay();
        $start = \Carbon\Carbon::parse($this->runningWeek->week_start)->startOfDay();
        $end = \Carbon\Carbon::parse($this->runningWeek->end_date)->startOfDay();

        return $etd->gte($start) && $etd->lte($end);
    }

    // ── 1. Array ──────────────────────────────────────────────────────────
    public function array(): array
    {
        $periods = $this->getPeriodsWithMonthTotals();
        $rows    = [];

        // ── Header Row 1: Group labels (FIRM / FORECAST per month group)
        $row1 = ['NO', 'ASSY NO', 'Type'];
        foreach ($periods as $period) {
            // All columns filled via merges in styles() for group headers
            $row1[] = '';
        }
        $rows[] = $row1;

        // ── Header Row 2: ETD / TOT
        $row2 = ['', '', 'Month'];
        foreach ($periods as $period) {
            $row2[] = ($period['week'] === 'TOT') ? 'TOT' : $period['month_label'];
        }
        $rows[] = $row2;

        // ── Header Row 3: ETA
        $row3 = ['', '', 'ETD'];
        foreach ($periods as $period) {
            $row3[] = ($period['week'] === 'TOT') ? '' : $period['etd'];
        }
        $rows[] = $row3;

        // ── Header Row 4: Week
        $row4 = ['', '', 'Week'];
        foreach ($periods as $period) {
            $row4[] = ($period['week'] === 'TOT') ? 'TOT' : $period['week'];
        }
        $rows[] = $row4;

        // ── Data Rows
        $grouped = $this->data->groupBy('assy_number');
        $index   = 0;

        foreach ($grouped as $assyNumber => $items) {
            $index++;
            $dataRow = [$index, $assyNumber, 'QTY'];

            foreach ($periods as $period) {
                if ($period['week'] === 'TOT') {
                    // Monthly subtotal for this part
                    $monthKey = $period['month'];
                    $typeKey  = $period['type'];
                    $total    = 0;
                    foreach ($items as $item) {
                        $itemMonth = $this->monthKey($item);
                        $itemType  = strtoupper((string) ($item->order_type ?? 'FORECAST'));
                        if ($itemMonth === $monthKey && $itemType === $typeKey) {
                            $total += (int)($item->qty ?? 0);
                        }
                    }
                    $dataRow[] = ($total === 0) ? '0' : $total;
                } else {
                    $key   = $period['key'];
                    $total = 0;
                    foreach ($items as $item) {
                        $itemKey = $this->periodKey($item);
                        if ($itemKey === $key) {
                            $total += (int)($item->qty ?? 0);
                        }
                    }
                    $dataRow[] = ($total === 0) ? '0' : $total;
                }
            }

            $rows[] = $dataRow;
        }

        // ── Grand Total Row
        $totalRow = ['', 'GRAND TOTAL', ''];
        foreach ($periods as $period) {
            if ($period['week'] === 'TOT') {
                $monthKey = $period['month'];
                $typeKey  = $period['type'];
                $total    = 0;
                foreach ($this->data as $item) {
                    $itemMonth = $this->monthKey($item);
                    $itemType  = strtoupper((string) ($item->order_type ?? 'FORECAST'));
                    if ($itemMonth === $monthKey && $itemType === $typeKey) {
                        $total += (int)($item->qty ?? 0);
                    }
                }
                $totalRow[] = ($total === 0) ? '0' : $total;
            } else {
                $key   = $period['key'];
                $total = 0;
                foreach ($this->data as $item) {
                    $itemKey = $this->periodKey($item);
                    if ($itemKey === $key) {
                        $total += (int)($item->qty ?? 0);
                    }
                }
                $totalRow[] = ($total === 0) ? '0' : $total;
            }
        }
        $rows[] = $totalRow;

        return $rows;
    }

    // ── 2. Column widths ──────────────────────────────────────────────────
    public function columnWidths(): array
    {
        $widths  = ['A' => 5, 'B' => 18, 'C' => 8];
        $periods = $this->getPeriodsWithMonthTotals();

        foreach ($periods as $i => $period) {
            $col           = Coordinate::stringFromColumnIndex($i + 4);
            $widths[$col]  = ($period['week'] === 'TOT') ? 7 : 11;
        }

        return $widths;
    }

    // ── 3. Start cell ─────────────────────────────────────────────────────
    public function startCell(): string
    {
        return 'A1';
    }

    // ── 4. Styles ─────────────────────────────────────────────────────────
    public function styles(Worksheet $sheet)
    {
        $periods      = $this->getPeriodsWithMonthTotals();
        $lastColIdx   = 3 + count($periods);
        $lastCol      = Coordinate::stringFromColumnIndex($lastColIdx);
        $totalRows    = $sheet->getHighestRow();
        $dataLastRow  = $totalRows - 1;

        // ── Row heights
        for ($r = 1; $r <= 4; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(16);
        }
        for ($r = 5; $r <= $totalRows; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(15);
        }

        // ── Freeze pane
        $sheet->freezePane('D5');

        // ── Merge fixed cols A-B across header rows
        $sheet->mergeCells('A1:A4');
        $sheet->mergeCells('B1:B4');

        // ── Build month-group metadata for merges & coloring
        // groups: [ ['type'=>'FIRM|FORECAST', 'month'=>'2025-02', 'startCol'=>3, 'endCol'=>8, 'totCol'=>8], ... ]
        $monthGroups = $this->buildMonthGroups($periods);

        // ── Row 1: Group header merges and colors
        foreach ($monthGroups as $group) {
            $startCol = Coordinate::stringFromColumnIndex($group['startCol']);
            $endCol   = Coordinate::stringFromColumnIndex($group['endCol']);

            if ($group['startCol'] <= $group['endCol']) {
                $sheet->mergeCells("{$startCol}1:{$endCol}1");
            }

            $groupLabel = $group['type'];
            $sheet->getCell("{$startCol}1")->setValue($groupLabel);

            $bgColor = ($group['type'] === 'FIRM')
                ? self::COLOR_FIRM_HEADER
                : self::COLOR_FORE_HEADER;

            $sheet->getStyle("{$startCol}1:{$endCol}1")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'font'      => ['bold' => true, 'color' => ['argb' => 'FF000000'], 'name' => 'Arial', 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
            ]);
        }

        // ── Fixed cols A-C header (rows 1-4)
        $sheet->getStyle('A1:C4')->applyFromArray([
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_FIXED]],
            'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_TEXT_WHITE], 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
        ]);

        // ── Set headers in column C (row 2-4) — labels that aren't set by data array
        $sheet->getCell('C2')->setValue('Month');
        $sheet->getCell('C3')->setValue('ETD');
        $sheet->getCell('C4')->setValue('Week');

        // ── Period column headers (rows 2-4)
        foreach ($periods as $pIdx => $period) {
            $colIdx = 4 + $pIdx;
            $col    = Coordinate::stringFromColumnIndex($colIdx);
            $isTot  = ($period['week'] === 'TOT');
            $isFirm = ($period['type'] === 'FIRM');

            // Row 2 — Month: value and merge are handled by the monthSpanGroups block below.
            // We only apply base styling here as a fallback for single-column months.
            $bgMonth    = $isTot ? self::COLOR_TOT_HEADER_BG : ($isFirm ? 'FFFFF9C4' : 'FFFCE8D5');
            $colorMonth = $isTot ? self::COLOR_TEXT_WHITE     : ($isFirm ? 'FF5D4037' : 'FF6D3A00');

            $sheet->getStyle("{$col}2")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgMonth]],
                'font'      => ['bold' => true, 'color' => ['argb' => $colorMonth], 'name' => 'Arial', 'size' => 8],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
            ]);

            // Row 3 — ETD
            $bgEtd = $isTot ? self::COLOR_TOT_HEADER_BG : self::COLOR_HEADER_ETD;
            $colorEtd = self::COLOR_TEXT_WHITE;

            if (!$isTot && $this->isPeriodRunningWeek($period)) {
                $bgEtd = 'FFFF0000'; // Red
                $colorEtd = 'FF000000'; // Black
            }

            $sheet->getStyle("{$col}3")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgEtd]],
                'font'      => ['bold' => true, 'color' => ['argb' => $colorEtd], 'name' => 'Arial', 'size' => 8],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
            ]);
            if (!$isTot) {
                $sheet->getCell("{$col}3")->setValue($period['etd']);
            }

            // Row 4 — Week / TOT
            $bgWeek = $isTot ? self::COLOR_TOT_WEEK_BG : self::COLOR_HEADER_WEEK;
            $colorWeek = self::COLOR_TEXT_WHITE;

            $weekVal = ($period['week'] === 'TOT') ? 'TOT' : ($period['week'] ?: '');
            $sheet->getCell("{$col}4")->setValue($weekVal);
            $sheet->getStyle("{$col}4")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgWeek]],
                'font'      => ['bold' => true, 'color' => ['argb' => $colorWeek], 'name' => 'Arial', 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
            ]);

            // Merge rows 2-4 for TOT column (no ETD/ETA/WEEK content)
            if ($isTot) {
                $sheet->mergeCells("{$col}2:{$col}4");
            }
        }

        // ── Row 2: Merge month label across all week columns in that month group
        // Build month-span groups (exclude TOT columns since they are already merged rows 2-4)
        $monthSpanGroups = [];
        $currentSpan = null;

        foreach ($periods as $pIdx => $period) {
            if ($period['week'] === 'TOT') {
                // TOT column is already merged vertically — close current span
                if ($currentSpan !== null) {
                    $monthSpanGroups[] = $currentSpan;
                    $currentSpan = null;
                }
                continue;
            }

            $spanKey = ($period['type'] ?? 'FORECAST') . '|' . $period['month'];
            $colIdx  = 4 + $pIdx;

            if ($currentSpan === null || $currentSpan['key'] !== $spanKey) {
                if ($currentSpan !== null) {
                    $monthSpanGroups[] = $currentSpan;
                }
                $currentSpan = [
                    'key'        => $spanKey,
                    'label'      => $period['month_label'] ?? '',
                    'startCol'   => $colIdx,
                    'endCol'     => $colIdx,
                    'type'       => $period['type'] ?? 'FORECAST',
                ];
            } else {
                $currentSpan['endCol'] = $colIdx;
            }
        }
        if ($currentSpan !== null) {
            $monthSpanGroups[] = $currentSpan;
        }

        foreach ($monthSpanGroups as $span) {
            $startCol = Coordinate::stringFromColumnIndex($span['startCol']);
            $endCol   = Coordinate::stringFromColumnIndex($span['endCol']);
            $isFirm   = ($span['type'] === 'FIRM');

            // Merge + style the month label across the span
            if ($span['startCol'] < $span['endCol']) {
                $sheet->mergeCells("{$startCol}2:{$endCol}2");
            }

            $sheet->getCell("{$startCol}2")->setValue($span['label']);

            $bgColor   = $isFirm ? 'FFFFF9C4' : 'FFFCE8D5'; // soft yellow FIRM / soft orange FORECAST
            $textColor = $isFirm ? 'FF5D4037' : 'FF6D3A00'; // brown FIRM / dark orange FORECAST

            $sheet->getStyle("{$startCol}2:{$endCol}2")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'font'      => ['bold' => true, 'color' => ['argb' => $textColor], 'name' => 'Arial', 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_HEADER]]],
            ]);
        }

        // ── Data rows — fixed left cols A-C
        for ($r = 5; $r <= $dataLastRow; $r++) {
            $sheet->getStyle("A{$r}:C{$r}")->applyFromArray([
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_LEFT_BG]],
                'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_TEXT_WHITE], 'name' => 'Arial', 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']]],
            ]);

            // Assy number: left-aligned with indent
            $sheet->getStyle("B{$r}")->getAlignment()
                  ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                  ->setIndent(1);

            // QTY cell: lighter bg + smaller muted font
            $sheet->getStyle("C{$r}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_LEFT_QTY_BG]],
                'font' => ['bold' => false, 'color' => ['argb' => self::COLOR_TEXT_QTY_LABEL], 'name' => 'Arial', 'size' => 8],
            ]);
        }

        // ── Data rows — period columns (alternating bg + TOT col differentiated)
        for ($r = 5; $r <= $dataLastRow; $r++) {
            $isOdd = ($r % 2 === 1);

            foreach ($periods as $pIdx => $period) {
                $colIdx    = 4 + $pIdx;
                $col       = Coordinate::stringFromColumnIndex($colIdx);
                $cellCoord = "{$col}{$r}";
                $isTot     = ($period['week'] === 'TOT');

                if ($isTot) {
                    $bgColor = $isOdd ? self::COLOR_TOT_DATA_ODD : self::COLOR_TOT_DATA_EVEN;

                    $sheet->getStyle($cellCoord)->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                        'font'      => [
                            'bold' => true,
                            'color' => ['argb' => self::COLOR_TEXT_WHITE],
                            'name' => 'Arial',
                            'size' => 9
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'numberFormat' => ['formatCode' => '0'],
                        'borders'   => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => 'FF5A8F75']],
                            'left'       => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF2D5A3D']],
                            'right'      => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF2D5A3D']],
                        ],
                    ]);
                } else {
                    $bgColor = $isOdd ? self::COLOR_ROW_ODD : self::COLOR_ROW_EVEN;

                    $sheet->getStyle($cellCoord)->applyFromArray([
                        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                        'font'      => [
                            'name' => 'Arial',
                            'size' => 9,
                            'bold' => false,
                            'color' => ['argb' => self::COLOR_TEXT_VALUE]
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                        'numberFormat' => ['formatCode' => '0'],
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER_LIGHT]]],
                    ]);
                }

                // Zero / non-zero coloring
                $cellVal = $sheet->getCell($cellCoord)->getValue();
                if ($isTot) {
                    $fontColor = ($cellVal === 0 || $cellVal === '0') ? 'FF7AA68A' : self::COLOR_TEXT_WHITE;
                    $sheet->getStyle($cellCoord)->getFont()->getColor()->setARGB($fontColor);
                    $sheet->getStyle($cellCoord)->getFont()->setBold($cellVal !== 0 && $cellVal !== '0');
                } else {
                    if ($cellVal === 0 || $cellVal === '0') {
                        $sheet->getStyle($cellCoord)->getFont()->getColor()->setARGB(self::COLOR_TEXT_MUTED);
                        $sheet->getStyle($cellCoord)->getFont()->setBold(false);
                    } else {
                        $sheet->getStyle($cellCoord)->getFont()
                              ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(self::COLOR_TEXT_VALUE));
                        $sheet->getStyle($cellCoord)->getFont()->setBold(true);
                    }
                }
            }
        }

        // ── GRAND TOTAL row
        $sheet->getStyle("A{$totalRows}:{$lastCol}{$totalRows}")->applyFromArray([
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_TOTAL_BG]],
            'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_TEXT_WHITE], 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '0'],
            'borders'   => [
                'top'        => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_TOTAL_TOP]],
                'bottom'     => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_TOTAL_BG]],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => self::COLOR_TOTAL_BORDER]],
            ],
        ]);

        // Grand total label: left-aligned
        $sheet->getStyle("B{$totalRows}")->getAlignment()
              ->setHorizontal(Alignment::HORIZONTAL_LEFT)
              ->setIndent(1);

        // ── Outer border
        $sheet->getStyle("A1:{$lastCol}{$totalRows}")->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E2A3A']]],
        ]);

        return [];
    }

    // ── Helper: cached periods ────────────────────────────────────────────
    protected function getPeriodsWithMonthTotals(): array
    {
        if ($this->_cachedPeriods !== null) {
            return $this->_cachedPeriods;
        }

        // 1. Group unique periods by (order_type → month)
        $byTypeMonth = []; // ['FIRM' => ['2025-02' => [...periods...]], 'FORECAST' => [...]]

        foreach ($this->data as $item) {
            $type  = strtoupper((string) ($item->order_type ?? 'FORECAST'));
            $month = $this->monthKey($item);
            $week  = $this->normalizeWeek($item->week);
            $key   = $this->periodKey($item);

            $byTypeMonth[$type][$month][$key] = [
                'key'         => $key,
                'etd'         => $item->etd ? date('j-M', strtotime($item->etd)) : '',
                'eta'         => $item->eta ? date('n/j', strtotime($item->eta)) : '',
                'week'        => $week,
                'etd_raw'     => $item->etd,
                'eta_raw'     => $item->eta,
                'month'       => $month,
                'month_label' => $this->monthLabelWithYear($month, $item->etd),
                'type'        => $type,
            ];
        }

        // Sort types: FIRM first, FORECAST after
        $sortedTypes = array_filter(['FIRM', 'FORECAST'], fn($t) => isset($byTypeMonth[$t]));

        $result = [];

        foreach ($sortedTypes as $type) {
            $months = $byTypeMonth[$type];
            uksort($months, function($a, $b) use ($months) {
                $minA = null;
                foreach ($months[$a] as $p) {
                    $d = (!empty($p['etd_raw'])) ? $p['etd_raw'] : ((!empty($p['eta_raw'])) ? $p['eta_raw'] : null);
                    if ($d) {
                        $dVal = ($d instanceof \Carbon\Carbon || $d instanceof \DateTimeInterface) 
                            ? $d->format('Y-m-d') 
                            : (string) $d;
                        if ($minA === null || $dVal < $minA) {
                            $minA = $dVal;
                        }
                    }
                }
                $minB = null;
                foreach ($months[$b] as $p) {
                    $d = (!empty($p['etd_raw'])) ? $p['etd_raw'] : ((!empty($p['eta_raw'])) ? $p['eta_raw'] : null);
                    if ($d) {
                        $dVal = ($d instanceof \Carbon\Carbon || $d instanceof \DateTimeInterface) 
                            ? $d->format('Y-m-d') 
                            : (string) $d;
                        if ($minB === null || $dVal < $minB) {
                            $minB = $dVal;
                        }
                    }
                }

                if ($minA !== null && $minB !== null) {
                    return strcmp($minA, $minB);
                }

                if ($minA !== null) return -1;
                if ($minB !== null) return 1;

                $aliases = [
                    'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
                    'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12
                ];
                $aliasA = $aliases[strtoupper(substr($a, 0, 3))] ?? 99;
                $aliasB = $aliases[strtoupper(substr($b, 0, 3))] ?? 99;
                return $aliasA <=> $aliasB;
            });

            foreach ($months as $month => $periods) {
                // Sort weeks inside month by ETD date
                usort($periods, fn($a, $b) => strcmp($a['etd_raw'], $b['etd_raw']));

                // Keep every actual ETD/ETA period. Fill later slots so the sheet
                // still has the customer-specific monthly shape.
                $weekCount = 0;
                foreach ($periods as $period) {
                    $result[]  = $period;
                    $weekCount++;
                }
                while ($weekCount < $this->periodsPerMonth()) {
                    $weekNum = $weekCount + 1;
                    $result[] = [
                        'week'    => '',
                        'month'   => $month,
                        'type'    => $type,
                        'key'     => "pad|{$type}|{$month}|{$weekNum}",
                        'etd'     => '',
                        'eta'     => '',
                        'etd_raw' => '',
                        'eta_raw' => '',
                        'month_label' => $this->monthLabelWithYear($month, null),
                    ];
                    $weekCount++;
                }

                // TOT column after the weeks of this month
                $result[] = [
                    'week'    => 'TOT',
                    'month'   => $month,
                    'type'    => $type,
                    'key'     => "tot|{$type}|{$month}",
                    'etd'     => '',
                    'eta'     => '',
                    'etd_raw' => '',
                    'eta_raw' => '',
                    'month_label' => $this->monthLabel($month),
                ];
            }
        }

        $this->_cachedPeriods = $result;
        return $result;
    }

    // ── Helper: build month groups for Row-1 merges ───────────────────────
    /**
     * Returns array of groups, each group spanning one (type, month) block:
     * ['type' => 'FIRM', 'month' => '2025-02', 'startCol' => 4, 'endCol' => 9]
     */
    private function periodKey(object $item): string
    {
        return implode('|', [
            strtoupper((string) ($item->order_type ?? 'FORECAST')),
            $item->etd ?? '',
            $this->normalizeWeek($item->week ?? ''),
        ]);
    }

    private function monthKey(object $item): string
    {
        $month = trim((string) ($item->month ?? ''));
        if (preg_match('/^(\d{4})-(\d{1,2})/', $month, $matches)) {
            return sprintf('%04d-%02d', (int) $matches[1], (int) $matches[2]);
        }

        $alias = strtoupper(substr($month, 0, 3));
        if ($alias !== '') {
            return $alias; // Group by month field alias (MAR, APR, etc.) — keeps data in correct month
        }

        if (!empty($item->etd)) {
            $timestamp = strtotime((string) $item->etd);
            if ($timestamp !== false) {
                return date('Y-m', $timestamp);
            }
        }

        return 'unknown';
    }

    /**
     * Compute month label including year suffix (e.g. 'Mar-26').
     * Uses $etd to extract the year when $month is a 3-letter alias ('MAR').
     * Falls back to monthLabel() when ETD is unavailable.
     */
    private function monthLabelWithYear(string $month, mixed $etd = null): string
    {
        $base = $this->monthLabel($month); // e.g. 'Mar' or 'Mar-26' (when month is 'YYYY-MM')

        // Already has year (e.g. month was '2026-03' → monthLabel returns 'Mar-26')
        if (str_contains($base, '-')) {
            return $base;
        }

        // Alias format (e.g. 'MAR') — get year from ETD
        if (!empty($etd)) {
            $ts = strtotime((string) $etd);
            if ($ts !== false) {
                return $base . '-' . date('y', $ts);
            }
        }

        return $base; // fallback: 'Mar' if no ETD available
    }

    private function monthLabel(string $month): string
    {
        $names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $aliases = [
            'JAN' => 0, 'FEB' => 1, 'MAR' => 2, 'APR' => 3, 'MAY' => 4, 'JUN' => 5,
            'JUL' => 6, 'AUG' => 7, 'SEP' => 8, 'OCT' => 9, 'NOV' => 10, 'DEC' => 11,
        ];

        if (preg_match('/^(\d{4})-(\d{1,2})/', $month, $matches)) {
            $monthIndex = max(0, min(11, (int) $matches[2] - 1));
            return $names[$monthIndex] . '-' . substr((string) $matches[1], -2);
        }

        $alias = strtoupper(substr($month, 0, 3));
        if (array_key_exists($alias, $aliases)) {
            return $names[$aliases[$alias]];
        }

        return $month === 'unknown' ? '' : $month;
    }

    // Removed assySppNumber

    private function normalizeWeek(mixed $week): mixed
    {
        if ($week === null || $week === '') {
            return '';
        }

        if ($week === 'TOT') {
            return 'TOT';
        }

        if (is_numeric($week)) {
            return (int) $week;
        }

        if (preg_match('/\d+/', (string) $week, $matches)) {
            return (int) $matches[0];
        }

        return $week;
    }

    protected function periodsPerMonth(): int
    {
        return $this->periodsPerMonth ?? 5;
    }

    protected function buildMonthGroups(array $periods): array
    {
        $groups      = [];
        $currentGroup = null;

        foreach ($periods as $pIdx => $period) {
            $colIdx  = 4 + $pIdx;
            $groupKey = ($period['type'] ?? 'FORECAST') . '|' . $period['month'];

            if ($currentGroup === null || $currentGroup['key'] !== $groupKey) {
                if ($currentGroup !== null) {
                    $groups[] = $currentGroup;
                }
                $currentGroup = [
                    'key'      => $groupKey,
                    'type'     => $period['type'] ?? 'FORECAST',
                    'month'    => $period['month'],
                    'startCol' => $colIdx,
                    'endCol'   => $colIdx,
                ];
            } else {
                $currentGroup['endCol'] = $colIdx;
            }
        }

        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }
}
