<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    Camera,
    CircleDollarSign,
    Image as ImageIcon,
    Package,
    Pencil,
    Plus,
    Search,
    Star,
    Tags,
    Trash2,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import BarcodeScannerModal from '../../../components/BarcodeScannerModal.vue';
import ProductImageSlider from '../../../components/ProductImageSlider.vue';
import DashboardLayout from '../../../layouts/DashboardLayout.vue';

type Category = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
};

type Product = {
    id: number;
    product_category_id: number;
    supplier_id: number | null;
    name: string;
    sku: string;
    barcode: string | null;
    description: string | null;
    price: string;
    supplier_price: string;
    stock: number;
    unit: string;
    status: 'draft' | 'active' | 'archived';
    is_featured: boolean;
    image_url: string | null;
    image_urls: string[];
    images: Array<{ id: number; image_url: string; sort_order: number }>;
    category: { id: number; name: string };
    supplier: Supplier | null;
};

type Supplier = { id: number; code: string; name: string };

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    products: {
        data: Product[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: Category[];
    suppliers: Supplier[];
    filters: {
        search: string;
        category: string | number;
        status: string;
        supplier: string | number;
    };
    stats: {
        total: number;
        active: number;
        low_stock: number;
        inventory_value: number | string;
        inventory_cost: number | string;
        projected_profit: number | string;
    };
}>();

const page = usePage();
const showProductModal = ref(false);
const showCategoryModal = ref(false);
const showBarcodeScanner = ref(false);
const editingProduct = ref<Product | null>(null);
const existingImages = ref<Product['images']>([]);
const newImagePreviews = ref<Array<{ file: File; url: string }>>([]);
const search = ref(props.filters.search || '');
const categoryFilter = ref(String(props.filters.category || ''));
const statusFilter = ref(props.filters.status || '');
const supplierFilter = ref(String(props.filters.supplier || ''));

const flashSuccess = computed(() => page.props.flash?.success as string | null);
const categoryError = computed(
    () => page.props.errors?.category_delete as string | undefined,
);

const productForm = useForm({
    product_category_id: '',
    supplier_id: '',
    name: '',
    barcode: '',
    description: '',
    price: '',
    supplier_price: '',
    stock: 0,
    unit: 'pcs',
    status: 'draft',
    is_featured: false,
    images: [] as File[],
    remove_image_ids: [] as number[],
});

const categoryForm = useForm({ name: '', description: '' });

const statItems = computed(() => [
    {
        label: 'Total produk',
        value: props.stats.total,
        icon: Package,
        tone: 'graphite',
    },
    {
        label: 'Produk aktif',
        value: props.stats.active,
        icon: Star,
        tone: 'green',
    },
    {
        label: 'Stok menipis',
        value: props.stats.low_stock,
        icon: TriangleAlert,
        tone: 'amber',
    },
    {
        label: 'Nilai persediaan',
        value: formatCurrency(props.stats.inventory_value),
        icon: CircleDollarSign,
        tone: 'red',
    },
    {
        label: 'Modal persediaan',
        value: formatCurrency(props.stats.inventory_cost),
        icon: CircleDollarSign,
        tone: 'graphite',
    },
    {
        label: 'Potensi laba',
        value: formatCurrency(props.stats.projected_profit),
        icon: CircleDollarSign,
        tone: 'green',
    },
]);

function formatCurrency(value: number | string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function statusLabel(status: Product['status']): string {
    return { draft: 'Draft', active: 'Aktif', archived: 'Diarsipkan' }[status];
}

function unitLabel(unit: string): string {
    return (
        {
            pcs: 'Pcs',
            pack: 'Pak',
            box: 'Kotak',
            bottle: 'Botol',
            portion: 'Porsi',
            set: 'Set',
            other: 'Lainnya',
        }[unit] || unit
    );
}

function resetImageEditor(): void {
    newImagePreviews.value.forEach(({ url }) => URL.revokeObjectURL(url));
    newImagePreviews.value = [];
    existingImages.value = [];
    productForm.images = [];
    productForm.remove_image_ids = [];
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '<')
        .replace('&raquo;', '>')
        .replace(/<[^>]*>/g, '');
}

function openCreate(): void {
    editingProduct.value = null;
    productForm.reset();
    productForm.clearErrors();
    productForm.stock = 0;
    productForm.unit = 'pcs';
    productForm.status = 'draft';
    resetImageEditor();
    showProductModal.value = true;
}

function openEdit(product: Product): void {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm.product_category_id = String(product.product_category_id);
    productForm.supplier_id = product.supplier_id
        ? String(product.supplier_id)
        : '';
    productForm.name = product.name;
    productForm.barcode = product.barcode || '';
    productForm.description = product.description || '';
    productForm.price = String(Number(product.price));
    productForm.supplier_price = String(Number(product.supplier_price || 0));
    productForm.stock = product.stock;
    productForm.unit = product.unit;
    productForm.status = product.status;
    productForm.is_featured = product.is_featured;
    resetImageEditor();
    existingImages.value = [...product.images];
    showProductModal.value = true;
}

function closeProductModal(): void {
    showProductModal.value = false;
    showBarcodeScanner.value = false;
    resetImageEditor();
}

function selectImages(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selectedFiles = Array.from(input.files || []);
    const availableSlots = Math.max(
        0,
        8 - existingImages.value.length - newImagePreviews.value.length,
    );

    if (selectedFiles.length > availableSlots) {
        productForm.setError(
            'images',
            'Maksimal delapan foto untuk setiap produk.',
        );
    } else {
        productForm.clearErrors('images');
    }

    selectedFiles.slice(0, availableSlots).forEach((file) => {
        newImagePreviews.value.push({
            file,
            url: URL.createObjectURL(file),
        });
    });
    productForm.images = newImagePreviews.value.map(({ file }) => file);
    input.value = '';
}

function removeExistingImage(imageId: number): void {
    productForm.remove_image_ids.push(imageId);
    existingImages.value = existingImages.value.filter(
        (image) => image.id !== imageId,
    );
}

function removeNewImage(index: number): void {
    const preview = newImagePreviews.value[index];

    if (preview) {
        URL.revokeObjectURL(preview.url);
    }

    newImagePreviews.value.splice(index, 1);
    productForm.images = newImagePreviews.value.map(({ file }) => file);
}

function handleBarcodeDetected(value: string): void {
    productForm.barcode = value;
    productForm.clearErrors('barcode');
    showBarcodeScanner.value = false;
}

function submitProduct(): void {
    const isEditing = Boolean(editingProduct.value);
    const url = isEditing
        ? `/adminup/products/${editingProduct.value?.id}`
        : '/adminup/products';

    productForm
        .transform((data) => ({
            ...data,
            _method: isEditing ? 'put' : undefined,
        }))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeProductModal,
        });
}

