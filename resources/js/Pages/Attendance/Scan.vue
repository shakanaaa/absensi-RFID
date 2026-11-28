<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    recent: Array,
    stats: Object,
    today: String,
});

const direction = ref('in');
const uid = ref('');
const inputEl = ref(null);
const busy = ref(false);
const result = ref(null);
const resultKey = ref(0);
const feed = ref([...props.recent]);
const stats = ref({ ...props.stats });

const now = ref(new Date());
let clockTimer = null;
onMounted(() => {
    clockTimer = setInterval(() => { now.value = new Date(); }, 1000);
    inputEl.value?.focus();
});
onBeforeUnmount(() => clearInterval(clockTimer));

const clock = () =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
const dateLabel = () =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

const OUTCOME_LABEL = {
    hadir: 'Hadir',
    terlambat: 'Terlambat',
    pulang: 'Pulang tercatat',
    tanpa_check_in: 'Pulang tanpa absen masuk — diabaikan',
    sudah_di_sekolah: 'Sudah tercatat masuk',
    sudah_pulang: 'Sudah tercatat pulang',
    unknown_card: 'Kartu tidak terdaftar',
    inactive_card: 'Kartu nonaktif',
    libur: 'Hari libur — tidak dihitung',
    ignored_duplicate_scan: 'Pemindaian ganda diabaikan',
    ignored: 'Diabaikan',
};

const isGood = (o) => ['hadir', 'pulang'].includes(o);

const submit = async () => {
    const value = uid.value.trim();
    if (!value || busy.value) return;

    busy.value = true;
    try {
        const res = await fetch(route('scan.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ uid_text: value, direction: direction.value }),
        });
        const data = await res.json();

        if (!res.ok) {
            result.value = { outcome: 'error', text: data.message ?? 'Gagal memproses scan.', student: null };
        } else {
            const label = OUTCOME_LABEL[data.outcome] ?? data.outcome;
            result.value = {
                outcome: data.outcome,
                text: label,
                student: data.student_name,
            };
            resultKey.value++;

            feed.value.unshift({
                id: Date.now(),
                uid_text: value,
                student_name: data.student_name,
                direction: direction.value,
                outcome: data.outcome,
                event_time: new Date().toLocaleTimeString('id-ID'),
            });
            feed.value = feed.value.slice(0, 15);

            if (data.outcome === 'hadir') stats.value.hadir++;
            if (data.outcome === 'terlambat') stats.value.terlambat++;
            if (data.outcome === 'pulang') stats.value.pulang++;
        }
    } catch {
        result.value = { outcome: 'error', text: 'Koneksi gagal. Coba lagi.', student: null };
    } finally {
        busy.value = false;
        uid.value = '';
        await nextTick();
        inputEl.value?.focus();
    }
};

