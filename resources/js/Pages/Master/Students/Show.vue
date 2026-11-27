<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    student: Object,
    classes: Array,
    years: Array,
});
const page = usePage();
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');

const statusLabel = { active: 'Aktif', inactive: 'Nonaktif', lulus: 'Lulus' };
const cardStatusLabel = {
    active: 'Aktif', suspended: 'Ditangguhkan', lost: 'Hilang',
    damaged: 'Rusak', replaced: 'Diganti', revoked: 'Dicabut', archived: 'Arsip',
};
</script>

<template>
    <Head :title="`Siswa — ${student.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">{{ student.name }}</h2>
                <Link
                    v-if="isAdmin"
                    :href="route('master.students.edit', student.id)"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Ubah Data
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Ringkasan -->
            <div class="grid gap-4 rounded-lg bg-white p-6 shadow sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">Status</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">{{ statusLabel[student.status] ?? student.status }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase text-gray-500">Jenis Kelamin</p>
                    <p class="mt-1 text-sm font-medium text-gray-900">
                        {{ student.gender === 'male' ? 'Laki-laki' : student.gender === 'female' ? 'Perempuan' : '-' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Riwayat kelas -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-800">Riwayat Kelas</h3>
                    <table class="mt-3 min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Kelas</th>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Tahun Ajaran</th>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">No. Absen</th>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Periode</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in student.memberships" :key="m.id">
                                <td class="py-2 text-sm text-gray-900">{{ m.class?.name }}</td>
                                <td class="py-2 text-sm text-gray-600">{{ m.academic_year?.name }}</td>
                                <td class="py-2 text-sm text-gray-600">{{ m.attendance_number ?? '-' }}</td>
                                <td class="py-2 text-sm text-gray-600">
                                    {{ m.from_date }} s.d. {{ m.to_date ?? 'sekarang' }}
                                </td>
                            </tr>
                            <tr v-if="!student.memberships?.length">
                                <td colspan="4" class="py-4 text-center text-sm text-gray-500">Belum ada riwayat kelas.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Kartu RFID -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-800">Kartu RFID</h3>
                    <table class="mt-3 min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">UID</th>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Sejak</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="a in student.card_assignments" :key="a.id">
                                <td class="py-2 font-mono text-sm text-gray-900">{{ a.card?.uid_text }}</td>
                                <td class="py-2 text-sm text-gray-600">{{ cardStatusLabel[a.card?.status] ?? a.card?.status }}</td>
                                <td class="py-2 text-sm text-gray-600">{{ a.assigned_at?.slice(0, 10) }}</td>
                            </tr>
                            <tr v-if="!student.card_assignments?.length">
                                <td colspan="3" class="py-4 text-center text-sm text-gray-500">Belum ada kartu terdaftar.</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="mt-3 text-xs text-gray-500">
                        Manajemen kartu (daftar massal, ganti, nonaktif) tersedia di modul RFID — segera hadir.
                    </p>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
