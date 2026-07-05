<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Drug;
use App\Models\DrugUnit;
use Illuminate\Http\Request;

class DrugUnitController extends Controller
{
    /**
     * List all pricing units for a drug.
     */
    public function index(Drug $drug)
    {
        return response()->json($drug->units()->get());
    }

    /**
     * Create a new unit for a drug.
     */
    public function store(Request $request, Drug $drug)
    {
        $validated = $request->validate([
            'label'      => ['required', 'string', 'max:100'],
            'satuan'     => ['required', 'string', 'max:50'],
            'konversi'   => ['required', 'integer', 'min:1'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_default' => ['boolean'],
        ]);

        // If this unit is set as default, unset others
        if (!empty($validated['is_default'])) {
            $drug->units()->update(['is_default' => false]);
        }

        $unit = $drug->units()->create($validated);

        return response()->json(['message' => 'Unit berhasil ditambahkan.', 'unit' => $unit], 201);
    }

    /**
     * Update an existing unit.
     */
    public function update(Request $request, Drug $drug, DrugUnit $unit)
    {
        // Make sure the unit belongs to this drug
        abort_if($unit->drug_id !== $drug->id, 404);

        $validated = $request->validate([
            'label'      => ['required', 'string', 'max:100'],
            'satuan'     => ['required', 'string', 'max:50'],
            'konversi'   => ['required', 'integer', 'min:1'],
            'harga_jual' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_default' => ['boolean'],
        ]);

        if (!empty($validated['is_default'])) {
            $drug->units()->where('id', '!=', $unit->id)->update(['is_default' => false]);
        }

        $unit->update($validated);

        return response()->json(['message' => 'Unit berhasil diperbarui.', 'unit' => $unit]);
    }

    /**
     * Delete a unit.
     */
    public function destroy(Drug $drug, DrugUnit $unit)
    {
        abort_if($unit->drug_id !== $drug->id, 404);

        $unit->delete();

        return response()->json(['message' => 'Unit berhasil dihapus.']);
    }
}
