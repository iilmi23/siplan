<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

class SaiExportStrategy extends AbstractExportStrategy
{
    public function getTemplatePath(): string
    {
        return 'docs/SAI_3- SPP BC SAI-T MAR FAB apr w2.xlsx';
    }

    public function getMainSheet(): string
    {
        return 'QTY ';
    }

    public function getStartRow(): int
    {
        return 9;
    }

    public function getPartCol(): int
    {
        return 5; // Kolom E
    }

    public function getUmhCol(): int
    {
        return 7; // Kolom G
    }

    public function getMonthStartCol(): int
    {
        return 9; // Kolom I
    }

    public function getMonthRow(): int
    {
        return 6;
    }

    public function getDateRow(): int
    {
        return 7;
    }

    public function getMapping(): array
    {
        return [
            2 => 'pattern',     // B
            4 => 'assy_code',   // D
            5 => 'assy_number', // E
            6 => 'carline',     // F
        ];
    }

    public function getSheets(): array
    {
        return [
            'QTY ' => 9,
            'GUM UMH' => 9,
            'AS400 JEMBATAN' => 9,
            'AS400 TRANSFER' => 9,
            'AS400' => 9,
        ];
    }

    public function getSRColumnIndex(int $monthIdx, int $weekNo): int
    {
        return 14 + $monthIdx * 6 + ($weekNo - 1);
    }

    public function populateWeekRow(DOMDocument $dom, DOMElement $row, string $mLabel, array $data, int $year, int $r): void
    {
        // Untuk SAI, A => label, B => jumlah minggu
    }

    public function getProdFormula(
        string $delCol,
        string $balCol,
        string $firstMonthBalCol,
        int $r,
        int $prodVal,
        int $delVal,
        int $balVal,
        int $firstMonthBal
    ): string {
        $baseVal = ($balVal > $delVal) ? 0 : ($delVal - $balVal);
        $diff = $prodVal - $baseVal;
        $adjStr = '';
        if ($diff > 0) {
            $adjStr = '+' . $diff;
        } elseif ($diff < 0) {
            $adjStr = (string)$diff;
        }
        return "=IF({$balCol}{$r}>{$delCol}{$r},0,{$delCol}{$r}-{$balCol}{$r}){$adjStr}";
    }

    public function hasSpecialIndexCol(): bool
    {
        return true;
    }

    public function writeSpecialIndexCol(DOMDocument $dom, DOMElement $row, int $r, array $styleCache, callable $cellStyleApplier, callable $valueSetter): void
    {
        $cell = call_user_func($cellStyleApplier, $row, 'C', $styleCache);
        if ($r === 9) {
            call_user_func($valueSetter, $dom, $cell, 1);
        } else {
            call_user_func($valueSetter, $dom, $cell, "=C" . ($r - 1) . "+1");
        }
    }

    public function hasAdditionalHeaders(): bool
    {
        return true;
    }

    public function writeAdditionalHeaders(
        \ZipArchive $zip,
        array $sheetPathMap,
        array $months,
        callable $sheetDomLoader,
        callable $sheetDomSaver,
        callable $rowResolver,
        callable $cellResolver,
        callable $valueSetter
    ): void {
        if (isset($sheetPathMap['GUM UMH'])) {
            $gumDom = call_user_func($sheetDomLoader, $zip, $sheetPathMap['GUM UMH']);
            $gumSheetData = $gumDom->getElementsByTagName('sheetData')->item(0);
            if ($gumSheetData) {
                foreach ($months as $idx => $month) {
                    $gumColIdx = 12 + $idx * 3; // L, O, R...
                    $gumColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($gumColIdx);

                    $row1 = call_user_func($rowResolver, $gumDom, $gumSheetData, $this->getMonthRow());
                    $c1 = call_user_func($cellResolver, $gumDom, $row1, $gumColLetter . $this->getMonthRow());
                    call_user_func($valueSetter, $gumDom, $c1, $month['label']);

                    $row2 = call_user_func($rowResolver, $gumDom, $gumSheetData, $this->getDateRow());
                    $c2 = call_user_func($cellResolver, $gumDom, $row2, $gumColLetter . $this->getDateRow());
                    call_user_func($valueSetter, $gumDom, $c2, $month['range_label']);
                }
                call_user_func($sheetDomSaver, $zip, $sheetPathMap['GUM UMH'], $gumDom);
            }
        }
    }
}
