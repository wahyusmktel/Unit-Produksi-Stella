<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = collect([
            ['code' => 'SUP-KANT-01', 'name' => 'CV Karya Nusantara', 'contact_person' => 'Rizky Pratama', 'phone' => '081234567801', 'email' => 'karya@example.test', 'address' => 'Bandar Lampung', 'notes' => 'Supplier ATK dan merchandise.', 'is_active' => true],
            ['code' => 'SUP-BOGA-01', 'name' => 'Telkom Boga Mandiri', 'contact_person' => 'Siti Rahma', 'phone' => '081234567802', 'email' => 'boga@example.test', 'address' => 'Lampung Selatan', 'notes' => 'Supplier makanan dan minuman.', 'is_active' => true],
            ['code' => 'SUP-KREA-01', 'name' => 'Studio Kreasi Siswa', 'contact_person' => 'Dimas Saputra', 'phone' => '081234567803', 'email' => 'kreasi@example.test', 'address' => 'Pringsewu', 'notes' => 'Produk kreatif dan aksesoris.', 'is_active' => true],
        ])->mapWithKeys(function (array $data): array {
            $supplier = Supplier::updateOrCreate(['code' => $data['code']], $data);

            return [$data['code'] => $supplier];
        });

        $products = [
            ['category' => 'Makanan', 'supplier' => 'SUP-BOGA-01', 'name' => 'Keripik Pisang Cokelat', 'barcode' => '8997001000011', 'description' => 'Keripik pisang Lampung dengan lapisan cokelat, dikemas higienis untuk camilan sekolah.', 'price' => 15000, 'supplier_price' => 10000, 'stock' => 45, 'unit' => 'pack', 'featured' => true],
            ['category' => 'Makanan', 'supplier' => 'SUP-BOGA-01', 'name' => 'Roti Isi Cokelat', 'barcode' => '8997001000028', 'description' => 'Roti lembut dengan isian cokelat, cocok untuk sarapan dan waktu istirahat.', 'price' => 8000, 'supplier_price' => 5000, 'stock' => 30, 'unit' => 'pcs', 'featured' => false],
            ['category' => 'Minuman', 'supplier' => 'SUP-BOGA-01', 'name' => 'Air Mineral 600 ml', 'barcode' => '8997001000035', 'description' => 'Air mineral kemasan praktis untuk aktivitas belajar sehari-hari.', 'price' => 5000, 'supplier_price' => 3000, 'stock' => 80, 'unit' => 'bottle', 'featured' => false],
            ['category' => 'Minuman', 'supplier' => 'SUP-BOGA-01', 'name' => 'Kopi Susu Telkom', 'barcode' => '8997001000042', 'description' => 'Kopi susu racikan Unit Produksi dengan rasa seimbang dan kemasan siap minum.', 'price' => 12000, 'supplier_price' => 7500, 'stock' => 25, 'unit' => 'bottle', 'featured' => true],
            ['category' => 'ATK', 'supplier' => 'SUP-KANT-01', 'name' => 'Buku Catatan Stella', 'barcode' => '8997001000059', 'description' => 'Buku catatan bergaris dengan sampul identitas SMK Telkom Lampung.', 'price' => 18000, 'supplier_price' => 11000, 'stock' => 50, 'unit' => 'pcs', 'featured' => true],
            ['category' => 'ATK', 'supplier' => 'SUP-KANT-01', 'name' => 'Pulpen Gel Merah Hitam', 'barcode' => '8997001000066', 'description' => 'Pulpen gel nyaman untuk mencatat dengan desain warna sekolah.', 'price' => 7000, 'supplier_price' => 3500, 'stock' => 70, 'unit' => 'pcs', 'featured' => false],
            ['category' => 'Merchandise', 'supplier' => 'SUP-KANT-01', 'name' => 'Tumbler Telkom School', 'barcode' => '8997001000073', 'description' => 'Tumbler reusable berlogo sekolah, kapasitas 600 ml dan mudah dibawa.', 'price' => 65000, 'supplier_price' => 42000, 'stock' => 20, 'unit' => 'pcs', 'featured' => true],
            ['category' => 'Merchandise', 'supplier' => 'SUP-KANT-01', 'name' => 'Kaos Unit Produksi', 'barcode' => '8997001000080', 'description' => 'Kaos katun identitas Unit Produksi dengan sablon berkualitas.', 'price' => 95000, 'supplier_price' => 65000, 'stock' => 18, 'unit' => 'pcs', 'featured' => true],
            ['category' => 'Aksesoris', 'supplier' => 'SUP-KREA-01', 'name' => 'Gantungan Kunci Akrilik', 'barcode' => '8997001000097', 'description' => 'Gantungan kunci akrilik karya siswa dengan desain khas Telkom School.', 'price' => 15000, 'supplier_price' => 8000, 'stock' => 40, 'unit' => 'pcs', 'featured' => false],
            ['category' => 'Aksesoris', 'supplier' => 'SUP-KREA-01', 'name' => 'Lanyard SMK Telkom', 'barcode' => '8997001000103', 'description' => 'Lanyard sekolah dengan kait kuat untuk kartu identitas dan aksesori.', 'price' => 25000, 'supplier_price' => 14000, 'stock' => 35, 'unit' => 'pcs', 'featured' => true],
        ];

        foreach ($products as $index => $data) {
            $category = ProductCategory::where('name', $data['category'])->firstOrFail();
            $supplier = $suppliers->get($data['supplier']);
            $slug = Str::slug($data['name']);
            Product::updateOrCreate(['slug' => $slug], [
                'product_category_id' => $category->id,
                'supplier_id' => $supplier?->id,
                'name' => $data['name'],
                'sku' => 'UP-DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'barcode' => $data['barcode'],
                'description' => $data['description'],
                'price' => $data['price'],
                'supplier_price' => $data['supplier_price'],
                'stock' => $data['stock'],
                'unit' => $data['unit'],
                'status' => 'active',
                'is_featured' => $data['featured'],
            ]);
        }
    }
}
