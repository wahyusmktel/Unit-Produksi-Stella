<?php

namespace App\Http\Controllers\AdminUp;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer', 'exists:product_categories,id'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'archived'])],
        ]);

        $products = Product::query()
            ->with('category:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, int $category) => $query->where('product_category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('AdminUp/Products/Index', [
            'products' => $products,
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'stats' => [
                'total' => Product::count(),
                'active' => Product::where('status', 'active')->count(),
                'low_stock' => Product::where('stock', '<=', 5)->count(),
                'inventory_value' => Product::query()->sum(DB::raw('price * stock')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $imagePath = $request->file('image')?->store('products', 'public');

        try {
            Product::create([
                ...$data,
                'slug' => $this->uniqueSlug($data['name']),
                'image_path' => $imagePath,
                'is_featured' => $request->boolean('is_featured'),
            ]);
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $newImagePath = $request->file('image')?->store('products', 'public');
        $oldImagePath = $product->image_path;

        try {
            $product->update([
                ...$data,
                'slug' => $this->uniqueSlug($data['name'], $product),
                'image_path' => $newImagePath ?: $oldImagePath,
                'is_featured' => $request->boolean('is_featured'),
            ]);
        } catch (Throwable $exception) {
            if ($newImagePath) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }

        if ($newImagePath && $oldImagePath) {
            Storage::disk('public')->delete($oldImagePath);
        }

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $imagePath = $product->image_path;
        $product->delete();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['required', 'string', 'max:60', Rule::unique('products', 'sku')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'unit' => ['required', Rule::in(['pcs', 'pack', 'box', 'bottle', 'portion', 'set', 'other'])],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_featured' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
    }

    private function uniqueSlug(string $name, ?Product $except = null): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $counter = 2;

        while (Product::where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except->id))->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
