<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { getSetting, setSetting, getDb, enqueueEvent, pendingCount, logScan } from './db.js';
import { pair, getMe } from './api.js';
import { isPaired, loadConfig, startSync, stopSync, syncOnce, onSyncEvent, isOnline, sendHeartbeat } from './sync.js';

const APP_VERSION = '0.1.0';

// State
const screen = ref('loading'); // loading | pair | setup | main
const serverUrl = ref(localStorage.getItem('server_url') || 'http://localhost/absensi/public');
const pairingCode = ref('');
const pairError = ref('');
const pairing = ref(false);

const terminal = ref({ code: '', type: 'in' });
const mode = ref('in'); // in | out — REG mengikuti terminal_type
const uidPattern = ref('');
const debounceSeconds = ref(5);
const clockDrift = ref(0);

const scanValue = ref('');
const scanInput = ref(null);
const lastScan = ref(null); // {uid, at, source}
const queuePending = ref(0);
const online = ref(isOnline());
const syncLog = ref([]);
const simMode = ref(false); // mode simulasi — BUKAN input RFID fisik

const directionLabel = computed(() => (mode.value === 'out' ? 'Keluar' : 'Masuk'));
const terminalTypeLabel = computed(() => ({ in: 'Terminal Masuk', out: 'Terminal Keluar', registration: 'Terminal Registrasi' }[terminal.value.type] ?? terminal.value.type));

// Debounce & dedupe scan berulang
let lastScanAt = 0;
let lastUid = '';

const handleScan = async (raw, source = 'keyboard') => {
    const uid = String(raw ?? '').trim();
    if (!uid) return;

    // Anti scan berulang: debounce berdasar waktu lokal + tolak UID identik < debounce.
    const now = Date.now();
    const debounceMs = Math.max(debounceSeconds.value, 1) * 1000;
    if (uid === lastUid && now - lastScanAt < debounceMs) {
        flash('Scan diabaikan (duplikat).');
        return;
    }
    lastUid = uid;
    lastScanAt = now;

    // Simpan durable dulu — baru dianggap tercatat.
    const eventTime = new Date(Date.now() - clockDrift.value * 1000).toISOString();
    await enqueueEvent({
        uid_text: uid,
        event_time: eventTime,
        payload: { source, direction: mode.value, app_version: APP_VERSION },
    });
    await logScan(uid, source);
    queuePending.value = await pendingCount();
    lastScan.value = { uid, at: new Date().toLocaleTimeString('id-ID'), source };

    // Coba sinkron segera; gagal → antrean menunggu.
    const token = await getSetting('device_token');
    if (token && isOnline()) {
        syncOnce(token).then((r) => {
            queuePending.value = r.pending;
        });
    }
    scanValue.value = '';
    scanInput.value?.focus();
};

const flash = (msg) => {
    syncLog.value.unshift({ at: new Date().toLocaleTimeString('id-ID'), msg });
    if (syncLog.value.length > 30) syncLog.value.pop();
};

// Pairing
const doPair = async () => {
    pairingError.value = '';
    pairing.value = true;
    try {
        const res = await pair(pairingCode.value.trim());
        if (!res.ok) {
            pairError.value = res.data?.message ?? 'Pairing gagal.';
            return;
        }
        await setSetting('device_token', res.data.token);
        await setSetting('terminal_id', res.data.terminal.id);
        await setSetting('terminal_code', res.data.terminal.code);
        await setSetting('terminal_type', res.data.terminal.type);
        terminal.value = { code: res.data.terminal.code, type: res.data.terminal.type };
        await loadConfig(res.data.token);
        enterMain(res.data.token);
    } catch (e) {
        pairError.value = 'Tidak dapat terhubung ke server.';
    } finally {
        pairing.value = false;
    }
};

const enterMain = async (token) => {
    mode.value = terminal.value.type === 'out' ? 'out' : 'in';
    uidPattern.value = (await getSetting('reader_pattern')) || '';
    debounceSeconds.value = Number(await getSetting('debounce_seconds')) || 5;
    clockDrift.value = Number(await getSetting('clock_drift_seconds')) || 0;
    queuePending.value = await pendingCount();
    screen.value = 'main';
    startSync(token, APP_VERSION);
    onSyncEvent((e) => {
        if (e.type === 'synced') { queuePending.value = e.pending; flash(`${e.count} event terkirim.`); }
        if (e.type === 'sync_error') flash(`Sinkronisasi gagal: ${e.message}`);
    });
    setTimeout(() => scanInput.value?.focus(), 50);
};

// Simulasi scan (mode uji — BUKAN RFID fisik)
const simulateScan = () => {
    if (!simMode.value) return;
    const uid = 'SIM-' + Math.random().toString(16).slice(2, 10).toUpperCase();
    handleScan(uid, 'simulation');
};

const saveServerUrl = async () => {
    localStorage.setItem('server_url', serverUrl.value);
    flash('URL server disimpan.');
};

const unpair = async () => {
    stopSync();
    // Hapus token saja — antrean event dipertahankan untuk sinkronisasi ulang.
    const db = await getDb();
    await db.execute("DELETE FROM settings WHERE key = 'device_token'");
    screen.value = 'pair';
};

onMounted(async () => {
    window.addEventListener('online', () => (online.value = true));
    window.addEventListener('offline', () => (online.value = false));

    if (await isPaired()) {
        const token = await getSetting('device_token');
        try {
            const me = await getMe(token);
            if (me.ok) {
                terminal.value = { code: me.data.terminal.code, type: me.data.terminal.type };
                await loadConfig(token);
                enterMain(token);
                return;
            }
        } catch { /* lanjut ke pairing */ }
        screen.value = 'pair';
    } else {
        screen.value = 'pair';
    }
});

