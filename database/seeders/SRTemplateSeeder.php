<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\SRMappingTemplate;

class SRTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            'SAI' => [
                'name' => 'SAI Default Template',
                'orientation' => 'horizontal',
                'sheet_index' => 1,
                'header_row' => 11,
                'data_start_row' => 13,
                'assy_number_column' => 'B',
                'qty_start_column' => 'F',
                'qty_end_column' => 'CZ',
                'date_header_row' => 10,
                'default_order_type' => 'FORECAST',
                'skip_keywords' => ['total', 'subtotal', 'grand total', 'balance', 'cum'],
                'is_active' => false,
            ],
            'TYC' => [
                'name' => 'TYC Default Template',
                'orientation' => 'horizontal',
                'sheet_index' => 0,
                'header_row' => 19,
                'data_start_row' => 20,
                'assy_number_column' => 'D',
                'qty_start_column' => 'H',
                'qty_end_column' => 'CZ',
                'date_header_row' => 18,
                'default_order_type' => 'FIRM',
                'skip_keywords' => ['total', 'subtotal', 'grand total', 'balance'],
                'is_active' => false,
            ],
            'YNA' => [
                'name' => 'YNA Default Template',
                'orientation' => 'vertical',
                'sheet_index' => 1,
                'header_row' => 1,
                'data_start_row' => 2,
                'assy_number_column' => 'A',
                'qty_column' => 'B',
                'etd_column' => 'C',
                'default_order_type' => 'FORECAST',
                'is_active' => false,
            ],
            'YC' => [
                'name' => 'YC Default Template',
                'orientation' => 'horizontal',
                'sheet_index' => 0,
                'header_row' => 20,
                'data_start_row' => 21,
                'assy_number_column' => 'E',
                'qty_start_column' => 'K',
                'qty_end_column' => 'CZ',
                'date_header_row' => 17,
                'default_order_type' => 'FORECAST',
                'is_active' => false,
            ],
        ];

        foreach ($templates as $code => $data) {
            $customer = Customer::where('code', $code)->first();
            if ($customer) {
                SRMappingTemplate::updateOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'name' => $data['name']
                    ],
                    $data
                );
            }
        }
    }
}
