<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Analgesik & Antipiretik',    'description' => 'Obat pereda nyeri dan penurun demam'],
            ['name' => 'Antibiotik',                  'description' => 'Obat untuk infeksi bakteri'],
            ['name' => 'Antasida & Lambung',          'description' => 'Obat gangguan saluran pencernaan'],
            ['name' => 'Antihipertensi',              'description' => 'Obat tekanan darah tinggi'],
            ['name' => 'Antidiabetes',                'description' => 'Obat kencing manis / diabetes'],
            ['name' => 'Antihistamin & Alergi',       'description' => 'Obat alergi dan reaksi hipersensitivitas'],
            ['name' => 'Vitamin & Suplemen',          'description' => 'Vitamin, mineral, dan suplemen kesehatan'],
            ['name' => 'Obat Batuk & Pilek',          'description' => 'Obat flu, batuk, dan ISPA'],
            ['name' => 'Obat Kulit Topikal',          'description' => 'Krim, salep, dan obat oles untuk kulit'],
            ['name' => 'Obat Mata & Telinga',         'description' => 'Tetes mata, salep mata, tetes telinga'],
            ['name' => 'Antifungi',                   'description' => 'Obat jamur dan infeksi fungal'],
            ['name' => 'Kolesterol & Jantung',        'description' => 'Obat kolesterol dan kardiovaskular'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
