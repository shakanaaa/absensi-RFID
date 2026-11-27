<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ years: Object });
const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');

const statusLabel = {
    draft: 'Draft',
    active: 'Aktif',
    closed: 'Ditutup',
    archived: 'Arsip',
};
</script>

<template>
    <Head title="Tahun Ajaran" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Tahun Ajaran</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <Link
                    v-if="isAdmin"
                    :href="route('master.academic-years.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Tambah Tahun Ajaran
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mulai</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Selesai</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Keanggotaan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th v-if="isAdmin" class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="year in years.data" :key="year.id">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ year.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ year.start_date }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ year.end_date }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ year.memberships_count }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': year.status === 'active',
                                        'bg-gray-100 text-gray-700': ['draft', 'closed', 'archived'].includes(year.status),
                                    }"
                                >
                                    {{ statusLabel[year.status] ?? year.status }}
                                </span>
                            </td>
                            <td v-if="isAdmin" class="px-4 py-3 text-right text-sm">
                                <Link :href="route('master.academic-years.edit', year.id)" class="text-indigo-600 hover:underline">Ubah</Link>
                                <span class="mx-1 text-gray-300">|</span>
                                <Link
                                    :href="route('master.academic-years.destroy', year.id)"
                                    method="delete"
                                    as="button"
                                    class="text-red-600 hover:underline"
                                    @click="confirm(`Arsipkan tahun ajaran ${year.name}?`) || $event.preventDefault()"
                                >
                                    Arsipkan
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!years.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                Belum ada tahun ajaran.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="years.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in years.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rounded-md px-3 py-1.5 text-sm"
                    :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
