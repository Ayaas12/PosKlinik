<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Drug;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DrugSeeder extends Seeder
{
    public function run(): void
    {
        $analgesik   = Category::where('name', 'Analgesik & Antipiretik')->first()->id;
        $antibiotik  = Category::where('name', 'Antibiotik')->first()->id;
        $antasida    = Category::where('name', 'Antasida & Lambung')->first()->id;
        $antihiper   = Category::where('name', 'Antihipertensi')->first()->id;
        $antidiab    = Category::where('name', 'Antidiabetes')->first()->id;
        $antihistam  = Category::where('name', 'Antihistamin & Alergi')->first()->id;
        $vitamin     = Category::where('name', 'Vitamin & Suplemen')->first()->id;
        $batuk       = Category::where('name', 'Obat Batuk & Pilek')->first()->id;
        $kulit       = Category::where('name', 'Obat Kulit Topikal')->first()->id;
        $kolesterol  = Category::where('name', 'Kolesterol & Jantung')->first()->id;

        $kimiaFarma  = Supplier::where('name', 'PT Kimia Farma Tbk')->first()->id;
        $kalbe       = Supplier::where('name', 'PT Kalbe Farma Tbk')->first()->id;
        $sanbe       = Supplier::where('name', 'PT Sanbe Farma')->first()->id;
        $dexa        = Supplier::where('name', 'PT Dexa Medica')->first()->id;

        $drugs = [
            // Analgesik
            ['kode_obat' => 'OBT-001', 'name' => 'Paracetamol 500mg',        'generic_name' => 'Acetaminophen',     'category_id' => $analgesik,  'supplier_id' => $kimiaFarma, 'satuan' => 'strip', 'harga_beli' => 3500,  'harga_jual' => 5000,   'stok' => 200, 'stok_minimum' => 50,  'tanggal_kadaluarsa' => '2027-06-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-002', 'name' => 'Ibuprofen 400mg',           'generic_name' => 'Ibuprofen',         'category_id' => $analgesik,  'supplier_id' => $kalbe,     'satuan' => 'strip', 'harga_beli' => 4000,  'harga_jual' => 6000,   'stok' => 150, 'stok_minimum' => 40,  'tanggal_kadaluarsa' => '2027-03-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-003', 'name' => 'Asam Mefenamat 500mg',      'generic_name' => 'Mefenamic Acid',    'category_id' => $analgesik,  'supplier_id' => $dexa,      'satuan' => 'strip', 'harga_beli' => 5000,  'harga_jual' => 8000,   'stok' => 100, 'stok_minimum' => 30,  'tanggal_kadaluarsa' => '2026-12-01', 'memerlukan_resep' => true],
            // Antibiotik
            ['kode_obat' => 'OBT-004', 'name' => 'Amoxicillin 500mg',         'generic_name' => 'Amoxicillin',       'category_id' => $antibiotik, 'supplier_id' => $sanbe,     'satuan' => 'strip', 'harga_beli' => 6000,  'harga_jual' => 10000,  'stok' => 80,  'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2026-09-01', 'memerlukan_resep' => true],
            ['kode_obat' => 'OBT-005', 'name' => 'Ciprofloxacin 500mg',       'generic_name' => 'Ciprofloxacin',     'category_id' => $antibiotik, 'supplier_id' => $kimiaFarma,'satuan' => 'strip', 'harga_beli' => 8000,  'harga_jual' => 13000,  'stok' => 60,  'stok_minimum' => 15,  'tanggal_kadaluarsa' => '2026-11-01', 'memerlukan_resep' => true],
            ['kode_obat' => 'OBT-006', 'name' => 'Metronidazole 500mg',       'generic_name' => 'Metronidazole',     'category_id' => $antibiotik, 'supplier_id' => $dexa,      'satuan' => 'strip', 'harga_beli' => 4500,  'harga_jual' => 7500,   'stok' => 70,  'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2027-01-01', 'memerlukan_resep' => true],
            // Antasida
            ['kode_obat' => 'OBT-007', 'name' => 'Antasida DOEN',             'generic_name' => 'Al(OH)3 + Mg(OH)2','category_id' => $antasida,   'supplier_id' => $kimiaFarma,'satuan' => 'botol', 'harga_beli' => 8000,  'harga_jual' => 12000,  'stok' => 120, 'stok_minimum' => 25,  'tanggal_kadaluarsa' => '2027-08-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-008', 'name' => 'Omeprazole 20mg',           'generic_name' => 'Omeprazole',        'category_id' => $antasida,   'supplier_id' => $kalbe,     'satuan' => 'strip', 'harga_beli' => 7000,  'harga_jual' => 11000,  'stok' => 90,  'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2027-04-01', 'memerlukan_resep' => true],
            // Antihipertensi
            ['kode_obat' => 'OBT-009', 'name' => 'Amlodipine 5mg',            'generic_name' => 'Amlodipine',        'category_id' => $antihiper,  'supplier_id' => $dexa,      'satuan' => 'strip', 'harga_beli' => 5500,  'harga_jual' => 9000,   'stok' => 110, 'stok_minimum' => 30,  'tanggal_kadaluarsa' => '2027-05-01', 'memerlukan_resep' => true],
            ['kode_obat' => 'OBT-010', 'name' => 'Captopril 25mg',            'generic_name' => 'Captopril',         'category_id' => $antihiper,  'supplier_id' => $kimiaFarma,'satuan' => 'strip', 'harga_beli' => 4000,  'harga_jual' => 7000,   'stok' => 8,   'stok_minimum' => 25,  'tanggal_kadaluarsa' => '2026-10-15', 'memerlukan_resep' => true],
            // Antidiabetes
            ['kode_obat' => 'OBT-011', 'name' => 'Metformin 500mg',           'generic_name' => 'Metformin HCl',     'category_id' => $antidiab,   'supplier_id' => $sanbe,     'satuan' => 'strip', 'harga_beli' => 3000,  'harga_jual' => 5500,   'stok' => 130, 'stok_minimum' => 30,  'tanggal_kadaluarsa' => '2027-02-01', 'memerlukan_resep' => true],
            ['kode_obat' => 'OBT-012', 'name' => 'Glibenclamide 5mg',         'generic_name' => 'Glibenclamide',     'category_id' => $antidiab,   'supplier_id' => $kimiaFarma,'satuan' => 'strip', 'harga_beli' => 2500,  'harga_jual' => 4500,   'stok' => 5,   'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2026-08-20', 'memerlukan_resep' => true],
            // Antihistamin
            ['kode_obat' => 'OBT-013', 'name' => 'Cetirizine 10mg',           'generic_name' => 'Cetirizine HCl',    'category_id' => $antihistam, 'supplier_id' => $kalbe,     'satuan' => 'strip', 'harga_beli' => 3500,  'harga_jual' => 6000,   'stok' => 140, 'stok_minimum' => 35,  'tanggal_kadaluarsa' => '2027-07-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-014', 'name' => 'Loratadine 10mg',           'generic_name' => 'Loratadine',        'category_id' => $antihistam, 'supplier_id' => $sanbe,     'satuan' => 'strip', 'harga_beli' => 4000,  'harga_jual' => 7000,   'stok' => 100, 'stok_minimum' => 25,  'tanggal_kadaluarsa' => '2027-06-01', 'memerlukan_resep' => false],
            // Vitamin
            ['kode_obat' => 'OBT-015', 'name' => 'Vitamin C 500mg',           'generic_name' => 'Ascorbic Acid',     'category_id' => $vitamin,    'supplier_id' => $kalbe,     'satuan' => 'botol', 'harga_beli' => 25000, 'harga_jual' => 40000,  'stok' => 60,  'stok_minimum' => 15,  'tanggal_kadaluarsa' => '2027-09-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-016', 'name' => 'Vitamin B Kompleks',        'generic_name' => 'B1+B6+B12',         'category_id' => $vitamin,    'supplier_id' => $kimiaFarma,'satuan' => 'botol', 'harga_beli' => 20000, 'harga_jual' => 32000,  'stok' => 45,  'stok_minimum' => 10,  'tanggal_kadaluarsa' => '2027-10-01', 'memerlukan_resep' => false],
            // Batuk & Pilek
            ['kode_obat' => 'OBT-017', 'name' => 'OBH Combi Batuk Berdahak', 'generic_name' => 'Guaifenesin',        'category_id' => $batuk,      'supplier_id' => $kalbe,     'satuan' => 'botol', 'harga_beli' => 15000, 'harga_jual' => 25000,  'stok' => 75,  'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2027-03-01', 'memerlukan_resep' => false],
            ['kode_obat' => 'OBT-018', 'name' => 'CTM (Chlorpheniramine)',    'generic_name' => 'Chlorpheniramine',   'category_id' => $batuk,      'supplier_id' => $kimiaFarma,'satuan' => 'strip', 'harga_beli' => 2000,  'harga_jual' => 3500,   'stok' => 200, 'stok_minimum' => 50,  'tanggal_kadaluarsa' => '2027-12-01', 'memerlukan_resep' => false],
            // Kulit
            ['kode_obat' => 'OBT-019', 'name' => 'Hidrokortison Krim 2.5%',  'generic_name' => 'Hydrocortisone',     'category_id' => $kulit,      'supplier_id' => $dexa,      'satuan' => 'tube',  'harga_beli' => 6000,  'harga_jual' => 10000,  'stok' => 50,  'stok_minimum' => 15,  'tanggal_kadaluarsa' => '2027-01-01', 'memerlukan_resep' => false],
            // Kolesterol
            ['kode_obat' => 'OBT-020', 'name' => 'Simvastatin 20mg',          'generic_name' => 'Simvastatin',        'category_id' => $kolesterol, 'supplier_id' => $sanbe,     'satuan' => 'strip', 'harga_beli' => 5500,  'harga_jual' => 9000,   'stok' => 85,  'stok_minimum' => 20,  'tanggal_kadaluarsa' => '2027-05-01', 'memerlukan_resep' => true],
        ];

        foreach ($drugs as $drug) {
            Drug::updateOrCreate(['kode_obat' => $drug['kode_obat']], $drug);
        }
    }
}
