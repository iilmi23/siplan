<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

interface ExportStrategyInterface
{
    public function getTemplatePath(): string;
    public function getMainSheet(): string;
    public function getStartRow(): int;
    public function getPartCol(): int;
    public function getUmhCol(): int;
    public function getMonthStartCol(): int;
    public function getMonthRow(): int;
    public function getDateRow(): int;
    public function getMapping(): array;
    public function getSheets(): array;

    public function getSRColumnIndex(int $monthIdx, int $weekNo): int;

    public function populateWeekRow(DOMDocument $dom, DOMElement $row, string $mLabel, array $data, int $year, int $r): void;

    public function getProdFormula(
        string $delCol,
        string $balCol,
        string $firstMonthBalCol,
        int $r,
        int $prodVal,
        int $delVal,
        int $balVal,
        int $firstMonthBal
    ): string;

    public function hasSpecialIndexCol(): bool;
    public function writeSpecialIndexCol(DOMDocument $dom, DOMElement $row, int $r, array $styleCache, callable $cellStyleApplier, callable $valueSetter): void;

    public function hasAdditionalHeaders(): bool;
    public function writeAdditionalHeaders(
        \ZipArchive $zip,
        array $sheetPathMap,
        array $months,
        callable $sheetDomLoader,
        callable $sheetDomSaver,
        callable $rowResolver,
        callable $cellResolver,
        callable $valueSetter
    ): void;
}
