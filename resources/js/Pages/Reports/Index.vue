<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    date: String,
    isSchoolDay: Boolean,
    classes: Array,
    whatsappUrl: String,
    whatsappConfigured: Boolean,
});

const statusClass = {
    hadir: 'bg-green-100 text-green-800',
    terlambat: 'bg-amber-100 text-amber-800',
    tidak_hadir: 'bg-red-100 text-red-800',
    belum_masuk: 'bg-gray-100 text-gray-600',
};
const statusLabel = { hadir: 'Hadir', terlambat: 'Terlambat', tidak_hadir: 'Tidak Hadir', belum_masuk: 'Belum Masuk' };

const totals = computed(() => {
    const t = { hadir: 0, terlambat: 0, tidak_hadir: 0, belum_masuk: 0 };
    for (const c of props.classes) {
        for (const k of Object.keys(t)) t[k] += c.counts[k] ?? 0;
    }
    return t;
});

const pickDate = (e) => {
    router.get(route('reports.index'), { date: e.target.value }, { preserveState: true });
};
</script>

<template>
    <Head title="Laporan Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Laporan Absensi Harian</h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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
                <div class="ml-auto flex items-center gap-3">
                    <span class="text-sm text-gray-500">
                        {{ isSchoolDay ? 'Hari sekolah efektif' : 'Bukan hari efektif' }}
                    </span>
                    <a
                        v-if="whatsappUrl"
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    >
                        Kirim ke WhatsApp
                    </a>
                    <span
                        v-else
                        class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-500"
                        title="Atur nomor WhatsApp di Pengaturan"
                    >
                        WhatsApp (nomor belum diatur)
                    </span>
                </div>
            </div>

            <!-- Totals -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Hadir</p>
                    <p class="mt-1 text-2xl font-semibold text-green-600">{{ totals.hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Terlambat</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-600">{{ totals.terlambat }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Tidak Hadir</p>
                    <p class="mt-1 text-2xl font-semibold text-red-600">{{ totals.tidak_hadir }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 text-center shadow">
                    <p class="text-xs font-medium uppercase text-gray-500">Belum Masuk</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-600">{{ totals.belum_masuk }}</p>
                </div>
            </div>

            <!-- Per kelas -->
            <div class="space-y-4">
                <section v-for="c in classes" :key="c.class_id" class="overflow-hidden rounded-lg bg-white shadow">
                    <header class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-gray-800">{{ c.name }} <span class="font-normal text-gray-400">({{ c.total }} siswa)</span></h3>
                        <p class="text-xs text-gray-500">
                            H {{ c.counts.hadir }} · T {{ c.counts.terlambat }} · TH {{ c.counts.tidak_hadir }} · BM {{ c.counts.belum_masuk }}
                        </p>
                    </header>
                    <table class="min-w-full divide-y divide-gray-100">
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="s in c.students" :key="s.student_id">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ s.name }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[s.status]">
                                        {{ statusLabel[s.status] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-500">
                                    {{ s.check_in_at ? `Masuk ${s.check_in_at}` : '' }}{{ s.check_in_at && s.check_out_at ? ' · ' : '' }}{{ s.check_out_at ? `Pulang ${s.check_out_at}` : '' }}
                                </td>
                            </tr>
                            <tr v-if="!c.students.length">
                                <td class="px-4 py-4 text-center text-sm text-gray-500">Tidak ada siswa.</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
                <p v-if="!classes.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                    Tidak ada kelas aktif.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
