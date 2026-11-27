<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    today: String,
    counts: Object,
    totalStudents: Number,
    perClass: Array,
    recentTaps: Array,
});

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
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Dashboard — {{ today }}</h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Ringkasan hari ini -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Hadir</p>
                    <p class="mt-1 text-3xl font-semibold text-green-600">{{ counts.hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Terlambat</p>
                    <p class="mt-1 text-3xl font-semibold text-amber-600">{{ counts.terlambat }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Tidak Hadir</p>
                    <p class="mt-1 text-3xl font-semibold text-red-600">{{ counts.tidak_hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Belum Masuk</p>
                    <p class="mt-1 text-3xl font-semibold text-gray-600">{{ counts.belum_masuk }}</p>
                    <p class="mt-1 text-xs text-gray-400">dari {{ totalStudents }} siswa</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Per kelas -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-800">Kehadiran per Kelas</h3>
                        <Link :href="route('attendance.index')" class="text-sm text-indigo-600 hover:underline">Detail</Link>
                    </div>
                    <table class="mt-3 min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="py-2 text-left text-xs font-medium uppercase text-gray-500">Kelas</th>
                                <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">Siswa</th>
                                <th class="py-2 text-right text-xs font-medium uppercase text-gray-500">Hadir+</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in perClass" :key="c.id">
                                <td class="py-2 text-sm text-gray-900">{{ c.name }}</td>
                                <td class="py-2 text-right text-sm text-gray-600">{{ c.students }}</td>
                                <td class="py-2 text-right text-sm font-medium" :class="c.present === c.students ? 'text-green-700' : 'text-gray-900'">
                                    {{ c.present }}/{{ c.students }}
                                </td>
                            </tr>
                            <tr v-if="!perClass.length">
                                <td colspan="3" class="py-4 text-center text-sm text-gray-500">Belum ada kelas aktif.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <!-- Tap terakhir -->
                <section class="rounded-lg bg-white p-6 shadow">
                    <h3 class="text-sm font-semibold text-gray-800">Tap RFID Terakhir</h3>
                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="t in recentTaps" :key="t.id" class="flex items-center justify-between py-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-900">{{ t.student ?? t.uid }}</span>
                                <span class="ml-2 text-xs text-gray-500">
                                    {{ t.direction === 'in' ? 'IN' : 'OUT' }} · {{ t.terminal }}
                                </span>
                            </div>
                            <div class="text-right">
                                <span class="font-mono text-xs text-gray-500">{{ t.time }}</span>
                                <p class="text-xs" :class="['hadir','terlambat','pulang'].includes(t.outcome) ? 'text-green-600' : 'text-gray-400'">
                                    {{ outcomeLabel[t.outcome] ?? t.outcome ?? '-' }}
                                </p>
                            </div>
                        </li>
                        <li v-if="!recentTaps.length" class="py-4 text-center text-sm text-gray-500">
                            Belum ada tap hari ini.
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
