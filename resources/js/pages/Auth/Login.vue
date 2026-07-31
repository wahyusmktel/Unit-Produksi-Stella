<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, CheckCircle2, ShieldCheck } from '@lucide/vue';

defineProps<{ error?: string | null }>();
</script>

<template>
    <Head title="Masuk" />
    <main class="login-page">
        <div class="login-visual" aria-hidden="true">
            <img src="/images/unit-produksi/hero-workshop.webp" alt="" />
            <div class="login-visual__overlay"></div>
            <div class="login-visual__copy">
                <span>PRODUCTION SYSTEM</span
                ><strong>Kerja nyata.<br />Dikelola dengan presisi.</strong>
            </div>
        </div>
        <section class="login-panel">
            <Link href="/" class="back-link"
                ><ArrowLeft :size="18" /> Kembali ke beranda</Link
            >
            <div class="login-form-wrap">
                <div class="brand login-brand">
                    <span class="brand-mark">UP</span
                    ><span
                        ><strong>UNIT PRODUKSI</strong
                        ><small>SMK Telkom Lampung</small></span
                    >
                </div>
                <p class="eyebrow dark"><span></span> AKSES TERINTEGRASI</p>
                <h1>Masuk ke sistem</h1>
                <p class="login-lead">
                    Gunakan akun SISFO aktif Anda. Tidak ada akun atau kata
                    sandi tambahan yang perlu dibuat.
                </p>
                <div v-if="error" class="login-error" role="alert">
                    {{ error }}
                </div>
                <a href="/auth/sisfo" class="sso-button"
                    ><span class="sso-logo">TS</span
                    ><span
                        ><strong>Masuk dengan SISFO</strong
                        ><small>Single Sign-On SMK Telkom Lampung</small></span
                    ><ArrowRight :size="20"
                /></a>
                <div class="login-trust">
                    <ShieldCheck :size="20" /><span
                        ><strong>Autentikasi aman</strong> melalui server SSO
                        resmi SISFO.</span
                    >
                </div>
                <ul class="login-points">
                    <li>
                        <CheckCircle2 :size="17" /> Satu akun untuk ekosistem
                        aplikasi sekolah
                    </li>
                    <li>
                        <CheckCircle2 :size="17" /> Hak akses mengikuti profil
                        SISFO
                    </li>
                </ul>
            </div>
            <p class="login-footer">Unit Produksi · SMK Telkom Lampung</p>
        </section>
    </main>
</template>
