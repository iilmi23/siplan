<?php

namespace App\Http\Requests\Assy;

use Illuminate\Foundation\Http\FormRequest;

class ImportAssySheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv',
            'sheet' => 'required|string',
            'carline_id' => [
                'nullable',
                function ($attribute, $value, $fail) {
                    if ($value !== null && $value !== '' && $value !== 'all' && !\App\Models\Carline::where('id', $value)->exists()) {
                        $fail('Car Line yang dipilih tidak valid.');
                    }
                }
            ],
        ];
    }
}
