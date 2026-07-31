<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Boxes,
    Camera,
    ChevronRight,
    CircuitBoard,
    Code2,
    Menu,
    PackageCheck,
    Printer,
    Shirt,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const page = usePage();
const menuOpen = ref(false);
const scrolled = ref(false);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const services = [
    {
        icon: Code2,
        code: '01',
        title: 'Solusi Digital',
        description:
            'Website, aplikasi internal, dan otomasi proses yang dirancang untuk kebutuhan nyata mitra.',
    },
    {
        icon: Printer,
        code: '02',
        title: 'Produksi Kreatif',
        description:
            'Desain visual, cetak promosi, signage, dan identitas komunikasi dengan standar produksi.',
    },
    {
        icon: Camera,
        code: '03',
        title: 'Media Production',
        description:
            'Foto produk, dokumentasi acara, video profil, dan konten digital yang siap dipublikasikan.',
    },
    {
        icon: CircuitBoard,
        code: '04',
        title: 'IoT & Prototyping',
        description:
            'Purwarupa perangkat cerdas, integrasi sensor, serta eksperimen teknologi terapan.',
    },
    {
        icon: Shirt,
        code: '05',
        title: 'Merchandise',
        description:
            'Seragam, apparel, dan merchandise institusi dari konsep hingga hasil produksi.',
    },
    {
        icon: Boxes,
        code: '06',
        title: 'Product Lab',
        description:
            'Pendampingan ide, validasi produk, pengemasan, dan persiapan produk menuju pasar.',
    },
];

const onScroll = () => {
    scrolled.value = window.scrollY > 30;
};
onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));
</script>