function submitCategory(): void {
    categoryForm.post('/adminup/product-categories', {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset();
            showCategoryModal.value = false;
        },
    });
}

function deleteProduct(product: Product): void {
    if (window.confirm(`Hapus produk "${product.name}"?`)) {
        router.delete(`/adminup/products/${product.id}`, {
            preserveScroll: true,
        });
    }
}

function deleteCategory(category: Category): void {
    if (window.confirm(`Hapus kategori "${category.name}"?`)) {
        router.delete(`/adminup/product-categories/${category.id}`, {
            preserveScroll: true,
        });
    }
}

function applyFilters(): void {
    router.get(
        '/adminup/products',
        {
            search: search.value || undefined,
            category: categoryFilter.value || undefined,
            status: statusFilter.value || undefined,
            supplier: supplierFilter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters(): void {
    search.value = '';
    categoryFilter.value = '';
    statusFilter.value = '';
    supplierFilter.value = '';
    applyFilters();
}

onBeforeUnmount(resetImageEditor);
</script>

<template>
    <Head title="Produk" />
    <DashboardLayout>
        <div class="catalog-heading">
            <div>
                <p>KATALOG UNIT PRODUKSI</p>
                <h1>Produk</h1>
                <span
                    >Kelola produk, harga, stok, dan kesiapan katalog
                    penjualan.</span
                >
            </div>
            <div class="catalog-heading__actions">
                <button
                    class="button catalog-secondary-button"
                    type="button"
                    @click="showCategoryModal = true"
                >
                    <Tags :size="18" /> Kelola kategori
                </button>
                <button
                    class="button button--primary"
                    type="button"
                    @click="openCreate"
                >
                    <Plus :size="18" /> Tambah produk
                </button>
            </div>
        </div>

        <div v-if="flashSuccess" class="catalog-alert catalog-alert--success">
            {{ flashSuccess }}
        </div>
        <div v-if="categoryError" class="catalog-alert catalog-alert--error">
            {{ categoryError }}
        </div>

        <section class="catalog-stat-grid">
            <article v-for="item in statItems" :key="item.label">
                <div :class="['metric-icon', `metric-icon--${item.tone}`]">
                    <component :is="item.icon" :size="21" />
                </div>
                <p>{{ item.label }}</p>
                <strong>{{ item.value }}</strong>
            </article>
        </section>

        <section class="catalog-workspace">
            <div class="catalog-list-panel">
                <form class="catalog-toolbar" @submit.prevent="applyFilters">
                    <label class="catalog-search"
                        ><Search :size="18" /><input
                            v-model="search"
                            type="search"
                            placeholder="Cari nama, SKU, atau barcode"
                    /></label>
                    <select
                        v-model="categoryFilter"
                        aria-label="Filter kategori"
                    >
                        <option value="">Semua kategori</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <select v-model="statusFilter" aria-label="Filter status">
                        <option value="">Semua status</option>
                        <option value="draft">Draft</option>
                        <option value="active">Aktif</option>
                        <option value="archived">Diarsipkan</option>
                    </select>
                    <select
                        v-model="supplierFilter"
                        aria-label="Filter supplier"
                    >
                        <option value="">Semua supplier</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                        </option>
                    </select>
                    <button type="submit">Terapkan</button>
                    <button
                        v-if="
                            search ||
                            categoryFilter ||
                            statusFilter ||
                            supplierFilter
                        "
                        type="button"
                        class="clear-filter"
                        @click="clearFilters"
                    >
                        Reset
                    </button>
                </form>

                <div v-if="products.data.length" class="catalog-table-wrap">
                    <table class="catalog-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Kategori</th>
                                <th>Supplier</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th aria-label="Aksi"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in products.data"
                                :key="product.id"
                            >
                                <td>
                                    <div class="product-identity">
                                        <ProductImageSlider
                                            class="product-thumb"
                                            :images="product.image_urls"
                                            :alt="product.name"
                                        />
                                        <div>
                                            <strong>{{ product.name }}</strong
                                            ><span
                                                >{{ product.sku }}
                                                <Star
                                                    v-if="product.is_featured"
                                                    :size="12"
                                                    fill="currentColor"
                                            /></span>
                                            <small v-if="product.barcode"
                                                >Barcode:
                                                {{ product.barcode }}</small
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td>{{ product.category.name }}</td>
                                <td>{{ product.supplier?.name || '-' }}</td>
                                <td>
                                    <strong>{{
                                        formatCurrency(product.price)
                                    }}</strong>
                                    <small class="table-subvalue"
                                        >Modal
                                        {{
                                            formatCurrency(
                                                product.supplier_price,
                                            )
                                        }}</small
                                    >
                                </td>
                                <td>
                                    <span
                                        :class="[
                                            'stock-label',
                                            {
                                                'stock-label--low':
                                                    product.stock <= 5,
                                            },
                                        ]"
                                        >{{ product.stock }}
                                        {{ unitLabel(product.unit) }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        :class="`product-status product-status--${product.status}`"
                                        >{{ statusLabel(product.status) }}</span
                                    >
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button
                                            type="button"
                                            title="Edit produk"
                                            @click="openEdit(product)"
                                        >
                                            <Pencil :size="17" /></button
                                        ><button
                                            type="button"
                                            title="Hapus produk"
                                            class="danger"
                                            @click="deleteProduct(product)"
                                        >
                                            <Trash2 :size="17" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="catalog-empty">
                    <Package :size="36" />
                    <h2>Belum ada produk</h2>
                    <p>Tambahkan produk pertama atau ubah filter pencarian.</p>
                    <button type="button" @click="openCreate">
                        <Plus :size="17" /> Tambah produk
                    </button>
                </div>

                <div v-if="products.total" class="catalog-pagination">
                    <span
                        >Menampilkan {{ products.from }}-{{ products.to }} dari
                        {{ products.total }} produk</span
                    >
                    <nav aria-label="Paginasi produk">
                        <template
                            v-for="link in products.links"
                            :key="link.label"
                            ><Link
                                v-if="link.url"
                                :href="link.url"
                                :class="{ active: link.active }"
                                preserve-scroll
                                >{{ paginationLabel(link.label) }}</Link
                            ><span v-else>{{
                                paginationLabel(link.label)
                            }}</span></template
                        >
                    </nav>
                </div>
            </div>

            <aside class="category-panel">
                <div class="category-panel__heading">
                    <div>
                        <h2>Kategori</h2>
                        <p>{{ categories.length }} kategori tersedia</p>
                    </div>
                    <button
                        type="button"
                        title="Tambah kategori"
                        @click="showCategoryModal = true"
                    >
                        <Plus :size="18" />
                    </button>
                </div>
                <div class="category-list">
                    <div v-for="category in categories" :key="category.id">
                        <span
                            ><strong>{{ category.name }}</strong
                            ><small
                                >{{ category.products_count }} produk</small
                            ></span
                        ><button
                            type="button"
                            title="Hapus kategori"
                            :disabled="category.products_count > 0"
                            @click="deleteCategory(category)"
                        >
                            <Trash2 :size="15" />
                        </button>
                    </div>
                </div>
                <div class="catalog-foundation-note">
                    <Archive :size="18" />
                    <p>
                        <strong>Siap untuk storefront</strong>Status produk dan
                        stok sudah disiapkan sebagai sumber katalog publik dan
                        transaksi QRIS.
                    </p>
                </div>
            </aside>
        </section>

        <div
            v-if="showProductModal"
            class="catalog-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="product-modal-title"
            @click.self="closeProductModal"
        >
            <form
                class="catalog-modal__panel product-form"
                @submit.prevent="submitProduct"
            >
                <header>
                    <div>
                        <p>
                            {{
                                editingProduct
                                    ? 'PERBARUI KATALOG'
                                    : 'PRODUK BARU'
                            }}
                        </p>
                        <h2 id="product-modal-title">
                            {{
                                editingProduct ? 'Edit produk' : 'Tambah produk'
                            }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        title="Tutup"
                        @click="closeProductModal"
                    >
                        <X :size="21" />
                    </button>
                </header>
                <div class="product-form__body">
                    <div class="product-images-editor">
                        <label class="image-uploader"
                            ><input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                @change="selectImages"
                            /><span
                                ><ImageIcon :size="28" /><strong
                                    >Tambah foto produk</strong
                                ><small
                                    >Maksimal 8 foto, masing-masing 5 MB</small
                                ></span
                            ></label
                        >
                        <div
                            v-if="
                                existingImages.length || newImagePreviews.length
                            "
                            class="product-image-preview-list"
                        >
                            <div
                                v-for="image in existingImages"
                                :key="`existing-${image.id}`"
                            >
                                <img :src="image.image_url" alt="Foto produk" />
                                <button
                                    type="button"
                                    title="Hapus foto"
                                    @click="removeExistingImage(image.id)"
                                >
                                    <X :size="13" />
                                </button>
                            </div>
                            <div
                                v-for="(preview, index) in newImagePreviews"
                                :key="preview.url"
                            >
                                <img :src="preview.url" alt="Foto baru" />
                                <button
                                    type="button"
                                    title="Hapus foto baru"
                                    @click="removeNewImage(index)"
                                >
                                    <X :size="13" />
                                </button>
                            </div>
                        </div>
                        <small
                            v-if="productForm.errors.images"
                            class="error-text"
                            >{{ productForm.errors.images }}</small
                        >
                        <p>
                            Foto pertama digunakan sebagai sampul. Slider akan
                            berpindah otomatis dan tetap dapat digerakkan
                            manual.
                        </p>
                    </div>
                    <div class="form-grid">
                        <label class="span-2"
                            ><span>Nama produk</span
                            ><input
                                v-model="productForm.name"
                                type="text"
                                placeholder="Contoh: Tumbler Telkom School"
                            /><small v-if="productForm.errors.name">{{
                                productForm.errors.name
                            }}</small></label
                        >
                        <label
                            ><span>SKU otomatis</span
                            ><input
                                :value="
                                    editingProduct?.sku ||
                                    'Dibuat otomatis saat disimpan'
                                "
                                type="text"
                                readonly
                            /><small class="form-hint"
                                >SKU dibuat berdasarkan kategori dan identitas
                                unik produk.</small
                            ></label
                        >
                        <label
                            ><span>Kategori</span
                            ><select v-model="productForm.product_category_id">
                                <option value="" disabled>
                                    Pilih kategori
                                </option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </option></select
                            ><small
                                v-if="productForm.errors.product_category_id"
                                >{{
                                    productForm.errors.product_category_id
                                }}</small
                            ></label
                        >
                        <label class="span-2"
                            ><span>Supplier produk</span
                            ><select v-model="productForm.supplier_id">
                                <option value="">Tanpa supplier</option>
                                <option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :value="String(supplier.id)"
                                >
                                    {{ supplier.name }} - {{ supplier.code }}
                                </option></select
                            ><small v-if="productForm.errors.supplier_id">{{
                                productForm.errors.supplier_id
                            }}</small></label
                        >
                        <label class="span-2"
                            ><span>Nomor barcode barang</span>
                            <div class="barcode-input-group">
                                <input
                                    v-model="productForm.barcode"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="Ketik atau pindai barcode barang"
                                />
                                <button
                                    type="button"
                                    title="Pindai barcode dengan kamera"
                                    @click="showBarcodeScanner = true"
                                >
                                    <Camera :size="19" />
                                </button>
                            </div>
                            <small v-if="productForm.errors.barcode">{{
                                productForm.errors.barcode
                            }}</small
                            ><small v-else class="form-hint"
                                >Mendukung EAN, UPC, Code 39/93/128, ITF,
                                Codabar, dan QR.</small
                            ></label
                        >
                        <label
                            ><span>Harga jual</span>
                            <div class="input-prefix">
                                <b>Rp</b
                                ><input
                                    v-model="productForm.price"
                                    type="number"
                                    min="0"
                                    step="500"
                                    placeholder="0"
                                />
                            </div>
                            <small v-if="productForm.errors.price">{{
                                productForm.errors.price
                            }}</small></label
                        >
                        <label
                            ><span>Harga supplier</span>
                            <div class="input-prefix">
                                <b>Rp</b
                                ><input
                                    v-model="productForm.supplier_price"
                                    type="number"
                                    min="0"
                                    step="500"
                                    placeholder="0"
                                />
                            </div>
                            <small v-if="productForm.errors.supplier_price">{{
                                productForm.errors.supplier_price
                            }}</small
                            ><small v-else class="form-hint"
                                >Digunakan sebagai harga modal untuk menghitung
                                laba.</small
                            ></label
                        >
                        <label
                            ><span>Stok</span
                            ><input
                                v-model.number="productForm.stock"
                                type="number"
                                min="0"
                                step="1"
                            /><small v-if="productForm.errors.stock">{{
                                productForm.errors.stock
                            }}</small></label
                        >
                        <label
                            ><span>Satuan</span
                            ><select v-model="productForm.unit">
                                <option value="pcs">Pcs</option>
                                <option value="pack">Pak</option>
                                <option value="box">Kotak</option>
                                <option value="bottle">Botol</option>
                                <option value="portion">Porsi</option>
                                <option value="set">Set</option>
                                <option value="other">Lainnya</option>
                            </select></label
                        >
                        <label
                            ><span>Status</span
                            ><select v-model="productForm.status">
                                <option value="draft">Draft</option>
                                <option value="active">Aktif</option>
                                <option value="archived">Diarsipkan</option>
                            </select></label
                        >
                        <label class="span-2"
                            ><span>Deskripsi produk</span
                            ><textarea
                                v-model="productForm.description"
                                rows="4"
                                placeholder="Jelaskan spesifikasi, bahan, ukuran, atau informasi penting produk."
                            ></textarea
                            ><small v-if="productForm.errors.description">{{
                                productForm.errors.description
                            }}</small></label
                        >
                        <label class="featured-toggle span-2"
                            ><input
                                v-model="productForm.is_featured"
                                type="checkbox"
                            /><span
                                ><strong>Produk unggulan</strong
                                ><small
                                    >Tandai untuk diprioritaskan pada storefront
                                    mendatang.</small
                                ></span
                            ></label
                        >
                    </div>
                </div>
                <footer>
                    <button
                        type="button"
                        class="catalog-secondary-button"
                        @click="closeProductModal"
                    >
                        Batal</button
                    ><button
                        type="submit"
                        class="button button--primary"
                        :disabled="productForm.processing"
                    >
                        {{
                            productForm.processing
                                ? 'Menyimpan...'
                                : editingProduct
                                  ? 'Simpan perubahan'
                                  : 'Tambah produk'
                        }}
                    </button>
                </footer>
            </form>
        </div>

        <BarcodeScannerModal
            v-if="showBarcodeScanner"
            @detected="handleBarcodeDetected"
            @close="showBarcodeScanner = false"
        />

        <div
            v-if="showCategoryModal"
            class="catalog-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="category-modal-title"
            @click.self="showCategoryModal = false"
        >
            <form
                class="catalog-modal__panel category-form"
                @submit.prevent="submitCategory"
            >
                <header>
                    <div>
                        <p>KATEGORI PRODUK</p>
                        <h2 id="category-modal-title">Tambah kategori</h2>
                    </div>
                    <button
                        type="button"
                        title="Tutup"
                        @click="showCategoryModal = false"
                    >
                        <X :size="21" />
                    </button>
                </header>
                <div class="category-form__body">
                    <label
                        ><span>Nama kategori</span
                        ><input
                            v-model="categoryForm.name"
                            type="text"
                            placeholder="Contoh: Produk Digital"
                        /><small v-if="categoryForm.errors.name">{{
                            categoryForm.errors.name
                        }}</small></label
                    ><label
                        ><span>Deskripsi</span
                        ><textarea
                            v-model="categoryForm.description"
                            rows="3"
                            placeholder="Deskripsi singkat kategori."
                        ></textarea
                        ><small v-if="categoryForm.errors.description">{{
                            categoryForm.errors.description
                        }}</small></label
                    >
                </div>
                <footer>
                    <button
                        type="button"
                        class="catalog-secondary-button"
                        @click="showCategoryModal = false"
                    >
                        Batal</button
                    ><button
                        type="submit"
                        class="button button--primary"
                        :disabled="categoryForm.processing"
                    >
                        {{
                            categoryForm.processing
                                ? 'Menyimpan...'
                                : 'Tambah kategori'
                        }}
                    </button>
                </footer>
            </form>
        </div>
    </DashboardLayout>
</template>
