<?php

namespace App\Imports;

use App\Models\Assy;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\Importable;

class AssyMasterImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError, SkipsOnFailure
{
    use Importable, SkipsErrors;
    
    private int $carlineId;
    private int $rowCount = 0;
    private array $validationErrors = [];
    
    public function __construct(int $carlineId)
    {
        $this->carlineId = $carlineId;
    }
    
    public function prepareForValidation(array $data, int $index): array
    {
        if (!empty($data['assy_sirep'])) {
            $data['assy_number'] = trim((string) $data['assy_sirep']);
        }
        return $data;
    }

    public function model(array $row)
    {
        $this->rowCount++;
        
        $assyNumber = !empty($row['assy_sirep']) ? trim((string) $row['assy_sirep']) : ($row['assy_number'] ?? null);
        
        // Cek apakah assy_number sudah ada untuk carline ini
        $exists = Assy::where('assy_number', $assyNumber)
                            ->where('carline_id', $this->carlineId)
                            ->exists();
        
        if ($exists) {
            throw new \Exception("assy_number {$assyNumber} already exists for this Car Line");
        }
        
        return new Assy([
            'assy_number' => $assyNumber,
            'assy_code' => $row['assy_code'],
            'level' => $row['level'],
            'carline_id' => $this->carlineId,
            'pattern' => $row['pattern'] ?? null,
            'standard_sea_quantity' => $row['standard_sea_quantity'] ?? null,
            'standard_air_quantity' => $row['standard_air_quantity'] ?? null,
            'max_quantity_sea' => $row['max_quantity_sea'] ?? null,
            'max_quantity_air' => $row['max_quantity_air'] ?? null,
            'umh' => $row['umh'],
            'is_active' => true,
        ]);
    }
    
    public function rules(): array
    {
        return [
            'assy_number' => 'required|string',
            'assy_code' => 'required|string',
            'level' => 'required|string',
            'umh' => 'required|numeric',
            'pattern' => 'nullable|string',
            'standard_sea_quantity' => 'nullable|integer',
            'standard_air_quantity' => 'nullable|integer',
            'max_quantity_sea' => 'nullable|integer',
            'max_quantity_air' => 'nullable|integer',
        ];
    }
    
    public function getRowCount(): int
    {
        return $this->rowCount;
    }
    
    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            $this->validationErrors[] = "Row {$failure->row()}: " . implode(', ', $failure->errors());
        }
    }
    
    public function getErrors(): array
    {
        $errors = $this->validationErrors;
        foreach ($this->errors as $e) {
            if ($e instanceof \Throwable) {
                $errors[] = $e->getMessage();
            } else {
                $errors[] = (string) $e;
            }
        }
        return $errors;
    }
}
