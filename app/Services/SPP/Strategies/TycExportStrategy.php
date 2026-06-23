<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

class TycExportStrategy extends AbstractExportStrategy
{
    public function getTemplatePath(): string
    {
        return 'docs/TYC_3-SPP 359D MAR JAI-2026-2.26.xlsx';
    }

    public function getMainSheet(): string
    {
        return 'GUM';
    }

    public function getStartRow(): int
    {
        return 15;
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
            2 => 'pattern',     // B
            3 => 'assy_number', // C
            4 => 'level',       // D
            5 => 'assy_code',   // E
            7 => 'std_pack',    // G
        ];
    }

    public function getSheets(): array
    {
        return [
            'GUM' => 15,
            'down rate' => 15,
            '(AS-400 Jembatan)' => 9,
            '(AS-400 Transfer)' => 9,
        ];
    }

    public function getSRColumnIndex(int $monthIdx, int $weekNo): int
    {
        return 3 + $monthIdx * 6 + ($weekNo - 1);
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
