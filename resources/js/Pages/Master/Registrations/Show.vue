<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
    session: Object,
    rows: Array,
    attempts: Array,
    registered: Number,
    total: Number,
    pending: Number,
    nextStudentId: { type: Number, default: null },
    outcomeSummary: { type: Object, default: () => ({}) },
});
const page = usePage();
const canOperate = computed(() => ['admin', 'petugas_piket'].includes(page.props.auth.user?.role));
const running = computed(() => props.session.status === 'running');
const paused = computed(() => props.session.status === 'paused');
const active = computed(() => ['running', 'paused'].includes(props.session.status));

const selected = ref(null); // row siswa yang sedang discan
const uidInput = ref('');
const scanInput = ref(null);
const scanning = ref(false);
const lastResult = ref(null); // {outcome, uid, student, ok}
const error = ref('');

const outcomeLabel = {
    assigned: 'Kartu tertaut',
    assigned_new_card: 'Kartu baru dibuat & tertaut',
    already_assigned: 'Sudah tertaut ke siswa ini',
    card_in_use: 'Kartu dipakai siswa lain',
    student_has_card: 'Siswa sudah punya kartu aktif',
    card_blocked: 'Kartu nonaktif / diblokir',
    invalid_uid: 'UID tidak valid',
    skipped: 'Di-skip',
    duplicate_read: 'Pembacaan ulang kartu yang sama (diabaikan). Angkat kartu.',
    undone: 'Pendaftaran dibatalkan',
};
const outcomeOk = ['assigned', 'assigned_new_card', 'already_assigned', 'duplicate_read'];

const statusLabel = {
    running: 'Berjalan',
    paused: 'Dijeda',
    finished: 'Selesai',
    cancelled: 'Dibatalkan',
};

// Auto-pilih siswa berikutnya dari antrean server.
const autoPick = async () => {
    if (!running.value || !props.nextStudentId) {
        selected.value = null;
        return;
    }
    const row = props.rows.find((r) => r.student_id === props.nextStudentId && !r.card_uid && !r.skipped);
    if (row) await pick(row);
};

const pick = async (row) => {
    selected.value = row;
    lastResult.value = null;
    error.value = '';
    await nextTick();
    scanInput.value?.focus();
};

const submitScan = async () => {
    const uid = uidInput.value.trim();
    if (!uid || !selected.value || scanning.value) return;

    scanning.value = true;
    error.value = '';
    try {
        const res = await fetch(route('master.registrations.scan', props.session.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ student_id: selected.value.student_id, uid_text: uid }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message ?? 'Scan gagal.';
        } else {
            lastResult.value = data;
            uidInput.value = '';
            // Auto-next: pindah ke siswa berikutnya yang belum punya kartu.
            await router.reload({ only: ['rows', 'attempts', 'registered', 'total', 'pending', 'nextStudentId'] });
            const next = props.rows.find((r) => r.student_id === data.next_student_id && !r.card_uid && !r.skipped);
            if (next) {
                await pick(next);
            } else {
                selected.value = null;
            }
        }
    } catch {
        error.value = 'Koneksi gagal.';
    } finally {
        scanning.value = false;
        scanInput.value?.focus();
    }
};

const post = (name, confirmText = null) => {
    const run = () => router.post(route(`master.registrations.${name}`, props.session.id));
    if (confirmText) {
        if (confirm(confirmText)) run();
    } else {
        run();
    }
};

const finish = () => {
    if (confirm('Tutup sesi registrasi? Scan tidak bisa dilakukan lagi.')) {
        router.post(route('master.registrations.finish', props.session.id));
    }
};

// Saat halaman dibuka / sesi dilanjutkan, pilih siswa antrean pertama.
watch(() => props.nextStudentId, autoPick, { immediate: true });
</script>

