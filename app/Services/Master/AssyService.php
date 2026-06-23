<?php

namespace App\Services\Master;

use App\Imports\AssyMasterImport;
use App\Models\Assy;
use App\Models\Carline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssyService
{
    private const REQUIRED_UPDATE_COLUMNS = [
        'assy_number',
    ];

    private const IMPORT_COLUMNS = [
        'assy_number',
        'assy_sirep',
        'carline',
        'assy_code',
        'level',
        'pattern',
        'umh',
        'standard_sea_quantity',
        'standard_air_quantity',
        'max_quantity_sea',
        'max_quantity_air',
    ];

    private const HEADER_ALIASES = [
        'assy code' => 'assy_code',
        'assy_code' => 'assy_code',
        'carline' => 'carline',
        'car line' => 'carline',
        'assy number' => 'assy_number',
        'assy_number' => 'assy_number',
        'assy no' => 'assy_number',
        'assy sirep' => 'assy_sirep',
        'level' => 'level',
        'standard sea packing' => 'standard_sea_quantity',
        'standard sea quantity' => 'standard_sea_quantity',
        'standard_sea_quantity' => 'standard_sea_quantity',
        'std sea packing' => 'standard_sea_quantity',
        'standard air packing' => 'standard_air_quantity',
        'standard air quantity' => 'standard_air_quantity',
        'standard_air_quantity' => 'standard_air_quantity',
        'std air packing' => 'standard_air_quantity',
        'max packing sea' => 'max_quantity_sea',
        'max quantity sea' => 'max_quantity_sea',
        'max_quantity_sea' => 'max_quantity_sea',
        'max packing air' => 'max_quantity_air',
        'max quantity air' => 'max_quantity_air',
        'max_quantity_air' => 'max_quantity_air',
        'pattern' => 'pattern',
        'umh' => 'umh',
    ];

    public function query(array $filters): Builder
    {
        return Assy::with('carline')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('assy_number', 'like', "%{$search}%")
                        ->orWhere('assy_code', 'like', "%{$search}%")
                        ->orWhere('level', 'like', "%{$search}%");
                });
            })
            ->when($filters['carline_id'] ?? null, fn($query, $carlineId) => $query->where('carline_id', $carlineId))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '',
                fn($query) => $query->where('is_active', $filters['is_active'] === '1')
            );
    }

    public function uploadMaster(UploadedFile $file, int $carlineId): array
    {
        $import = new AssyMasterImport($carlineId);
        Excel::import($import, $file);

        return [
            'count' => $import->getRowCount(),
            'errors' => $import->getErrors(),
        ];
    }

    public function sheetNames(UploadedFile $file): array
    {
        return IOFactory::load($file->getPathname())->getSheetNames();
    }

    public function previewSheet(UploadedFile $file, string $sheetName): array
    {
        $rows = $this->sheetRows($file, $sheetName);
        [$headers, $rows] = $this->headerAndDataRows($rows);

        return collect($rows)
            ->reject(fn ($row) => $this->isBlankRow($row))
            ->map(function ($row) use ($headers) {
                $rowData = [];

                foreach ($headers as $index => $header) {
                    $header = trim((string) $header);

                    if ($header === '') {
                        continue;
                    }

                    $rowData[$header] = $row[$index] ?? '';
                }

                return $rowData;
            })
            ->values()
            ->all();
    }

    public function importSheet(UploadedFile $file, string $sheetName, ?int $carlineId = null): array
    {
        $rows = $this->sheetRows($file, $sheetName);
        [$headers, $rows] = $this->headerAndDataRows($rows);
        $headers = array_map(fn($header) => $this->normalizeHeader($header), $headers);
        $columns = $this->columnIndexes($headers);
        
        $hasAssyKey = array_key_exists('assy_number', $columns) || array_key_exists('assy_sirep', $columns);

        if (!$hasAssyKey) {
            throw new \DomainException('File Excel harus memiliki kolom: assy_number atau assy_sirep');
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        // Load all carlines code -> id map to resolve dynamically
        $carlinesMap = \App\Models\Carline::pluck('id', 'code')
            ->mapWithKeys(fn($id, $code) => [strtoupper(trim($code)) => $id]);

        // Pre-parse rows to get all assy numbers and codes for bulk querying
        $allAssyNumbers = [];
        $allAssyCodes = [];
        foreach ($rows as $row) {
            if ($this->isBlankRow($row)) {
                continue;
            }
            $payload = $this->rowPayload($row, $columns, $carlineId ?? 0);
            if (!empty($payload['assy_number'])) {
                $allAssyNumbers[] = $payload['assy_number'];
            }
            if (!empty($payload['assy_code'])) {
                $allAssyCodes[] = $payload['assy_code'];
            }
        }

        // Fetch all existing Assy records from database in single queries
        $existingAssiesByNumber = Assy::whereIn('assy_number', $allAssyNumbers)->get()->keyBy('assy_number');
        $existingAssiesByCode = Assy::whereIn('assy_code', $allAssyCodes)->get()->keyBy('assy_code');
        $upsertPayloads = [];

        DB::transaction(function () use ($rows, $columns, $carlineId, $carlinesMap, $existingAssiesByNumber, $existingAssiesByCode, &$created, &$updated, &$errors, &$upsertPayloads) {
            $processedNumbers = [];
            $processedCodes = [];
            $now = now();

            foreach ($rows as $rowIndex => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $rowNumber = $rowIndex + 2;
                $currentRowCarlineId = $carlineId;

                if (!$currentRowCarlineId) {
                    $carlineIndex = $columns['carline'] ?? null;
                    $carlineCode = $carlineIndex !== null ? trim((string) ($row[$carlineIndex] ?? '')) : '';

                    if ($carlineCode === '') {
                        $errors[] = "Baris {$rowNumber}: Kolom 'carline' kosong padahal memilih Semua Car Line";
                        continue;
                    }

                    $currentRowCarlineId = $carlinesMap[strtoupper($carlineCode)] ?? null;

                    if (!$currentRowCarlineId) {
                        $errors[] = "Baris {$rowNumber}: Car Line dengan kode '{$carlineCode}' tidak ditemukan di database";
                        continue;
                    }
                }

                $payload = $this->rowPayload($row, $columns, $currentRowCarlineId);

                if (empty($payload['assy_number'])) {
                    $errors[] = "Baris {$rowNumber}: Assy number kosong";
                    continue;
                }

                // Check for duplicate keys in the Excel file itself to prevent SQL unique key errors on insert
                if (in_array($payload['assy_number'], $processedNumbers) || (!empty($payload['assy_code']) && in_array($payload['assy_code'], $processedCodes))) {
                    $errors[] = "Baris {$rowNumber}: Duplikasi data Assy number '{$payload['assy_number']}' atau Assy code '{$payload['assy_code']}' dalam file Excel";
                    continue;
                }

                $assy = $existingAssiesByNumber->get($payload['assy_number']) 
                    ?? ($payload['assy_code'] ? $existingAssiesByCode->get($payload['assy_code']) : null);

                if ($assy) {
                    if ((int) $assy->carline_id !== $currentRowCarlineId) {
                        $currentCode = \App\Models\Carline::find($assy->carline_id)?->code ?? 'lain';
                        $errors[] = "Baris {$rowNumber}: Assy number '{$payload['assy_number']}' atau Assy code '{$payload['assy_code']}' sudah terdaftar di Car Line '{$currentCode}'";
                        continue;
                    }

                    $updateFields = $this->updatePayload($payload);
                    $mergedPayload = array_merge([
                        'id' => $assy->id,
                        'carline_id' => $assy->carline_id,
                        'assy_number' => $assy->assy_number,
                        'assy_code' => $assy->assy_code,
                        'level' => $assy->level,
                        'pattern' => $assy->pattern,
                        'standard_sea_quantity' => $assy->standard_sea_quantity,
                        'standard_air_quantity' => $assy->standard_air_quantity,
                        'max_quantity_sea' => $assy->max_quantity_sea,
                        'max_quantity_air' => $assy->max_quantity_air,
                        'umh' => $assy->umh,
                        'is_active' => $assy->is_active,
                        'created_at' => $assy->created_at,
                        'updated_at' => $now,
                    ], $updateFields);

                    $upsertPayloads[] = $mergedPayload;
                    $updated++;
                } else {
                    if ($this->hasBlankCreatePayload($payload)) {
                        $errors[] = "Baris {$rowNumber}: Data baru wajib memiliki assy_code dan level";
                        continue;
                    }

                    $payload['created_at'] = $now;
                    $payload['updated_at'] = $now;
                    $upsertPayloads[] = $payload;
                    $created++;
                }

                $processedNumbers[] = $payload['assy_number'];
                if ($payload['assy_code']) {
                    $processedCodes[] = $payload['assy_code'];
                }
            }

            if (!empty($upsertPayloads)) {
                $chunks = array_chunk($upsertPayloads, 200);
                foreach ($chunks as $chunk) {
                    Assy::upsert(
                        $chunk,
                        ['id'],
                        ['carline_id', 'assy_number', 'assy_code', 'level', 'pattern', 'standard_sea_quantity', 'standard_air_quantity', 'max_quantity_sea', 'max_quantity_air', 'umh', 'is_active', 'updated_at']
                    );
                }
            }
        });

        return [
            'success' => ($created + $updated) > 0,
            'message' => $this->importMessage($created, $updated, $errors),
            'imported' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function createTemplate(?Carline $carline = null): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Assy');

        $this->fillTemplateSheet($sheet, $carline);
        $this->fillInstructionSheet($spreadsheet, $carline);

        $spreadsheet->setActiveSheetIndex(0);

        return [
            'spreadsheet' => $spreadsheet,
            'filename' => $carline
                ? 'template_assy_' . $carline->code . '_' . date('Ymd') . '.xlsx'
                : 'template_assy_all_carlines_' . date('Ymd') . '.xlsx',
        ];
    }

    public function streamTemplate(?Carline $carline = null): void
    {
        $template = $this->createTemplate($carline);
        (new Xlsx($template['spreadsheet']))->save('php://output');
    }

    private function sheetRows(UploadedFile $file, string $sheetName): array
    {
        $sheet = IOFactory::load($file->getPathname())->getSheetByName($sheetName);

        if (!$sheet) {
            throw new \DomainException('Sheet tidak ditemukan');
        }

        $rows = $sheet->toArray();

        if (empty($rows)) {
            throw new \DomainException('Sheet kosong');
        }

        return $rows;
    }

    private function columnIndexes(array $headers): array
    {
        $columns = [];

        foreach ($headers as $index => $header) {
            if (in_array($header, self::IMPORT_COLUMNS, true)) {
                $columns[$header] = $index;
            }
        }

        return $columns;
    }

    private function headerAndDataRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $headers = array_map(fn($header) => $this->normalizeHeader($header), $row);
            $hasAssyKey = in_array('assy_number', $headers, true) || in_array('assy_sirep', $headers, true);

            if ($hasAssyKey) {
                return [$row, array_slice($rows, $index + 1)];
            }
        }

        throw new \DomainException('Header Excel tidak ditemukan. Pastikan ada kolom: assy_number atau assy_sirep');
    }

    private function normalizeHeader(mixed $header): string
    {
        $header = strtolower(trim((string) $header));
        $header = preg_replace('/\s+/', ' ', $header) ?? $header;

        return self::HEADER_ALIASES[$header] ?? str_replace(' ', '_', $header);
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function rowPayload(array $row, array $columns, int $carlineId): array
    {
        $assyNumber = '';
        if (isset($columns['assy_sirep'])) {
            $assyNumber = trim((string) ($row[$columns['assy_sirep']] ?? ''));
        }
        if ($assyNumber === '' && isset($columns['assy_number'])) {
            $assyNumber = trim((string) ($row[$columns['assy_number']] ?? ''));
        }

        return [
            'carline_id' => $carlineId,
            'assy_number' => $assyNumber,
            'assy_code' => isset($columns['assy_code']) ? trim((string) ($row[$columns['assy_code']] ?? '')) : '',
            'level' => isset($columns['level']) ? trim((string) ($row[$columns['level']] ?? '')) : '',
            'pattern' => isset($columns['pattern']) ? trim((string) ($row[$columns['pattern']] ?? '')) : null,
            'standard_sea_quantity' => $this->nullableInteger($row, $columns, 'standard_sea_quantity'),
            'standard_air_quantity' => $this->nullableInteger($row, $columns, 'standard_air_quantity'),
            'max_quantity_sea' => $this->nullableInteger($row, $columns, 'max_quantity_sea'),
            'max_quantity_air' => $this->nullableInteger($row, $columns, 'max_quantity_air'),
            'umh' => $this->nullableFloat($row, $columns, 'umh') ?? 0,
            'is_active' => true,
        ];
    }

    private function updatePayload(array $payload): array
    {
        $update = [];

        foreach (['assy_number', 'assy_code', 'level', 'pattern', 'standard_sea_quantity', 'standard_air_quantity', 'max_quantity_sea', 'max_quantity_air', 'umh'] as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                $update[$field] = $payload[$field];
            }
        }

        return $update;
    }

    private function hasBlankCreatePayload(array $payload): bool
    {
        return $payload['assy_code'] === ''
            || $payload['level'] === ''
            || $payload['standard_sea_quantity'] === null;
    }

    private function nullableInteger(array $row, array $columns, string $field): ?int
    {
        if (!isset($columns[$field]) || trim((string) ($row[$columns[$field]] ?? '')) === '') {
            return null;
        }

        return (int) $row[$columns[$field]];
    }

    private function nullableFloat(array $row, array $columns, string $field): ?float
    {
        if (!isset($columns[$field]) || trim((string) ($row[$columns[$field]] ?? '')) === '') {
            return null;
        }

        return (float) $row[$columns[$field]];
    }

    private function importMessage(int $created, int $updated, array $errors): string
    {
        $processed = $created + $updated;
        $message = $processed > 0
            ? "Berhasil memproses {$processed} assy ({$created} baru, {$updated} update)"
            : 'Tidak ada data yang berhasil diimport';

        if (empty($errors)) {
            return $message;
        }

        $message .= '. Error: ' . implode(', ', array_slice($errors, 0, 5));

        if (count($errors) > 5) {
            $message .= ' dan ' . (count($errors) - 5) . ' error lainnya';
        }

        return $message;
    }

    private function fillTemplateSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, ?Carline $carline = null): void
    {
        $sheet->setCellValue('A1', 'TEMPLATE IMPORT ASSY MASTER');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Car Line:');
        $sheet->setCellValue('B2', $carline ? $carline->code : 'ALL CAR LINES');
        $sheet->mergeCells('B2:J2');
        $sheet->getStyle('A2')->getFont()->setBold(true);

        $headers = ['assy_code', 'carline', 'assy_number', 'level', 'standard_sea_quantity', 'standard_air_quantity', 'max_quantity_sea', 'max_quantity_air', 'pattern', 'umh'];
        $headerRow = 4;

        foreach ($headers as $index => $header) {
            $column = chr(65 + $index);
            $sheet->setCellValue($column . $headerRow, strtoupper($header));
            $sheet->getColumnDimension($column)->setWidth(20);
        }

        $sheet->getStyle('A4:J4')->applyFromArray($this->headerStyle());
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:J4');

        $query = Assy::with('carline');
        if ($carline) {
            $query->where('carline_id', $carline->id);
        }

        $rows = $query->orderBy('carline_id')
            ->orderBy('assy_number')
            ->get()
            ->map(fn(Assy $assy) => [
                $assy->assy_code,
                $assy->carline?->code ?? '',
                $assy->assy_number,
                $assy->level,
                $assy->standard_sea_quantity,
                $assy->standard_air_quantity,
                $assy->max_quantity_sea,
                $assy->max_quantity_air,
                $assy->pattern,
                $assy->umh,
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            $code = $carline ? $carline->code : '508D';
            $rows = [
                ['DZ01', $code, '82115-0E490 K', 'K 0001', 4, 4, 8, 8, 'LHD-HEV', 5.3387],
                ['DVB1', $code, '82115-0E480 K', 'K 0301', 4, 4, 8, 8, 'LHD-HEV', 4.9637],
                ['DYB9', $code, '82115-0E440 M', 'M 0001', 4, 4, 8, 8, 'LHD-HEV', 4.3842],
            ];
        }

        foreach ($rows as $index => $rowData) {
            $row = 5 + $index;
            $sheet->fromArray($rowData, null, 'A' . $row);
        }

        $lastDataRow = 4 + count($rows);

        if ($lastDataRow >= 5) {
            $sheet->getStyle("A5:J{$lastDataRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        for ($i = 0; $i < 10; $i++) {
            $row = 5 + count($rows) + $i;
            $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($this->borderStyle());
        }
    }

    private function fillInstructionSheet(Spreadsheet $spreadsheet, ?Carline $carline = null): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Petunjuk');
        $sheet->fromArray([
            ['PETUNJUK PENGISIAN TEMPLATE', ''],
            ['', ''],
            ['Informasi Car Line:', ''],
            ['Car Line ID:', $carline ? $carline->id : 'ALL'],
            ['Car Line Code:', $carline ? $carline->code : 'ALL CAR LINES'],
            ['', ''],
            ['Kolom wajib untuk update data SIREP:', ''],
            ['1. assy_number', 'Nomor Part Assy yang sudah ada di Master Assy'],
            ['2. standard_sea_quantity', 'Standard sea quantity (Angka bulat)'],
            ['3. umh', 'Nilai UMH (Decimal, contoh: 5.3387)'],
            ['', ''],
            ['Kolom tambahan:', ''],
            ['- carline', 'Kode Car Line (Wajib diisi jika mode Semua Car Line agar data tidak salah masuk)'],
            ['- pattern', 'Opsional, akan ikut diperbarui jika diisi'],
            ['- level', 'Wajib jika assy_number belum ada dan ingin membuat data baru'],
            ['- assy_code', 'Wajib jika assy_number belum ada dan ingin membuat data baru'],
            ['', ''],
            ['Format Data:', ''],
            ['- assy_number: Maksimal 50 karakter', ''],
            ['- assy_code: Maksimal 20 karakter', ''],
            ['- level: Maksimal 20 karakter', ''],
            ['- pattern: Maksimal 255 karakter', ''],
            ['- umh: 0 - 9999.999999', ''],
            ['- standard_sea_quantity: Minimal 0', ''],
            ['', ''],
            ['Catatan Penting:', ''],
            ['- Jika memilih Car Line saat import, carline kolom Excel diabaikan', ''],
            ['- Jika memilih "Semua Car Line" saat import, carline kolom Excel wajib valid sesuai master Car Line di database', ''],
            ['- Jika assy_number sudah ada, sistem akan update umh dan standard_sea_quantity', ''],
            ['- Jika assy_number belum ada, assy_code dan level wajib diisi', ''],
            ['- File maksimal 1000 baris data', ''],
            ['- Format file: .xlsx, .xls, atau .csv', ''],
            ['- Baris pertama (header) tidak akan diimport', ''],
            ['- Data contoh bisa dihapus atau diganti dengan data asli', ''],
        ]);

        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D6F42');
        $sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
    }

    private function headerStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D6F42'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ];
    }

    private function borderStyle(): array
    {
        return [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ];
    }
}
