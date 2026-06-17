<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DrugController extends Controller
{
    public function index(Request $request)
    {
        $query = Drug::with(['category:id,name', 'supplier:id,name'])
            ->when($request->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('name', 'like', '%' . $request->search . '%')
                      ->orWhere('kode_obat', 'like', '%' . $request->search . '%')
                      ->orWhere('barcode', 'like', '%' . $request->search . '%')
                      ->orWhere('generic_name', 'like', '%' . $request->search . '%')
                )
            )
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->low_stock, fn($q) => $q->whereColumn('stok', '<=', 'stok_minimum'))
            ->when($request->near_expiry, fn($q) =>
                $q->whereNotNull('tanggal_kadaluarsa')
                  ->where('tanggal_kadaluarsa', '<=', now()->addDays(30))
            )
            ->when($request->stok_filter === 'low', fn($q) =>
                $q->where('stok', '>', 0)->whereColumn('stok', '<=', 'stok_minimum')
            )
            ->when($request->stok_filter === 'empty', fn($q) =>
                $q->where('stok', 0)
            );

        $drugs = $query->orderBy('name')->paginate($request->per_page ?? 15);

        return response()->json($drugs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_obat'          => ['required', 'string', 'max:50', 'unique:drugs,kode_obat'],
            'name'               => ['required', 'string', 'max:200'],
            'generic_name'       => ['nullable', 'string', 'max:200'],
            'category_id'        => ['required', 'exists:categories,id'],
            'supplier_id'        => ['nullable', 'exists:suppliers,id'],
            'barcode'            => ['nullable', 'string', 'max:100', 'unique:drugs,barcode'],
            'satuan'             => ['required', 'string', 'in:pcs,strip,botol,tube,kapsul,sachet,ampul'],
            'harga_beli'         => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'harga_jual'         => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stok'               => ['required', 'integer', 'min:0'],
            'stok_minimum'       => ['required', 'integer', 'min:0'],
            'tanggal_kadaluarsa' => ['nullable', 'date', 'after:today'],
            'lokasi_rak'         => ['nullable', 'string', 'max:50'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'memerlukan_resep'   => ['boolean'],
            'is_active'          => ['boolean'],
        ]);

        $drug = DB::transaction(function () use ($validated, $request) {
            $drug = Drug::create($validated);

            // Record initial stock movement
            if ($drug->stok > 0) {
                StockMovement::create([
                    'drug_id'    => $drug->id,
                    'user_id'    => $request->user()->id,
                    'type'       => 'masuk',
                    'quantity'   => $drug->stok,
                    'stok_before'=> 0,
                    'stok_after' => $drug->stok,
                    'catatan'    => 'Stok awal produk baru',
                ]);
            }

            return $drug;
        });

        return response()->json(['message' => 'Obat berhasil ditambahkan.', 'drug' => $drug->load(['category', 'supplier'])], 201);
    }

    public function show(Drug $drug)
    {
        return response()->json($drug->load(['category', 'supplier']));
    }

    public function update(Request $request, Drug $drug)
    {
        $validated = $request->validate([
            'kode_obat'          => ['required', 'string', 'max:50', Rule::unique('drugs', 'kode_obat')->ignore($drug->id)],
            'name'               => ['required', 'string', 'max:200'],
            'generic_name'       => ['nullable', 'string', 'max:200'],
            'category_id'        => ['required', 'exists:categories,id'],
            'supplier_id'        => ['nullable', 'exists:suppliers,id'],
            'barcode'            => ['nullable', 'string', 'max:100', Rule::unique('drugs', 'barcode')->ignore($drug->id)],
            'satuan'             => ['required', 'string', 'in:pcs,strip,botol,tube,kapsul,sachet,ampul'],
            'harga_beli'         => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'harga_jual'         => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'stok_minimum'       => ['required', 'integer', 'min:0'],
            'tanggal_kadaluarsa' => ['nullable', 'date'],
            'lokasi_rak'         => ['nullable', 'string', 'max:50'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'memerlukan_resep'   => ['boolean'],
            'is_active'          => ['boolean'],
        ]);

        $drug->update($validated);

        return response()->json(['message' => 'Obat berhasil diperbarui.', 'drug' => $drug->load(['category', 'supplier'])]);
    }

    public function destroy(Drug $drug)
    {
        if ($drug->transactionItems()->exists()) {
            return response()->json(['message' => 'Obat tidak dapat dihapus karena sudah ada dalam transaksi.'], 422);
        }

        $drug->delete();

        return response()->json(['message' => 'Obat berhasil dihapus.']);
    }

    /**
     * Adjust stock manually (apoteker/admin only).
     */
    public function adjustStock(Request $request, Drug $drug)
    {
        $validated = $request->validate([
            'type'     => ['required', 'string', 'in:masuk,keluar,penyesuaian'],
            'quantity' => ['required', 'integer', 'min:1'],
            'catatan'  => ['nullable', 'string', 'max:500'],
        ]);

        $result = DB::transaction(function () use ($validated, $drug, $request) {
            $stokBefore = $drug->stok;

            if ($validated['type'] === 'keluar') {
                if ($drug->stok < $validated['quantity']) {
                    return ['error' => 'Stok tidak mencukupi.'];
                }
                $drug->decrement('stok', $validated['quantity']);
                $stokAfter = $stokBefore - $validated['quantity'];
                $qty = -$validated['quantity'];
            } elseif ($validated['type'] === 'masuk') {
                $drug->increment('stok', $validated['quantity']);
                $stokAfter = $stokBefore + $validated['quantity'];
                $qty = $validated['quantity'];
            } else {
                // penyesuaian: set to exact value
                $validated['quantity'] = $request->validate(['target_stok' => ['required', 'integer', 'min:0']])['target_stok'] ?? $drug->stok;
                $qty = $validated['quantity'] - $stokBefore;
                $drug->update(['stok' => $validated['quantity']]);
                $stokAfter = $validated['quantity'];
            }

            StockMovement::create([
                'drug_id'    => $drug->id,
                'user_id'    => $request->user()->id,
                'type'       => $validated['type'],
                'quantity'   => $qty,
                'stok_before'=> $stokBefore,
                'stok_after' => $stokAfter,
                'catatan'    => $validated['catatan'] ?? null,
            ]);

            return ['stok' => $drug->fresh()->stok];
        });

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Stok berhasil disesuaikan.', 'stok' => $result['stok']]);
    }

    /**
     * Stock movement history for a specific drug.
     */
    public function movements(Request $request, Drug $drug)
    {
        $movements = $drug->stockMovements()
            ->with(['user:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        return response()->json($movements);
    }

    /**
     * Search drugs for POS (fast endpoint).
     */
    public function search(Request $request)
    {
        $query = $request->validate(['q' => ['required', 'string', 'min:1', 'max:100']])['q'];

        $drugs = Drug::with('category:id,name')
            ->where('is_active', true)
            ->where(fn($q) =>
                $q->where('name', 'like', '%' . $query . '%')
                  ->orWhere('kode_obat', 'like', '%' . $query . '%')
                  ->orWhere('barcode', $query)
            )
            ->select('id', 'kode_obat', 'name', 'harga_jual', 'stok', 'satuan', 'category_id', 'memerlukan_resep')
            ->limit(20)
            ->get();

        return response()->json($drugs);
    }
}