<template>
    <Head :title="`Registrasi — ${session.class?.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Registrasi Kartu — {{ session.class?.name }}</h2>
                    <p class="text-xs text-gray-500">
                        Operator: {{ session.operator?.name }} ·
                        <span :class="running ? 'text-green-700' : paused ? 'text-amber-600' : 'text-gray-500'">
                            {{ statusLabel[session.status] ?? session.status }}
                        </span>
                    </p>
                </div>
                <div v-if="canOperate && active" class="flex items-center gap-2">
                    <SecondaryButton v-if="running" @click="post('pause')">Jeda</SecondaryButton>
                    <PrimaryButton v-if="paused" @click="post('resume')">Lanjutkan</PrimaryButton>
                    <SecondaryButton v-if="selected && running" @click="post('skip', 'Lewati siswa ini?')">Skip Siswa Ini</SecondaryButton>
                    <SecondaryButton v-if="running" @click="post('undo', 'Batalkan pendaftaran kartu terakhir? Kartu dilepas dari siswa itu dan bisa didaftarkan ulang.')">Batalkan Pendaftaran Terakhir</SecondaryButton>
                    <SecondaryButton v-if="running" @click="finish">Tutup Sesi</SecondaryButton>
                    <DangerButton @click="post('cancel', 'Batalkan sesi registrasi? Kartu yang sudah tertaut TIDAK dihapus.')">
                        Batalkan Sesi
                    </DangerButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <!-- Progress & scan console -->
            <div class="rounded-lg bg-white p-4 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-gray-700">
                            Terdaftar: <span class="font-semibold">{{ registered }}</span> / {{ total }} siswa
                            <span class="text-gray-400">·</span>
                            Sisa antrean: <span class="font-semibold">{{ pending }}</span>
                            <span class="text-gray-400">·</span>
                            Di-skip: <span class="font-semibold">{{ total - registered - pending }}</span>
                        </p>
                        <div class="mt-1 h-2 w-full max-w-md overflow-hidden rounded bg-gray-200">
                            <div
                                class="h-full bg-indigo-600 transition-all"
                                :style="{ width: total ? `${(registered / total) * 100}%` : '0%' }"
                            ></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="selected" class="rounded bg-indigo-50 px-2 py-1 text-sm text-indigo-800">
                            Scan untuk: <strong>{{ selected.name }}</strong>
                        </span>
                        <input
                            v-if="canOperate && running"
                            ref="scanInput"
                            v-model="uidInput"
                            type="text"
                            inputmode="text"
                            autocomplete="off"
                            placeholder="Arahkan scanner ke sini / ketik UID lalu Enter"
                            class="w-80 rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            @keyup.enter="submitScan"
                        />
                    </div>
                </div>
                <p v-if="lastResult" class="mt-2 text-sm" :class="outcomeOk.includes(lastResult.outcome) ? 'text-green-700' : 'text-red-600'">
                    {{ outcomeLabel[lastResult.outcome] ?? lastResult.outcome }} —
                    UID <span class="font-mono">{{ lastResult.uid }}</span>
                    <template v-if="lastResult.student">({{ lastResult.student }})</template>
                </p>
                <p v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</p>
                <p v-if="paused" class="mt-2 text-sm text-amber-600">Sesi dijeda — scan dinonaktifkan. Klik "Lanjutkan" untuk melanjutkan.</p>
                <div v-if="!active && Object.keys(outcomeSummary).length" class="mt-3 border-t border-gray-100 pt-3">
                    <p class="text-xs font-medium uppercase text-gray-500">Ringkasan Hasil Sesi</p>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs">
                        <span
                            v-for="(count, outcome) in outcomeSummary" :key="outcome"
                            class="rounded px-2 py-1"
                            :class="outcomeOk.includes(outcome) ? 'bg-green-50 text-green-800' : 'bg-gray-100 text-gray-700'"
                        >
                            {{ outcomeLabel[outcome] ?? outcome }}: {{ count }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Roster -->
                <div class="overflow-hidden rounded-lg bg-white shadow lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">No</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Kartu</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr
                                v-for="row in rows"
                                :key="row.student_id"
                                :class="selected?.student_id === row.student_id ? 'bg-indigo-50' : ''"
                            >
                                <td class="px-4 py-2.5 text-sm text-gray-600">{{ row.attendance_number ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-sm font-medium text-gray-900">
                                    {{ row.name }}
                                    <span v-if="row.skipped" class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-600">di-skip</span>
                                </td>
                                <td class="px-4 py-2.5 font-mono text-sm" :class="row.card_uid ? 'text-green-700' : 'text-gray-400'">
                                    {{ row.card_uid ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-right text-sm">
                                    <button
                                        v-if="canOperate && running && !row.card_uid && !row.skipped"
                                        class="rounded px-2 py-1 text-xs font-medium"
                                        :class="selected?.student_id === row.student_id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                        @click="pick(row)"
                                    >
                                        {{ selected?.student_id === row.student_id ? 'Scanning…' : 'Scan' }}
                                    </button>
                                    <span v-else-if="row.skipped" class="text-xs text-gray-400">skip</span>
                                </td>
                            </tr>
                            <tr v-if="!rows.length">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">Kelas tidak memiliki siswa aktif.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Riwayat scan -->
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <h3 class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-800">Riwayat Scan</h3>
                    <ul class="max-h-[32rem] divide-y divide-gray-100 overflow-y-auto">
                        <li v-for="a in attempts" :key="a.id" class="px-4 py-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-gray-900">{{ a.student?.name ?? '—' }}</span>
                                <span
                                    class="text-xs"
                                    :class="outcomeOk.includes(a.outcome) ? 'text-green-700' : 'text-red-600'"
                                >
                                    {{ outcomeLabel[a.outcome] ?? a.outcome }}
                                </span>
                            </div>
                            <p class="font-mono text-xs text-gray-500">
                                {{ a.uid_text ?? '—' }} · {{ a.attempted_at?.slice(11, 16) }}
                            </p>
                        </li>
                        <li v-if="!attempts.length" class="px-4 py-6 text-center text-sm text-gray-500">
                            Belum ada scan.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
