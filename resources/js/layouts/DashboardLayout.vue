<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Boxes,
    ChevronRight,
    FileText,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    PanelLeftClose,
    Settings,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';

const sidebarOpen = ref(false);
const sidebarCompact = ref(false);
const page = usePage();
const user = computed(
    () =>
        page.props.auth?.user as
            | {
                  name?: string;
                  email?: string;
                  avatar_url?: string;
                  sso_roles?: string[];
              }
            | undefined,
);
const initials = computed(
    () =>
        user.value?.name
            ?.split(' ')
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase() || 'UP',
);
const currentPath = computed(() => page.url.split('?')[0]);
const isAdminUp = computed(() =>
    (user.value?.sso_roles || []).some(
        (role) => role.toLowerCase() === 'adminup',
    ),
);
const navItems = computed(() => [
    {
        label: 'Dashboard',
        icon: LayoutDashboard,
        href: '/dashboard',
        active: currentPath.value === '/dashboard',
        enabled: true,
    },
    {
        label: 'Pesanan',
        icon: FileText,
        href: '#',
        active: false,
        enabled: false,
    },
    {
        label: 'Produksi',
        icon: Boxes,
        href: '#',
        active: false,
        enabled: false,
    },
    {
        label: 'Produk',
        icon: Package,
        href: '/adminup/products',
        active: currentPath.value.startsWith('/adminup/products'),
        enabled: isAdminUp.value,
    },
    { label: 'Tim', icon: Users, href: '#', active: false, enabled: false },
    {
        label: 'Pengaturan',
        icon: Settings,
        href: '#',
        active: false,
        enabled: false,
    },
]);
const currentSection = computed(
    () => navItems.value.find((item) => item.active)?.label || 'Dashboard',
);
const logout = () => router.post('/logout');
</script>

<template>
    <div
        :class="[
            'dashboard-shell',
            { 'dashboard-shell--compact': sidebarCompact },
        ]"
    >
        <div
            v-if="sidebarOpen"
            class="sidebar-backdrop"
            @click="sidebarOpen = false"
        ></div>
        <aside
            :class="[
                'dashboard-sidebar',
                { 'dashboard-sidebar--open': sidebarOpen },
            ]"
        >
            <div class="sidebar-brand">
                <span class="brand-mark">UP</span
                ><span
                    ><strong>UNIT PRODUKSI</strong
                    ><small>Management System</small></span
                ><button
                    type="button"
                    aria-label="Tutup menu"
                    @click="sidebarOpen = false"
                >
                    <X :size="21" />
                </button>
            </div>
            <nav class="sidebar-nav">
                <span class="nav-section-label">WORKSPACE</span>
                <template v-for="item in navItems" :key="item.label">
                    <Link
                        v-if="item.enabled"
                        :href="item.href"
                        :class="{ active: item.active }"
                        @click="sidebarOpen = false"
                        ><component :is="item.icon" :size="20" /><span>{{
                            item.label
                        }}</span
                        ><ChevronRight v-if="item.active" :size="16"
                    /></Link>
                    <span
                        v-else
                        class="sidebar-nav-disabled"
                        :title="`${item.label} akan tersedia pada tahap berikutnya`"
                        ><component :is="item.icon" :size="20" /><span>{{
                            item.label
                        }}</span></span
                    >
                </template>
            </nav>
            <div class="sidebar-user">
                <div class="user-avatar">
                    <img
                        v-if="user?.avatar_url"
                        :src="user.avatar_url"
                        alt=""
                    /><span v-else>{{ initials }}</span>
                </div>
                <div>
                    <strong>{{ user?.name }}</strong
                    ><small>{{ user?.email }}</small>
                </div>
                <button type="button" title="Keluar" @click="logout">
                    <LogOut :size="18" />
                </button>
            </div>
        </aside>
        <div class="dashboard-main">
            <header class="dashboard-topbar">
                <div>
                    <button
                        class="mobile-sidebar-button"
                        type="button"
                        aria-label="Buka menu"
                        @click="sidebarOpen = true"
                    >
                        <Menu :size="22" /></button
                    ><button
                        class="compact-button"
                        type="button"
                        title="Ubah ukuran sidebar"
                        @click="sidebarCompact = !sidebarCompact"
                    >
                        <PanelLeftClose :size="20" /></button
                    ><span class="topbar-divider"></span
                    ><span class="topbar-context"
                        >Workspace / <strong>{{ currentSection }}</strong></span
                    >
                </div>
                <div class="topbar-actions">
                    <button type="button" title="Notifikasi">
                        <Bell :size="20" /><span></span>
                    </button>
                    <div class="topbar-user">
                        <div class="user-avatar small">
                            <img
                                v-if="user?.avatar_url"
                                :src="user.avatar_url"
                                alt=""
                            /><span v-else>{{ initials }}</span>
                        </div>
                        <div>
                            <strong>{{ user?.name }}</strong
                            ><small>{{
                                user?.sso_roles?.[0] || 'Pengguna SISFO'
                            }}</small>
                        </div>
                    </div>
                </div>
            </header>
            <main class="dashboard-content"><slot /></main>
            <footer class="dashboard-footer">
                <span>Unit Produksi SMK Telkom Lampung</span
                ><span>Terhubung dengan SISFO SSO</span>
            </footer>
        </div>
    </div>
</template>
