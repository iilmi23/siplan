<?php

namespace App\Services\SPP\Strategies;

use DOMDocument;
use DOMElement;

abstract class AbstractExportStrategy implements ExportStrategyInterface
{
    public function hasSpecialIndexCol(): bool
    {
        return false;
    }

    public function writeSpecialIndexCol(DOMDocument $dom, DOMElement $row, int $r, array $styleCache, callable $cellStyleApplier, callable $valueSetter): void
    {
        // Default: tidak ada kolom indeks khusus
    }

    public function hasAdditionalHeaders(): bool
    {
        return false;
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
        // Default: tidak ada header tambahan yang perlu ditulis
    }
}
