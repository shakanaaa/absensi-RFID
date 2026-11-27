<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({ grades: Object });
const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
</script>

<template>
    <Head title="Tingkat" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Tingkat</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <Link
                    v-if="isAdmin"
                    :href="route('master.grades.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Tambah Tingkat
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Level</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Jumlah Kelas</th>
                            <th v-if="isAdmin" class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="grade in grades.data" :key="grade.id">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ grade.name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ grade.level ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ grade.classes_count }}</td>
                            <td v-if="isAdmin" class="px-4 py-3 text-right text-sm">
                                <Link :href="route('master.grades.edit', grade.id)" class="text-indigo-600 hover:underline">Ubah</Link>
                                <span class="mx-1 text-gray-300">|</span>
                                <Link
                                    :href="route('master.grades.destroy', grade.id)"
                                    method="delete"
                                    as="button"
                                    class="text-red-600 hover:underline"
                                    @click="confirm(`Hapus tingkat ${grade.name}?`) || $event.preventDefault()"
                                >
                                    Hapus
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!grades.data.length">
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                Belum ada tingkat.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="grades.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in grades.links"
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
