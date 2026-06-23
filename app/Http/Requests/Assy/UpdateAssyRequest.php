<?php

namespace App\Http\Requests\Assy;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carline_id' => 'required|exists:carline,id',
            'assy_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('assy')->ignore($this->route('assy')?->id),
            ],
            'assy_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('assy')->ignore($this->route('assy')?->id),
            ],
            'level' => 'required|string|max:20',
            'pattern' => 'nullable|string|max:255',
            'standard_sea_quantity' => 'nullable|integer|min:0',
            'standard_air_quantity' => 'nullable|integer|min:0',
            'max_quantity_sea' => 'nullable|integer|min:0',
            'max_quantity_air' => 'nullable|integer|min:0',
            'umh' => 'required|numeric|min:0|max:9999.999999',
            'is_active' => 'boolean',
        ];
    }
}
