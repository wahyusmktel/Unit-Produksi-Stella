<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUpProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_up_user_cannot_access_product_catalog(): void
    {
        $user = User::factory()->create(['sso_roles' => ['Guru Kelas']]);

        $this->actingAs($user)
            ->get(route('adminup.products.index'))
            ->assertForbidden();
    }

    public function test_admin_up_can_view_product_catalog_with_default_categories(): void
    {
        $this->actingAs($this->adminUp())
            ->get(route('adminup.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AdminUp/Products/Index')
                ->has('categories', 6)
                ->where('stats.total', 0));
    }

    public function test_admin_up_can_create_category_and_product(): void
    {
        $admin = $this->adminUp();

        $this->actingAs($admin)
            ->post(route('adminup.product-categories.store'), [
                'name' => 'Produk Digital',
                'description' => 'Produk digital karya siswa.',
            ])
            ->assertRedirect();

        $category = ProductCategory::where('name', 'Produk Digital')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('adminup.products.store'), [
                'product_category_id' => $category->id,
                'name' => 'Template Presentasi Sekolah',
                'sku' => 'UP-DIGI-001',
                'description' => 'Template presentasi siap digunakan.',
                'price' => 25000,
                'stock' => 20,
                'unit' => 'pcs',
                'status' => 'active',
                'is_featured' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'name' => 'Template Presentasi Sekolah',
            'sku' => 'UP-DIGI-001',
            'product_category_id' => $category->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_up_can_update_and_delete_product(): void
    {
        $admin = $this->adminUp();
        $category = ProductCategory::firstOrFail();
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Produk Lama',
            'slug' => 'produk-lama',
            'sku' => 'UP-OLD-001',
            'price' => 10000,
            'stock' => 4,
            'unit' => 'pcs',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->put(route('adminup.products.update', $product), [
                'product_category_id' => $category->id,
                'name' => 'Produk Baru',
                'sku' => 'UP-NEW-001',
                'description' => 'Deskripsi yang diperbarui.',
                'price' => 15000,
                'stock' => 12,
                'unit' => 'pack',
                'status' => 'active',
                'is_featured' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Produk Baru',
            'sku' => 'UP-NEW-001',
            'stock' => 12,
        ]);

        $this->actingAs($admin)
            ->delete(route('adminup.products.destroy', $product))
            ->assertRedirect();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $admin = $this->adminUp();
        $category = ProductCategory::firstOrFail();
        Product::create([
            'product_category_id' => $category->id,
            'name' => 'Produk Terhubung',
            'slug' => 'produk-terhubung',
            'sku' => 'UP-LINK-001',
            'price' => 10000,
            'stock' => 1,
            'unit' => 'pcs',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->delete(route('adminup.product-categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHasErrors('category_delete');

        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    private function adminUp(): User
    {
        return User::factory()->create(['sso_roles' => ['adminup']]);
    }
}
