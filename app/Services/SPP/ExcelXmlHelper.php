<?php

namespace App\Services\SPP;

use DOMDocument;
use DOMElement;
use ZipArchive;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelXmlHelper
{
    public function getSharedStrings(ZipArchive $zip): array
    {
        $sstXml = $zip->getFromName('xl/sharedStrings.xml');
        if (!$sstXml) return [];
        $dom = new DOMDocument();
        $dom->loadXML($sstXml);
        $sst = [];
        $sis = $dom->getElementsByTagName('si');
        foreach ($sis as $si) {
            $ts = $si->getElementsByTagName('t');
            $str = '';
            foreach ($ts as $t) {
                $str .= $t->nodeValue;
            }
            $sst[] = $str;
        }
        return $sst;
    }

    public function getSheetPaths(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $workbookRelsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        $wbDOM = new DOMDocument();
        $wbDOM->loadXML($workbookXml);
        $sheets = $wbDOM->getElementsByTagName('sheet');
        $sheetMap = [];
        foreach ($sheets as $sheet) {
            $name = $sheet->getAttribute('name');
            $rId = $sheet->getAttribute('r:id');
            if (!$rId) {
                $rId = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            }
            $sheetMap[$name] = $rId;
        }

        $relsDOM = new DOMDocument();
        $relsDOM->loadXML($workbookRelsXml);
        $relationships = $relsDOM->getElementsByTagName('Relationship');
        $relMap = [];
        foreach ($relationships as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            $relMap[$id] = $target;
        }

        $paths = [];
        foreach ($sheetMap as $name => $rId) {
            if (isset($relMap[$rId])) {
                $paths[$name] = 'xl/' . $relMap[$rId];
            }
        }
        return $paths;
    }

    public function getSheetDOM(ZipArchive $zip, string $sheetPath): DOMDocument
    {
        $xml = $zip->getFromName($sheetPath);
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml);
        return $dom;
    }

    public function saveSheetDOM(ZipArchive $zip, string $sheetPath, DOMDocument $dom): void
    {
        $zip->addFromString($sheetPath, $dom->saveXML());
    }

    public function getOrCreateRow(DOMDocument $dom, DOMElement $sheetData, int $rowNum): DOMElement
    {
        $rows = $sheetData->getElementsByTagName('row');
        foreach ($rows as $row) {
            $r = (int)$row->getAttribute('r');
            if ($r === $rowNum) {
                return $row;
            }
            if ($r > $rowNum) {
                $newRow = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
                $newRow->setAttribute('r', $rowNum);
                $sheetData->insertBefore($newRow, $row);
                return $newRow;
            }
        }
        $newRow = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'row');
        $newRow->setAttribute('r', $rowNum);
        $sheetData->appendChild($newRow);
        return $newRow;
    }

    public function getOrCreateCell(DOMDocument $dom, DOMElement $row, string $cellRef): DOMElement
    {
        $cells = $row->getElementsByTagName('c');
        $cellCol = preg_replace('/\d+/', '', $cellRef);
        $cellColIdx = Coordinate::columnIndexFromString($cellCol);

        foreach ($cells as $cell) {
            $r = $cell->getAttribute('r');
            if ($r === $cellRef) {
                return $cell;
            }
            $col = preg_replace('/\d+/', '', $r);
            $colIdx = Coordinate::columnIndexFromString($col);
            if ($colIdx > $cellColIdx) {
                $newCell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
                $newCell->setAttribute('r', $cellRef);
                $row->insertBefore($newCell, $cell);
                return $newCell;
            }
        }

        $newCell = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'c');
        $newCell->setAttribute('r', $cellRef);
        $row->appendChild($newCell);
        return $newCell;
    }

    public function setCellValue(DOMDocument $dom, DOMElement $cell, mixed $val): void
    {
        while ($cell->hasChildNodes()) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');

        if ($val === null || $val === '') {
            return;
        }

        if (is_string($val) && strpos($val, '=') === 0) {
            $formula = ltrim($val, '=');
            $fElem = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'f');
            $fElem->appendChild($dom->createTextNode($formula));
            $cell->appendChild($fElem);
            return;
        }

        if (is_numeric($val)) {
            $vElem = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'v');
            $vElem->appendChild($dom->createTextNode((string)$val));
            $cell->appendChild($vElem);
        } else {
            $cell->setAttribute('t', 'inlineStr');
            $isElem = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'is');
            $tElem = $dom->createElementNS('http://schemas.openxmlformats.org/spreadsheetml/2006/main', 't');
            $tElem->appendChild($dom->createTextNode((string)$val));
            $isElem->appendChild($tElem);
            $cell->appendChild($isElem);
        }
    }

    public function findEndRowDOM(DOMDocument $dom, int $startRow, array $sharedStrings): int
    {
        $sheetData = $dom->getElementsByTagName('sheetData')->item(0);
        if (!$sheetData) return $startRow;

        $rows = [];
        foreach ($sheetData->getElementsByTagName('row') as $rowElem) {
            $r = (int)$rowElem->getAttribute('r');
            $rows[$r] = $rowElem;
        }

        $highestRow = empty($rows) ? $startRow : max(array_keys($rows));

        for ($r = $startRow; $r <= $highestRow; $r++) {
            if (!isset($rows[$r])) {
                return $r - 1;
            }

            $rowElem = $rows[$r];
            $isEmpty = true;
            $hasTotal = false;

            $cells = $rowElem->getElementsByTagName('c');
            foreach ($cells as $cell) {
                $cellRef = $cell->getAttribute('r');
                $col = preg_replace('/\d+/', '', $cellRef);
                $colIdx = Coordinate::columnIndexFromString($col);
                if ($colIdx > 10) continue;

                $val = $this->getCellValueSafe($cell, $sharedStrings);
                if ($val !== null && $val !== '') {
                    $isEmpty = false;
                    if (strpos(strtolower((string)$val), 'total') !== false) {
                        $hasTotal = true;
                        break;
                    }
                }
            }

            if ($isEmpty || $hasTotal) {
                return $r - 1;
            }
        }

        return $highestRow;
    }

    public function getCellValueSafe(DOMElement $cell, array $sharedStrings)
    {
        $type = $cell->getAttribute('t');
        if ($type === 's') {
            $v = $cell->getElementsByTagName('v')->item(0);
            if ($v) {
                $idx = (int)$v->nodeValue;
                return $sharedStrings[$idx] ?? '';
            }
            return '';
        }
        if ($type === 'inlineStr') {
            $ts = $cell->getElementsByTagName('t');
            $str = '';
            foreach ($ts as $t) {
                $str .= $t->nodeValue;
            }
            return $str;
        }
        $v = $cell->getElementsByTagName('v')->item(0);
        if ($v) {
            return $v->nodeValue;
        }
        return '';
    }

    public function getCellRawValueOrFormula(DOMElement $cell, array $sharedStrings = [])
    {
        $f = $cell->getElementsByTagName('f')->item(0);
        if ($f) {
            return '=' . $f->nodeValue;
        }
        $t = $cell->getAttribute('t');
        if ($t === 's') {
            $v = $cell->getElementsByTagName('v')->item(0);
            if ($v) {
                $idx = (int)$v->nodeValue;
                return $sharedStrings[$idx] ?? '';
            }
            return '';
        }
        if ($t === 'inlineStr') {
            $is = $cell->getElementsByTagName('is')->item(0);
            if ($is) {
                $tElem = $is->getElementsByTagName('t')->item(0);
                return $tElem ? $tElem->nodeValue : '';
            }
            return '';
        }
        $v = $cell->getElementsByTagName('v')->item(0);
        return $v ? $v->nodeValue : '';
    }

    public function shiftRowsDown(DOMDocument $dom, DOMElement $sheetData, int $belowRow, int $shiftOffset, int $oldEndRow = 0, int $newEndRow = 0): void
    {
        $rows = $sheetData->getElementsByTagName('row');
        $rowsToShift = [];
        foreach ($rows as $row) {
            $r = (int)$row->getAttribute('r');
            if ($r > $belowRow) {
                $rowsToShift[] = $row;
            }
        }

        usort($rowsToShift, function($a, $b) {
            return (int)$b->getAttribute('r') <=> (int)$a->getAttribute('r');
        });

        foreach ($rowsToShift as $row) {
            $oldR = (int)$row->getAttribute('r');
            $newR = $oldR + $shiftOffset;
            $row->setAttribute('r', $newR);

            $cells = $row->getElementsByTagName('c');
            foreach ($cells as $cell) {
                $ref = $cell->getAttribute('r');
                $col = preg_replace('/\d+/', '', $ref);
                $cell->setAttribute('r', $col . $newR);

                $fElem = $cell->getElementsByTagName('f')->item(0);
                if ($fElem) {
                    $formula = $fElem->nodeValue;
                    $adjusted = $this->adjustFormulaRowNumbers($formula, $belowRow, $shiftOffset, $oldEndRow, $newEndRow);
                    $fElem->nodeValue = '';
                    $fElem->appendChild($dom->createTextNode($adjusted));
                }
            }
        }
    }

    public function adjustFormulaRowNumbers(string $formula, int $belowRow, int $shiftOffset, int $oldEndRow = 0, int $newEndRow = 0): string
    {
        if ($oldEndRow > 0 && $newEndRow > 0) {
            $formula = preg_replace('/([A-Z]+)' . $oldEndRow . '\b/i', '${1}' . $newEndRow, $formula);
        }
        return preg_replace_callback('/([A-Z]+)(\d+)\b/i', function($matches) use ($belowRow, $shiftOffset) {
            $col = $matches[1];
            $row = (int)$matches[2];
            if ($row > $belowRow) {
                return $col . ($row + $shiftOffset);
            }
            return $matches[0];
        }, $formula);
    }

    public function getRowStyleCache(DOMElement $sheetData, int $startRow): array
    {
        $styleCache = [];
        $rows = $sheetData->getElementsByTagName('row');
        foreach ($rows as $row) {
            if ((int)$row->getAttribute('r') === $startRow) {
                $cells = $row->getElementsByTagName('c');
                foreach ($cells as $cell) {
                    $ref = $cell->getAttribute('r');
                    $col = preg_replace('/\d+/', '', $ref);
                    $s = $cell->getAttribute('s');
                    if ($s !== '') {
                        $styleCache[$col] = $s;
                    }
                }
                break;
            }
        }
        return $styleCache;
    }

    public function applyCellStyle(DOMElement $cell, string $colLetter, array $styleCache): void
    {
        if (isset($styleCache[$colLetter])) {
            $cell->setAttribute('s', $styleCache[$colLetter]);
        }
    }

    public function copyRowAttributes(DOMElement $srcRow, DOMElement $destRow): void
    {
        foreach ($srcRow->attributes as $attr) {
            if ($attr->name !== 'r') {
                $destRow->setAttribute($attr->name, $attr->value);
            }
        }
    }

    public function removeMergeCellsDOM(DOMDocument $dom, int $startRow): void
    {
        $mergeCellsList = $dom->getElementsByTagName('mergeCells');
        if ($mergeCellsList->length === 0) {
            return;
        }
        $mergeCellsElem = $mergeCellsList->item(0);
        $mergeCells = $mergeCellsElem->getElementsByTagName('mergeCell');
        $toRemove = [];
        foreach ($mergeCells as $mergeCell) {
            $ref = $mergeCell->getAttribute('ref');
            $parts = explode(':', $ref);
            if (count($parts) === 2) {
                $startRef = $parts[0];
                $startRowCell = (int)preg_replace('/[A-Z]+/i', '', $startRef);
                if ($startRowCell >= $startRow) {
                    $toRemove[] = $mergeCell;
                }
            }
        }
        foreach ($toRemove as $node) {
            $mergeCellsElem->removeChild($node);
        }
        $mergeCellsElem->setAttribute('count', $mergeCellsElem->getElementsByTagName('mergeCell')->length);
        if ((int)$mergeCellsElem->getAttribute('count') === 0) {
            $mergeCellsElem->parentNode->removeChild($mergeCellsElem);
        }
    }

    public function cleanFormulaReferences(string $formula): string
    {
        $formula = preg_replace('/\[\d+\]Sheet1/i', "'Sheet1'", $formula);
        $formula = preg_replace('/#REF!/i', "'Sheet1'!\$B\$1:\$AZ\$5000", $formula);
        return $formula;
    }

    public function cleanSheetFormulas(DOMDocument $dom): void
    {
        // 1. Clean cell formulas
        $cells = $dom->getElementsByTagName('c');
        foreach ($cells as $cell) {
            $f = $cell->getElementsByTagName('f')->item(0);
            if ($f) {
                $formula = $f->nodeValue;
                if (strpos($formula, '#REF') !== false || preg_match('/\[\d+\]Sheet1/i', $formula)) {
                    $cleaned = $this->cleanFormulaReferences('=' . $formula);
                    $this->setCellValue($dom, $cell, $cleaned);
                }
            }
        }

        // 2. Clean conditional formatting formulas
        $formulas = $dom->getElementsByTagName('formula');
        foreach ($formulas as $formulaElem) {
            $formula = $formulaElem->nodeValue;
            if (strpos($formula, '#REF') !== false || preg_match('/\[\d+\]Sheet1/i', $formula)) {
                $cleaned = $this->cleanFormulaReferences($formula);
                $formulaElem->nodeValue = '';
                $formulaElem->appendChild($dom->createTextNode($cleaned));
            }
        }
    }
}
