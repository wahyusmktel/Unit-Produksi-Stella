<?php

namespace App\Http\Controllers\AdminUp;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('product_categories', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        ProductCategory::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'is_active' => true,
        ]);

        return back()->with('success', 'Kategori produk berhasil ditambahkan.');
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->withErrors([
                'category_delete' => 'Kategori masih digunakan oleh produk dan belum dapat dihapus.',
            ]);
        }

        $category->delete();

        return back()->with('success', 'Kategori produk berhasil dihapus.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $counter = 2;

        while (ProductCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
