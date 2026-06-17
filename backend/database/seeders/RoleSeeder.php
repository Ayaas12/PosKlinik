<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',     'display_name' => 'Administrator',     'description' => 'Akses penuh ke semua fitur sistem'],
            ['name' => 'apoteker',  'display_name' => 'Apoteker',          'description' => 'Mengelola resep, stok, dan verifikasi obat'],
            ['name' => 'kasir',     'display_name' => 'Kasir',             'description' => 'Melakukan transaksi penjualan'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
