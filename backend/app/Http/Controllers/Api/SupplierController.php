<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount('drugs')
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return response()->json($suppliers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'string', 'email', 'max:100'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'is_active'      => ['boolean'],
        ]);

        $supplier = Supplier::create($validated);
        return response()->json(['message' => 'Supplier berhasil ditambahkan.', 'supplier' => $supplier], 201);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'phone'          => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'string', 'email', 'max:100'],
            'address'        => ['nullable', 'string', 'max:500'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'is_active'      => ['boolean'],
        ]);

        $supplier->update($validated);
        return response()->json(['message' => 'Supplier berhasil diperbarui.', 'supplier' => $supplier]);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->drugs()->exists()) {
            return response()->json(['message' => 'Supplier tidak dapat dihapus karena masih digunakan.'], 422);
        }
        $supplier->delete();
        return response()->json(['message' => 'Supplier berhasil dihapus.']);
    }
}
