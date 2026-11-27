<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ sessions: Object });
const page = usePage();
const canOperate = computed(() => ['admin', 'petugas_piket'].includes(page.props.auth.user?.role));

const statusLabel = { running: 'Berjalan', finished: 'Selesai', cancelled: 'Dibatalkan' };
</script>

<template>
    <Head title="Registrasi Kartu Massal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Registrasi Kartu Massal</h2>
                <Link
                    v-if="canOperate"
                    :href="route('master.registrations.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Sesi Baru
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kelas</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Operator</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Scan</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mulai</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="s in sessions.data" :key="s.id">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ s.class?.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ s.operator?.name }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span :class="s.status === 'running' ? 'text-green-700' : 'text-gray-500'">
                                    {{ statusLabel[s.status] ?? s.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ s.attempts_count }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ s.started_at?.slice(0, 16).replace('T', ' ') }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="route('master.registrations.show', s.id)" class="text-indigo-600 hover:underline">Buka</Link>
                            </td>
                        </tr>
                        <tr v-if="!sessions.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada sesi registrasi.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="sessions.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in sessions.links"
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
