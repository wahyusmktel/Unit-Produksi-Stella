<?php

namespace App\Http\Controllers\AdminUp;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'supplier' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $products = Product::query()
            ->with([
                'category:id,name',
                'supplier:id,name,code',
                'images:id,product_id,image_path,sort_order',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, int $category) => $query->where('product_category_id', $category))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['supplier'] ?? null, fn ($query, int $supplier) => $query->where('supplier_id', $supplier))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('AdminUp/Products/Index', [
            'products' => $products,
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['category'] ?? '',
                'status' => $filters['status'] ?? '',
                'supplier' => $filters['supplier'] ?? '',
            ],
            'stats' => [
                'total' => Product::count(),
                'active' => Product::where('status', 'active')->count(),
                'low_stock' => Product::where('stock', '<=', 5)->count(),
                'inventory_value' => Product::query()->sum(DB::raw('price * stock')),
                'inventory_cost' => Product::query()->sum(DB::raw('supplier_price * stock')),
                'projected_profit' => Product::query()->sum(DB::raw('(price - supplier_price) * stock')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $imagePaths = $this->storeUploadedImages($request);

        try {
            DB::transaction(function () use ($data, $imagePaths, $request): void {
                $category = ProductCategory::query()
                    ->whereKey((int) $data['product_category_id'])
                    ->firstOrFail();
                $product = Product::create([
                    ...Arr::except($data, ['images', 'remove_image_ids']),
                    'slug' => $this->uniqueSlug($data['name']),
                    'sku' => $this->generateSku($category),
                    'image_path' => $imagePaths[0] ?? null,
                    'is_featured' => $request->boolean('is_featured'),
                ]);

                foreach ($imagePaths as $sortOrder => $imagePath) {
                    $product->images()->create([
                        'image_path' => $imagePath,
                        'sort_order' => $sortOrder,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePaths);

            throw $exception;
        }

        return back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $newImagePaths = $this->storeUploadedImages($request);
        $removeImageIds = array_map('intval', $data['remove_image_ids'] ?? []);
        $removedImagePaths = $product->images()
            ->whereKey($removeImageIds)
            ->pluck('image_path')
            ->all();

        try {
            DB::transaction(function () use ($data, $newImagePaths, $product, $removeImageIds, $request): void {
                if ($removeImageIds !== []) {
                    $product->images()->whereKey($removeImageIds)->delete();
                }

                $sortOrder = 0;
                $product->images()->orderBy('sort_order')->orderBy('id')->get()
                    ->each(function ($image) use (&$sortOrder): void {
                        $image->update(['sort_order' => $sortOrder++]);
                    });

                foreach ($newImagePaths as $imagePath) {
                    $product->images()->create([
                        'image_path' => $imagePath,
                        'sort_order' => $sortOrder++,
                    ]);
                }

                $product->update([
                    ...Arr::except($data, ['images', 'remove_image_ids']),
                    'slug' => $this->uniqueSlug($data['name'], $product),
                    'image_path' => $product->images()->orderBy('sort_order')->value('image_path'),
                    'is_featured' => $request->boolean('is_featured'),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newImagePaths);

            throw $exception;
        }

        Storage::disk('public')->delete($removedImagePaths);

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $imagePaths = $product->images()->pluck('image_path')
            ->push($product->image_path)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $product->delete();

        Storage::disk('public')->delete($imagePaths);

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'product_category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:160'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'supplier_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'lte:price'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'unit' => ['required', Rule::in(['pcs', 'pack', 'box', 'bottle', 'portion', 'set', 'other'])],
            'status' => ['required', Rule::in(['draft', 'active', 'archived'])],
            'is_featured' => ['nullable', 'boolean'],
            'images' => ['nullable', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:product_images,id'],
        ]);

        $removeImageIds = array_map('intval', $data['remove_image_ids'] ?? []);
        $existingCount = 0;

        if ($product) {
            $ownedRemoveCount = $product->images()->whereKey($removeImageIds)->count();

            if ($ownedRemoveCount !== count($removeImageIds)) {
                throw ValidationException::withMessages([
                    'remove_image_ids' => 'Foto yang dipilih tidak termasuk dalam produk ini.',
                ]);
            }

            $existingCount = $product->images()->count() - $ownedRemoveCount;
        }

        $newImageCount = count($request->file('images', []));
        if ($existingCount + $newImageCount > 8) {
            throw ValidationException::withMessages([
                'images' => 'Maksimal delapan foto untuk setiap produk.',
            ]);
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function storeUploadedImages(Request $request): array
    {
        return collect($request->file('images', []))
            ->map(fn ($image): string => $image->store('products', 'public'))
            ->values()
            ->all();
    }

    private function generateSku(ProductCategory $category): string
    {
        $categoryCode = preg_replace('/[^A-Z0-9]/', '', Str::upper($category->slug));
        $categoryCode = substr($categoryCode ?: 'GEN', 0, 4);

        do {
            $sku = 'UP-'.$categoryCode.'-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
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
