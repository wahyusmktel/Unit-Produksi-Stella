<?php

namespace App\Http\Controllers\AdminUp;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $sales = [];
        $salesRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('supplier_id, SUM(order_items.subtotal) as revenue, SUM(order_items.profit) as profit')
            ->groupBy('supplier_id')
            ->get();

        foreach ($salesRows as $sale) {
            $sales[(int) $sale->supplier_id] = [
                'revenue' => (float) $sale->revenue,
                'profit' => (float) $sale->profit,
            ];
        }

        $suppliers = Supplier::query()
            ->withCount('products')
            ->withSum('products as total_stock', 'stock')
            ->withSum('products as inventory_cost', DB::raw('supplier_price * stock'))
            ->with(['products' => fn ($query) => $query
                ->select('id', 'supplier_id', 'name', 'sku', 'stock', 'unit', 'supplier_price', 'price')
                ->orderBy('name')])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query
                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(8)
            ->withQueryString()
            ->through(function (Supplier $supplier) use ($sales): Supplier {
                $sale = $sales[$supplier->id] ?? ['revenue' => 0.0, 'profit' => 0.0];
                $supplier->setAttribute('revenue', $sale['revenue']);
                $supplier->setAttribute('profit', $sale['profit']);

                return $supplier;
            });

        return Inertia::render('AdminUp/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $filters['search'] ?? ''],
            'summary' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('is_active', true)->count(),
                'revenue' => array_sum(array_column($sales, 'revenue')),
                'profit' => array_sum(array_column($sales, 'profit')),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Supplier::create([...$data, 'code' => $this->generateCode($data['name'])]);

        return back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->products()->exists() || $supplier->orderItems()->exists()) {
            return back()->withErrors(['supplier_delete' => 'Supplier masih memiliki produk atau riwayat transaksi sehingga tidak dapat dihapus. Nonaktifkan supplier sebagai gantinya.']);
        }

        $supplier->delete();

        return back()->with('success', 'Supplier berhasil dihapus.');
    }

    public function updateStock(Request $request, Supplier $supplier, Product $product): RedirectResponse
    {
        abort_unless($product->supplier_id === $supplier->id, 404);
        $data = $request->validate([
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'supplier_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);
        $product->update($data);

        return back()->with('success', "Stok {$product->name} berhasil diperbarui.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', Rule::in([true, false, 0, 1, '0', '1'])],
        ]);
    }

    private function generateCode(string $name): string
    {
        $prefix = substr(preg_replace('/[^A-Z0-9]/', '', Str::upper($name)) ?: 'SUP', 0, 4);

        do {
            $code = 'SUP-'.$prefix.'-'.Str::upper(Str::random(4));
        } while (Supplier::where('code', $code)->exists());

        return $code;
    }
}
