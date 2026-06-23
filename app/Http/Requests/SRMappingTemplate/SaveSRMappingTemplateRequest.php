<?php

namespace App\Http\Requests\SRMappingTemplate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSRMappingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'orientation' => ['required', Rule::in(['vertical', 'horizontal', 'block'])],
            'sheet_index' => ['nullable', 'integer', 'min:0'],
            'header_row' => ['nullable', 'integer', 'min:1'],
            'data_start_row' => ['required', 'integer', 'min:1'],
            'assy_number_column' => ['required', 'string', 'max:8'],
            'qty_column' => ['nullable', 'required_if:orientation,vertical', 'string', 'max:8'],
            'qty_start_column' => ['nullable', 'required_if:orientation,horizontal', 'string', 'max:8'],
            'qty_end_column' => ['nullable', 'required_if:orientation,horizontal', 'string', 'max:8'],
            'date_header_row' => ['nullable', 'required_if:orientation,horizontal', 'integer', 'min:1'],
            'etd_column' => ['nullable', 'required_if:orientation,vertical', 'string', 'max:8'],
            'eta_column' => ['nullable', 'string', 'max:8'],
            'order_type_column' => ['nullable', 'string', 'max:8'],
            'default_order_type' => ['nullable', 'string', 'max:50'],
            'model_column' => ['nullable', 'string', 'max:8'],
            'family_column' => ['nullable', 'string', 'max:8'],
            'port_column' => ['nullable', 'string', 'max:8'],
            'month_column' => ['nullable', 'string', 'max:8'],
            'week_column' => ['nullable', 'string', 'max:8'],
            'year_column' => ['nullable', 'string', 'max:8'],
            'date_format' => ['nullable', 'string', 'max:50'],
            'skip_keywords' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'block_identifier' => ['nullable', 'string', 'max:255'],
            'label_column' => ['nullable', 'string', 'max:8'],
            'value_column' => ['nullable', 'string', 'max:8'],
            'date_label_column' => ['nullable', 'string', 'max:8'],
            'assy_number_row_offset' => ['nullable', 'integer', 'min:0'],
            'etd_row_offset' => ['nullable', 'integer', 'min:0'],
            'eta_row_offset' => ['nullable', 'integer', 'min:0'],
            'qty_row_offset' => ['nullable', 'integer', 'min:0'],
            'family_row_offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $headerRow = $this->input('header_row');
            $dataStartRow = $this->input('data_start_row');

            if ($headerRow !== null && $dataStartRow !== null && (int)$dataStartRow <= (int)$headerRow) {
                $validator->errors()->add('data_start_row', 'Data Start Row harus lebih besar dari Header Row.');
            }

            $isActive = filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            if ($isActive) {
                $customerId = $this->input('customer_id');
                $query = \App\Models\SRMappingTemplate::where('customer_id', $customerId)
                    ->where('is_active', true);

                $template = $this->route('sr_mapping_template');
                if ($template) {
                    $templateId = is_object($template) ? $template->id : $template;
                    $query->where('id', '!=', $templateId);
                }

                if ($query->exists()) {
                    $validator->errors()->add('is_active', 'Customer ini sudah memiliki template aktif di sistem. Harap nonaktifkan template aktif yang ada terlebih dahulu.');
                }
            }
        });
    }
}
