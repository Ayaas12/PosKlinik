<?php

namespace Database\Seeders;

use App\Models\Drug;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotency guard — skip if transactions already exist (e.g. container restart)
        if (Transaction::exists()) {
            return;
        }

        $kasir = User::whereHas('role', fn($q) => $q->where('name', 'kasir'))->first();
        $drugs = Drug::where('is_active', true)->get();
        if (!$kasir || $drugs->isEmpty()) {
            return;
        }

        $counter = 1;
        // Generate 30 days of transactions
        for ($day = 30; $day >= 1; $day--) {
            $date = now()->subDays($day);
            $txPerDay = rand(5, 15);

            for ($t = 0; $t < $txPerDay; $t++) {
                $nomorTransaksi = 'TRX-' . $date->format('Ymd') . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
                $counter++;

                $itemCount = rand(1, 4);
                $selectedDrugs = $drugs->random($itemCount);
                $subtotal = 0;
                $transactionItems = [];

                foreach ($selectedDrugs as $drug) {
                    $qty = rand(1, 5);
                    $price = $drug->harga_jual;
                    $itemSubtotal = $qty * $price;
                    $subtotal += $itemSubtotal;

                    $transactionItems[] = [
                        'drug_id'   => $drug->id,
                        'drug_name' => $drug->name,
                        'harga_jual'=> $price,
                        'quantity'  => $qty,
                        'diskon'    => 0,
                        'subtotal'  => $itemSubtotal,
                    ];
                }

                $diskon = rand(0, 1) ? round($subtotal * 0.05, 0) : 0;
                $total = $subtotal - $diskon;
                $bayar = $total + rand(0, 5) * 1000;
                $kembalian = $bayar - $total;

                $transaction = Transaction::create([
                    'nomor_transaksi' => $nomorTransaksi,
                    'user_id'         => $kasir->id,
                    'subtotal'        => $subtotal,
                    'diskon'          => $diskon,
                    'pajak'           => 0,
                    'total'           => $total,
                    'bayar'           => $bayar,
                    'kembalian'       => $kembalian,
                    'metode_bayar'    => ['tunai', 'qris', 'transfer'][rand(0, 2)],
                    'status'          => 'selesai',
                    'created_at'      => $date,
                    'updated_at'      => $date,
                ]);

                foreach ($transactionItems as $item) {
                    $transaction->items()->create($item);
                }
            }
        }
    }
}
