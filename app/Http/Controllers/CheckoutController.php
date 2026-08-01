<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\DanaQrisGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function store(Request $request, DanaQrisGateway $dana): RedirectResponse
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'payment_method' => ['required', Rule::in(['cash', 'qris'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        if ($data['payment_method'] === 'qris' && ! $dana->configured()) {
            throw ValidationException::withMessages(['payment_method' => 'Pembayaran QRIS sedang disiapkan. Silakan pilih pembayaran tunai.']);
        }

        $order = DB::transaction(function () use ($data, $request): Order {
            $requested = $request->collect('items')->keyBy('product_id');
            $products = Product::query()
                ->whereKey($requested->keys())
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($products->count() !== $requested->count()) {
                throw ValidationException::withMessages(['items' => 'Salah satu produk sudah tidak tersedia. Muat ulang katalog.']);
            }

            $subtotal = 0.0;
            $profitTotal = 0.0;
            $orderItems = [];

            foreach ($products as $product) {
                $quantity = (int) data_get($requested->get($product->id), 'quantity');
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages(['items' => "Stok {$product->name} hanya tersisa {$product->stock}."]);
                }

                $lineSubtotal = (float) $product->price * $quantity;
                $lineProfit = ((float) $product->price - (float) $product->supplier_price) * $quantity;
                $subtotal += $lineSubtotal;
                $profitTotal += $lineProfit;
                $orderItems[] = [
                    'product_id' => $product->id,
                    'supplier_id' => $product->supplier_id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'supplier_price' => $product->supplier_price,
                    'subtotal' => $lineSubtotal,
                    'profit' => $lineProfit,
                ];
                $product->decrement('stock', $quantity);
            }

            $order = Order::create([
                'public_token' => (string) Str::uuid(),
                'order_number' => 'UP-'.now()->format('ymd').'-'.Str::upper(Str::random(7)),
                'user_id' => $request->user()?->id,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'profit_total' => $profitTotal,
                'notes' => $data['notes'] ?? null,
            ]);
            $order->items()->createMany($orderItems);

            return $order;
        });

        if ($order->payment_method === 'qris') {
            try {
                $result = $dana->generate($order, $request->ip());
                $order->update([
                    'payment_reference' => $result['reference'],
                    'qris_payload' => $result['payload'],
                    'qris_image' => $result['image'],
                    'qris_expires_at' => $result['expires_at'],
                ]);
            } catch (Throwable $exception) {
                Log::error('DANA QRIS generation failed.', ['order' => $order->order_number, 'message' => $exception->getMessage()]);

                return to_route('store.order', $order)->with('error', 'Pesanan tersimpan, tetapi QRIS belum dapat dibuat. Hubungi pengelola Unit Produksi.');
            }
        }

        return to_route('store.order', $order)->with('success', 'Pesanan berhasil dibuat.');
    }
}
