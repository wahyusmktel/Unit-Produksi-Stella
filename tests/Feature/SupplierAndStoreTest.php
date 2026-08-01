<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierAndStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_seeder_creates_suppliers_and_ten_products(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertDatabaseCount('suppliers', 3);
        $this->assertDatabaseCount('products', 10);
        $this->assertSame(10, Product::where('status', 'active')->count());
    }

    public function test_public_store_is_available_for_guests(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->get(route('store.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Store/Index')
                ->has('products.data', 10)
                ->where('payment.qris_enabled', false));
    }

    public function test_guest_can_checkout_with_cash_and_stock_and_profit_are_snapshotted(): void
    {
        $product = $this->product(stock: 8, price: 15000, supplierPrice: 9000);

        $response = $this->post(route('store.checkout'), [
            'customer_name' => 'Pembeli Guest',
            'customer_email' => 'guest@example.test',
            'customer_phone' => '081234567890',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('store.order', $order));
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'total' => 45000,
            'profit_total' => 18000,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'quantity' => 3,
            'unit_price' => 15000,
            'supplier_price' => 9000,
            'profit' => 18000,
        ]);
    }

    public function test_checkout_rejects_quantity_above_current_stock(): void
    {
        $product = $this->product(stock: 2);

        $this->from(route('store.index'))->post(route('store.checkout'), [
            'customer_name' => 'Pembeli',
            'customer_phone' => '081234567890',
            'payment_method' => 'cash',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertRedirect(route('store.index'))->assertSessionHasErrors('items');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_qris_checkout_is_rejected_until_dana_is_configured(): void
    {
        $product = $this->product();

        $this->from(route('store.index'))->post(route('store.checkout'), [
            'customer_name' => 'Pembeli',
            'customer_phone' => '081234567890',
            'payment_method' => 'qris',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertRedirect(route('store.index'))->assertSessionHasErrors('payment_method');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_admin_up_can_manage_supplier_and_update_product_stock(): void
    {
        $admin = User::factory()->create(['sso_roles' => ['adminup']]);
        $this->actingAs($admin)->post(route('adminup.suppliers.store'), [
            'name' => 'Supplier Pengujian',
            'contact_person' => 'Budi',
            'phone' => '081200000001',
            'email' => 'supplier@example.test',
            'is_active' => true,
        ])->assertRedirect();

        $supplier = Supplier::where('name', 'Supplier Pengujian')->firstOrFail();
        $product = $this->product(supplier: $supplier, stock: 4, supplierPrice: 7000);

        $this->actingAs($admin)
            ->patch(route('adminup.suppliers.products.stock', [$supplier, $product]), [
                'stock' => 19,
                'supplier_price' => 8000,
            ])->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 19,
            'supplier_price' => 8000,
        ]);
    }

    private function product(
        ?Supplier $supplier = null,
        int $stock = 10,
        int $price = 15000,
        int $supplierPrice = 9000,
    ): Product {
        $supplier ??= Supplier::create([
            'code' => 'SUP-TEST-01',
            'name' => 'Supplier Test',
            'is_active' => true,
        ]);
        $category = ProductCategory::firstOrFail();

        return Product::create([
            'product_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'name' => 'Produk Test',
            'slug' => 'produk-test-'.uniqid(),
            'sku' => 'UP-TEST-'.strtoupper(fake()->bothify('##??')),
            'price' => $price,
            'supplier_price' => $supplierPrice,
            'stock' => $stock,
            'unit' => 'pcs',
            'status' => 'active',
        ]);
    }
}