onUnmounted(() => stopSync());
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-gray-100 flex flex-col">
        <!-- Header status -->
        <header class="flex items-center justify-between border-b border-gray-700 px-6 py-3">
            <div>
                <h1 class="text-lg font-semibold">Terminal Absensi RFID</h1>
                <p class="text-xs text-gray-400">
                    {{ terminalTypeLabel }} · {{ terminal.code || '—' }} · v{{ APP_VERSION }}
                    <span v-if="simMode" class="ml-2 rounded bg-purple-700 px-1.5 py-0.5 text-[10px] font-bold">SIMULASI — bukan input RFID fisik</span>
                </p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span class="rounded px-2 py-1" :class="online ? 'bg-green-800' : 'bg-red-800'">
                    {{ online ? 'Online' : 'Offline' }}
                </span>
                <span class="rounded bg-gray-700 px-2 py-1">Antrean: {{ queuePending }}</span>
                <button class="rounded bg-gray-700 px-2 py-1 hover:bg-gray-600" @click="unpair">Lepas Pairing</button>
            </div>
        </header>

        <!-- Loading -->
        <div v-if="screen === 'loading'" class="flex flex-1 items-center justify-center text-gray-400">
            Memuat…
        </div>

        <!-- Pairing -->
        <div v-else-if="screen === 'pair'" class="mx-auto mt-16 w-full max-w-md space-y-4 px-4">
            <div class="rounded-lg bg-gray-800 p-6">
                <h2 class="text-lg font-semibold">Pairing Perangkat</h2>
                <p class="mt-1 text-sm text-gray-400">
                    Masukkan kode pairing dari menu Master → Terminal di aplikasi web (berlaku 15 menit).
                </p>
                <label class="mt-4 block text-xs text-gray-400">URL Server</label>
                <div class="mt-1 flex gap-2">
                    <input v-model="serverUrl" class="w-full rounded bg-gray-700 px-3 py-2 font-mono text-sm" @change="saveServerUrl" />
                </div>
                <label class="mt-4 block text-xs text-gray-400">Kode Pairing</label>
                <input
                    v-model="pairingCode"
                    class="mt-1 w-full rounded bg-gray-700 px-3 py-2 text-center font-mono text-2xl tracking-[0.5em]"
                    placeholder="XXXX-XXXX"
                    maxlength="9"
                    @keyup.enter="doPair"
                />
                <p v-if="pairError" class="mt-2 text-sm text-red-400">{{ pairError }}</p>
                <button
                    class="mt-4 w-full rounded bg-indigo-600 py-2.5 font-medium hover:bg-indigo-500 disabled:opacity-50"
                    :disabled="pairing || !pairingCode.trim()"
                    @click="doPair"
                >
                    {{ pairing ? 'Memproses…' : 'Pairing' }}
                </button>
            </div>
        </div>

        <!-- Main scan screen -->
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-6 px-6">
            <div class="text-center">
                <p class="text-sm text-gray-400">Arahkan scanner RFID ke input di bawah, atau gunakan mode simulasi:</p>
                <p class="mt-1 text-3xl font-bold" :class="mode === 'out' ? 'text-orange-400' : 'text-green-400'">
                    {{ directionLabel }}
                </p>
            </div>

            <input
                ref="scanInput"
                v-model="scanValue"
                type="text"
                autocomplete="off"
                class="w-full max-w-xl rounded-lg border-2 border-indigo-500 bg-gray-800 px-6 py-4 text-center font-mono text-2xl tracking-widest focus:outline-none focus:ring-4 focus:ring-indigo-500/30"
                placeholder="UID kartu…"
                @keyup.enter="handleScan(scanValue, 'keyboard')"
            />

            <div class="flex items-center gap-4">
                <button
                    class="rounded-lg px-4 py-2 text-sm"
                    :class="simMode ? 'bg-purple-700' : 'bg-gray-700 hover:bg-gray-600'"
                    @click="simMode = !simMode"
                >
                    {{ simMode ? 'Simulasi: AKTIF' : 'Mode Simulasi' }}
                </button>
                <button
                    v-if="simMode"
                    class="rounded-lg bg-purple-600 px-6 py-2 text-sm font-medium hover:bg-purple-500"
                    @click="simulateScan"
                >
                    Scan Simulasi
                </button>
            </div>
            <p v-if="simMode" class="text-xs text-purple-300">
                Mode simulasi menghasilkan UID acak — BUKAN input dari reader RFID fisik.
            </p>

            <div v-if="lastScan" class="rounded-lg bg-gray-800 px-6 py-3 text-center">
                <p class="text-sm text-gray-400">Scan terakhir</p>
                <p class="font-mono text-xl">{{ lastScan.uid }}</p>
                <p class="text-xs text-gray-500">{{ lastScan.at }} · {{ lastScan.source }}</p>
            </div>

            <!-- Log sinkronisasi -->
            <div class="w-full max-w-xl rounded-lg bg-gray-800/60 p-4">
                <h3 class="text-xs font-semibold uppercase text-gray-500">Log Sinkronisasi</h3>
                <ul class="mt-2 max-h-40 space-y-1 overflow-y-auto font-mono text-xs text-gray-400">
                    <li v-for="(l, i) in syncLog" :key="i">{{ l.at }} — {{ l.msg }}</li>
                    <li v-if="!syncLog.length" class="text-gray-600">Belum ada aktivitas.</li>
                </ul>
            </div>
        </div>
    </div>
</template>
