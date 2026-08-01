<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    CircleDollarSign,
    PackageCheck,
    Pencil,
    Plus,
    Search,
    Trash2,
    Truck,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardLayout from '../../../layouts/DashboardLayout.vue';

type SupplierProduct = {
    id: number;
    name: string;
    sku: string;
    stock: number;
    unit: string;
    supplier_price: string;
    price: string;
};

type Supplier = {
    id: number;
    code: string;
    name: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    products_count: number;
    total_stock: number | null;
    inventory_cost: string | null;
    revenue: number;
    profit: number;
    products: SupplierProduct[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    suppliers: {
        data: Supplier[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string };
    summary: { total: number; active: number; revenue: number; profit: number };
}>();

const page = usePage();
const search = ref(props.filters.search);
const showSupplierModal = ref(false);
const showStockModal = ref(false);
const editingSupplier = ref<Supplier | null>(null);
const stockSupplier = ref<Supplier | null>(null);
const stockProduct = ref<SupplierProduct | null>(null);
const flashSuccess = computed(() => page.props.flash?.success as string | null);
const deleteError = computed(
    () => page.props.errors?.supplier_delete as string | undefined,
);

const supplierForm = useForm({
    name: '',
    contact_person: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    is_active: true,
});
const stockForm = useForm({ stock: 0, supplier_price: '' });

const summaryCards = computed(() => [
    { label: 'Total supplier', value: props.summary.total, icon: Truck },
    {
        label: 'Supplier aktif',
        value: props.summary.active,
        icon: PackageCheck,
    },
    {
        label: 'Omzet produk supplier',
        value: currency(props.summary.revenue),
        icon: CircleDollarSign,
    },
    {
        label: 'Laba bersih UP',
        value: currency(props.summary.profit),
        icon: CircleDollarSign,
    },
]);

function currency(value: number | string | null): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '<')
        .replace('&raquo;', '>')
        .replace(/<[^>]*>/g, '');
}

function openCreate(): void {
    editingSupplier.value = null;
    supplierForm.reset();
    supplierForm.clearErrors();
    supplierForm.is_active = true;
    showSupplierModal.value = true;
}

function openEdit(supplier: Supplier): void {
    editingSupplier.value = supplier;
    supplierForm.clearErrors();
    supplierForm.name = supplier.name;
    supplierForm.contact_person = supplier.contact_person || '';
    supplierForm.phone = supplier.phone || '';
    supplierForm.email = supplier.email || '';
    supplierForm.address = supplier.address || '';
    supplierForm.notes = supplier.notes || '';
    supplierForm.is_active = supplier.is_active;
    showSupplierModal.value = true;
}

function submitSupplier(): void {
    const supplier = editingSupplier.value;
    const options = {
        preserveScroll: true,
        onSuccess: () => (showSupplierModal.value = false),
    };

    if (supplier) {
        supplierForm.put(`/adminup/suppliers/${supplier.id}`, options);
    } else {
        supplierForm.post('/adminup/suppliers', options);
    }
}

function deleteSupplier(supplier: Supplier): void {
    if (window.confirm(`Hapus supplier "${supplier.name}"?`)) {
        router.delete(`/adminup/suppliers/${supplier.id}`, {
            preserveScroll: true,
        });
    }
}

function openStock(supplier: Supplier, product: SupplierProduct): void {
    stockSupplier.value = supplier;
    stockProduct.value = product;
    stockForm.clearErrors();
    stockForm.stock = product.stock;
    stockForm.supplier_price = String(Number(product.supplier_price));
    showStockModal.value = true;
}

function submitStock(): void {
    if (!stockSupplier.value || !stockProduct.value) {
        return;
    }

    stockForm.patch(
        `/adminup/suppliers/${stockSupplier.value.id}/products/${stockProduct.value.id}/stock`,
        {
            preserveScroll: true,
            onSuccess: () => (showStockModal.value = false),
        },
    );
}

