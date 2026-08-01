<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('sku', 60)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->unsignedInteger('stock')->default(0);
            $table->string('unit', 30)->default('pcs');
            $table->string('image_path')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('product_categories')->insert([
            ['name' => 'Makanan', 'slug' => 'makanan', 'description' => 'Produk makanan siap jual.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Minuman', 'slug' => 'minuman', 'description' => 'Produk minuman kemasan atau siap saji.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aksesoris', 'slug' => 'aksesoris', 'description' => 'Aksesoris sekolah dan produk kreatif.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ATK', 'slug' => 'atk', 'description' => 'Alat tulis dan kebutuhan belajar.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Merchandise', 'slug' => 'merchandise', 'description' => 'Merchandise Unit Produksi dan sekolah.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lainnya', 'slug' => 'lainnya', 'description' => 'Kategori untuk produk di luar kelompok utama.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
