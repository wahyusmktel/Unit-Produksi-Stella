<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\DanaQrisGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(Request $request, DanaQrisGateway $dana): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', Rule::exists('product_categories', 'slug')],
        ]);

        return Inertia::render('Store/Index', [
            'products' => Product::query()
                ->with(['category:id,name,slug', 'images:id,product_id,image_path,sort_order'])
                ->where('status', 'active')
                ->where('stock', '>', 0)
                ->when($filters['search'] ?? null, fn ($query, string $search) => $query
                    ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")))
                ->when($filters['category'] ?? null, fn ($query, string $slug) => $query
                    ->whereHas('category', fn ($query) => $query->where('slug', $slug)))
                ->orderByDesc('is_featured')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'categories' => ProductCategory::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($query) => $query->where('status', 'active')->where('stock', '>', 0))
                ->withCount(['products' => fn ($query) => $query->where('status', 'active')->where('stock', '>', 0)])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => ['search' => $filters['search'] ?? '', 'category' => $filters['category'] ?? ''],
            'payment' => ['qris_enabled' => $dana->configured()],
        ]);
    }

    public function order(Order $order): Response
    {
        return Inertia::render('Store/Order', [
            'order' => $order->load('items:id,order_id,product_name,sku,quantity,unit_price,subtotal'),
        ]);
    }
}
