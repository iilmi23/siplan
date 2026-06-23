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
use PhpOffice\PhpSpreadsheet\Style\Conditional;

class SPPExport implements FromArray, WithStyles, WithColumnWidths, WithCustomStartCell
{
    protected Collection $records;
    protected array $months;

    public function __construct(Collection $records, array $months)
    {
        $this->records = $records;
        $this->months = $months;
    }

    public function array(): array
    {
        $rows = [];
        
        // Row 1: Main Headers
        $row1 = ['CARLINE', 'PATTERN', 'HARNESS No.', 'LEVEL', 'ASSY CODE', 'Std pack', 'UMH'];
        foreach ($this->months as $month) {
            $row1[] = $month['label'];
            $row1[] = '';
            $row1[] = '';
        }
        $row1[] = 'TOTAL';
        $rows[] = $row1;

        // Row 2: Sub-headers (BAL, DEL, PROD)
        $row2 = ['', '', '', '', '', '', ''];
        foreach ($this->months as $month) {
            $row2[] = 'BAL';
            $row2[] = 'DEL';
            $row2[] = 'PROD';
        }
        $row2[] = '';
        $rows[] = $row2;

        // Data Rows
        $grouped = $this->records->groupBy('assy_number');
        
        // Key items by period to make lookups O(1)
        $groupedKeyed = $grouped->map(function ($items) {
            return $items->keyBy('period');
        });

        // Pre-initialize month totals structure
        $totalsPerPeriod = [];
        foreach ($this->months as $month) {
            $totalsPerPeriod[$month['period']] = [
                'bal' => 0,
                'del' => 0,
                'prod' => 0,
            ];
        }
        $grandTotal = 0;

        foreach ($grouped as $assyNumber => $items) {
            $first = $items->first();
            $keyed = $groupedKeyed[$assyNumber];
            
            $row = [
                $first->carline ?? '',
                $first->assy?->pattern ?? ($first->pattern ?? ''),
                $assyNumber,
                $first->level ?? '',
                $first->assy_code ?? '',
                $first->std_pack ?? '',
                $first->umh ?? '',
            ];

            $totalQty = 0;
            foreach ($this->months as $month) {
                $period = $month['period'];
                $item = $keyed->get($period);
                
                $bal = $item ? $item->bal_qty : 0;
                $del = $item ? $item->del_qty : 0;
                $prod = $item ? $item->prod_qty : 0;

                $row[] = $bal;
                $row[] = $del;
                $row[] = $prod;

                $totalQty += $del;

                $totalsPerPeriod[$period]['bal'] += $bal;
                $totalsPerPeriod[$period]['del'] += $del;
                $totalsPerPeriod[$period]['prod'] += $prod;
            }

            $row[] = $totalQty;
            $rows[] = $row;
            $grandTotal += $totalQty;
        }

        // Total Row
        $totalRow = ['TOTAL', '', '', '', '', '', ''];
        foreach ($this->months as $month) {
            $period = $month['period'];
            $totalRow[] = $totalsPerPeriod[$period]['bal'];
            $totalRow[] = $totalsPerPeriod[$period]['del'];
            $totalRow[] = $totalsPerPeriod[$period]['prod'];
        }
        $totalRow[] = $grandTotal;
        $rows[] = $totalRow;

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 12, // CARLINE
            'B' => 18, // PATTERN
            'C' => 18, // HARNESS No.
            'D' => 10, // LEVEL
            'E' => 12, // ASSY CODE
            'F' => 10, // Std pack
            'G' => 10, // UMH
        ];

        // Months BAL, DEL, PROD sub-columns
        $colIdx = 8;
        foreach ($this->months as $month) {
            $col1 = Coordinate::stringFromColumnIndex($colIdx++);
            $col2 = Coordinate::stringFromColumnIndex($colIdx++);
            $col3 = Coordinate::stringFromColumnIndex($colIdx++);
            $widths[$col1] = 8;
            $widths[$col2] = 8;
            $widths[$col3] = 8;
        }

        // TOTAL column
        $widths[Coordinate::stringFromColumnIndex($colIdx)] = 12;

