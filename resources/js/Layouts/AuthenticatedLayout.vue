<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SideNavLink from '@/Components/SideNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingSidebar = ref(false);

const role = computed(() => page.props.auth.user?.role ?? '');
const roleLabel = computed(() => page.props.auth.user?.role_label ?? '');
const isAdmin = computed(() => role.value === 'admin');

const masterLinks = [
    { label: 'Tahun Ajaran', route: 'master.academic-years.index' },
    { label: 'Tingkat', route: 'master.grades.index' },
    { label: 'Konsentrasi', route: 'master.concentrations.index' },
    { label: 'Kelas', route: 'master.classes.index' },
    { label: 'Siswa', route: 'master.students.index' },
    { label: 'Kartu RFID', route: 'master.cards.index' },
    { label: 'Registrasi Kartu', route: 'master.registrations.index' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Mobile sidebar backdrop -->
        <div
            v-if="showingSidebar"
            class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
            @click="showingSidebar = false"
        />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-gray-200 bg-white transition-transform duration-200 lg:translate-x-0',
                showingSidebar ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 items-center border-b border-gray-200 px-5">
                <Link :href="route('dashboard')" class="flex items-center gap-2">
                    <ApplicationLogo class="block h-8 w-auto fill-current text-gray-800" />
                </Link>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navigasi utama">
                <SideNavLink
                    :href="route('dashboard')"
                    :active="route().current('dashboard')"
                >
                    Dashboard
                </SideNavLink>

                <div class="mt-6 space-y-1">
                    <SideNavLink
                        :href="route('scan.index')"
                        :active="route().current('scan.*')"
                    >
                        Scan Absensi
                    </SideNavLink>
                    <SideNavLink
                        :href="route('attendance.index')"
                        :active="route().current('attendance.*')"
                    >
                        Absensi
                    </SideNavLink>
                    <SideNavLink
                        :href="route('reports.index')"
                        :active="route().current('reports.*')"
                    >
                        Laporan
                    </SideNavLink>
                </div>

                <p class="mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Master Data
                </p>
                <div class="mt-1 space-y-1">
                    <SideNavLink
                        v-for="item in masterLinks"
                        :key="item.route"
                        :href="route(item.route)"
                        :active="route().current(item.route)"
                    >
                        {{ item.label }}
                    </SideNavLink>
                </div>

                <template v-if="isAdmin">
                    <p class="mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Administrasi
                    </p>
                    <div class="mt-1 space-y-1">
                        <SideNavLink
                            :href="route('promotion.index')"
                            :active="route().current('promotion.*')"
                        >
                            Kenaikan Kelas
                        </SideNavLink>
                        <SideNavLink
                            :href="route('settings.index')"
                            :active="route().current('settings.*')"
                        >
                            Pengaturan
                        </SideNavLink>
                    </div>
                </template>
            </nav>

            <div class="border-t border-gray-200 px-3 py-3">
                <div class="px-3 text-sm font-medium text-gray-800">
                    {{ page.props.auth.user?.name }}
                </div>
                <div class="px-3 text-xs text-gray-500">{{ roleLabel }}</div>
                <div class="mt-2 space-y-1">
                    <SideNavLink
                        :href="route('profile.edit')"
                        :active="route().current('profile.*')"
                    >
                        Profil
                    </SideNavLink>
                    <SideNavLink
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="w-full text-left"
                    >
                        Keluar
                    </SideNavLink>
                </div>
            </div>
        </aside>

        <!-- Main column -->
        <div class="lg:pl-64">
            <!-- Topbar -->
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
                        aria-label="Buka menu"
                        @click="showingSidebar = !showingSidebar"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <slot name="header" />
                </div>

                <div class="hidden items-center gap-3 sm:flex">
                    <span
                        v-if="page.props.auth.user?.school"
                        class="text-sm text-gray-500"
                    >
                        {{ page.props.auth.user.school.name }}
                    </span>
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100"
                            >
                                {{ page.props.auth.user?.name }}
                                <svg class="-me-0.5 ms-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Profil</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Keluar
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="page.props.flash?.success || page.props.flash?.error" class="mx-auto max-w-7xl px-4 pt-4 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                    role="status"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>
            </div>

            <!-- Page Content -->
            <main class="py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
