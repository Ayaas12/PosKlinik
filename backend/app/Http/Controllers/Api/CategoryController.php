<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::withCount('drugs')
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category = Category::create($validated);
        return response()->json(['message' => 'Kategori berhasil ditambahkan.', 'category' => $category], 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update($validated);
        return response()->json(['message' => 'Kategori berhasil diperbarui.', 'category' => $category]);
    }

    public function destroy(Category $category)
    {
        if ($category->drugs()->exists()) {
            return response()->json(['message' => 'Kategori tidak dapat dihapus karena masih digunakan.'], 422);
        }
        $category->delete();
        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
