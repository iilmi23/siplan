<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

class YnaExportStrategy extends AbstractExportStrategy
{
    public function getTemplatePath(): string
    {
        return 'docs/YNA_3. SPP 491D-564D JAI SR 3-2-2026 - Copy.xlsX';
    }

    public function getMainSheet(): string
    {
        return 'GUM';
    }

    public function getStartRow(): int
    {
        return 14;
    }

    public function getPartCol(): int
    {
        return 3; // Kolom C
    }

    public function getUmhCol(): int
    {
        return 8; // Kolom H
    }

    public function getMonthStartCol(): int
    {
        return 9; // Kolom I
    }

    public function getMonthRow(): int
    {
        return 11;
    }

    public function getDateRow(): int
    {
        return 12;
    }

    public function getMapping(): array
    {
        return [
            1 => 'pattern',     // A
            2 => 'carline',     // B
            3 => 'assy_number', // C
            4 => 'level',       // D
            5 => 'assy_code',   // E
            7 => 'std_pack',    // G
        ];
    }

    public function getSheets(): array
    {
        return [
            'GUM' => 14,
            'down rate' => 14,
            '(AS-400 Jembatan)' => 8,
            '(AS-400 Transfer)' => 8,
        ];
    }

    public function getSRColumnIndex(int $monthIdx, int $weekNo): int
    {
        $monthStarts = [
            0 => 3,   // Bulan 1
            1 => 12,  // Bulan 2
            2 => 17,  // Bulan 3
            3 => 21,  // Bulan 4
            4 => 25,  // Bulan 5
            5 => 30,  // Bulan 6
        ];
        $start = $monthStarts[$monthIdx] ?? (3 + $monthIdx * 6);
        return $start + ($weekNo - 1);
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
        $baseVal = $delVal - $balVal + $firstMonthBal;
        $diff = $prodVal - $baseVal;
        $adjStr = '';
        if ($diff > 0) {
            $adjStr = '+' . $diff;
        } elseif ($diff < 0) {
            $adjStr = (string)$diff;
        }
        return "={$delCol}{$r}-{$balCol}{$r}+{$firstMonthBalCol}{$r}{$adjStr}";
    }
}
