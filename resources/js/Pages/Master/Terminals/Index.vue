<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    terminals: Array,
    readerProfiles: Array,
    newToken: String,
    newPairingCode: String,
    pairingTerminal: String,
});
const page = usePage();
const canWrite = computed(() => ['admin', 'petugas_piket'].includes(page.props.auth.user?.role));
const showForm = ref(false);
const copied = ref(false);

const form = useForm({ terminal_code: '', type: 'in', location: '', reader_profile_id: '', status: 'active' });

const typeLabel = { in: 'Masuk', out: 'Keluar' };
const statusLabel = { active: 'Aktif', inactive: 'Nonaktif', maintenance: 'Maintenance' };

const create = () => {
    form.post(route('master.terminals.store'), { onSuccess: () => { form.reset(); showForm.value = false; } });
};

const issueToken = (terminal) => {
    router.post(route('master.terminals.token', terminal.id), {}, { preserveScroll: true });
};

const issuePairingCode = (terminal) => {
    router.post(route('master.terminals.pairing-code', terminal.id), {}, { preserveScroll: true });
};

const revokeToken = (token) => {
    if (confirm('Cabut token ini? Terminal tidak akan bisa upload event lagi.')) {
        router.post(route('master.terminal-tokens.revoke', token.id), {}, { preserveScroll: true });
    }
};

const updateStatus = (terminal, status) => {
    router.patch(route('master.terminals.update', terminal.id), {
        terminal_code: terminal.terminal_code,
        type: terminal.type,
        location: terminal.location,
        reader_profile_id: terminal.reader_profile_id,
        status,
    }, { preserveScroll: true });
};

const copyToken = async (value) => {
    await navigator.clipboard.writeText(value ?? props.newToken);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};
</script>

<template>
    <Head title="Terminal RFID" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">Terminal RFID</h2>
                <button
                    v-if="canWrite"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="showForm = !showForm"
                >
                    {{ showForm ? 'Batal' : 'Tambah Terminal' }}
                </button>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <!-- Kode pairing (tampil sekali, berlaku 15 menit) -->
            <div v-if="newPairingCode" class="rounded-md border border-indigo-300 bg-indigo-50 p-4">
                <p class="text-sm font-medium text-indigo-900">
                    Kode pairing untuk <strong>{{ pairingTerminal }}</strong> — masukkan di aplikasi terminal, berlaku 15 menit:
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <code class="flex-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-lg tracking-widest text-gray-900">{{ newPairingCode }}</code>
                    <button class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700" @click="copyToken(newPairingCode)">
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </button>
                </div>
            </div>

            <!-- Token baru (tampil sekali) -->
            <div v-if="newToken" class="rounded-md border border-amber-300 bg-amber-50 p-4">
                <p class="text-sm font-medium text-amber-900">
                    Token baru diterbitkan — simpan sekarang, tidak akan ditampilkan lagi:
                </p>
                <div class="mt-2 flex items-center gap-2">
                    <code class="flex-1 overflow-x-auto rounded bg-white px-3 py-2 font-mono text-sm text-gray-900">{{ newToken }}</code>
                    <button class="rounded-md bg-amber-600 px-3 py-2 text-sm text-white hover:bg-amber-700" @click="copyToken">
                        {{ copied ? 'Tersalin' : 'Salin' }}
                    </button>
                </div>
            </div>

            <!-- Form terminal -->
            <form v-if="showForm" class="grid gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-3 sm:items-end" @submit.prevent="create">
                <div>
                    <InputLabel for="terminal_code" value="Kode Terminal" />
                    <TextInput id="terminal_code" v-model="form.terminal_code" class="mt-1 w-full" required />
                    <InputError :message="form.errors.terminal_code" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="type" value="Arah" />
                    <select id="type" v-model="form.type" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="in">Masuk</option>
                        <option value="out">Keluar</option>
                    </select>
                </div>
                <div>
                    <InputLabel for="location" value="Lokasi" />
                    <TextInput id="location" v-model="form.location" class="mt-1 w-full" placeholder="Gerbang Utama" />
                </div>
                <div>
                    <InputLabel for="reader_profile_id" value="Profil Reader" />
                    <select id="reader_profile_id" v-model="form.reader_profile_id" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                        <option value="">—</option>
                        <option v-for="p in readerProfiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <PrimaryButton class="sm:col-span-2" :disabled="form.processing">Simpan</PrimaryButton>
            </form>

            <!-- Daftar terminal -->
            <div class="space-y-4">
                <div v-for="t in terminals" :key="t.id" class="rounded-lg bg-white p-5 shadow">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-mono text-sm font-semibold text-gray-900">{{ t.terminal_code }}</p>
                            <p class="text-xs text-gray-500">
                                {{ typeLabel[t.type] }} · {{ t.location ?? 'Tanpa lokasi' }} ·
                                Reader: {{ t.reader_profile?.name ?? 'default' }}
                                <span :class="t.is_online ? 'font-medium text-green-700' : 'text-gray-400'">
                                    · {{ t.is_online ? 'Online' : 'Offline' }}
                                </span>
                                <template v-if="t.last_seen_at"> · Terakhir terlihat {{ t.last_seen_at.slice(0, 16).replace('T', ' ') }}</template>
                                <template v-if="t.app_version"> · v{{ t.app_version }}</template>
                            </p>
                        </div>
                        <div class="flex items-center gap-2" v-if="canWrite">
                            <select
                                :value="t.status"
                                class="rounded-md border-gray-300 py-1 text-xs"
                                @change="updateStatus(t, $event.target.value)"
                            >
                                <option v-for="(label, key) in statusLabel" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <button class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700" @click="issueToken(t)">
                                Token Baru
                            </button>
                            <button class="rounded-md border border-indigo-300 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-50" @click="issuePairingCode(t)">
                                Kode Pairing
                            </button>
                        </div>
                        <span v-else class="text-xs" :class="t.status === 'active' ? 'text-green-700' : 'text-gray-500'">
                            {{ statusLabel[t.status] }}
                        </span>
                    </div>

                    <!-- Token aktif -->
                    <div class="mt-3 border-t border-gray-100 pt-3">
                        <p class="text-xs font-medium uppercase text-gray-500">Token</p>
                        <ul class="mt-1 space-y-1">
                            <li v-for="tok in t.tokens" :key="tok.id" class="flex items-center justify-between text-xs text-gray-600">
                                <span>
                                    {{ tok.label ?? 'Token' }} —
                                    <span :class="tok.revoked_at ? 'text-red-600' : 'text-green-700'">
                                        {{ tok.revoked_at ? 'Dicabut' : 'Aktif' }}
                                    </span>
                                    <span v-if="tok.last_used_at" class="text-gray-400"> · dipakai {{ tok.last_used_at.slice(0, 16).replace('T', ' ') }}</span>
                                </span>
                                <button
                                    v-if="canWrite && !tok.revoked_at"
                                    class="text-red-600 hover:underline"
                                    @click="revokeToken(tok)"
                                >Cabut</button>
                            </li>
                            <li v-if="!t.tokens?.length" class="text-xs text-gray-400">Belum ada token.</li>
                        </ul>
                    </div>
                </div>
                <p v-if="!terminals.length" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 shadow">
                    Belum ada terminal terdaftar.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
