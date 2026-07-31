<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Boxes,
    CheckCircle2,
    Clock3,
    FileText,
    Package,
    Plus,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';

const page = usePage();
const firstName = computed(
    () => String(page.props.auth?.user?.name || 'Pengguna').split(' ')[0],
);
const metrics = [
    {
        label: 'Pesanan aktif',
        value: '12',
        change: '+3 minggu ini',
        icon: FileText,
        tone: 'red',
    },
    {
        label: 'Dalam produksi',
        value: '08',
        change: '4 mendekati tenggat',
        icon: Boxes,
        tone: 'amber',
    },
    {
        label: 'Selesai bulan ini',
        value: '27',
        change: '+18% dari Juli',
        icon: CheckCircle2,
        tone: 'green',
    },
    {
        label: 'Produk terdaftar',
        value: '46',
        change: '6 kategori layanan',
        icon: Package,
        tone: 'graphite',
    },
];
const projects = [
    {
        code: 'UP-0826-014',
        name: 'Media Promosi PPDB',
        client: 'Panitia PPDB',
        status: 'Produksi',
        progress: 72,
        due: '04 Agu',
    },
    {
        code: 'UP-0826-013',
        name: 'Website Profil UMKM',
        client: 'Koperasi Mitra',
        status: 'Review',
        progress: 88,
        due: '05 Agu',
    },
    {
        code: 'UP-0826-012',
        name: 'Paket Dokumentasi Acara',
        client: 'Hubin',
        status: 'Persiapan',
        progress: 36,
        due: '08 Agu',
    },
    {
        code: 'UP-0826-011',
        name: 'Prototipe Monitoring Suhu',
        client: 'Lab IoT',
        status: 'Produksi',
        progress: 61,
        due: '12 Agu',
    },
];
</script>

<template>
    <Head title="Dashboard" />
    <DashboardLayout>
        <div class="dashboard-heading">
            <div>
                <p>Sabtu, 1 Agustus 2026</p>
                <h1>Selamat datang, {{ firstName }}.</h1>
                <span>Berikut ringkasan aktivitas Unit Produksi hari ini.</span>
            </div>
            <button type="button" class="button button--primary">
                <Plus :size="18" /> Pesanan baru
            </button>
        </div>
        <section class="metric-grid">
            <article
                v-for="metric in metrics"
                :key="metric.label"
                class="metric-card"
            >
                <div :class="['metric-icon', `metric-icon--${metric.tone}`]">
                    <component :is="metric.icon" :size="22" />
                </div>
                <p>{{ metric.label }}</p>
                <strong>{{ metric.value }}</strong
                ><span>{{ metric.change }}</span>
            </article>
        </section>
        <section class="dashboard-grid">
            <div class="work-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Proyek berjalan</h2>
                        <p>Pekerjaan prioritas yang perlu dipantau.</p>
                    </div>
                    <button type="button">
                        Lihat semua <ArrowUpRight :size="17" />
                    </button>
                </div>
                <div class="project-table">
                    <div class="project-row project-row--head">
                        <span>Proyek</span><span>Klien</span><span>Status</span
                        ><span>Progres</span><span>Tenggat</span>
                    </div>
                    <div
                        v-for="project in projects"
                        :key="project.code"
                        class="project-row"
                    >
                        <span
                            ><small>{{ project.code }}</small
                            ><strong>{{ project.name }}</strong></span
                        ><span>{{ project.client }}</span
                        ><span
                            ><i
                                :class="`status status--${project.status.toLowerCase()}`"
                                >{{ project.status }}</i
                            ></span
                        ><span
                            ><b>{{ project.progress }}%</b
                            ><i class="progress"
                                ><i
                                    :style="{ width: `${project.progress}%` }"
                                ></i></i></span
                        ><span>{{ project.due }}</span>
                    </div>
                </div>
            </div>
            <aside class="activity-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Aktivitas terbaru</h2>
                        <p>Pembaruan dari tim.</p>
                    </div>
                </div>
                <ol>
                    <li>
                        <span class="activity-dot red"
                            ><CheckCircle2 :size="15"
                        /></span>
                        <div>
                            <strong>Desain disetujui</strong>
                            <p>Media Promosi PPDB siap masuk produksi.</p>
                            <small><Clock3 :size="13" /> 24 menit lalu</small>
                        </div>
                    </li>
                    <li>
                        <span class="activity-dot dark"
                            ><Users :size="15"
                        /></span>
                        <div>
                            <strong>Tim proyek diperbarui</strong>
                            <p>Dua anggota ditambahkan ke Website Profil.</p>
                            <small><Clock3 :size="13" /> 1 jam lalu</small>
                        </div>
                    </li>
                    <li>
                        <span class="activity-dot amber"
                            ><Boxes :size="15"
                        /></span>
                        <div>
                            <strong>Material diterima</strong>
                            <p>Bahan cetak pesanan UP-0826-014 tercatat.</p>
                            <small><Clock3 :size="13" /> 3 jam lalu</small>
                        </div>
                    </li>
                </ol>
            </aside>
        </section>
    </DashboardLayout>
</template>
