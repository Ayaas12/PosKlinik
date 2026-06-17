<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Seeder;

class DoctorPatientSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = [
            ['name' => 'dr. Ahmad Fauzi, Sp.PD',    'str_number' => 'STR-001/2020', 'specialization' => 'Penyakit Dalam',    'phone' => '0812-1111-2222', 'clinic' => 'Klinik Sehat Bersama'],
            ['name' => 'dr. Rina Kusuma, Sp.A',      'str_number' => 'STR-002/2020', 'specialization' => 'Anak',              'phone' => '0813-2222-3333', 'clinic' => 'RS Ibu dan Anak'],
            ['name' => 'dr. Hendra Wijaya, Sp.JP',   'str_number' => 'STR-003/2021', 'specialization' => 'Jantung & Pembuluh','phone' => '0821-3333-4444', 'clinic' => 'Klinik Jantung Sehat'],
            ['name' => 'dr. Sari Dewi, Umum',        'str_number' => 'STR-004/2022', 'specialization' => 'Umum',              'phone' => '0822-4444-5555', 'clinic' => 'Puskesmas Algenz'],
            ['name' => 'dr. Bambang Purnomo, Sp.OG', 'str_number' => 'STR-005/2021', 'specialization' => 'Kandungan',         'phone' => '0851-5555-6666', 'clinic' => 'RS Bunda'],
        ];

        foreach ($doctors as $doc) {
            Doctor::updateOrCreate(['str_number' => $doc['str_number']], $doc);
        }

        $patients = [
            ['name' => 'Budi Santoso',   'nik' => '3271010101800001', 'phone' => '0812-0001-0001', 'address' => 'Jl. Mawar No.1, Bandung', 'tanggal_lahir' => '1980-01-01', 'jenis_kelamin' => 'L'],
            ['name' => 'Siti Aminah',    'nik' => '3271015502850002', 'phone' => '0813-0002-0002', 'address' => 'Jl. Melati No.5, Bandung', 'tanggal_lahir' => '1985-02-15', 'jenis_kelamin' => 'P'],
            ['name' => 'Rendi Setiawan', 'nik' => '3271010303900003', 'phone' => '0821-0003-0003', 'address' => 'Jl. Anggrek No.10, Bandung','tanggal_lahir' => '1990-03-03', 'jenis_kelamin' => 'L'],
            ['name' => 'Diana Putri',    'nik' => '3271014404920004', 'phone' => '0822-0004-0004', 'address' => 'Jl. Kamboja No.2, Bandung', 'tanggal_lahir' => '1992-04-04', 'jenis_kelamin' => 'P'],
            ['name' => 'Eko Prasetyo',   'nik' => '3271010505750005', 'phone' => '0851-0005-0005', 'address' => 'Jl. Dahlia No.7, Bandung',  'tanggal_lahir' => '1975-05-05', 'jenis_kelamin' => 'L'],
            ['name' => 'Nurul Hidayah',  'nik' => '3271016606880006', 'phone' => '0857-0006-0006', 'address' => 'Jl. Flamboyan No.3, Bandung','tanggal_lahir' => '1988-06-06', 'jenis_kelamin' => 'P'],
            ['name' => 'Yusuf Rahman',   'nik' => '3271010707820007', 'phone' => '0878-0007-0007', 'address' => 'Jl. Cempaka No.9, Bandung', 'tanggal_lahir' => '1982-07-07', 'jenis_kelamin' => 'L'],
            ['name' => 'Indah Lestari',  'nik' => '3271018808950008', 'phone' => '0896-0008-0008', 'address' => 'Jl. Kenanga No.12, Bandung','tanggal_lahir' => '1995-08-08', 'jenis_kelamin' => 'P'],
        ];

        foreach ($patients as $patient) {
            Patient::updateOrCreate(['nik' => $patient['nik']], $patient);
        }
    }
}
