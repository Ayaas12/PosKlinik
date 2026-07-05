<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BatchController extends Controller
{
    /**
     * List all batches, optionally filtered by drug.
     */
    public function index(Request $request)
    {
        $query = DrugBatch::with([
            'drug:id,kode_obat,name,satuan,lokasi_rak',
            'supplier:id,name',
            'receivedBy:id,name',
        ])
        ->when($request->drug_id, fn($q) => $q->where('drug_id', $request->drug_id))
        ->when($request->search, fn($q) =>
            $q->where(fn($q) =>
                $q->where('batch_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('drug', fn($q) =>
                      $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('kode_obat', 'like', '%' . $request->search . '%')
                  )
            )
        )
        ->when($request->expiry_filter === 'expired', fn($q) =>
            $q->whereNotNull('tanggal_kadaluarsa')->where('tanggal_kadaluarsa', '<', now()->toDateString())
        )
        ->when($request->expiry_filter === 'near', fn($q) =>
            $q->whereNotNull('tanggal_kadaluarsa')
              ->where('tanggal_kadaluarsa', '>=', now()->toDateString())
              ->where('tanggal_kadaluarsa', '<=', now()->addDays(90)->toDateString())
        )
        ->when($request->has_stock, fn($q) => $q->where('quantity_remaining', '>', 0))
        ->orderByDesc('tanggal_diterima')
        ->orderByDesc('id');

        return response()->json($query->paginate($request->per_page ?? 15));
    }

    /**
     * List batches for a specific drug.
     */
    public function byDrug(Request $request, Drug $drug)
    {
        $batches = DrugBatch::with(['supplier:id,name', 'receivedBy:id,name'])
            ->where('drug_id', $drug->id)
            ->orderByDesc('tanggal_diterima')
            ->get();

        return response()->json([
            'drug'    => $drug->only(['id', 'kode_obat', 'name', 'stok', 'satuan', 'stok_minimum']),
            'batches' => $batches,
        ]);
    }

    /**
     * Receive a new batch (adds stock to the drug automatically).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id'             => ['required', 'exists:drugs,id'],
            'supplier_id'         => ['nullable', 'exists:suppliers,id'],
            'batch_number'        => ['required', 'string', 'max:100'],
            'quantity_received'   => ['required', 'integer', 'min:1'],
            'harga_beli'          => ['required', 'numeric', 'min:0'],
            'tanggal_kadaluarsa'  => ['nullable', 'date'],
            'tanggal_diterima'    => ['required', 'date'],
            'catatan'             => ['nullable', 'string', 'max:500'],
        ]);

        // Check for duplicate batch number for same drug
        $exists = DrugBatch::where('drug_id', $validated['drug_id'])
            ->where('batch_number', $validated['batch_number'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Nomor batch sudah ada untuk obat ini.',
                'errors'  => ['batch_number' => ['Nomor batch sudah digunakan untuk obat ini.']],
            ], 422);
        }

        $batch = DB::transaction(function () use ($validated, $request) {
            $drug = Drug::lockForUpdate()->findOrFail($validated['drug_id']);
            $stokBefore = $drug->stok;

            $batch = DrugBatch::create([
                ...$validated,
                'quantity_remaining' => $validated['quantity_received'],
                'received_by'        => $request->user()->id,
            ]);

            // Increment drug aggregate stock
            $drug->increment('stok', $validated['quantity_received']);

            // Update drug's purchase price to latest batch price
            $drug->update(['harga_beli' => $validated['harga_beli']]);

            // Record stock movement linked to this batch
            StockMovement::create([
                'drug_id'        => $drug->id,
                'user_id'        => $request->user()->id,
                'type'           => 'masuk',
                'quantity'       => $validated['quantity_received'],
                'stok_before'    => $stokBefore,
                'stok_after'     => $stokBefore + $validated['quantity_received'],
                'reference_type' => 'batch',
                'reference_id'   => $batch->id,
                'batch_id'       => $batch->id,
                'catatan'        => 'Penerimaan batch ' . $batch->batch_number .
                                    ($validated['catatan'] ? ' — ' . $validated['catatan'] : ''),
            ]);

            return $batch->load(['drug:id,kode_obat,name,stok,satuan,lokasi_rak', 'supplier:id,name', 'receivedBy:id,name']);
        });

        return response()->json([
            'message' => 'Batch berhasil diterima dan stok diperbarui.',
            'batch'   => $batch,
        ], 201);
    }

    /**
     * Show a single batch.
     */
    public function show(DrugBatch $batch)
    {
        return response()->json(
            $batch->load(['drug:id,kode_obat,name,stok,satuan,lokasi_rak', 'supplier:id,name', 'receivedBy:id,name'])
        );
    }

    /**
     * Update batch notes / expiry (quantity cannot be changed; use adjustStock instead).
     */
    public function update(Request $request, DrugBatch $batch)
    {
        $validated = $request->validate([
            'tanggal_kadaluarsa' => ['nullable', 'date'],
            'catatan'            => ['nullable', 'string', 'max:500'],
            'supplier_id'        => ['nullable', 'exists:suppliers,id'],
        ]);

        $batch->update($validated);

        return response()->json([
            'message' => 'Batch berhasil diperbarui.',
            'batch'   => $batch->load(['drug', 'supplier', 'receivedBy:id,name']),
        ]);
    }

    /**
     * Summary stats for the batch management page header.
     */
    public function summary()
    {
        $total       = DrugBatch::count();
        $expired     = DrugBatch::whereNotNull('tanggal_kadaluarsa')
                          ->where('tanggal_kadaluarsa', '<', now()->toDateString())
                          ->where('quantity_remaining', '>', 0)
                          ->count();
        $nearExpiry  = DrugBatch::whereNotNull('tanggal_kadaluarsa')
                          ->where('tanggal_kadaluarsa', '>=', now()->toDateString())
                          ->where('tanggal_kadaluarsa', '<=', now()->addDays(90)->toDateString())
                          ->where('quantity_remaining', '>', 0)
                          ->count();
        $thisMonth   = DrugBatch::whereMonth('tanggal_diterima', now()->month)
                          ->whereYear('tanggal_diterima', now()->year)
                          ->count();

        return response()->json(compact('total', 'expired', 'nearExpiry', 'thisMonth'));
    }
}