<template>
    <Head title="Unit Produksi">
        <meta
            name="description"
            content="Unit Produksi SMK Telkom Lampung menghadirkan solusi digital, kreatif, media, dan teknologi melalui kolaborasi siswa dan tenaga profesional."
        />
    </Head>

    <div class="landing-page">
        <header :class="['site-header', { 'site-header--scrolled': scrolled }]">
            <a
                href="#beranda"
                class="brand"
                aria-label="Unit Produksi - beranda"
            >
                <span class="brand-mark">UP</span>
                <span
                    ><strong>UNIT PRODUKSI</strong
                    ><small>SMK Telkom Lampung</small></span
                >
            </a>
            <nav
                :class="['main-nav', { 'main-nav--open': menuOpen }]"
                aria-label="Navigasi utama"
            >
                <a href="#layanan" @click="menuOpen = false">Layanan</a>
                <a href="#proses" @click="menuOpen = false">Cara Kerja</a>
                <a href="#portofolio" @click="menuOpen = false">Kapabilitas</a>
                <a href="#kontak" @click="menuOpen = false">Kontak</a>
                <Link
                    :href="isAuthenticated ? '/dashboard' : '/login'"
                    class="nav-action"
                >
                    {{ isAuthenticated ? 'Dashboard' : 'Masuk dengan SISFO' }}
                    <ArrowRight :size="17" />
                </Link>
            </nav>
            <button
                class="menu-button"
                type="button"
                :aria-expanded="menuOpen"
                aria-label="Buka menu"
                @click="menuOpen = !menuOpen"
            >
                <X v-if="menuOpen" :size="24" />
                <Menu v-else :size="24" />
            </button>
        </header>

        <main>
            <section id="beranda" class="hero-section">
                <img
                    src="/images/unit-produksi/hero-workshop.webp"
                    alt="Siswa dan mentor bekerja di studio Unit Produksi"
                    class="hero-image"
                />
                <div class="hero-shade"></div>
                <div class="hero-content">
                    <p class="eyebrow">
                        <span></span> TALENTA SEKOLAH, STANDAR INDUSTRI
                    </p>
                    <h1>Unit Produksi<br /><em>SMK Telkom Lampung</em></h1>
                    <p class="hero-copy">
                        Ruang kolaborasi tempat kompetensi, teknologi, dan
                        kebutuhan industri bertemu untuk menghasilkan karya yang
                        dapat digunakan.
                    </p>
                    <div class="hero-actions">
                        <a href="#layanan" class="button button--primary"
                            >Jelajahi layanan <ArrowRight :size="18"
                        /></a>
                        <a href="#kontak" class="button button--ghost"
                            >Diskusikan kebutuhan</a
                        >
                    </div>
                </div>
                <div class="hero-metrics">
                    <div><strong>06</strong><span>Kelompok layanan</span></div>
                    <div>
                        <strong>360°</strong><span>Dari ide ke produksi</span>
                    </div>
                    <div>
                        <strong>Real</strong><span>Project-based learning</span>
                    </div>
                </div>
                <a href="#layanan" class="scroll-cue" aria-label="Lihat layanan"
                    ><span></span>SCROLL</a
                >
            </section>

            <section id="layanan" class="services-section section-shell">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow dark">
                            <span></span> APA YANG KAMI KERJAKAN
                        </p>
                        <h2>Satu studio.<br />Banyak kemungkinan.</h2>
                    </div>
                    <p>
                        Kami menyatukan siswa bertalenta, guru pembimbing, dan
                        proses produksi yang terukur untuk menjawab kebutuhan
                        sekolah, UMKM, komunitas, dan mitra industri.
                    </p>
                </div>
                <div class="service-grid">
                    <article
                        v-for="service in services"
                        :key="service.code"
                        class="service-card"
                    >
                        <div class="service-card__top">
                            <span>{{ service.code }}</span
                            ><component
                                :is="service.icon"
                                :size="29"
                                stroke-width="1.6"
                            />
                        </div>
                        <div>
                            <h3>{{ service.title }}</h3>
                            <p>{{ service.description }}</p>
                        </div>
                        <ChevronRight :size="20" class="service-arrow" />
                    </article>
                </div>
            </section>

            <section id="portofolio" class="showcase-section">
                <div class="showcase-image">
                    <img
                        src="/images/unit-produksi/service-showcase.webp"
                        alt="Kumpulan produk dan layanan Unit Produksi"
                    />
                </div>
                <div class="showcase-copy">
                    <p class="eyebrow light">
                        <span></span> KAPABILITAS TERINTEGRASI
                    </p>
                    <h2>Dibuat untuk<br />benar-benar digunakan.</h2>
                    <p>
                        Setiap brief diperlakukan sebagai pekerjaan profesional:
                        dipahami, dirancang, diproduksi, lalu diperiksa sebelum
                        diserahkan.
                    </p>
                    <ul>
                        <li>
                            <PackageCheck :size="20" /><span
                                ><strong>Quality checkpoint</strong> pada setiap
                                tahap produksi</span
                            >
                        </li>
                        <li>
                            <Sparkles :size="20" /><span
                                ><strong>Pendampingan mentor</strong> untuk
                                menjaga hasil dan pembelajaran</span
                            >
                        </li>
                        <li>
                            <CircuitBoard :size="20" /><span
                                ><strong>Lintas kompetensi</strong> dalam satu
                                alur kerja yang transparan</span
                            >
                        </li>
                    </ul>
                </div>
            </section>

            <section id="proses" class="process-section section-shell">
                <div class="section-heading compact">
                    <div>
                        <p class="eyebrow dark"><span></span> ALUR KERJA</p>
                        <h2>Terstruktur sejak brief.</h2>
                    </div>
                    <p>
                        Progres pekerjaan dapat dipantau, keputusan tercatat,
                        dan hasil akhir disiapkan sesuai tujuan penggunaan.
                    </p>
                </div>
                <ol class="process-list">
                    <li>
                        <span>01</span>
                        <div>
                            <h3>Discovery</h3>
                            <p>
                                Memahami tujuan, pengguna, ruang lingkup, serta
                                ukuran keberhasilan.
                            </p>
                        </div>
                    </li>
                    <li>
                        <span>02</span>
                        <div>
                            <h3>Design</h3>
                            <p>
                                Menyusun konsep, estimasi, prototipe, dan
                                persetujuan produksi.
                            </p>
                        </div>
                    </li>
                    <li>
                        <span>03</span>
                        <div>
                            <h3>Build</h3>
                            <p>
                                Produksi kolaboratif dengan pengawasan mentor
                                dan kontrol mutu.
                            </p>
                        </div>
                    </li>
                    <li>
                        <span>04</span>
                        <div>
                            <h3>Deliver</h3>
                            <p>
                                Serah terima hasil, dokumentasi, evaluasi, dan
                                dukungan lanjutan.
                            </p>
                        </div>
                    </li>
                </ol>
            </section>

            <section id="kontak" class="cta-section">
                <div>
                    <p class="eyebrow light"><span></span> MULAI KOLABORASI</p>
                    <h2>
                        Punya kebutuhan nyata?<br />Mari jadikan proyek bersama.
                    </h2>
                </div>
                <a
                    href="mailto:unitproduksi@smktelkom-lpg.sch.id"
                    class="button button--white"
                    >Hubungi Unit Produksi <ArrowRight :size="18"
                /></a>
            </section>
        </main>

        <footer class="site-footer">
            <a href="#beranda" class="brand brand--footer"
                ><span class="brand-mark">UP</span
                ><span
                    ><strong>UNIT PRODUKSI</strong
                    ><small>SMK Telkom Lampung</small></span
                ></a
            >
            <p>
                Belajar dari pekerjaan nyata.<br />Menghasilkan dampak yang
                nyata.
            </p>
            <div class="footer-meta">
                <a href="#layanan">Layanan</a><a href="#proses">Cara Kerja</a
                ><a href="#kontak">Kontak</a
                ><span>© {{ new Date().getFullYear() }}</span>
            </div>
        </footer>
    </div>
</template>
