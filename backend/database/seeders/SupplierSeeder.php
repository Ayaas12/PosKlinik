<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'PT Kimia Farma Tbk',     'phone' => '021-3847254', 'email' => 'order@kimiafarma.co.id',    'address' => 'Jl. Veteran No.9, Jakarta Pusat', 'contact_person' => 'Bpk. Hendra'],
            ['name' => 'PT Kalbe Farma Tbk',     'phone' => '021-4287771', 'email' => 'distribusi@kalbe.co.id',    'address' => 'Jl. Let. Jend. Suprapto Kav.4, Jakarta Pusat', 'contact_person' => 'Ibu Ratna'],
            ['name' => 'PT Sanbe Farma',          'phone' => '022-6034699', 'email' => 'sales@sanbe.co.id',         'address' => 'Jl. Industri Cimareme No.8, Bandung', 'contact_person' => 'Bpk. Agus'],
            ['name' => 'PT Dexa Medica',          'phone' => '0711-811855', 'email' => 'info@dexamedica.com',       'address' => 'Jl. Jend. Basuki Rahmat No.5, Palembang', 'contact_person' => 'Ibu Maya'],
            ['name' => 'PT Mensa Bina Sukses',   'phone' => '021-5851234', 'email' => 'mbs@mensafarm.com',         'address' => 'Jl. Panjang No.68, Jakarta Barat', 'contact_person' => 'Bpk. Wahyu'],
        ];

        foreach ($suppliers as $sup) {
            Supplier::updateOrCreate(['name' => $sup['name']], $sup);
        }
    }
}
