<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Port;
use App\Models\Customer;

class PortSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil customer berdasarkan code
        $yna = Customer::where('code', 'YNA')->first();
        $tyc = Customer::where('code', 'TYC')->first();
        $yc = Customer::where('code', 'YC')->first();

        // Seeder port untuk YNA
        if ($yna) {
            Port::updateOrCreate(
                ['name' => 'MEMPHIS', 'customer_id' => $yna->id]
            );

            Port::updateOrCreate(
                ['name' => 'KITCHENER', 'customer_id' => $yna->id]
            );
        }

        // Seeder port untuk TYC
        if ($tyc) {
            Port::updateOrCreate(
                ['name' => 'KAO', 'customer_id' => $tyc->id]
            );
        }

        // Seeder port untuk YC
        if ($yc) {
            $ycPorts = ['HAKATA', 'MOJI', 'HIROSHIMA', 'SENDAI', 'NAGOYA'];
            foreach ($ycPorts as $portName) {
                Port::updateOrCreate(
                    ['name' => $portName, 'customer_id' => $yc->id]
                );
            }
        }
    }
}
