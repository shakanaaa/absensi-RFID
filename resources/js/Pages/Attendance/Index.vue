<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    date: String,
    isSchoolDay: Boolean,
    classes: Array,
    classId: Number,
    students: Array,
    summary: Object,
    recentTaps: Array,
});

const statusClass = {
    hadir: 'bg-green-100 text-green-800',
    terlambat: 'bg-amber-100 text-amber-800',
    tidak_hadir: 'bg-red-100 text-red-800',
    belum_masuk: 'bg-gray-100 text-gray-600',
};

const outcomeLabel = {
    hadir: 'Hadir',
    terlambat: 'Terlambat',
    pulang: 'Pulang',
    sudah_di_sekolah: 'Sudah di sekolah',
    sudah_pulang: 'Sudah pulang',
    tanpa_check_in: 'OUT tanpa IN',
    unknown_card: 'Kartu tidak dikenal',
    inactive_card: 'Kartu nonaktif',
    libur: 'Hari libur',
    ignored_duplicate_scan: 'Tap ganda',
};

const pickDate = (e) => {
    router.get(route('attendance.index'), { date: e.target.value, class_id: props.classId }, { preserveState: true });
};

const pickClass = (e) => {
    router.get(route('attendance.index'), { date: props.date, class_id: e.target.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Absensi Harian</h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Filter -->
            <div class="flex flex-wrap items-end gap-4 rounded-lg bg-white p-4 shadow">
                <div>
                    <label class="block text-xs font-medium text-gray-500">Tanggal</label>
                    <input
                        type="date"
                        :value="date"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="pickDate"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500">Kelas</label>
                    <select
                        :value="classId"
                        class="mt-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        @change="pickClass"
                    >
                        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <span
                    class="ml-auto rounded-full px-3 py-1 text-xs font-medium"
                    :class="isSchoolDay ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600'"
                >
                    {{ isSchoolDay ? 'Hari sekolah efektif' : 'Bukan hari efektif' }}
                </span>
            </div>

            <!-- Ringkasan -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Hadir</p>
                    <p class="mt-1 text-2xl font-semibold text-green-600">{{ summary.hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Terlambat</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-600">{{ summary.terlambat }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Tidak Hadir</p>
                    <p class="mt-1 text-2xl font-semibold text-red-600">{{ summary.tidak_hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Belum Masuk</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-600">{{ summary.belum_masuk }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Daftar siswa -->
                <section class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">No. Absen</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Masuk</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Pulang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="s in students" :key="s.id">
                                <td class="px-4 py-2 text-sm text-gray-500">{{ s.student_no }}</td>
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ s.name }}</td>
                                <td class="px-4 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[s.status]">
                                        {{ s.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-sm text-gray-600">{{ s.check_in_at ?? '-' }}</td>
                                <td class="px-4 py-2 font-mono text-sm text-gray-600">{{ s.check_out_at ?? '-' }}</td>
                            </tr>
                            <tr v-if="!students.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada siswa aktif di kelas ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Feed tap -->
                <section class="rounded-lg bg-white p-4 shadow">
                    <h3 class="text-sm font-semibold text-gray-800">Tap Terbaru</h3>
                    <ul class="mt-2 max-h-96 divide-y divide-gray-100 overflow-y-auto">
                        <li v-for="t in recentTaps" :key="t.id" class="py-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900">{{ t.student ?? t.uid }}</span>
                                <span class="font-mono text-xs text-gray-500">{{ t.time }}</span>
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ t.direction === 'in' ? 'Tap Masuk' : 'Tap Pulang' }} · {{ t.terminal }}
                                · <span :class="['hadir','terlambat','pulang'].includes(t.outcome) ? 'text-green-600' : 'text-gray-400'">{{ outcomeLabel[t.outcome] ?? t.outcome ?? '-' }}</span>
                            </p>
                        </li>
                        <li v-if="!recentTaps.length" class="py-4 text-center text-sm text-gray-500">
                            Belum ada tap.
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