const initials = (name) =>
    (name ?? '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
</script>

<template>
    <Head title="Scan Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Scan Absensi</h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <!-- Header: jam & tanggal -->
            <div class="flex items-center justify-between rounded-xl bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-4 text-white shadow">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-indigo-200">{{ dateLabel() }}</p>
                    <p class="text-3xl font-bold tabular-nums">{{ clock() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-indigo-200">Total hadir hari ini</p>
                    <p class="text-2xl font-bold">{{ stats.hadir + stats.terlambat }}</p>
                </div>
            </div>

            <!-- Statistik -->
            <div class="grid grid-cols-4 gap-3">
                <div class="rounded-lg bg-white p-3 text-center shadow">
                    <p class="text-2xl font-bold text-green-600">{{ stats.hadir }}</p>
                    <p class="text-xs text-gray-500">Hadir</p>
                </div>
                <div class="rounded-lg bg-white p-3 text-center shadow">
                    <p class="text-2xl font-bold text-amber-500">{{ stats.terlambat }}</p>
                    <p class="text-xs text-gray-500">Terlambat</p>
                </div>
                <div class="rounded-lg bg-white p-3 text-center shadow">
                    <p class="text-2xl font-bold text-indigo-600">{{ stats.pulang }}</p>
                    <p class="text-xs text-gray-500">Sudah Pulang</p>
                </div>
                <div class="rounded-lg bg-white p-3 text-center shadow">
                    <p class="text-2xl font-bold text-gray-400">{{ stats.belum }}</p>
                    <p class="text-xs text-gray-500">Belum</p>
                </div>
            </div>

            <!-- Area scan -->
            <div class="rounded-xl bg-white p-6 shadow">
                <!-- Mode toggle -->
                <div class="mb-5 grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-1">
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition"
                        :class="direction === 'in' ? 'bg-white text-indigo-700 shadow' : 'text-gray-500 hover:text-gray-700'"
                        @click="direction = 'in'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
                        Masuk
                    </button>
                    <button
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition"
                        :class="direction === 'out' ? 'bg-white text-purple-700 shadow' : 'text-gray-500 hover:text-gray-700'"
                        @click="direction = 'out'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.25 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        Pulang
                    </button>
                </div>

                <!-- Zona scan -->
                <form @submit.prevent="submit">
                    <div
                        class="relative rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
                        :class="direction === 'in' ? 'border-indigo-200 bg-indigo-50/50' : 'border-purple-200 bg-purple-50/50'"
                    >
                        <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
                            <div class="h-16 w-16 rounded-full border-2 border-dashed animate-pulse" :class="direction === 'in' ? 'border-indigo-300' : 'border-purple-300'"></div>
                        </div>
                        <p class="relative text-sm font-medium" :class="direction === 'in' ? 'text-indigo-700' : 'text-purple-700'">
                            {{ direction === 'in' ? 'Arahkan kartu — mode MASUK' : 'Arahkan kartu — mode PULANG' }}
                        </p>
                        <p class="relative mt-1 text-xs text-gray-400">Reader keyboard-wedge akan mengisi UID otomatis</p>
                        <input
                            ref="inputEl"
                            v-model="uid"
                            type="text"
                            autocomplete="off"
                            autofocus
                            placeholder="UID kartu"
                            class="relative mt-4 w-full max-w-xs rounded-lg border-gray-300 text-center font-mono text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <button type="submit" class="sr-only">Submit</button>
                </form>

                <!-- Hasil scan -->
                <div
                    v-if="result"
                    :key="resultKey"
                    class="mt-4 flex items-center gap-4 rounded-xl border px-5 py-4 shadow-sm"
                    :class="isGood(result.outcome) ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'"
                >
                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full"
                        :class="isGood(result.outcome) ? 'bg-green-500 text-white' : 'bg-red-500 text-white'"
                    >
                        <svg v-if="isGood(result.outcome)" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <svg v-else class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-lg font-bold" :class="isGood(result.outcome) ? 'text-green-800' : 'text-red-800'">
                            {{ result.student ?? result.text }}
                        </p>
                        <p v-if="result.student" class="text-sm" :class="isGood(result.outcome) ? 'text-green-600' : 'text-red-600'">
                            {{ result.text }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Feed scan terakhir -->
            <div class="overflow-hidden rounded-xl bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="text-sm font-semibold text-gray-700">Scan Terakhir</p>
                    <p class="text-xs text-gray-400">{{ feed.length }} catatan</p>
                </div>
                <ul class="divide-y divide-gray-50">
                    <li v-for="e in feed" :key="e.id" class="flex items-center gap-3 px-5 py-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="e.direction === 'out' ? 'bg-purple-100 text-purple-700' : 'bg-indigo-100 text-indigo-700'"
                        >
                            {{ initials(e.student_name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900">{{ e.student_name ?? e.uid_text }}</p>
                            <p class="text-xs text-gray-400">{{ e.uid_text }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="e.direction === 'out' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                            >
                                {{ e.direction === 'out' ? 'Pulang' : 'Masuk' }}
                            </span>
                            <span class="w-16 text-right text-xs tabular-nums text-gray-400">{{ e.event_time }}</span>
                            <span
                                class="w-24 text-right text-xs font-semibold"
                                :class="isGood(e.outcome) ? 'text-green-600' : e.outcome === 'terlambat' ? 'text-amber-500' : 'text-red-500'"
                            >
                                {{ OUTCOME_LABEL[e.outcome] ?? e.outcome }}
                            </span>
                        </div>
                    </li>
                    <li v-if="!feed.length" class="px-5 py-10 text-center text-sm text-gray-400">
                        Belum ada scan.
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
