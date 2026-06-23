<?php

namespace App\Services\SR;

use App\Models\Customer;
use App\Services\SR\Mappers\GenericTemplateMapper;
use App\Services\SR\Mappers\SAIMapper;
use App\Services\SR\SRMapperInterface;
use App\Services\SR\Mappers\TYCMapper;
use App\Services\SR\Mappers\YCMapper;
use App\Services\SR\Mappers\YNAMapper;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SRMapperService
{
    public function mapUploadedSheet(string $tempPath, Customer $customer, int $sheetIndex, ?string $originalName = null): array
    {
        $reader = $this->createReader($tempPath);
        $spreadsheet = $reader->load($tempPath);
        $worksheet = $spreadsheet->getSheet($sheetIndex);

        if ($worksheet === null) {
            throw new \Exception('Sheet tidak valid. Tersedia: '.$spreadsheet->getSheetCount().' sheet.');
        }

        $sheetName = $worksheet->getTitle();
        $sheetData = $this->worksheetToArray($worksheet);

        if (empty($sheetData)) {
            throw new \Exception('Sheet yang dipilih kosong.');
        }

        $options = $this->extractSheetOptions($worksheet, $customer->code);
        if ($originalName !== null) {
            $options['source_file'] = $originalName;
        }

        $mapped = null;
        $warning = null;

        // 1. Coba mapping menggunakan template dinamis database jika ada yang aktif
        $template = $customer->activeSrMappingTemplate;
        if ($template !== null) {
            try {
                if ($template->sheet_index !== null && (int) $template->sheet_index !== $sheetIndex) {
                    throw new \Exception(
                        'Template SR aktif hanya berlaku untuk sheet index '.$template->sheet_index.
                        '. Sheet yang dipilih: '.$sheetIndex.'.'
                    );
                }

                $mapper = new GenericTemplateMapper($template, $customer);

                Log::info('SR mapping started using database template', [
                    'customer' => $customer->code,
                    'sheet_index' => $sheetIndex,
                    'sheet_name' => $sheetName,
                    'template_id' => $template->id,
                ]);

                $mapped = $this->runMapper($mapper, $customer->code, $sheetData, $tempPath, $sheetIndex, $options, $customer->id);
                $mapped = array_values(array_filter($mapped));

                if (empty($mapped)) {
                    throw new \Exception('Hasil mapping template dinamis menghasilkan data kosong.');
                }
            } catch (\Throwable $e) {
                $warning = 'WARNING: Template dinamis gagal (Error: '.$e->getMessage().'), sistem fallback ke mapper hardcoded.';
                Log::warning('SR mapping template failed, will attempt hardcoded fallback', [
                    'customer' => $customer->code,
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
                $mapped = null;
            }
        }

        // 2. Jika tidak ada template aktif atau pemrosesan template gagal/kosong, fallback ke mapper hardcoded
        if ($mapped === null) {
            $customMapper = $this->resolveCustomMapper($customer->code);

            if ($customMapper === null) {
                if ($warning !== null) {
                    throw new \Exception('Mapping menggunakan template gagal: '.$warning.'. Serta tidak ada hardcoded mapper untuk customer ini.');
                }
                throw new \Exception('Customer '.$customer->code.' belum punya mapper khusus atau template SR aktif. Buat template di menu SR Mapping Templates.');
            }

            Log::info('SR mapping started using hardcoded mapper', [
                'customer' => $customer->code,
                'sheet_index' => $sheetIndex,
                'sheet_name' => $sheetName,
                'mapper' => class_basename($customMapper),
                'due_to' => $warning ? 'template_error' : 'no_template',
            ]);

            if (strtoupper($customer->code) === 'YC') {
                $mapped = $this->runYCMapper($customMapper, $tempPath, $options, true, $sheetIndex);
            } else {
                $mapped = $this->runMapper($customMapper, $customer->code, $sheetData, $tempPath, $sheetIndex, $options, $customer->id);
            }

            $mapped = array_values(array_filter($mapped));
        }

        Log::info('SR mapping completed', [
            'customer' => $customer->code,
            'sheet_name' => $sheetName,
            'mapped_rows' => count($mapped),
            'fallback_used' => ($warning !== null),
        ]);

        return [$mapped, $sheetName, $warning];
    }

    private function resolveCustomMapper(string $customerCode): ?SRMapperInterface
    {
        return match (strtoupper($customerCode)) {
            'TYC' => new TYCMapper(),
            'YNA' => new YNAMapper(),
            'SAI' => new SAIMapper(),
            'YC' => new YCMapper(),
            default => null,
        };
    }

    private function runMapper(
        SRMapperInterface $mapper,
        string $customerCode,
        array $sheetData,
        string $tempPath,
        int $sheetIndex,
        array $options,
        ?int $customerId = null
    ): array {
        if ($mapper instanceof GenericTemplateMapper) {
            return $mapper->map($sheetData);
        }

        if ($mapper instanceof YNAMapper) {
            return $mapper->map($sheetData, null, $tempPath, $sheetIndex, $customerId);
        }

        if ($mapper instanceof TYCMapper) {
            return $mapper->map($sheetData, null, $options);
        }

        if ($mapper instanceof SAIMapper) {
            return $mapper->map($sheetData, null, $options);
        }

        return $mapper->map($sheetData);
    }

    private function runYCMapper(YCMapper $mapper, string $tempPath, array $options, bool $singleSheetMode, ?int $sheetIndex): array
    {
        $reader = $this->createReader($tempPath);
        $spreadsheet = $reader->load($tempPath);
        $allSheets = [];
        $sheetNames = [];

        foreach ($spreadsheet->getWorksheetIterator() as $index => $worksheet) {
            $sheetNames[$index] = $worksheet->getTitle();

            if ($singleSheetMode && $sheetIndex !== null && $index !== $sheetIndex) {
                continue;
            }

            $allSheets[$index] = $this->worksheetToArray($worksheet);
        }

        if (empty($allSheets)) {
            throw new \Exception('Tidak ada sheet yang bisa diproses');
        }

        $sheetResults = $mapper->mapAllSheets($allSheets, $sheetNames, [], null, $options);
        $result = [];

        foreach ($sheetResults as $sheetRecords) {
            if (is_array($sheetRecords)) {
                $result = array_merge($result, $sheetRecords);
            }
        }

        return $result;
    }

    private function worksheetToArray(Worksheet $worksheet): array
    {
        $sheetData = [];
        $highestRow = $worksheet->getHighestRow();
        $highestColIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());

        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = [];

            for ($col = 1; $col <= $highestColIndex; $col++) {
                $cell = $worksheet->getCellByColumnAndRow($col, $row);
                $val = $cell->getValue();
                if (is_string($val) && str_starts_with(trim($val), '=')) {
                    try {
                        $val = $cell->getOldCalculatedValue();
                        if ($val === null || $val === '') {
                            $val = $cell->getCalculatedValue();
                        }
                    } catch (\Throwable $e) {
                        try {
                            $val = $cell->getCalculatedValue();
                        } catch (\Throwable $ex) {
                            $val = null;
                        }
                    }
                }
                $rowData[] = $val;
            }

            $sheetData[$row - 1] = $rowData;
        }

        return $sheetData;
    }

    private function createReader(string $filePath): \PhpOffice\PhpSpreadsheet\Reader\IReader
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $reader = match ($extension) {
            'xlsx', 'xlsm' => new \PhpOffice\PhpSpreadsheet\Reader\Xlsx(),
            'xls' => new \PhpOffice\PhpSpreadsheet\Reader\Xls(),
            default => throw new \Exception("Unsupported file type: {$extension}"),
        };

        $reader->setReadDataOnly(true);

        return $reader;
    }

    private function extractSheetOptions(Worksheet $worksheet, string $customerCode): array
    {
        $options = ['hidden_columns' => [], 'hidden_rows' => []];

        if (strtoupper($customerCode) === 'YNA') {
            return $options;
        }

        try {
            foreach ($worksheet->getColumnDimensions() as $colLetter => $colDim) {
                if (! $colDim->getVisible()) {
                    $options['hidden_columns'][] = Coordinate::columnIndexFromString($colLetter) - 1;
                }
            }

            foreach ($worksheet->getRowDimensions() as $rowNum => $rowDim) {
                if (! $rowDim->getVisible()) {
                    $options['hidden_rows'][] = (int) $rowNum - 1;
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('extractSheetOptions failed', ['error' => $exception->getMessage()]);
        }

        return $options;
    }
}