function applySearch(): void {
    router.get(
        '/adminup/suppliers',
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Supplier" />
    <DashboardLayout>
        <div class="catalog-heading">
            <div>
                <p>RANTAI PASOK UNIT PRODUKSI</p>
                <h1>Supplier</h1>
                <span
                    >Kelola mitra, harga modal, stok, omzet, dan kontribusi
                    laba.</span
                >
            </div>
            <button
                class="button button--primary"
                type="button"
                @click="openCreate"
            >
                <Plus :size="18" /> Tambah supplier
            </button>
        </div>

        <div v-if="flashSuccess" class="catalog-alert catalog-alert--success">
            {{ flashSuccess }}
        </div>
        <div v-if="deleteError" class="catalog-alert catalog-alert--error">
            {{ deleteError }}
        </div>

        <section class="catalog-stat-grid supplier-summary-grid">
            <article v-for="item in summaryCards" :key="item.label">
                <div class="metric-icon metric-icon--red">
                    <component :is="item.icon" :size="21" />
                </div>
                <p>{{ item.label }}</p>
                <strong>{{ item.value }}</strong>
            </article>
        </section>

        <section class="supplier-panel">
            <form class="supplier-search" @submit.prevent="applySearch">
                <label
                    ><Search :size="18" /><input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama, kode, atau PIC supplier"
                /></label>
                <button type="submit">Cari</button>
            </form>

            <div v-if="suppliers.data.length" class="supplier-grid">
                <article
                    v-for="supplier in suppliers.data"
                    :key="supplier.id"
                    class="supplier-card"
                >
                    <header>
                        <div class="supplier-monogram">
                            {{ supplier.name.slice(0, 2).toUpperCase() }}
                        </div>
                        <div>
                            <span>{{ supplier.code }}</span>
                            <h2>{{ supplier.name }}</h2>
                            <p>
                                {{ supplier.contact_person || 'PIC belum diisi'
                                }}<template v-if="supplier.phone">
                                    · {{ supplier.phone }}</template
                                >
                            </p>
                        </div>
                        <span
                            :class="[
                                'supplier-status',
                                { inactive: !supplier.is_active },
                            ]"
                            >{{
                                supplier.is_active ? 'Aktif' : 'Nonaktif'
                            }}</span
                        >
                    </header>

                    <div class="supplier-metrics">
                        <div>
                            <small>Produk</small
                            ><strong>{{ supplier.products_count }}</strong>
                        </div>
                        <div>
                            <small>Total stok</small
                            ><strong>{{ supplier.total_stock || 0 }}</strong>
                        </div>
                        <div>
                            <small>Omzet</small
                            ><strong>{{ currency(supplier.revenue) }}</strong>
                        </div>
                        <div>
                            <small>Laba UP</small
                            ><strong>{{ currency(supplier.profit) }}</strong>
                        </div>
                    </div>

                    <div class="supplier-product-list">
                        <div class="supplier-product-heading">
                            <strong>Produk supplier</strong
                            ><Link
                                :href="`/adminup/products?supplier=${supplier.id}`"
                                >Buka semua</Link
                            >
                        </div>
                        <div v-if="supplier.products.length">
                            <button
                                v-for="product in supplier.products.slice(0, 5)"
                                :key="product.id"
                                type="button"
                                @click="openStock(supplier, product)"
                            >
                                <span
                                    ><strong>{{ product.name }}</strong
                                    ><small
                                        >{{ product.sku }} · Modal
                                        {{
                                            currency(product.supplier_price)
                                        }}</small
                                    ></span
                                >
                                <span
                                    >{{ product.stock }} {{ product.unit
                                    }}<small>Update stok</small></span
                                >
                            </button>
                        </div>
                        <p v-else>Belum ada produk dari supplier ini.</p>
                    </div>

                    <footer>
                        <Link
                            :href="`/adminup/products?supplier=${supplier.id}`"
                            ><Boxes :size="16" /> List produk</Link
                        >
                        <button type="button" @click="openEdit(supplier)">
                            <Pencil :size="16" /> Edit
                        </button>
                        <button
                            type="button"
                            class="danger"
                            @click="deleteSupplier(supplier)"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </footer>
                </article>
            </div>
            <div v-else class="catalog-empty">
                <Truck :size="38" />
                <h2>Belum ada supplier</h2>
                <p>
                    Tambahkan supplier pertama untuk mulai mencatat harga modal.
                </p>
                <button type="button" @click="openCreate">
                    <Plus :size="17" /> Tambah supplier
                </button>
            </div>

            <div v-if="suppliers.total" class="catalog-pagination">
                <span
                    >Menampilkan {{ suppliers.from }}-{{ suppliers.to }} dari
                    {{ suppliers.total }} supplier</span
                >
                <nav>
                    <template v-for="link in suppliers.links" :key="link.label"
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
        </section>

        <div
            v-if="showSupplierModal"
            class="catalog-modal"
            @click.self="showSupplierModal = false"
        >
            <form
                class="catalog-modal__panel supplier-form-modal"
                @submit.prevent="submitSupplier"
            >
                <header>
                    <div>
                        <p>
                            {{
                                editingSupplier
                                    ? 'PERBARUI MITRA'
                                    : 'MITRA BARU'
                            }}
                        </p>
                        <h2>
                            {{
                                editingSupplier
                                    ? 'Edit supplier'
                                    : 'Tambah supplier'
                            }}
                        </h2>
                    </div>
                    <button type="button" @click="showSupplierModal = false">
                        <X :size="21" />
                    </button>
                </header>
                <div class="form-grid supplier-form-body">
                    <label class="span-2"
                        ><span>Nama supplier</span
                        ><input
                            v-model="supplierForm.name"
                            type="text"
                            placeholder="Nama perusahaan atau pemasok"
                        /><small v-if="supplierForm.errors.name">{{
                            supplierForm.errors.name
                        }}</small></label
                    >
                    <label
                        ><span>Nama PIC</span
                        ><input
                            v-model="supplierForm.contact_person"
                            type="text"
                            placeholder="Nama kontak utama"
                    /></label>
                    <label
                        ><span>Nomor WhatsApp</span
                        ><input
                            v-model="supplierForm.phone"
                            type="tel"
                            inputmode="tel"
                            placeholder="08xxxxxxxxxx"
                    /></label>
                    <label
                        ><span>Email</span
                        ><input
                            v-model="supplierForm.email"
                            type="email"
                            placeholder="supplier@example.com"
                    /></label>
                    <label
                        ><span>Status</span
                        ><select v-model="supplierForm.is_active">
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select></label
                    >
                    <label class="span-2"
                        ><span>Alamat</span
                        ><textarea
                            v-model="supplierForm.address"
                            rows="3"
                            placeholder="Alamat lengkap supplier"
                        ></textarea>
                    </label>
                    <label class="span-2"
                        ><span>Catatan</span
                        ><textarea
                            v-model="supplierForm.notes"
                            rows="3"
                            placeholder="Termin pembayaran, jadwal pengiriman, atau informasi lainnya"
                        ></textarea>
                    </label>
                </div>
                <footer>
                    <button
                        type="button"
                        class="catalog-secondary-button"
                        @click="showSupplierModal = false"
                    >
                        Batal</button
                    ><button
                        class="button button--primary"
                        :disabled="supplierForm.processing"
                    >
                        {{
                            supplierForm.processing
                                ? 'Menyimpan...'
                                : 'Simpan supplier'
                        }}
                    </button>
                </footer>
            </form>
        </div>

        <div
            v-if="showStockModal && stockProduct"
            class="catalog-modal"
            @click.self="showStockModal = false"
        >
            <form
                class="catalog-modal__panel stock-modal"
                @submit.prevent="submitStock"
            >
                <header>
                    <div>
                        <p>UPDATE CEPAT</p>
                        <h2>Stok produk</h2>
                    </div>
                    <button type="button" @click="showStockModal = false">
                        <X :size="21" />
                    </button>
                </header>
                <div class="stock-modal-body">
                    <PackageCheck :size="28" />
                    <div>
                        <strong>{{ stockProduct.name }}</strong
                        ><span
                            >{{ stockSupplier?.name }} ·
                            {{ stockProduct.sku }}</span
                        >
                    </div>
                </div>
                <div class="form-grid supplier-form-body">
                    <label
                        ><span>Stok terkini</span
                        ><input
                            v-model.number="stockForm.stock"
                            type="number"
                            min="0"
                        /><small v-if="stockForm.errors.stock">{{
                            stockForm.errors.stock
                        }}</small></label
                    ><label
                        ><span>Harga supplier</span>
                        <div class="input-prefix">
                            <b>Rp</b
                            ><input
                                v-model="stockForm.supplier_price"
                                type="number"
                                min="0"
                                step="500"
                            />
                        </div>
                        <small v-if="stockForm.errors.supplier_price">{{
                            stockForm.errors.supplier_price
                        }}</small></label
                    >
                </div>
                <footer>
                    <button
                        type="button"
                        class="catalog-secondary-button"
                        @click="showStockModal = false"
                    >
                        Batal</button
                    ><button
                        class="button button--primary"
                        :disabled="stockForm.processing"
                    >
                        Simpan stok
                    </button>
                </footer>
            </form>
        </div>
    </DashboardLayout>
</template>
