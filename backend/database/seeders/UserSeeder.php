<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole    = Role::where('name', 'admin')->first();
        $apotekerRole = Role::where('name', 'apoteker')->first();
        $kasirRole    = Role::where('name', 'kasir')->first();

        $users = [
            // Admin
            ['name' => 'Administrator',  'email' => 'admin@apotekalgenz.com',    'password' => 'Admin@12345', 'role_id' => $adminRole->id],
            // Apoteker
            ['name' => 'Apt. Siti Rahayu', 'email' => 'apoteker@apotekalgenz.com', 'password' => 'Apotek@1234', 'role_id' => $apotekerRole->id],
            ['name' => 'Apt. Budi Santoso', 'email' => 'budi@apotekalgenz.com',    'password' => 'Apotek@1234', 'role_id' => $apotekerRole->id],
            // Kasir
            ['name' => 'Dewi Anggraini', 'email' => 'kasir@apotekalgenz.com',    'password' => 'Kasir@12345', 'role_id' => $kasirRole->id],
            ['name' => 'Rudi Hartono',   'email' => 'rudi@apotekalgenz.com',     'password' => 'Kasir@12345', 'role_id' => $kasirRole->id],
        ];

        foreach ($users as $userData) {
            $userData['password'] = Hash::make($userData['password']);
            User::updateOrCreate(['email' => $userData['email']], $userData);
        }
    }
}
