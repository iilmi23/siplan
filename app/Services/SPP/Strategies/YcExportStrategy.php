<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

class YcExportStrategy extends AbstractExportStrategy
{
    public function getTemplatePath(): string
    {
        return 'docs/YC_3. SPP 900B-486D MAR (SR 202603-10) - ACTUAL - Copy.xlsx';
    }

    public function getMainSheet(): string
    {
        return 'Qty & UMH GUM';
    }

    public function getStartRow(): int
    {
        return 9;
    }

    public function getPartCol(): int
    {
        return 3; // Kolom C
    }

    public function getUmhCol(): int
    {
        return 9; // Kolom I
    }

    public function getMonthStartCol(): int
    {
        return 10; // Kolom J
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
            1 => 'carline',     // A
            2 => 'pattern',     // B
            3 => 'assy_number', // C
            4 => 'level',       // D
            5 => 'assy_code',   // E
        ];
    }

    public function getSheets(): array
    {
        return [
            'Qty & UMH GUM' => 9,
            'Summ Qty&UMH GUM ' => 9,
            'AS400 JEMBATAN' => 9,
            'AS400 TRANSFER' => 9,
            'Summ Qty & UMH' => 9,
        ];
    }

    public function getSRColumnIndex(int $monthIdx, int $weekNo): int
    {
        return 10 + $monthIdx * 6 + ($weekNo - 1);
    }

    public function populateWeekRow(DOMDocument $dom, DOMElement $row, string $mLabel, array $data, int $year, int $r): void
    {
        // Ditangani langsung atau via strategi jika dibutuhkan
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
}
