<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    students: Object,
    classes: Array,
    filters: Object,
});
const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
const search = ref(props.filters?.q ?? '');
const classFilter = ref(props.filters?.class_id ?? '');

const statusLabel = { active: 'Aktif', inactive: 'Nonaktif', lulus: 'Lulus' };

const applyFilters = () => {
    router.get(route('master.students.index'), {
        q: search.value || undefined,
        class_id: classFilter.value || undefined,
    }, { preserveState: true });
};
</script>

<template>
    <Head title="Siswa" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Siswa</h2>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama / NIS..."
                        class="w-full max-w-xs rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-model="classFilter"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="applyFilters"
                    >
                        <option value="">Semua kelas</option>
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <Link
                    v-if="isAdmin"
                    :href="route('master.students.create')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Tambah Siswa
                </Link>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">NIS</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kelas</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">No. Absen</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-for="student in students.data" :key="student.id">
                            <td class="px-4 py-3 text-sm text-gray-600">{{ student.student_no }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <Link :href="route('master.students.show', student.id)" class="text-indigo-600 hover:underline">
                                    {{ student.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ student.memberships?.[0]?.class?.name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ student.memberships?.[0]?.attendance_number ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ statusLabel[student.status] ?? student.status }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <Link :href="route('master.students.show', student.id)" class="text-indigo-600 hover:underline">Detail</Link>
                                <template v-if="isAdmin">
                                    <span class="mx-1 text-gray-300">|</span>
                                    <Link :href="route('master.students.edit', student.id)" class="text-indigo-600 hover:underline">Ubah</Link>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!students.data.length">
                            <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                Tidak ada siswa ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="students.last_page > 1" class="mt-4 flex justify-center gap-1" aria-label="Navigasi halaman">
                <Link
                    v-for="link in students.links"
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
