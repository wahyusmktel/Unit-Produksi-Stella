<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 160);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('product_category_id')->constrained()->nullOnDelete();
            $table->decimal('supplier_price', 15, 2)->default(0)->after('price');
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_token')->unique();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 160);
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30);
            $table->string('payment_method', 20)->index();
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('total', 15, 2);
            $table->decimal('profit_total', 15, 2)->default(0);
            $table->string('payment_reference', 64)->nullable()->index();
            $table->text('qris_payload')->nullable();
            $table->text('qris_image')->nullable();
            $table->timestamp('qris_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 160);
            $table->string('sku', 60);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('supplier_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('profit', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['supplier_id', 'status']);
            $table->dropColumn(['supplier_id', 'supplier_price']);
        });

        Schema::dropIfExists('suppliers');
    }
};
