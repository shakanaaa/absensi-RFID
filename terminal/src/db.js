import Database from '@tauri-apps/plugin-sql';

const DB_URL = 'sqlite:absensi_terminal.db';
let dbPromise = null;

export function getDb() {
    if (!dbPromise) {
        dbPromise = Database.load(DB_URL).then(async (db) => {
            await db.execute(`
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT
                );
                CREATE TABLE IF NOT EXISTS pending_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    event_id TEXT NOT NULL UNIQUE,
                    uid_text TEXT NOT NULL,
                    event_time TEXT NOT NULL,
                    sequence_number INTEGER,
                    payload TEXT,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    last_error TEXT,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    synced_at TEXT
                );
                CREATE INDEX IF NOT EXISTS idx_pending_synced ON pending_events(synced_at);
                CREATE TABLE IF NOT EXISTS scan_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uid_text TEXT NOT NULL,
                    scanned_at TEXT NOT NULL,
                    source TEXT NOT NULL DEFAULT 'keyboard'
                );
            `);
            return db;
        });
    }
    return dbPromise;
}

export async function getSetting(key) {
    const db = await getDb();
    const rows = await db.select('SELECT value FROM settings WHERE key = ?', [key]);
    return rows[0]?.value ?? null;
}

export async function setSetting(key, value) {
    const db = await getDb();
    await db.execute(
        'INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value',
        [key, String(value)]
    );
}

/**
 * Simpan event secara durable SEBELUM dianggap aman.
 * event_id UUID unik → retry sinkronisasi idempotent di server.
 */
export async function enqueueEvent({ uid_text, event_time, sequence_number = null, payload = null }) {
    const db = await getDb();
    const eventId = crypto.randomUUID();
    await db.execute(
        `INSERT INTO pending_events (event_id, uid_text, event_time, sequence_number, payload)
         VALUES (?, ?, ?, ?, ?)`,
        [eventId, uid_text, event_time, sequence_number, payload ? JSON.stringify(payload) : null]
    );
    return eventId;
}

export async function pendingEvents(limit = 500) {
    const db = await getDb();
    return db.select(
        'SELECT * FROM pending_events WHERE synced_at IS NULL ORDER BY id LIMIT ?',
        [limit]
    );
}

export async function markSynced(ids) {
    if (!ids.length) return;
    const db = await getDb();
    const placeholders = ids.map(() => '?').join(',');
    await db.execute(
        `UPDATE pending_events SET synced_at = datetime('now'), attempts = attempts + 1 WHERE id IN (${placeholders})`,
        ids
    );
}

export async function markFailed(ids, error) {
    if (!ids.length) return;
    const db = await getDb();
    const placeholders = ids.map(() => '?').join(',');
    await db.execute(
        `UPDATE pending_events SET attempts = attempts + 1, last_error = ? WHERE id IN (${placeholders})`,
        [String(error).slice(0, 500), ...ids]
    );
}

export async function pendingCount() {
    const db = await getDb();
    const rows = await db.select('SELECT COUNT(*) AS c FROM pending_events WHERE synced_at IS NULL');
    return rows[0]?.c ?? 0;
}

export async function logScan(uid, source = 'keyboard') {
    const db = await getDb();
    await db.execute('INSERT INTO scan_log (uid_text, scanned_at, source) VALUES (?, ?, ?)', [
        uid, new Date().toISOString(), source,
    ]);
}
