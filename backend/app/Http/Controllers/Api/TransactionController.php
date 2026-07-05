<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugUnit;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::with(['user:id,name', 'items'])
            ->when($request->search, fn($q) =>
                $q->where('nomor_transaksi', 'like', '%' . $request->search . '%')
            )
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 15);

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'diskon'               => ['nullable', 'numeric', 'min:0'],
            'bayar'                => ['required', 'numeric', 'min:0'],
            'metode_bayar'         => ['required', 'string', 'in:tunai,qris,transfer,kartu'],
            'catatan'              => ['nullable', 'string', 'max:500'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.drug_id'      => ['required', 'exists:drugs,id'],
            'items.*.drug_unit_id' => ['nullable', 'exists:drug_units,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.diskon'       => ['nullable', 'numeric', 'min:0'],
        ]);

        $result = DB::transaction(function () use ($validated, $request) {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $drug = Drug::lockForUpdate()->findOrFail($item['drug_id']);

                // Resolve unit (if provided)
                $unit     = null;
                $konversi = 1;
                $satuan   = $drug->satuan;

                if (!empty($item['drug_unit_id'])) {
                    $unit = DrugUnit::find($item['drug_unit_id']);
                    if ($unit && $unit->drug_id === $drug->id) {
                        $konversi = $unit->konversi;
                        $satuan   = $unit->satuan;
                    } else {
                        $unit = null; // invalid unit, fall back
                    }
                }

                // Stock deduction uses konversi multiplier
                $stockRequired = $item['quantity'] * $konversi;

                if ($drug->stok < $stockRequired) {
                    throw new \Exception("Stok {$drug->name} tidak mencukupi. Tersisa {$drug->stok} (dibutuhkan {$stockRequired}).");
                }

                $harga       = $unit ? (float) $unit->harga_jual : (float) $drug->harga_jual;
                $itemDiskon  = $item['diskon'] ?? 0;
                $itemSubtotal = ($harga * $item['quantity']) - $itemDiskon;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'drug'         => $drug,
                    'unit'         => $unit,
                    'quantity'     => $item['quantity'],
                    'harga'        => $harga,
                    'satuan'       => $satuan,
                    'konversi'     => $konversi,
                    'stockRequired'=> $stockRequired,
                    'diskon'       => $itemDiskon,
                    'subtotal'     => $itemSubtotal,
                ];
            }

            $diskon  = $validated['diskon'] ?? 0;
            $total   = $subtotal - $diskon;
            $bayar   = $validated['bayar'];
            $kembalian = $bayar - $total;

            if ($kembalian < 0) {
                throw new \Exception('Jumlah bayar kurang dari total transaksi.');
            }

            // Generate transaction number
            $nomor = 'TRX-' . now()->format('Ymd') . '-' . str_pad(
                Transaction::whereDate('created_at', now()->toDateString())->count() + 1, 4, '0', STR_PAD_LEFT
            );

            $transaction = Transaction::create([
                'nomor_transaksi' => $nomor,
                'user_id'         => $request->user()->id,
                'subtotal'        => $subtotal,
                'diskon'          => $diskon,
                'pajak'           => 0,
                'total'           => $total,
                'bayar'           => $bayar,
                'kembalian'       => $kembalian,
                'metode_bayar'    => $validated['metode_bayar'],
                'status'          => 'selesai',
                'catatan'         => $validated['catatan'] ?? null,
            ]);

            foreach ($itemsData as $item) {
                $stokBefore = $item['drug']->stok;

                $transaction->items()->create([
                    'drug_id'      => $item['drug']->id,
                    'drug_unit_id' => $item['unit']?->id,
                    'drug_name'    => $item['drug']->name,
                    'harga_jual'   => $item['harga'],
                    'satuan'       => $item['satuan'],
                    'konversi'     => $item['konversi'],
                    'quantity'     => $item['quantity'],
                    'diskon'       => $item['diskon'],
                    'subtotal'     => $item['subtotal'],
                ]);

                // Decrement stock using konversi factor
                $item['drug']->decrement('stok', $item['stockRequired']);

                // Record stock movement
                StockMovement::create([
                    'drug_id'        => $item['drug']->id,
                    'user_id'        => $request->user()->id,
                    'type'           => 'keluar',
                    'quantity'       => -$item['quantity'],
                    'stok_before'    => $stokBefore,
                    'stok_after'     => $stokBefore - $item['quantity'],
                    'reference_type' => 'transaction',
                    'reference_id'   => $transaction->id,
                    'catatan'        => 'Penjualan ' . $transaction->nomor_transaksi,
                ]);
            }

            return $transaction->load(['items', 'user:id,name']);
        });

        return response()->json([
            'message'     => 'Transaksi berhasil.',
            'transaction' => $result,
        ], 201);
    }

    public function show(Transaction $transaction)
    {
        return response()->json(
            $transaction->load(['items.drug:id,name,kode_obat', 'user:id,name'])
        );
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        if ($transaction->status !== 'selesai') {
            return response()->json(['message' => 'Transaksi ini tidak dapat dibatalkan.'], 422);
        }

        $this->authorize('cancel', $transaction);

        DB::transaction(function () use ($transaction, $request) {
            // Restore stock (account for konversi so 1 box restores box × konversi units)
            foreach ($transaction->items as $item) {
                $drug = Drug::lockForUpdate()->find($item->drug_id);
                if ($drug) {
                    $konversi   = $item->konversi ?? 1;
                    $restoreQty = $item->quantity * $konversi;
                    $stokBefore = $drug->stok;
                    $drug->increment('stok', $restoreQty);
                    StockMovement::create([
                        'drug_id'        => $drug->id,
                        'user_id'        => $request->user()->id,
                        'type'           => 'masuk',
                        'quantity'       => $restoreQty,
                        'stok_before'    => $stokBefore,
                        'stok_after'     => $stokBefore + $restoreQty,
                        'reference_type' => 'transaction',
                        'reference_id'   => $transaction->id,
                        'catatan'        => 'Pembatalan transaksi ' . $transaction->nomor_transaksi,
                    ]);
                }
            }

            $transaction->update(['status' => 'dibatalkan']);
        });

        return response()->json(['message' => 'Transaksi berhasil dibatalkan.']);
    }
}
