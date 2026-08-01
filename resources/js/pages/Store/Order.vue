<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock3,
    Home,
    PackageCheck,
    ShoppingBag,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';

defineProps<{
    order: {
        order_number: string;
        customer_name: string;
        customer_phone: string;
        payment_method: 'cash' | 'qris';
        payment_status: string;
        status: string;
        total: string;
        qris_image: string | null;
        qris_expires_at: string | null;
        created_at: string;
        items: Array<{
            id: number;
            product_name: string;
            sku: string;
            quantity: number;
            unit_price: string;
            subtotal: string;
        }>;
    };
}>();

const page = usePage();
const flashError = computed(() => page.props.flash?.error as string | null);
const flashSuccess = computed(() => page.props.flash?.success as string | null);
const currency = (value: string) =>
    new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(Number(value));
onMounted(() => localStorage.removeItem('up-store-cart'));
</script>

<template>
    <Head :title="`Pesanan ${order.order_number}`" />
    <main class="order-success-page">
        <div class="order-success-shell">
            <header>
                <Link href="/store" class="store-brand"
                    ><span>UP</span>
                    <div>
                        <strong>TELKOM STORE</strong
                        ><small>SMK Telkom Lampung</small>
                    </div></Link
                ><Link href="/store"><Home :size="17" /> Kembali ke store</Link>
            </header>
            <section class="order-confirmation">
                <div class="order-confirmation-icon">
                    <CheckCircle2 :size="34" />
                </div>
                <p>PESANAN DITERIMA</p>
                <h1>Terima kasih, {{ order.customer_name }}.</h1>
                <span
                    >Pesanan <strong>{{ order.order_number }}</strong> sudah
                    tercatat dan sedang menunggu konfirmasi pengelola.</span
                >
            </section>
            <div
                v-if="flashSuccess"
                class="catalog-alert catalog-alert--success"
            >
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="catalog-alert catalog-alert--error">
                {{ flashError }}
            </div>
            <div class="order-layout">
                <section class="order-detail-card">
                    <header>
                        <div>
                            <ShoppingBag :size="20" /><span
                                ><strong>Detail pesanan</strong
                                ><small
                                    >{{ order.items.length }} jenis
                                    produk</small
                                ></span
                            >
                        </div>
                        <span class="order-status"
                            ><Clock3 :size="14" /> Menunggu konfirmasi</span
                        >
                    </header>
                    <article v-for="item in order.items" :key="item.id">
                        <div class="order-item-placeholder">
                            <PackageCheck :size="20" />
                        </div>
                        <span
                            ><strong>{{ item.product_name }}</strong
                            ><small
                                >{{ item.sku }} ·
                                {{ item.quantity }} barang</small
                            ></span
                        ><b>{{ currency(item.subtotal) }}</b>
                    </article>
                    <footer>
                        <span>Total pembayaran</span
                        ><strong>{{ currency(order.total) }}</strong>
                    </footer>
                </section>
                <aside class="payment-instruction">
                    <template v-if="order.payment_method === 'cash'"
                        ><div class="payment-icon">
                            <ShoppingBag :size="28" />
                        </div>
                        <p>PEMBAYARAN TUNAI</p>
                        <h2>Bayar saat pengambilan</h2>
                        <span
                            >Tunjukkan nomor pesanan ini kepada petugas Unit
                            Produksi dan lakukan pembayaran tunai saat barang
                            diambil.</span
                        ><strong>{{ order.order_number }}</strong></template
                    ><template v-else
                        ><div class="payment-icon"><Clock3 :size="28" /></div>
                        <p>PEMBAYARAN QRIS</p>
                        <h2>Scan untuk membayar</h2>
                        <img
                            v-if="order.qris_image"
                            :src="order.qris_image"
                            alt="QRIS pembayaran DANA"
                        /><span v-else
                            >QRIS belum tersedia. Hubungi pengelola dengan
                            menyebutkan nomor pesanan.</span
                        ><small v-if="order.qris_expires_at"
                            >Selesaikan sebelum
                            {{
                                new Date(order.qris_expires_at).toLocaleString(
                                    'id-ID',
                                )
                            }}</small
                        ></template
                    >
                </aside>
            </div>
            <footer class="order-help">
                Butuh bantuan? Hubungi Unit Produksi melalui nomor sekolah
                dengan menyertakan nomor pesanan.
            </footer>
        </div>
    </main>
</template>
