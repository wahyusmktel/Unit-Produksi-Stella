<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    CircleDollarSign,
    Minus,
    Package,
    Plus,
    Search,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Store,
    Trash2,
    Truck,
    UserRound,
    WalletCards,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ProductImageSlider from '../../components/ProductImageSlider.vue';

type Product = {
    id: number;
    name: string;
    slug: string;
    sku: string;
    description: string | null;
    price: string;
    stock: number;
    unit: string;
    is_featured: boolean;
    image_urls: string[];
    category: { id: number; name: string; slug: string };
};
type CartItem = { product: Product; quantity: number };
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    products: {
        data: Product[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        total: number;
    };
    categories: Array<{
        id: number;
        name: string;
        slug: string;
        products_count: number;
    }>;
    filters: { search: string; category: string };
    payment: { qris_enabled: boolean };
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const search = ref(props.filters.search);
const selectedProduct = ref<Product | null>(null);
const cartOpen = ref(false);
const checkoutOpen = ref(false);
const cart = ref<CartItem[]>([]);
const cartCount = computed(() =>
    cart.value.reduce((sum, item) => sum + item.quantity, 0),
);
const subtotal = computed(() =>
    cart.value.reduce(
        (sum, item) => sum + Number(item.product.price) * item.quantity,
        0,
    ),
);

const checkoutForm = useForm({
    customer_name: '',
    customer_email: '',
    customer_phone: '',
    payment_method: 'cash',
    notes: '',
    items: [] as Array<{ product_id: number; quantity: number }>,
});

function currency(value: number | string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
}

function applySearch(): void {
    router.get('/store', {
        search: search.value || undefined,
        category: props.filters.category || undefined,
    });
}

function addToCart(product: Product): void {
    const existing = cart.value.find((item) => item.product.id === product.id);

    if (existing) {
        existing.quantity = Math.min(existing.quantity + 1, product.stock);
    } else {
        cart.value.push({ product, quantity: 1 });
    }

    selectedProduct.value = null;
    cartOpen.value = true;
}

function changeQuantity(item: CartItem, change: number): void {
    item.quantity = Math.max(
        1,
        Math.min(item.product.stock, item.quantity + change),
    );
}

function removeItem(productId: number): void {
    cart.value = cart.value.filter((item) => item.product.id !== productId);
}

function beginCheckout(): void {
    if (!cart.value.length) {
        return;
    }

    checkoutForm.clearErrors();
    checkoutForm.customer_name = user.value?.name || checkoutForm.customer_name;
    checkoutForm.customer_email =
        user.value?.email || checkoutForm.customer_email;
    cartOpen.value = false;
    checkoutOpen.value = true;
}

function submitCheckout(): void {
    checkoutForm.items = cart.value.map((item) => ({
        product_id: item.product.id,
        quantity: item.quantity,
    }));
    checkoutForm.post('/store/checkout', { preserveScroll: true });
}

function paginationLabel(label: string): string {
    return label
        .replace('&laquo;', '<')
        .replace('&raquo;', '>')
        .replace(/<[^>]*>/g, '');
}

onMounted(() => {
    try {
        const saved = JSON.parse(
            localStorage.getItem('up-store-cart') || '[]',
        ) as CartItem[];
        cart.value = saved.filter(
            (item) => item?.product?.id && item.quantity > 0,
        );
    } catch {
        localStorage.removeItem('up-store-cart');
    }
});

watch(
    cart,
    (value) => localStorage.setItem('up-store-cart', JSON.stringify(value)),
    { deep: true },
);
</script>

<template>
    <Head title="Store" />
    <div class="store-page">
        <header class="store-header">
            <div class="store-topline">
                <span>Unit Produksi SMK Telkom Lampung</span>
                <div>
                    <span
                        ><ShieldCheck :size="14" /> Belanja aman di lingkungan
                        sekolah</span
                    ><Link :href="user ? '/dashboard' : '/login'"
                        ><UserRound :size="14" />
                        {{ user ? 'Dashboard' : 'Login SISFO' }}</Link
                    >
                </div>
            </div>
            <div class="store-navbar">
                <Link href="/store" class="store-brand"
                    ><span>UP</span>
                    <div>
                        <strong>TELKOM STORE</strong
                        ><small>Produk sekolah & karya siswa</small>
                    </div></Link
                >
                <form class="store-search" @submit.prevent="applySearch">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari produk, merchandise, makanan, atau ATK"
                    /><button title="Cari"><Search :size="20" /></button>
                </form>
                <button
                    class="store-cart-button"
                    type="button"
                    title="Buka keranjang"
                    @click="cartOpen = true"
                >
                    <ShoppingCart :size="25" /><span v-if="cartCount">{{
                        cartCount
                    }}</span>
                </button>
            </div>
            <nav class="store-categories">
                <Link href="/store" :class="{ active: !filters.category }"
                    >Semua</Link
                ><Link
                    v-for="category in categories"
                    :key="category.id"
                    :href="`/store?category=${category.slug}`"
                    :class="{ active: filters.category === category.slug }"
                    >{{ category.name }}
                    <span>{{ category.products_count }}</span></Link
                >
            </nav>
        </header>

        <main>
            <section class="store-hero">
                <div>
                    <p>UNIT PRODUKSI STORE</p>
                    <h1>
                        Kebutuhan sekolah,<br /><span>dalam satu tempat.</span>
                    </h1>
                    <p>
                        Belanja produk pilihan, karya siswa, merchandise,
                        makanan, minuman, dan kebutuhan belajar dengan proses
                        yang praktis.
                    </p>
                    <a href="#produk"
                        >Belanja sekarang <ChevronRight :size="18"
                    /></a>
                </div>
                <div class="store-hero-visual">
                    <div>
                        <ShoppingBag :size="58" /><strong
                            >Produk sekolah pilihan</strong
                        ><span>Dikelola langsung oleh Unit Produksi</span>
                    </div>
                    <div>
                        <Truck :size="20" /><span
                            >Ambil langsung di sekolah</span
                        >
                    </div>
                </div>
            </section>

            <section class="store-benefits">
                <div>
                    <ShieldCheck :size="24" /><span
                        ><strong>Produk terverifikasi</strong
                        ><small>Dikelola Unit Produksi</small></span
                    >
                </div>
                <div>
                    <WalletCards :size="24" /><span
                        ><strong>Tunai & QRIS</strong
                        ><small>Pembayaran fleksibel</small></span
                    >
                </div>
                <div>
                    <Package :size="24" /><span
                        ><strong>Stok aktual</strong
                        ><small>Informasi diperbarui</small></span
                    >
                </div>
                <div>
                    <Store :size="24" /><span
                        ><strong>Ambil di sekolah</strong
                        ><small>Praktis tanpa ongkir</small></span
                    >
                </div>
            </section>

            <section id="produk" class="store-catalog-section">
                <div class="store-section-heading">
                    <div>
                        <p>
                            {{
                                filters.category
                                    ? 'HASIL KATEGORI'
                                    : 'REKOMENDASI UNTUK ANDA'
                            }}
                        </p>
                        <h2>
                            {{
                                filters.category
                                    ? categories.find(
                                          (item) =>
                                              item.slug === filters.category,
                                      )?.name
                                    : 'Produk terbaru'
                            }}
                        </h2>
                    </div>
                    <span>{{ products.total }} produk tersedia</span>
                </div>

                <div v-if="products.data.length" class="store-product-grid">
                    <article
                        v-for="product in products.data"
                        :key="product.id"
                        class="store-product-card"
                        @click="selectedProduct = product"
                    >
                        <div class="store-product-image">
                            <ProductImageSlider
                                :images="product.image_urls"
                                :alt="product.name"
                                :interval="5000"
                            /><span v-if="product.is_featured">Pilihan</span>
                        </div>
                        <div class="store-product-info">
                            <small>{{ product.category.name }}</small>
                            <h3>{{ product.name }}</h3>
                            <strong>{{ currency(product.price) }}</strong>
                            <div>
                                <span>Stok {{ product.stock }}</span
                                ><button
                                    type="button"
                                    title="Tambah ke keranjang"
                                    @click.stop="addToCart(product)"
                                >
                                    <ShoppingCart :size="17" />
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
                <div v-else class="store-empty">
                    <Search :size="36" />
                    <h2>Produk tidak ditemukan</h2>
                    <p>Coba kata pencarian atau kategori lainnya.</p>
                    <Link href="/store">Lihat semua produk</Link>
                </div>

                <nav v-if="products.last_page > 1" class="store-pagination">
                    <template v-for="link in products.links" :key="link.label"
                        ><Link
                            v-if="link.url"
                            :href="link.url"
                            :class="{ active: link.active }"
                            >{{ paginationLabel(link.label) }}</Link
                        ><span v-else>{{
                            paginationLabel(link.label)
                        }}</span></template
                    >
                </nav>
            </section>
        </main>

        <footer class="store-footer">
            <div class="store-brand">
                <span>UP</span>
                <div>
                    <strong>TELKOM STORE</strong
                    ><small>SMK Telkom Lampung</small>
                </div>
            </div>
            <p>
                Produk sekolah, karya kreatif, dan kebutuhan belajar dalam
                ekosistem Unit Produksi.
            </p>
            <div>
                <Link href="/">Unit Produksi</Link
                ><Link :href="user ? '/dashboard' : '/login'">{{
                    user ? 'Dashboard' : 'Login SISFO'
                }}</Link>
            </div>
        </footer>

        <div
            v-if="selectedProduct"
            class="store-modal"
            @click.self="selectedProduct = null"
        >
            <div class="product-detail-modal">
                <button
                    class="store-modal-close"
                    type="button"
                    @click="selectedProduct = null"
                >
                    <X :size="20" />
                </button>
                <div class="product-detail-gallery">
                    <ProductImageSlider
                        :images="selectedProduct.image_urls"
                        :alt="selectedProduct.name"
                    />
                </div>
                <div class="product-detail-copy">
                    <small
                        >{{ selectedProduct.category.name }} ·
                        {{ selectedProduct.sku }}</small
                    >
                    <h2>{{ selectedProduct.name }}</h2>
                    <strong>{{ currency(selectedProduct.price) }}</strong>
                    <p>
                        {{
                            selectedProduct.description ||
                            'Produk pilihan Unit Produksi SMK Telkom Lampung.'
                        }}
                    </p>
                    <span
                        >Stok tersedia: {{ selectedProduct.stock }}
                        {{ selectedProduct.unit }}</span
                    ><button
                        class="store-primary-button"
                        type="button"
                        @click="addToCart(selectedProduct)"
                    >
                        <ShoppingCart :size="18" /> Tambah ke keranjang
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="cartOpen"
            class="store-drawer-layer"
            @click.self="cartOpen = false"
        >
            <aside class="store-cart-drawer">
                <header>
                    <div>
                        <p>KERANJANG BELANJA</p>
                        <h2>{{ cartCount }} barang</h2>
                    </div>
                    <button type="button" @click="cartOpen = false">
                        <X :size="21" />
                    </button>
                </header>
                <div v-if="cart.length" class="cart-items">
                    <article v-for="item in cart" :key="item.product.id">
                        <ProductImageSlider
                            :images="item.product.image_urls"
                            :alt="item.product.name"
                        />
                        <div>
                            <strong>{{ item.product.name }}</strong
                            ><span>{{ currency(item.product.price) }}</span>
                            <div class="cart-quantity">
                                <button
                                    type="button"
                                    @click="changeQuantity(item, -1)"
                                >
                                    <Minus :size="14" /></button
                                ><b>{{ item.quantity }}</b
                                ><button
                                    type="button"
                                    @click="changeQuantity(item, 1)"
                                >
                                    <Plus :size="14" />
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            title="Hapus"
                            @click="removeItem(item.product.id)"
                        >
                            <Trash2 :size="17" />
                        </button>
                    </article>
                </div>
                <div v-else class="cart-empty">
                    <ShoppingCart :size="38" /><strong
                        >Keranjang masih kosong</strong
                    >
                    <p>Tambahkan produk yang ingin dibeli.</p>
                </div>
                <footer v-if="cart.length">
                    <div>
                        <span>Subtotal</span
                        ><strong>{{ currency(subtotal) }}</strong>
                    </div>
                    <button
                        class="store-primary-button"
                        type="button"
                        @click="beginCheckout"
                    >
                        Lanjut checkout <ChevronRight :size="18" />
                    </button>
                </footer>
            </aside>
        </div>

        <div
            v-if="checkoutOpen"
            class="store-modal"
            @click.self="checkoutOpen = false"
        >
            <form class="checkout-modal" @submit.prevent="submitCheckout">
                <header>
                    <div>
                        <p>CHECKOUT</p>
                        <h2>Selesaikan pesanan</h2>
                    </div>
                    <button type="button" @click="checkoutOpen = false">
                        <X :size="21" />
                    </button>
                </header>
                <div class="checkout-body">
                    <section class="checkout-form">
                        <h3>Data pemesan</h3>
                        <label
                            ><span>Nama lengkap</span
                            ><input
                                v-model="checkoutForm.customer_name"
                                type="text"
                                placeholder="Nama penerima"
                            /><small v-if="checkoutForm.errors.customer_name">{{
                                checkoutForm.errors.customer_name
                            }}</small></label
                        >
                        <div class="checkout-form-row">
                            <label
                                ><span>Nomor WhatsApp</span
                                ><input
                                    v-model="checkoutForm.customer_phone"
                                    type="tel"
                                    inputmode="tel"
                                    placeholder="08xxxxxxxxxx"
                                /><small
                                    v-if="checkoutForm.errors.customer_phone"
                                    >{{
                                        checkoutForm.errors.customer_phone
                                    }}</small
                                ></label
                            ><label
                                ><span>Email (opsional)</span
                                ><input
                                    v-model="checkoutForm.customer_email"
                                    type="email"
                                    placeholder="nama@email.com"
                            /></label>
                        </div>
                        <label
                            ><span>Catatan (opsional)</span
                            ><textarea
                                v-model="checkoutForm.notes"
                                rows="3"
                                placeholder="Catatan untuk pengelola"
                            ></textarea>
                        </label>
                        <h3>Metode pembayaran</h3>
                        <div class="payment-options">
                            <label
                                :class="{
                                    active:
                                        checkoutForm.payment_method === 'cash',
                                }"
                                ><input
                                    v-model="checkoutForm.payment_method"
                                    type="radio"
                                    value="cash"
                                /><CircleDollarSign :size="22" /><span
                                    ><strong>Tunai</strong
                                    ><small
                                        >Bayar saat mengambil pesanan</small
                                    ></span
                                ></label
                            ><label
                                :class="{
                                    active:
                                        checkoutForm.payment_method === 'qris',
                                    disabled: !payment.qris_enabled,
                                }"
                                ><input
                                    v-model="checkoutForm.payment_method"
                                    type="radio"
                                    value="qris"
                                    :disabled="!payment.qris_enabled"
                                /><WalletCards :size="22" /><span
                                    ><strong>QRIS DANA</strong
                                    ><small>{{
                                        payment.qris_enabled
                                            ? 'Bayar dari aplikasi QRIS'
                                            : 'Segera tersedia'
                                    }}</small></span
                                ></label
                            >
                        </div>
                        <small
                            v-if="checkoutForm.errors.payment_method"
                            class="checkout-error"
                            >{{ checkoutForm.errors.payment_method }}</small
                        ><small
                            v-if="checkoutForm.errors.items"
                            class="checkout-error"
                            >{{ checkoutForm.errors.items }}</small
                        >
                    </section>
                    <aside class="checkout-summary">
                        <h3>Ringkasan</h3>
                        <div v-for="item in cart" :key="item.product.id">
                            <span
                                >{{ item.product.name }} ×
                                {{ item.quantity }}</span
                            ><strong>{{
                                currency(
                                    Number(item.product.price) * item.quantity,
                                )
                            }}</strong>
                        </div>
                        <footer>
                            <span>Total pembayaran</span
                            ><strong>{{ currency(subtotal) }}</strong>
                        </footer>
                        <button
                            class="store-primary-button"
                            :disabled="checkoutForm.processing"
                        >
                            <ShoppingBag :size="18" />
                            {{
                                checkoutForm.processing
                                    ? 'Memproses...'
                                    : 'Buat pesanan'
                            }}
                        </button>
                        <p>
                            <ShieldCheck :size="15" /> Harga dan stok
                            diverifikasi kembali saat pesanan dibuat.
                        </p>
                    </aside>
                </div>
            </form>
        </div>
    </div>
</template>