        return $widths;
    }

    public function startCell(): string
    {
        return 'A1';
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIdx = 7 + count($this->months) * 3 + 1;
        $lastCol = Coordinate::stringFromColumnIndex($lastColIdx);
        $totalRows = $sheet->getHighestRow();

        // Headers
        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Merge Main headers for months
        $colIdx = 8;
        foreach ($this->months as $month) {
            $colStart = Coordinate::stringFromColumnIndex($colIdx);
            $colEnd = Coordinate::stringFromColumnIndex($colIdx + 2);
            $sheet->mergeCells("{$colStart}1:{$colEnd}1");
            $colIdx += 3;
        }

        // Merge fixed columns vertically for rows 1-2
        $sheet->mergeCells('A1:A2');
        $sheet->mergeCells('B1:B2');
        $sheet->mergeCells('C1:C2');
        $sheet->mergeCells('D1:D2');
        $sheet->mergeCells('E1:E2');
        $sheet->mergeCells('F1:F2');
        $sheet->mergeCells('G1:G2');
        $sheet->mergeCells("{$lastCol}1:{$lastCol}2");

        // Style fixed headers (Premium Slate/Gray)
        $sheet->getStyle("A1:G2")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF334155']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);

        // Style month headers
        $colIdx = 8;
        foreach ($this->months as $month) {
            $colStart = Coordinate::stringFromColumnIndex($colIdx);
            $colEnd = Coordinate::stringFromColumnIndex($colIdx + 2);
            $bgColor = $month['bucket'] === 'firm' ? 'FFEFEF80' : 'FFFCD34D'; // Warm Gold/Amber for Firm, Orange-Gold for Forecast
            
            $sheet->getStyle("{$colStart}1:{$colEnd}1")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B'], 'name' => 'Arial', 'size' => 9],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);
            
            $sheet->getStyle("{$colStart}2:{$colEnd}2")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF475569']],
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Arial', 'size' => 8],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            ]);
            
            $colIdx += 3;
        }

        // Style Total header
        $sheet->getStyle("{$lastCol}1:{$lastCol}2")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
        ]);

        if ($totalRows > 3) {
            // Apply base styles to the entire data range A3:lastCol(totalRows-1) at once:
            $sheet->getStyle("A3:{$lastCol}" . ($totalRows - 1))->applyFromArray([
                'font' => ['name' => 'Arial', 'size' => 9, 'color' => ['argb' => 'FF334155']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);

            // Metadata columns (A-G) are centered
            $sheet->getStyle("A3:G" . ($totalRows - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Column C (Harness No) is left aligned, bold, dark text
            $sheet->getStyle("C3:C" . ($totalRows - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
                'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
            ]);

            // Month columns (H to monthsLastCol) are right aligned, formatted as #,##0
            $monthsLastColIdx = 7 + count($this->months) * 3;
            $monthsLastCol = Coordinate::stringFromColumnIndex($monthsLastColIdx);
            $sheet->getStyle("H3:{$monthsLastCol}" . ($totalRows - 1))->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // Total column (lastCol) is slate background, bold, right aligned, formatted as #,##0
            $sheet->getStyle("{$lastCol}3:{$lastCol}" . ($totalRows - 1))->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF1F5F9']],
                'font' => ['bold' => true, 'color' => ['argb' => 'FF0F172A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // Apply alternating backgrounds to rows and set row heights
            for ($r = 3; $r < $totalRows; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(15);
                if ($r % 2 === 1) {
                    // Muted background for odd rows, even rows remain white
                    $sheet->getStyle("A{$r}:{$monthsLastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
                }
            }

            // Apply Conditional Formatting for zero and non-zero values in month columns
            $conditionalZero = new Conditional();
            $conditionalZero->setConditionType(Conditional::CONDITION_CELLIS);
            $conditionalZero->setOperatorType(Conditional::OPERATOR_EQUAL);
            $conditionalZero->addCondition('0');
            $conditionalZero->getStyle()->getFont()->getColor()->setARGB('FF94A3B8');
            $conditionalZero->getStyle()->getFont()->setBold(false);

            $conditionalNonZero = new Conditional();
            $conditionalNonZero->setConditionType(Conditional::CONDITION_CELLIS);
            $conditionalNonZero->setOperatorType(Conditional::OPERATOR_NOTEQUAL);
            $conditionalNonZero->addCondition('0');
            $conditionalNonZero->getStyle()->getFont()->getColor()->setARGB('FF0F172A');
            $conditionalNonZero->getStyle()->getFont()->setBold(true);

            $sheet->getStyle("H3:{$monthsLastCol}" . ($totalRows - 1))->setConditionalStyles([$conditionalZero, $conditionalNonZero]);
        }

        // Style Total Row
        $sheet->getRowDimension($totalRows)->setRowHeight(18);
        $sheet->getStyle("A{$totalRows}:{$lastCol}{$totalRows}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'name' => 'Arial', 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
            'numberFormat' => ['formatCode' => '#,##0'],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF94A3B8']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']],
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF334155']],
            ],
        ]);
        $sheet->getStyle("A{$totalRows}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setIndent(1);

        return [];
    }
}
