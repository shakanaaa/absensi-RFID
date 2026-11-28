<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const props = defineProps({
    recent: Array,
});

const direction = ref('in');
const uid = ref('');
const inputEl = ref(null);
const busy = ref(false);
const result = ref(null);
const feed = ref([...props.recent]);

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

const outcomeStyle = (o) => {
    if (['hadir', 'pulang'].includes(o)) return 'bg-green-50 text-green-800 border-green-200';
    if (o === 'terlambat') return 'bg-amber-50 text-amber-800 border-amber-200';
    return 'bg-red-50 text-red-800 border-red-200';
};

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
            result.value = { outcome: 'error', text: data.message ?? 'Gagal memproses scan.' };
        } else {
            const label = OUTCOME_LABEL[data.outcome] ?? data.outcome;
            result.value = {
                outcome: data.outcome,
                text: data.student_name ? `${data.student_name} — ${label}` : label,
            };
            feed.value.unshift({
                id: Date.now(),
                uid_text: value,
                student_name: data.student_name,
                direction: direction.value,
                outcome: data.outcome,
                event_time: new Date().toLocaleTimeString('id-ID'),
            });
            feed.value = feed.value.slice(0, 15);
        }
    } catch {
        result.value = { outcome: 'error', text: 'Koneksi gagal. Coba lagi.' };
    } finally {
        busy.value = false;
        uid.value = '';
        await nextTick();
        inputEl.value?.focus();
    }
};
</script>

<template>
    <Head title="Scan Absensi" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">Scan Absensi</h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow">
                <p class="mb-4 text-sm text-gray-500">
                    Dekatkan kartu RFID ke reader — UID otomatis terbaca. Pastikan halaman ini tetap terbuka di komputer yang tersambung reader.
                </p>

                <!-- Mode -->
                <div class="mb-4 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        class="rounded-md px-4 py-3 text-sm font-semibold transition"
                        :class="direction === 'in' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        @click="direction = 'in'"
                    >
                        Masuk
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-3 text-sm font-semibold transition"
                        :class="direction === 'out' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        @click="direction = 'out'"
                    >
                        Pulang
                    </button>
                </div>

                <!-- Input scan -->
                <form @submit.prevent="submit">
                    <input
                        ref="inputEl"
                        v-model="uid"
                        type="text"
                        autocomplete="off"
                        autofocus
                        placeholder="UID kartu (otomatis terbaca dari reader)"
                        class="w-full rounded-md border-gray-300 font-mono text-lg shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button type="submit" class="sr-only">Submit</button>
                </form>

                <!-- Hasil -->
                <div
                    v-if="result"
                    class="mt-4 rounded-md border px-4 py-3 text-sm font-medium"
                    :class="outcomeStyle(result.outcome)"
                >
                    {{ result.text }}
                </div>
            </div>

            <!-- Feed scan terakhir -->
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700">
                    Scan Terakhir
                </div>
                <ul class="divide-y divide-gray-100">
                    <li v-for="e in feed" :key="e.id" class="flex items-center justify-between px-4 py-2.5 text-sm">
                        <div>
                            <span class="font-medium text-gray-900">{{ e.student_name ?? e.uid_text }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ e.uid_text }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded px-1.5 py-0.5 text-xs" :class="e.direction === 'out' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                                {{ e.direction === 'out' ? 'Pulang' : 'Masuk' }}
                            </span>
                            <span class="text-xs text-gray-400">{{ e.event_time }}</span>
                            <span class="text-xs font-medium" :class="['hadir','pulang'].includes(e.outcome) ? 'text-green-600' : 'text-red-500'">
                                {{ OUTCOME_LABEL[e.outcome] ?? e.outcome }}
                            </span>
                        </div>
                    </li>
                    <li v-if="!feed.length" class="px-4 py-6 text-center text-sm text-gray-400">
                        Belum ada scan.
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
