<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DrugBatchSeeder extends Seeder
{
    public function run(): void
    {
        // Get an apoteker user for received_by
        $apoteker = User::whereHas('role', fn($q) => $q->where('name', 'apoteker'))->first();
        $userId = $apoteker ? $apoteker->id : 1;

        // 1. Normal Batch - Paracetamol 500mg (OBT-001)
        $para = Drug::where('kode_obat', 'OBT-001')->first();
        if ($para) {
            DrugBatch::updateOrCreate(
                ['drug_id' => $para->id, 'batch_number' => 'BTH-PARA-001'],
                [
                    'supplier_id'        => $para->supplier_id,
                    'received_by'        => $userId,
                    'quantity_received'  => 200,
                    'quantity_remaining' => 200,
                    'harga_beli'         => $para->harga_beli,
                    'tanggal_kadaluarsa' => now()->addYears(2)->toDateString(),
                    'tanggal_diterima'   => now()->subMonths(1)->toDateString(),
                    'catatan'            => 'Penerimaan batch rutin paracetamol.',
                ]
            );
        }

        // 2. Near Expiry Batch - Captopril 25mg (OBT-010) - 45 days left
        $capto = Drug::where('kode_obat', 'OBT-010')->first();
        if ($capto) {
            DrugBatch::updateOrCreate(
                ['drug_id' => $capto->id, 'batch_number' => 'BTH-CAP-002'],
                [
                    'supplier_id'        => $capto->supplier_id,
                    'received_by'        => $userId,
                    'quantity_received'  => 25,
                    'quantity_remaining' => 8, // sisa obat di batch ini
                    'harga_beli'         => $capto->harga_beli,
                    'tanggal_kadaluarsa' => now()->addDays(45)->toDateString(),
                    'tanggal_diterima'   => now()->subMonths(2)->toDateString(),
                    'catatan'            => 'Batch uji lokasi obat mendekati kedaluwarsa.',
                ]
            );
        }

        // 3. Expired Batch - Glibenclamide 5mg (OBT-012)
        $glib = Drug::where('kode_obat', 'OBT-012')->first();
        if ($glib) {
            DrugBatch::updateOrCreate(
                ['drug_id' => $glib->id, 'batch_number' => 'BTH-GLIB-003'],
                [
                    'supplier_id'        => $glib->supplier_id,
                    'received_by'        => $userId,
                    'quantity_received'  => 30,
                    'quantity_remaining' => 5, // sisa obat di batch ini
                    'harga_beli'         => $glib->harga_beli,
                    'tanggal_kadaluarsa' => now()->subDays(10)->toDateString(),
                    'tanggal_diterima'   => now()->subMonths(3)->toDateString(),
                    'catatan'            => 'Batch obat kedaluwarsa untuk karantina.',
                ]
            );
        }
    }
}
