import { getSetting, setSetting, pendingEvents, markSynced, markFailed, pendingCount } from './db.js';
import { getConfig, heartbeat, sendEvents, getMe } from './api.js';

const POLL_MS = 15_000;
const HEARTBEAT_MS = 60_000;
const MAX_ATTEMPTS = 10;

let timer = null;
let heartbeatTimer = null;
let syncing = false;
let listeners = new Set();

export function onSyncEvent(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

function emit(state) {
    listeners.forEach((fn) => fn(state));
}

export function isOnline() {
    return navigator.onLine;
}

/** Ambil konfigurasi terminal dari server; simpan reader pattern & rules. */
export async function loadConfig(token) {
    const res = await getConfig(token);
    if (!res.ok) throw new Error(res.data?.message ?? `Config gagal (${res.status})`);

    const cfg = res.data;
    await setSetting('terminal_id', cfg.terminal.id);
    await setSetting('terminal_code', cfg.terminal.code);
    await setSetting('terminal_type', cfg.terminal.type);
    await setSetting('reader_pattern', cfg.reader?.pattern ?? '');
    await setSetting('reader_uid_length', cfg.reader?.uid_length ?? '');
    await setSetting('debounce_seconds', cfg.rules?.debounce_seconds ?? 5);
    await setSetting('server_time', cfg.server_time);

    return cfg;
}

/**
 * Sinkronisasi antrean: ambil event pending → batch → tandai per hasil.
 * Hasil per event: 'accepted' & 'duplicate' = terkirim; lainnya gagal.
 * Kegagalan jaringan → semua tetap pending (retry berikutnya).
 */
export async function syncOnce(token) {
    if (syncing || !isOnline()) return { synced: 0, pending: await pendingCount() };
    syncing = true;

    try {
        const pending = await pendingEvents(500);
        if (!pending.length) return { synced: 0, pending: 0 };

        const events = pending.map((e) => ({
            event_id: e.event_id,
            uid_text: e.uid_text,
            event_time: e.event_time,
            sequence_number: e.sequence_number,
            payload: e.payload ? JSON.parse(e.payload) : undefined,
        }));

        const res = await sendEvents(token, events);
        if (!res.ok) {
            await markFailed(pending.map((e) => e.id), `HTTP ${res.status}: ${res.data?.message ?? ''}`);
            emit({ type: 'sync_error', message: `HTTP ${res.status}` });
            return { synced: 0, pending: await pendingCount() };
        }

        const results = res.data?.results ?? [];
        const byEventId = new Map(results.map((r) => [r.event_id, r]));
        const okIds = [];
        const failIds = [];

        for (const e of pending) {
            const r = byEventId.get(e.event_id);
            if (r && (r.status === 'accepted' || r.status === 'duplicate')) {
                okIds.push(e.id);
            } else {
                failIds.push(e.id);
            }
        }

        await markSynced(okIds);
        if (failIds.length) {
            await markFailed(failIds, 'ditolak server');
        }

        const left = await pendingCount();
        emit({ type: 'synced', count: okIds.length, pending: left });
        return { synced: okIds.length, pending: left };
    } catch (err) {
        await markFailed((await pendingEvents(500)).map((e) => e.id), err.message);
        emit({ type: 'sync_error', message: err.message });
        return { synced: 0, pending: await pendingCount(), error: err.message };
    } finally {
        syncing = false;
    }
}

/** Heartbeat + deteksi clock drift → simpan offset untuk koreksi event_time. */
export async function sendHeartbeat(token, appVersion, queuePending) {
    if (!isOnline()) return null;
    try {
        const res = await heartbeat(token, {
            app_version: appVersion,
            queue_pending: queuePending,
            device_time: new Date().toISOString(),
        });
        if (res.ok && res.data?.clock_drift_seconds !== null && res.data?.clock_drift_seconds !== undefined) {
            await setSetting('clock_drift_seconds', res.data.clock_drift_seconds);
        }
        return res.ok ? res.data : null;
    } catch {
        return null;
    }
}

/** Mulai loop sinkronisasi background. */
export function startSync(token, appVersion = '0.1.0') {
    stopSync();

    const tick = async () => {
        await syncOnce(token);
        const pending = await pendingCount();
        await sendHeartbeat(token, appVersion, pending);
    };

    tick();
    timer = setInterval(tick, POLL_MS);
    heartbeatTimer = setInterval(() => sendHeartbeat(token, appVersion), HEARTBEAT_MS);
}

export function stopSync() {
    if (timer) clearInterval(timer);
    if (heartbeatTimer) clearInterval(heartbeatTimer);
    timer = heartbeatTimer = null;
}

export async function isPaired() {
    return !!(await getSetting('device_token'));
}
